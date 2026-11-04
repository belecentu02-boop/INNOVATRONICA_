<?php
// Módulo interno: primera inclusión procesa POST; segunda dibuja la interfaz.
if (!defined('SA_PERSONAL_ALLOWED') || !es_superadmin()) { http_response_code(403); exit('Acceso denegado'); }
$sa_notice = $sa_notice ?? null;
$sa_roles = ['VENDEDOR_INTERNO'=>'Vendedor Interno','SUPERVISOR_COMERCIAL'=>'Supervisor Comercial','ADMIN'=>'Administrador General'];
if (!empty($sa_personal_procesar)) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = $_POST['sa_action'] ?? '';
        if (!is_string($action) || !in_array($action, ['set_active','create','edit','reset_password'], true)) {
            http_response_code(400); $sa_notice = ['error','Acción desconocida.'];
        } elseif (!verificar_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            http_response_code(403); $sa_notice = ['error','Token de seguridad inválido.'];
        } else {
            $lockedCode = false;
            try {
                $target = filter_var($_POST['target_id'] ?? null, FILTER_VALIDATE_INT);
                $role = $_POST['rol'] ?? '';
                $name = trim((string)($_POST['nombre'] ?? ''));
                $username = strtolower(trim((string)($_POST['usuario'] ?? '')));
                $password = (string)($_POST['password'] ?? '');
                if ($action === 'create' || $action === 'edit') {
                    if (mb_strlen($name) < 3 || mb_strlen($name) > 100 || !preg_match('/^[a-z0-9._-]{3,30}$/', $username) || !array_key_exists($role, $sa_roles)) {
                        throw new InvalidArgumentException('Nombre, usuario o rol inválido. Revisá los campos.');
                    }
                }
                if ($action === 'create' || $action === 'reset_password') {
                    if (strlen($password) < 12 || strlen($password) > 128) throw new InvalidArgumentException('La contraseña temporal debe tener entre 12 y 128 caracteres.');
                }
                if ($action !== 'create' && (!$target || $target < 1)) throw new InvalidArgumentException('Empleado inválido.');
                if ($action === 'create') {
                    // Mismo bloqueo de códigos utilizado por api.php.
                    $lk = $pdo->prepare('SELECT GET_LOCK(?, 5)');
                    $lk->execute(['innovatronica_codigo_empleado']);
                    if ((int)$lk->fetchColumn() !== 1) throw new RuntimeException('No se pudo reservar el legajo. Volvé a intentar.');
                    $lockedCode = true;
                    $pdo->beginTransaction();
                    $max = 0;
                    foreach ($pdo->query("SELECT codigo_empleado FROM usuarios WHERE codigo_empleado LIKE 'EMP-%'") as $r) {
                        if (preg_match('/^EMP-(\d+)$/', $r['codigo_empleado'], $m)) $max = max($max, (int)$m[1]);
                    }
                    $code = sprintf('EMP-%03d', $max + 1);
                    $st = $pdo->prepare('INSERT INTO usuarios (codigo_empleado,nombre,usuario,password_hash,rol,activo,debe_cambiar_password) VALUES (?,?,?,?,?,1,1)');
                    $st->execute([$code,$name,$username,password_hash($password,PASSWORD_DEFAULT),$role]);
                    $id = (int)$pdo->lastInsertId();
                    $event = 'SUPERADMIN_ALTA_USUARIO';
                    $detail = "Creó empleado id=$id ($code, $username), rol=$role";
                } else {
                    $pdo->beginTransaction();
                    $st = $pdo->prepare('SELECT id_empleado,usuario,nombre,rol,activo FROM usuarios WHERE id_empleado=? FOR UPDATE');
                    $st->execute([$target]); $old = $st->fetch(PDO::FETCH_ASSOC);
                    if (!$old || $old['rol'] === 'SUPERADMIN' || $target === (int)$u['id_empleado']) throw new InvalidArgumentException('Cuenta inexistente o protegida.');
                    if ($action === 'set_active') {
                        $active = $_POST['active'] ?? null;
                        if (!in_array($active,['0','1'],true)) throw new InvalidArgumentException('Estado inválido.');
                        $st = $pdo->prepare("UPDATE usuarios SET activo=? WHERE id_empleado=? AND rol<>'SUPERADMIN'");
                        $st->execute([(int)$active,$target]);
                        $event = 'SUPERADMIN_CAMBIO_ESTADO_USUARIO';
                        $detail = "Cambió estado de id=$target ({$old['usuario']}) de {$old['activo']} a $active";
                    } elseif ($action === 'edit') {
                        $st = $pdo->prepare("UPDATE usuarios SET nombre=?, usuario=?, rol=? WHERE id_empleado=? AND rol<>'SUPERADMIN'");
                        $st->execute([$name,$username,$role,$target]);
                        $event = 'SUPERADMIN_EDICION_USUARIO';
                        $detail = "Editó id=$target ({$old['usuario']}), rol anterior={$old['rol']}, rol nuevo=$role, usuario nuevo=$username";
                    } else {
                        $st = $pdo->prepare("UPDATE usuarios SET password_hash=?, debe_cambiar_password=1 WHERE id_empleado=? AND rol<>'SUPERADMIN'");
                        $st->execute([password_hash($password,PASSWORD_DEFAULT),$target]);
                        $event = 'SUPERADMIN_RESET_PASSWORD';
                        $detail = "Restableció contraseña de id=$target ({$old['usuario']}); cambio obligatorio habilitado";
                    }
                }
                $st = $pdo->prepare('INSERT INTO log_acciones (id_empleado,accion,detalles,ip) VALUES (?,?,?,?)');
                $st->execute([(int)$u['id_empleado'],$event,$detail,$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
                $pdo->commit();
                header('Location: superadmin.php?seccion=personal&resultado='.rawurlencode($action),true,303);
                exit;
            } catch (InvalidArgumentException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                http_response_code(422); $sa_notice = ['error',$e->getMessage()];
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('SUPERADMIN personal: '.$e->getMessage());
                http_response_code(409);
                $sa_notice = ['error',($e->getCode()==='23000') ? 'El nombre de usuario o legajo ya existe.' : 'No se pudo completar la operación.'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('SUPERADMIN personal: '.$e->getMessage());
                http_response_code(500); $sa_notice = ['error','No se pudo completar la operación.'];
            } finally {
                if ($lockedCode) {
                    try { $st=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $st->execute(['innovatronica_codigo_empleado']); }
                    catch (Throwable $e) { error_log('SUPERADMIN lock: '.$e->getMessage()); }
                }
            }
        }
    }
    return;
}
$sa_personal = [];
try {
    $sa_personal = $pdo->query('SELECT id_empleado,codigo_empleado,nombre,usuario,rol,activo,debe_cambiar_password FROM usuarios ORDER BY id_empleado')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { error_log('SUPERADMIN listado: '.$e->getMessage()); $sa_notice=['error','No se pudo consultar el personal.']; }
$sa_messages = ['create'=>'Empleado creado. Debe cambiar su contraseña al ingresar.','edit'=>'Datos del empleado actualizados y auditados.','reset_password'=>'Contraseña restablecida. Cambio obligatorio activado.','set_active'=>'Estado actualizado y registrado en auditoría.'];
?>
<section aria-labelledby="sa-personal-title">
  <div class="portal-section-heading"><h2 id="sa-personal-title">Gestión de empleados</h2><p>Altas, roles y control de acceso</p></div>
  <?php $result = $_GET['resultado'] ?? ''; if (isset($sa_messages[$result])): ?><div class="alert alert-success" role="status"><?= h($sa_messages[$result]) ?></div><?php endif; ?>
  <?php if ($sa_notice): ?><div class="alert <?= $sa_notice[0] === 'ok' ? 'alert-success' : 'alert-danger' ?>" role="alert"><?= h($sa_notice[1]) ?></div><?php endif; ?>
  <div class="sa-panel sa-personal-panel">
    <div class="sa-panel-head"><div><span class="portal-eyebrow">PERSONAL</span><h2>Cuentas registradas</h2></div><span class="portal-future-status"><?= count($sa_personal) ?> cuentas</span></div>
    <details class="sa-manage-box"><summary>+ Registrar nuevo empleado</summary>
      <form method="post" action="superadmin.php?seccion=personal" class="sa-manage-form" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= h(generar_csrf()) ?>"><input type="hidden" name="sa_action" value="create">
        <label>Nombre completo<input name="nombre" required minlength="3" maxlength="100"></label>
        <label>Usuario<input name="usuario" required minlength="3" maxlength="30" pattern="[a-z0-9._-]{3,30}"></label>
        <label>Rol<select name="rol" required><?php foreach ($sa_roles as $k=>$v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?></select></label>
        <label>Contraseña temporal<input type="password" name="password" required minlength="12" maxlength="128" autocomplete="new-password"></label>
        <p>El legajo se genera automáticamente. El empleado deberá cambiar la contraseña en su primer acceso.</p>
        <button class="sa-action-button" type="submit">Registrar empleado</button>
      </form>
    </details>
    <div class="sa-personal-scroll"><table class="sa-personal-table"><thead><tr><th>Empleado</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    <?php foreach ($sa_personal as $emp): $protected=$emp['rol']==='SUPERADMIN'; ?>
      <tr><td><strong><?= h($emp['nombre']) ?></strong><small><?= h($emp['codigo_empleado']) ?></small></td>
      <td><?= h($emp['usuario']) ?><?= $emp['debe_cambiar_password'] ? ' · Clave pendiente' : '' ?></td>
      <td><span class="badge <?= h(rol_badge_class($emp['rol'])) ?>"><?= h(rol_label($emp['rol'])) ?></span></td>
      <td><span class="sa-personal-state <?= $emp['activo'] ? 'is-active' : '' ?>"><?= $emp['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
      <td><?php if ($protected): ?><span class="sa-protected">Cuenta protegida</span><?php else: ?>
        <div class="sa-manage-actions">
          <details class="sa-manage-inline"><summary>Editar</summary>
            <form method="post" action="superadmin.php?seccion=personal" class="sa-manage-form" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= h(generar_csrf()) ?>"><input type="hidden" name="sa_action" value="edit"><input type="hidden" name="target_id" value="<?= (int)$emp['id_empleado'] ?>">
              <label>Nombre<input name="nombre" value="<?= h($emp['nombre']) ?>" required minlength="3" maxlength="100"></label>
              <label>Usuario<input name="usuario" value="<?= h($emp['usuario']) ?>" required minlength="3" maxlength="30" pattern="[a-z0-9._-]{3,30}"></label>
              <label>Rol<select name="rol"><?php foreach ($sa_roles as $k=>$v): ?><option value="<?= h($k) ?>" <?= $emp['rol']===$k?'selected':'' ?>><?= h($v) ?></option><?php endforeach; ?></select></label>
              <button class="sa-action-button" type="submit">Guardar cambios</button>
            </form>
          </details>
          <details class="sa-manage-inline"><summary>Clave</summary>
            <form method="post" action="superadmin.php?seccion=personal" class="sa-manage-form" autocomplete="off" onsubmit="return confirm('¿Restablecer la contraseña de esta cuenta?')">
              <input type="hidden" name="csrf_token" value="<?= h(generar_csrf()) ?>"><input type="hidden" name="sa_action" value="reset_password"><input type="hidden" name="target_id" value="<?= (int)$emp['id_empleado'] ?>">
              <label>Nueva contraseña temporal<input type="password" name="password" required minlength="12" maxlength="128" autocomplete="new-password"></label>
              <button class="sa-action-button" type="submit">Restablecer clave</button>
            </form>
          </details>
          <form method="post" action="superadmin.php?seccion=personal" class="sa-personal-form" onsubmit="return confirm('¿Confirmás el cambio de estado?')">
            <input type="hidden" name="csrf_token" value="<?= h(generar_csrf()) ?>"><input type="hidden" name="sa_action" value="set_active"><input type="hidden" name="target_id" value="<?= (int)$emp['id_empleado'] ?>"><input type="hidden" name="active" value="<?= $emp['activo'] ? '0' : '1' ?>"><button class="sa-action-button" type="submit"><?= $emp['activo'] ? 'Desactivar' : 'Activar' ?></button>
          </form>
        </div>
      <?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <p class="sa-personal-note">SUPERADMIN está protegido. Todas las modificaciones se registran en auditoría. No compartas contraseñas temporales por canales inseguros.</p>
  </div>
</section>
