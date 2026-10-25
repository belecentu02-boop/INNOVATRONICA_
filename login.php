<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Pantalla de Inicio de Sesión (Login) - Fase 1
 */

require_once __DIR__ . '/includes/auth.php';

// Si ya tiene sesión activa, redirigir según estado de cambio obligatorio
if (esta_autenticado()) {
    if (debe_cambiar_password_actual()) {
        header('Location: cambiar_password.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
if (isset($_GET['error']) && $_GET['error'] === 'desactivado') {
    $error = 'Tu sesión ha finalizado porque tu cuenta fue desactivada por el Administrador.';
}
$usuario_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verificación de token CSRF
    $token_recibido = $_POST['csrf_token'] ?? '';
    if (!verificar_csrf($token_recibido)) {
        $error = 'Solicitud no válida o token de seguridad expirado. Intente nuevamente.';
    } else {
        $usuario_val = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($usuario_val === '' || $password === '') {
            $error = 'Por favor complete todos los campos.';
        } else {
            try {
                $pdo = db();
                $st = $pdo->prepare('SELECT id_empleado, codigo_empleado, nombre, usuario, password_hash, rol, activo, debe_cambiar_password, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE usuario = ?');
                $st->execute([$usuario_val]);
                $u = $st->fetch();

                $ahora = new DateTime();

                if ($u) {
                    // Verificar si la cuenta está bloqueada temporalmente por intentos fallidos
                    if (!empty($u['bloqueado_hasta'])) {
                        $bloqueo = new DateTime($u['bloqueado_hasta']);
                        if ($bloqueo > $ahora) {
                            $minutos_restantes = ceil(($bloqueo->getTimestamp() - $ahora->getTimestamp()) / 60);
                            $error = sprintf('Cuenta bloqueada temporalmente por exceso de intentos fallidos. Reintente en %d minuto(s).', $minutos_restantes);
                        }
                    }

                    // Verificar si la cuenta está activa
                    if (!$error && (int)$u['activo'] !== 1) {
                        registrar_log('LOGIN_BLOQUEADO', 'Intento de inicio de sesión con usuario inactivo: ' . $u['usuario'], (int)$u['id_empleado']);
                        $error = 'Usuario inactivo. Comuníquese con el Administrador del Sistema.';
                    }

                    // Si no hay bloqueo, verificar credenciales
                    if (!$error) {
                        if (password_verify($password, $u['password_hash'])) {
                            // Login exitoso: resetear intentos fallidos
                            $upd = $pdo->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_empleado = ?');
                            $upd->execute([$u['id_empleado']]);

                            // Regenerar ID de sesión para prevenir Session Fixation
                            session_regenerate_id(true);
                            // No reutilizar un token CSRF creado antes de autenticar.
                            unset($_SESSION['csrf_token']);

                            // Guardar datos esenciales en sesión
                            $_SESSION['u'] = [
                                'id_empleado'     => (int)$u['id_empleado'],
                                'codigo_empleado' => $u['codigo_empleado'],
                                'nombre'          => $u['nombre'],
                                'usuario'         => $u['usuario'],
                                'rol'             => $u['rol']
                            ];

                            // Registrar acción en log de auditoría
                            registrar_log('LOGIN_EXITOSO', 'Inicio de sesión exitoso con rol ' . $u['rol'], (int)$u['id_empleado']);

                            // Si debe cambiar la contraseña, forzar destino a cambiar_password.php
                            if ((int)($u['debe_cambiar_password'] ?? 0) === 1) {
                                header('Location: cambiar_password.php');
                            } else {
                                header('Location: index.php');
                            }
                            exit;
                        } else {
                            // Password incorrecto: registrar intento fallido
                            $nuevos_intentos = (int)$u['intentos_fallidos'] + 1;
                            if ($nuevos_intentos >= 5) {
                                $bloqueo_hasta = (clone $ahora)->modify('+15 minutes')->format('Y-m-d H:i:s');
                                $upd = $pdo->prepare('UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id_empleado = ?');
                                $upd->execute([$nuevos_intentos, $bloqueo_hasta, $u['id_empleado']]);
                                registrar_log('CUENTA_BLOQUEADA', 'Bloqueo temporal por 5 intentos fallidos', (int)$u['id_empleado']);
                                $error = 'Cuenta bloqueada temporalmente por 15 minutos debido a reiterados intentos fallidos.';
                            } else {
                                $upd = $pdo->prepare('UPDATE usuarios SET intentos_fallidos = ? WHERE id_empleado = ?');
                                $upd->execute([$nuevos_intentos, $u['id_empleado']]);
                                registrar_log('LOGIN_FALLIDO', 'Contraseña incorrecta (intento ' . $nuevos_intentos . '/5)', (int)$u['id_empleado']);
                                $error = 'Usuario o contraseña incorrectos.';
                            }
                        }
                    }
                } else {
                    // Usuario no encontrado: mensaje genérico para no revelar existencia de usuarios
                    registrar_log('LOGIN_FALLIDO', 'Intento de login con usuario inexistente: ' . substr($usuario_val, 0, 30));
                    $error = 'Usuario o contraseña incorrectos.';
                }
            } catch (PDOException $e) {
                error_log('Error en login: ' . $e->getMessage());
                $error = 'Ocurrió un error al procesar el ingreso. Intente nuevamente.';
            }
        }
    }
}

$csrf_token = generar_csrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ingreso al Sistema · Innovatrónica S.A.</title>
  <link rel="stylesheet" href="assets/css/style.css">

  <style>
    .login-page {
      min-height: 100vh;
      min-height: 100svh;
      padding: 1.5rem;
      background-color: #17212f;
      background-image: linear-gradient(rgba(148,163,184,.045) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(148,163,184,.045) 1px, transparent 1px);
      background-size: 40px 40px;
    }
    .login-page .login-card {
      width: 100%; max-width: 420px;
      padding: 2rem; gap: 1.5rem;
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-top: 4px solid #b45309;
      border-radius: 12px;
      box-shadow: 0 16px 48px rgba(0,0,0,.22);
    }
    .login-page .brand-header { text-align: left; }
    .login-page .brand-logo-icon {
      width: 44px; height: 44px; margin-bottom: 1rem;
      font-size: 1.25rem; border-radius: 8px;
      background: #b45309;
      box-shadow: none;
    }
    .login-page .brand-title { font-size: 1.4rem; font-weight: 700; }
    .login-page .brand-subtitle { color: #475569; line-height: 1.5; }
    .login-page .login-heading {
      margin: 0 0 0.35rem; font-size: 1rem; font-weight: 600; color: #0f172a;
    }
    .login-page .login-intro { margin: 0; font-size: .875rem; color: #475569; }
    .login-page .form-input { min-height: 46px; background: #fff; font-size: 1rem; }
    .login-page .btn-primary { min-height: 46px; background: #b45309; box-shadow: none; }
    .login-page .btn-primary:hover { background: #92400e; transform: none; }
    .login-page .btn:focus-visible { outline: 3px solid #b45309; outline-offset: 3px; }
    .login-page .login-access-note {
      display: flex; align-items: center; gap: .5rem;
      margin: 0; padding-top: 1rem; border-top: 1px solid #e2e8f0;
      color: #475569; font-size: .75rem;
    }
    @media (max-width: 480px) {
      .login-page { padding: 1rem; }
      .login-page .login-card { padding: 1.5rem; }
    }

    /* Marca compacta en escritorio; mantiene la disposicion movil. */
    @media (min-width: 769px) {
      .login-page .brand-header {
        display: grid;
        grid-template-columns: 44px minmax(0, 1fr);
        column-gap: 0.85rem;
        align-items: center;
      }
      .login-page .brand-header .brand-logo-icon {
        grid-column: 1;
        grid-row: 1 / 3;
        margin: 0;
      }
      .login-page .brand-header .brand-title {
        grid-column: 2;
        grid-row: 1;
        margin: 0;
        line-height: 1.3;
      }
      .login-page .brand-header .brand-subtitle {
        grid-column: 2;
        grid-row: 2;
        margin: 0.2rem 0 0;
      }
    }

    /* Entrada breve, sin ocultar el formulario si no hay animaciones. */
    @keyframes login-card-enter {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @media (prefers-reduced-motion: no-preference) {
      .login-page .login-card {
        animation: login-card-enter 250ms ease-out;
      }
    }
    @media (prefers-reduced-motion: reduce) {
      .login-page .login-card { animation: none; }
    }
  </style>
</head>
<body class="login-page">

  <main class="login-card" aria-labelledby="login-title">
    <div class="brand-header">
      <div class="brand-logo-icon">IN</div>
      <h1 class="brand-title" id="login-title">Innovatrónica S.A.</h1>
      <p class="brand-subtitle">Control de Stock · Ventas Internas</p>
    </div>

    <div>
      <h2 class="login-heading">Iniciar sesión</h2>
      <p class="login-intro">Ingresá con tu cuenta de empleado.</p>
    </div>

    <?php if ($error !== ''): ?>
      <div class="alert alert-danger" role="alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="flex-shrink:0;"><path d="m10.3 3.9-8.1 14A2 2 0 0 0 3.9 21h16.2a2 2 0 0 0 1.7-3.1l-8.1-14a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>
        <span><?= h($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

      <div class="form-group" style="margin-bottom: 1rem;">
        <label for="usuario" class="form-label">Nombre de Usuario</label>
        <input 
          type="text" 
          id="usuario" 
          name="usuario" 
          class="form-input" 
          required 
          autofocus 
          value="<?= h($usuario_val) ?>"
          placeholder="Ingrese su usuario"
        >
      </div>

      <div class="form-group" style="margin-bottom: 1.25rem;">
        <label for="password" class="form-label">Contraseña</label>
        <input 
          type="password" 
          id="password" 
          name="password" 
          class="form-input" 
          required
          placeholder="Ingrese su contraseña"
        >
      </div>

      <button type="submit" class="btn btn-primary btn-block">
        Ingresar al Sistema
      </button>
    </form>


    <p class="login-access-note"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="flex-shrink:0;"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><span>Acceso exclusivo para personal autorizado.</span></p>
  </main>
</body>
</html>
