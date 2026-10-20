<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Módulo de Autenticación, Control de Acceso y Seguridad
 */

// Prevenir acceso directo al archivo desde el navegador
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Acceso directo prohibido.');
}

require_once __DIR__ . '/../config/db.php';

// Encabezados HTTP de seguridad defensiva
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
}

// Iniciar sesión segura si no está activa
if (session_status() === PHP_SESSION_NONE) {
    $segura = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $segura,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Escapar cadenas para salida segura en HTML (prevención de XSS).
 */
function h($str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generar o recuperar token CSRF actual.
 */
function generar_csrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validar token CSRF recibido contra el almacenado en la sesión.
 */
function verificar_csrf(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verificar si hay un usuario autenticado en la sesión actual.
 */
function esta_autenticado(): bool {
    return !empty($_SESSION['u']) && !empty($_SESSION['u']['id_empleado']);
}

/**
 * Obtener datos del usuario logueado en la sesión.
 */
function usuario_actual(): ?array {
    return esta_autenticado() ? $_SESSION['u'] : null;
}

/**
 * Comprobar si el usuario actual tiene rol de Administrador.
 */
function es_admin(): bool {
    return ($_SESSION['u']['rol'] ?? '') === 'ADMIN';
}

/**
 * Comprobar si el usuario actual tiene rol de Supervisor Comercial.
 */
function es_supervisor(): bool {
    return ($_SESSION['u']['rol'] ?? '') === 'SUPERVISOR_COMERCIAL';
}

/**
 * Comprobar si el usuario actual tiene rol de Vendedor Interno.
 */
function es_vendedor(): bool {
    return ($_SESSION['u']['rol'] ?? '') === 'VENDEDOR_INTERNO';
}

/**
 * Obtener la clase CSS del badge correspondiente al rol.
 */
function rol_badge_class(?string $rol = null): string {
    $r = $rol ?? ($_SESSION['u']['rol'] ?? '');
    switch ($r) {
        case 'ADMIN': return 'badge-admin';
        case 'SUPERVISOR_COMERCIAL': return 'badge-supervisor';
        default: return 'badge-vendedor';
    }
}

/**
 * Obtener la etiqueta legible correspondiente al rol.
 */
function rol_label(?string $rol = null): string {
    $r = $rol ?? ($_SESSION['u']['rol'] ?? '');
    switch ($r) {
        case 'ADMIN': return 'Administrador General';
        case 'SUPERVISOR_COMERCIAL': return 'Supervisor Comercial';
        default: return 'Vendedor Interno';
    }
}

/**
 * Redirigir al login si el usuario no tiene una sesión activa.
 */
function requerir_login(): void {
    if (!esta_autenticado()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Restringir acceso en el backend según lista de roles permitidos.
 * Devuelve código HTTP 403 si el rol no está autorizado.
 *
 * @param string[] $roles_permitidos
 */
function requerir_rol(array $roles_permitidos): void {
    requerir_login();
    $rolActual = $_SESSION['u']['rol'] ?? '';
    if (!in_array($rolActual, $roles_permitidos, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>403 Prohibido</title>'
           . '<link rel="stylesheet" href="assets/css/style.css"></head>'
           . '<body style="display:grid;place-items:center;min-height:100vh;">'
           . '<div class="login-card" style="text-align:center;">'
           . '<h2 style="color:var(--danger);margin-bottom:0.75rem;">Acceso Denegado (403)</h2>'
           . '<p>Tu rol (<strong>' . h($rolActual) . '</strong>) no tiene autorización para acceder a este módulo.</p>'
           . '<a href="index.php" class="btn btn-primary" style="margin-top:1.25rem;display:inline-block;">Volver al inicio</a>'
           . '</div></body></html>';
        exit;
    }
}

/**
 * Registrar un evento en la tabla log_acciones para auditoría.
 */
function registrar_log(string $accion, ?string $detalles = null, ?int $id_empleado = null): void {
    try {
        $pdo = db();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $empId = $id_empleado ?? ($_SESSION['u']['id_empleado'] ?? null);
        $st = $pdo->prepare('INSERT INTO log_acciones (id_empleado, accion, detalles, ip) VALUES (?, ?, ?, ?)');
        $st->execute([$empId, $accion, $detalles, $ip]);
    } catch (Exception $e) {
        error_log('Error al registrar en log_acciones: ' . $e->getMessage());
    }
}
