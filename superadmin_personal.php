<?php
// Fragmento interno: solo incluido por superadmin.php tras requerir_rol(['SUPERADMIN']).
if (!defined('SA_PERSONAL_ALLOWED')) { http_response_code(403); exit; }
$sa_notice = null;
if (!empty($sa_personal_procesar) && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['sa_action'] ?? '') === 'set_active') {
    if (!verificar_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $sa_notice = ['error', 'Solicitud rechazada: token de seguridad inválido.'];
    } else {
        $target = filter_var($_POST['target_id'] ?? null, FILTER_VALIDATE_INT);
        $active = $_POST['active'] ?? null;
        if (!$target || !in_array($active, ['0', '1'], true)) {
            $sa_notice = ['error', 'Datos de solicitud inválidos.'];
        } elseif ($target === (int) $u['id_empleado']) {
            $sa_notice = ['error', 'No podés desactivar tu propia cuenta.'];
        } else {
            try {
                $pdo->beginTransaction();
                $q = $pdo->prepare('SELECT id_empleado, usuario, rol, activo FROM usuarios WHERE id_empleado = ? FOR UPDATE');
                $q->execute([$target]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                if (!$row || $row['rol'] === 'SUPERADMIN') {
                    $pdo->rollBack();
                    $sa_notice = ['error', 'La cuenta no existe o está protegida.'];
                } elseif ((int)$row['activo'] === (int)$active) {
                    $pdo->rollBack();
                    $sa_notice = ['ok', 'La cuenta ya tenía ese estado.'];
                } else {
                    $q = $pdo->prepare('UPDATE usuarios SET activo = ? WHERE id_empleado = ? AND rol <> ?');
                    $q->execute([(int)$active, $target, 'SUPERADMIN']);
                    $detail = 'SUPERADMIN cambió estado de usuario id='.$target.' ('.$row['usuario'].') de '.(int)$row['activo'].' a '.$active;
                    $q = $pdo->prepare('INSERT INTO log_acciones (id_empleado, accion, detalles, ip) VALUES (?, ?, ?, ?)');
                    $q->execute([(int)$u['id_empleado'], 'SUPERADMIN_CAMBIO_ESTADO_USUARIO', $detail, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
                    $pdo->commit();
                    header('Location: superadmin.php?seccion=personal&resultado=actualizado', true, 303);
                    exit;
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('SUPERADMIN personal: '.$e->getMessage());
                $sa_notice = ['error', 'No se pudo completar la operación.'];
            }
        }
    }
}
// En la primera inclusión solo se procesan acciones, sin generar HTML.
if (!empty($sa_personal_procesar)) {
    return;
}

$sa_personal = [];
try {
    $sa_personal = $pdo->query('SELECT id_empleado, codigo_empleado, nombre, usuario, rol, activo FROM usuarios ORDER BY id_empleado ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { error_log('SUPERADMIN listado personal: '.$e->getMessage()); $sa_notice = ['error', 'No se pudo consultar el personal.']; }
?>
<section aria-labelledby="sa-personal-title">
  <div class="portal-section-heading"><h2 id="sa-personal-title">Gestión de empleados</h2><p>Listado completo y control de acceso</p></div>
  <?php if (($_GET['resultado'] ?? '') === 'actualizado'): ?><div class="alert alert-success" role="status">Estado actualizado y registrado en auditoría.</div><?php endif; ?>
  <?php if ($sa_notice): ?><div class="alert <?= $sa_notice[0] === 'ok' ? 'alert-success' : 'alert-danger' ?>" role="alert"><?= h($sa_notice[1]) ?></div><?php endif; ?>
  <div class="sa-panel sa-personal-panel">
    <div class="sa-panel-head"><div><span class="portal-eyebrow">PERSONAL</span><h2>Cuentas registradas</h2></div><span class="portal-future-status"><?= count($sa_personal) ?> cuentas</span></div>
    <div class="sa-personal-scroll"><table class="sa-personal-table"><thead><tr><th>Empleado</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
      <?php foreach ($sa_personal as $emp): ?>
      <tr><td><strong><?= h($emp['nombre']) ?></strong><small><?= h($emp['codigo_empleado']) ?></small></td><td><?= h($emp['usuario']) ?></td><td><?= h(ucwords(strtolower(str_replace('_',' ', $emp['rol'])))) ?></td><td><span class="sa-personal-state <?= $emp['activo'] ? 'is-active' : '' ?>"><?= $emp['activo'] ? 'Activo' : 'Inactivo' ?></span></td><td>
      <?php if ($emp['rol'] === 'SUPERADMIN'): ?><span class="sa-protected">Cuenta protegida</span>
      <?php else: ?><form method="post" action="superadmin.php?seccion=personal" class="sa-personal-form"><input type="hidden" name="csrf_token" value="<?= h(generar_csrf()) ?>"><input type="hidden" name="sa_action" value="set_active"><input type="hidden" name="target_id" value="<?= (int)$emp['id_empleado'] ?>"><input type="hidden" name="active" value="<?= $emp['activo'] ? '0' : '1' ?>"><button class="sa-action-button" type="submit" onclick="return confirm('¿Confirmás el cambio de estado de esta cuenta?')"><?= $emp['activo'] ? 'Desactivar' : 'Activar' ?></button></form><?php endif; ?>
      </td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <p class="sa-personal-note">Las cuentas SUPERADMIN están protegidas. Los cambios de estado se registran en auditoría. La edición de roles y credenciales se incorporará en el siguiente bloque.</p>
  </div>
</section>
