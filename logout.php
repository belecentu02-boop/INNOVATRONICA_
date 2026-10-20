<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Cierre de Sesión Seguro (Logout)
 */

require_once __DIR__ . '/includes/auth.php';

if (esta_autenticado()) {
    registrar_log('LOGOUT', 'Cierre de sesión manual');
}

// Vaciar datos de sesión
$_SESSION = [];

// Eliminar cookie de sesión si existe
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destruir sesión en el servidor
session_destroy();

// Redirigir a la pantalla de login
header('Location: login.php');
exit;
