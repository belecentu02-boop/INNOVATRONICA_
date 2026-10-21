<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Pantalla y Controlador: Cambio de Contraseña (Fase 3.1)
 * Soporta cambio obligatorio (debe_cambiar_password = 1) y voluntario desde el menú.
 * Fuente: docs/decisiones.md (Sección 16)
 */

require_once __DIR__ . '/includes/auth.php';

// Verificación obligatoria de sesión activa
requerir_login();

$u = usuario_actual();
$id_empleado = (int)$u['id_empleado'];
$pdo = db();

// Comprobar en tiempo real si el cambio es obligatorio
$es_obligatorio = debe_cambiar_password_actual();

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verificación obligatoria de token CSRF
    $token_recibido = $_POST['csrf_token'] ?? '';
    if (!verificar_csrf($token_recibido)) {
        $error = 'Token de seguridad inválido o sesión expirada. Por favor intente nuevamente.';
    } else {
        $password_actual    = $_POST['password_actual'] ?? '';
        $password_nueva     = $_POST['password_nueva'] ?? '';
        $password_confirmar = $_POST['password_confirmar'] ?? '';

        // Validar que los campos no estén vacíos
        if ($password_actual === '' || $password_nueva === '' || $password_confirmar === '') {
            $error = 'Por favor complete todos los campos del formulario.';
        } else {
            try {
                // Consultar hash actual y estado de bloqueo del usuario en tiempo real
                $stUser = $pdo->prepare('
                    SELECT id_empleado, codigo_empleado, usuario, password_hash, activo, intentos_fallidos, bloqueado_hasta 
                    FROM usuarios 
                    WHERE id_empleado = ? 
                    LIMIT 1
                ');
                $stUser->execute([$id_empleado]);
                $userData = $stUser->fetch(PDO::FETCH_ASSOC);

                if (!$userData || (int)$userData['activo'] !== 1) {
                    destruir_sesion();
                    header('Location: login.php?error=desactivado');
                    exit;
                }

                $ahora = new DateTime();

                // Verificar si la cuenta se encuentra temporalmente bloqueada por reiterados fallos
                if (!empty($userData['bloqueado_hasta'])) {
                    $bloqueo = new DateTime($userData['bloqueado_hasta']);
                    if ($bloqueo > $ahora) {
                        $minutos_restantes = ceil(($bloqueo->getTimestamp() - $ahora->getTimestamp()) / 60);
                        $error = sprintf('Cuenta bloqueada temporalmente por exceso de intentos fallidos. Reintente en %d minuto(s).', $minutos_restantes);
                    }
                }

                if (!$error) {
                    // Validar la contraseña actual (cuenta para el límite de 5 intentos fallidos según sección 16)
                    if (!password_verify($password_actual, $userData['password_hash'])) {
                        $nuevos_intentos = (int)$userData['intentos_fallidos'] + 1;
                        if ($nuevos_intentos >= 5) {
                            $bloqueo_hasta = (clone $ahora)->modify('+15 minutes')->format('Y-m-d H:i:s');
                            $upd = $pdo->prepare('UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id_empleado = ?');
                            $upd->execute([$nuevos_intentos, $bloqueo_hasta, $id_empleado]);
                            registrar_log('CUENTA_BLOQUEADA', 'Bloqueo temporal por 5 intentos fallidos en cambio de contraseña', $id_empleado);
                            $error = 'Cuenta bloqueada temporalmente por 15 minutos debido a reiterados intentos fallidos.';
                        } else {
                            $upd = $pdo->prepare('UPDATE usuarios SET intentos_fallidos = ? WHERE id_empleado = ?');
                            $upd->execute([$nuevos_intentos, $id_empleado]);
                            registrar_log('PASSWORD_CAMBIO_FALLIDO', 'Contraseña actual incorrecta (intento ' . $nuevos_intentos . '/5)', $id_empleado);
                            $error = 'La contraseña actual ingresada es incorrecta. Intentos restantes antes del bloqueo: ' . (5 - $nuevos_intentos) . '.';
                        }
                    } else {
                        // Contraseña actual correcta: validar requisitos de la nueva contraseña
                        if (mb_strlen($password_nueva) < 8) {
                            $error = 'La nueva contraseña debe contener un mínimo de 8 caracteres.';
                        } elseif ($password_nueva !== $password_confirmar) {
                            $error = 'La confirmación no coincide con la nueva contraseña ingresada.';
                        } elseif (password_verify($password_nueva, $userData['password_hash'])) {
                            // Regla de sección 16: "distinta de la actual"
                            $error = 'La nueva contraseña debe ser distinta de la contraseña actual.';
                        } else {
                            // Cifrar la nueva contraseña
                            $nuevo_hash = password_hash($password_nueva, PASSWORD_DEFAULT);

                            // Actualizar en base de datos: nuevo hash, debe_cambiar_password = 0, reset de intentos fallidos
                            $stUpd = $pdo->prepare('
                                UPDATE usuarios 
                                SET password_hash = ?, debe_cambiar_password = 0, intentos_fallidos = 0, bloqueado_hasta = NULL 
                                WHERE id_empleado = ?
                            ');
                            $stUpd->execute([$nuevo_hash, $id_empleado]);

                            // Regenerar identificador de sesión por seguridad (decisiones.md secc. 16)
                            session_regenerate_id(true);

                            // Registrar evento en log de auditoría
                            $tipo_cambio = $es_obligatorio ? 'obligatorio' : 'voluntario';
                            registrar_log('CAMBIO_PASSWORD', "Cambio de contraseña exitoso ({$tipo_cambio}) para {$userData['codigo_empleado']} ({$userData['usuario']})", $id_empleado);

                            // Limpiar caché estática del estado en memoria
                            obtener_estado_usuario_db(true);

                            // Redireccionar al dashboard con notificación de confirmación
                            header('Location: index.php?msg=password_actualizada');
                            exit;
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log('Error PDO en cambiar_password.php: ' . $e->getMessage());
                $error = 'Ocurrió un error al procesar el cambio de contraseña. Por favor intente nuevamente.';
            } catch (Exception $e) {
                error_log('Error en cambiar_password.php: ' . $e->getMessage());
                $error = 'Error del sistema: ' . $e->getMessage();
            }
        }
    }
}

$csrf_token = generar_csrf();
$page_title = ($es_obligatorio ? 'Actualización Obligatoria de Contraseña' : 'Cambiar Contraseña') . ' · Innovatrónica S.A.';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container" style="max-width: 640px; margin: 2.5rem auto; padding: 0 1rem;">

  <!-- Tarjeta de Contenedor Principal -->
  <div class="pwd-card">

    <!-- Encabezado de la Tarjeta -->
    <div class="pwd-card-header">
      <div class="pwd-icon-box <?= $es_obligatorio ? 'pwd-icon-warning' : 'pwd-icon-primary' ?>">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="7.5" cy="15.5" r="4.5"/>
          <path d="m21 2-9.6 9.6"/>
          <path d="m15.5 7.5 3 3L21 8l-2-2"/>
        </svg>
      </div>
      <div>
        <h1 class="pwd-card-title">
          <?= $es_obligatorio ? 'Actualización Obligatoria de Contraseña' : 'Cambiar Contraseña' ?>
        </h1>
        <p class="pwd-card-sub">
          <?= $es_obligatorio 
              ? 'Por políticas de seguridad, debes reemplazar la contraseña temporal asignada por el Administrador antes de utilizar el sistema.' 
              : 'Actualiza periódicamente tu contraseña para proteger tu cuenta y operaciones.' 
          ?>
        </p>
      </div>
    </div>

    <!-- Alertas de Estado -->
    <?php if ($es_obligatorio): ?>
      <div class="alert alert-warning" role="alert" style="margin-bottom: 1.5rem;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <div>
          <strong>Acceso Restringido:</strong> Mientras esta actualización esté pendiente, todas las funciones y consultas del sistema permanecerán bloqueadas.
        </div>
      </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <div class="alert alert-danger" role="alert" style="margin-bottom: 1.5rem;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;">
          <circle cx="12" cy="12" r="10"/>
          <line x1="15" y1="9" x2="9" y2="15"/>
          <line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        <span><?= h($error) ?></span>
      </div>
    <?php endif; ?>

    <!-- Formulario de Cambio de Contraseña (POST Directo) -->
    <form method="POST" action="cambiar_password.php" autocomplete="off" id="form-cambio-password">
      <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

      <!-- Resumen del Usuario Operador -->
      <div class="pwd-user-meta-box">
        <div>
          <span class="pwd-meta-label">COLABORADOR</span>
          <strong><?= h($u['nombre']) ?></strong>
        </div>
        <div style="text-align: right;">
          <span class="pwd-meta-label">LEGAJO / USUARIO</span>
          <span style="font-family: monospace; font-weight: 700;"><?= h($u['codigo_empleado']) ?></span>
          <span style="color: var(--text-muted); font-size: 0.85rem;">(@<?= h($u['usuario']) ?>)</span>
        </div>
      </div>

      <!-- 1. Contraseña Actual -->
      <div class="form-group" style="margin-bottom: 1.25rem;">
        <label for="password_actual" class="form-label">Contraseña Actual *</label>
        <div class="input-password-wrapper">
          <input 
            type="password" 
            id="password_actual" 
            name="password_actual" 
            class="form-input" 
            required 
            autofocus 
            placeholder="Ingrese su contraseña actual o temporal"
          >
          <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('password_actual', this)" aria-label="Mostrar u ocultar contraseña">
            <svg class="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <span class="form-helper-text">
          Nota de seguridad: Esta verificación cuenta para el límite de 5 intentos fallidos antes de bloquear la cuenta por 15 minutos.
        </span>
      </div>

      <!-- 2. Nueva Contraseña -->
      <div class="form-group" style="margin-bottom: 1.25rem;">
        <label for="password_nueva" class="form-label">Nueva Contraseña *</label>
        <div class="input-password-wrapper">
          <input 
            type="password" 
            id="password_nueva" 
            name="password_nueva" 
            class="form-input" 
            required 
            minlength="8" 
            placeholder="Mínimo 8 caracteres"
          >
          <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('password_nueva', this)" aria-label="Mostrar u ocultar contraseña">
            <svg class="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <!-- 3. Confirmar Nueva Contraseña -->
      <div class="form-group" style="margin-bottom: 1.5rem;">
        <label for="password_confirmar" class="form-label">Confirmar Nueva Contraseña *</label>
        <div class="input-password-wrapper">
          <input 
            type="password" 
            id="password_confirmar" 
            name="password_confirmar" 
            class="form-input" 
            required 
            minlength="8" 
            placeholder="Repita la nueva contraseña"
          >
          <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('password_confirmar', this)" aria-label="Mostrar u ocultar contraseña">
            <svg class="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <!-- Checklist de Políticas de Seguridad (Sección 16) -->
      <div class="pwd-requirements-card">
        <div class="pwd-requirements-title">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
          <span>Requisitos obligatorios de seguridad:</span>
        </div>
        <ul class="pwd-requirements-list">
          <li id="req-len">
            <span class="req-bullet">●</span> Longitud mínima de 8 caracteres.
          </li>
          <li id="req-diff">
            <span class="req-bullet">●</span> Debe ser distinta a la contraseña actual.
          </li>
          <li id="req-match">
            <span class="req-bullet">●</span> La nueva contraseña y su confirmación deben coincidir.
          </li>
        </ul>
      </div>

      <!-- Botones de Acción -->
      <div class="pwd-actions-row">
        <?php if (!$es_obligatorio): ?>
          <a href="index.php" class="btn btn-outline" style="flex: 1;">
            Cancelar
          </a>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary" id="btn-submit-pwd" style="flex: 2;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
            <polyline points="17 21 17 13 7 13 7 21"/>
            <polyline points="7 3 7 8 15 8"/>
          </svg>
          <span>Establecer y Guardar Contraseña</span>
        </button>
      </div>
    </form>

  </div>
</main>

<script>
  function togglePasswordVisibility(fieldId, btn) {
    const input = document.getElementById(fieldId);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    btn.classList.toggle('active', isPassword);
  }

  // Validación reactiva ligera de requisitos
  (function() {
    const pNueva = document.getElementById('password_nueva');
    const pConf = document.getElementById('password_confirmar');
    const pAct = document.getElementById('password_actual');
    const reqLen = document.getElementById('req-len');
    const reqDiff = document.getElementById('req-diff');
    const reqMatch = document.getElementById('req-match');

    function checkLive() {
      const vNueva = pNueva.value;
      const vConf = pConf.value;
      const vAct = pAct.value;

      if (reqLen) {
        reqLen.classList.toggle('valid', vNueva.length >= 8);
      }
      if (reqDiff) {
        reqDiff.classList.toggle('valid', vNueva.length > 0 && vAct.length > 0 && vNueva !== vAct);
      }
      if (reqMatch) {
        reqMatch.classList.toggle('valid', vNueva.length > 0 && vConf.length > 0 && vNueva === vConf);
      }
    }

    if (pNueva && pConf && pAct) {
      pNueva.addEventListener('input', checkLive);
      pConf.addEventListener('input', checkLive);
      pAct.addEventListener('input', checkLive);
    }
  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
