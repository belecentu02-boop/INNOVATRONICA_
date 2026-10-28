<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Pantalla de Inicio de Sesión (Login) - Fase 1
 */

require_once __DIR__ . '/includes/auth.php';
header('Cache-Control: no-store, private');

// Si ya tiene sesión activa, redirigir según estado de cambio obligatorio
if (esta_autenticado()) {
    if (debe_cambiar_password_actual()) {
        header('Location: cambiar_password.php');
    } else {
        header('Location: portal.php');
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
                                header('Location: portal.php');
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
  <title>Portal de empleados · Innovatrónica S.A.</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
  <link rel="stylesheet" href="assets/css/portal.css?v=<?= filemtime(__DIR__ . '/assets/css/portal.css') ?>">
  <link rel="icon" type="image/png" href="assets/img/marca/icono-innovatronica.png">
</head>
<body class="employee-login">
  <a class="portal-skip-link" href="#login-form-title">Saltar al ingreso</a>
  <main class="employee-login-layout">
    <aside class="employee-login-story" aria-label="Innovatrónica S.A.">
      <div class="employee-story-brand">
        <img src="assets/img/marca/icono-innovatronica.png" width="54" height="54" alt="">
        <div><strong>Innovatrónica S.A.</strong><span>Portal interno de la empresa</span></div>
      </div>
      <div class="employee-story-copy">
        <span class="portal-eyebrow">PERSONAS · TECNOLOGÍA · CONEXIÓN</span>
        <h2>Una cuenta.<br><span>Tu espacio<br>de trabajo.</span></h2>
        <p>Conectá con las herramientas de tu área y seguí cada operación desde un mismo lugar.</p>
        <svg class="employee-story-graphic" viewBox="0 0 330 120" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
          <path d="M25 30h70l28 28h58l25-25h85M25 90h66l30-28M181 62l24 26h86"/>
          <rect x="3" y="17" width="28" height="28" rx="7" fill="#17212f"/><rect x="3" y="76" width="28" height="28" rx="7" fill="#17212f"/>
          <rect x="126" y="35" width="51" height="51" rx="12" stroke="#df995d" fill="#17212f"/><path d="m140 54 12-6 12 6v14l-12 6-12-6V54Z" stroke="#df995d"/><path d="m140 54 12 7 12-7M152 61v13" stroke="#df995d"/>
          <circle cx="301" cy="33" r="7" stroke="#df995d"/><circle cx="301" cy="88" r="7"/>
          <circle cx="17" cy="31" r="3"/><circle cx="17" cy="90" r="3"/>
        </svg>
      </div>
      <p class="employee-story-foot">SISTEMA INTERNO / INNOVATRÓNICA S.A.</p>
    </aside>

    <section class="employee-login-panel" aria-labelledby="login-form-title">
      <div class="employee-login-form">
        <div class="employee-login-brand"><img src="assets/img/marca/logo-innovatronica.png" width="2048" height="682" alt="Innovatrónica S.A."></div>
        <span class="portal-eyebrow">PORTAL DE EMPLEADOS</span>
        <h1 id="login-form-title" tabindex="-1">Bienvenido a tu espacio.</h1>
        <p class="employee-login-intro">Ingresá con tu cuenta para acceder a las herramientas de tu área.</p>
    <?php if ($error !== ''): ?>
      <div class="alert alert-danger" role="alert">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="flex-shrink:0;"><path d="m10.3 3.9-8.1 14A2 2 0 0 0 3.9 21h16.2a2 2 0 0 0 1.7-3.1l-8.1-14a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>
        <span><?= h($error) ?></span>
      </div>
    <?php endif; ?>

    <form data-loading-text="Ingresando…" method="POST" action="login.php">
      <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

      <div class="form-group" style="margin-bottom: 1rem;">
        <label for="usuario" class="form-label">Nombre de Usuario</label>
        <input
          type="text"
          id="usuario"
          name="usuario"
          autocomplete="username" autocapitalize="none" spellcheck="false"
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
          autocomplete="current-password"
          class="form-input"
          required
          placeholder="Ingrese su contraseña"
        >
      </div>

      <button type="submit" class="btn btn-primary btn-block">
        Ingresar a mi espacio
      </button>
    </form>
        <p class="login-access-note"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><span>Acceso exclusivo para personal autorizado.<br>Si necesitás una cuenta, contactá al administrador.</span></p>
      </div>
      <p class="employee-login-footer">© <?= date('Y') ?> Innovatrónica S.A. · Portal interno</p>
    </section>
  </main>
  <script src="assets/js/ui-feedback.js" defer></script>
</body>
</html>
