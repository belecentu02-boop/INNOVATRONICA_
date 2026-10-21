<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Cierre de Sesión Seguro (Logout)
 */

require_once __DIR__ . '/includes/auth.php';

if (esta_autenticado()) {
    registrar_log('LOGOUT', 'Cierre de sesión manual');
}

// Destruir sesión y cookie de forma exhaustiva
destruir_sesion();

// Redirigir a la pantalla de login
header('Location: login.php');
exit;
