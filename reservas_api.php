<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
require_once __DIR__ . '/includes/auth.php';
if (!esta_autenticado() || !verificar_sesion_activa()) {
    if (esta_autenticado()) destruir_sesion();
    http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Sesión no iniciada o usuario desactivado.']); exit;
}
if (debe_cambiar_password_actual()) {
    http_response_code(403); echo json_encode(['ok'=>false,'codigo'=>'PASSWORD_CHANGE_REQUIRED','error'=>'Debe cambiar su contraseña.']); exit;
}
$rol = $_SESSION['u']['rol'] ?? '';
if (!in_array($rol, ['ADMIN','SUPERVISOR_COMERCIAL','VENDEDOR_INTERNO'], true)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Acceso denegado.']); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { header('Allow: GET'); http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Método no permitido.']); exit; }
try {
    define('INNOVATRONICA_STOCK_API', true); require_once __DIR__ . '/includes/stock.php';
    define('INNOVATRONICA_RESERVAS_API', true); require_once __DIR__ . '/includes/reservas.php';
    echo json_encode(reservas_listar(db(), $_GET, (int)$_SESSION['u']['id_empleado'], $rol), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (ReservasError $e) {
    http_response_code(422); echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Error al consultar reservas: ' . $e->getMessage());
    http_response_code(500); echo json_encode(['ok'=>false,'error'=>'No se pudieron consultar las reservas.']);
}
