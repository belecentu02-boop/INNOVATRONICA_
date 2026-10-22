<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Cierre de Sesión Seguro (Logout)
 */

require_once __DIR__ . '/includes/auth.php';

// Cerrar sesión es una acción de estado: solo se acepta por POST con token CSRF válido.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

$token_recibido = $_POST['csrf_token'] ?? '';
if (!verificar_csrf(is_string($token_recibido) ? $token_recibido : null)) {
    http_response_code(403);
    exit('Solicitud no válida o token de seguridad expirado.');
}

if (esta_autenticado()) {
    registrar_log('LOGOUT', 'Cierre de sesión manual');
}

// Destruir sesión y cookie de forma exhaustiva
destruir_sesion();

// Redirigir a la pantalla de login
header('Location: login.php');
exit;
