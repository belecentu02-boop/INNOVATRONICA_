<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * API REST Interna y Endpoints de Gestión (Fase 3: Gestión de Personal)
 * Protección estricta con es_admin(), verificación de sesión activa y token CSRF.
 */

// Encabezados HTTP de API y seguridad defensiva
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

require_once __DIR__ . '/includes/auth.php';

// 1. Verificación de autenticación y de usuario activo en tiempo real
if (!esta_autenticado() || !verificar_sesion_activa()) {
    if (esta_autenticado()) {
        destruir_sesion();
    }
    http_response_code(401);
    echo json_encode([
        'ok'    => false,
        'error' => 'Sesión no iniciada o usuario desactivado. Por favor ingrese nuevamente.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Verificación de cambio de contraseña obligatorio (decisiones.md secc. 16)
if (debe_cambiar_password_actual()) {
    http_response_code(403);
    echo json_encode([
        'ok'     => false,
        'error'  => 'Debe cambiar su contraseña antes de realizar cualquier operación en el sistema.',
        'codigo' => 'PASSWORD_CHANGE_REQUIRED'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Verificación estricta de rol ADMIN (backend)
if (!es_admin()) {
    http_response_code(403);
    echo json_encode([
        'ok'    => false,
        'error' => 'Acceso denegado (403): Se requieren permisos de Administrador General para operar esta API.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 3. Captura unificada de parámetros (soporta GET, POST form-urlencoded y JSON payload)
$params = $_GET;
if ($method === 'POST') {
    $params = array_merge($params, $_POST);
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonInput = json_decode($rawInput, true);
        if (is_array($jsonInput)) {
            $params = array_merge($params, $jsonInput);
        }
    }

    // 4. Verificación obligatoria de token CSRF para todas las peticiones POST
    $token_recibido = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($params['csrf_token'] ?? '');
    if (!verificar_csrf($token_recibido)) {
        http_response_code(403);
        echo json_encode([
            'ok'    => false,
            'error' => 'Token CSRF inválido o sesión expirada. Por favor recargue la página.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$accion = trim($params['accion'] ?? ($params['action'] ?? ''));
$pdo = db();
$current_admin_id = (int)($_SESSION['u']['id_empleado'] ?? 0);

try {
    switch ($accion) {

        // ============================================================
        // 1. LISTAR USUARIOS
        // ============================================================
        case 'listar_usuarios':
            $stmt = $pdo->query("
                SELECT 
                    id_empleado,
                    codigo_empleado,
                    nombre,
                    usuario,
                    rol,
                    activo,
                    debe_cambiar_password,
                    DATE_FORMAT(creado_en, '%d/%m/%Y %H:%i') AS creado_en,
                    DATE_FORMAT(actualizado_en, '%d/%m/%Y %H:%i') AS actualizado_en
                FROM usuarios 
                ORDER BY id_empleado ASC
            ");
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'ok'               => true,
                'current_admin_id' => $current_admin_id,
                'usuarios'         => $usuarios
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ============================================================
        // 2. CREAR USUARIO (ALTA)
        // ============================================================
        case 'crear_usuario':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['ok' => false, 'error' => 'Método no permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $nombre = trim($params['nombre'] ?? '');
            $usuario = trim(strtolower($params['usuario'] ?? ''));
            $password = $params['password'] ?? '';
            $rol = trim($params['rol'] ?? '');

            // Validaciones de entrada
            if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'El nombre completo debe tener entre 3 y 100 caracteres.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (!preg_match('/^[a-z0-9._-]{3,30}$/', $usuario)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'El usuario debe tener entre 3 y 30 caracteres alfanuméricos (letras minúsculas, números, puntos, guiones).'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (mb_strlen($password) < 8) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'La contraseña inicial debe contener al menos 8 caracteres.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Regla de negocio (decisiones.md secc. 15): Desde la interfaz NO se crean ADMIN
            $roles_permitidos = ['VENDEDOR_INTERNO', 'SUPERVISOR_COMERCIAL'];
            if (!in_array($rol, $roles_permitidos, true)) {
                http_response_code(400);
                echo json_encode([
                    'ok'    => false,
                    'error' => 'Rol inválido. Desde la interfaz solo se permite crear Vendedor Interno o Supervisor Comercial.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Verificar si el usuario ya existe
            $stCheck = $pdo->prepare('SELECT id_empleado FROM usuarios WHERE usuario = ?');
            $stCheck->execute([$usuario]);
            if ($stCheck->fetch()) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => "El nombre de usuario '{$usuario}' ya se encuentra registrado."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Generación automática de código de empleado correlativo (EMP-001, EMP-002, etc.)
            $stCodigos = $pdo->query("SELECT codigo_empleado FROM usuarios WHERE codigo_empleado LIKE 'EMP-%'");
            $maxNum = 0;
            while ($row = $stCodigos->fetch(PDO::FETCH_ASSOC)) {
                $num = (int)substr($row['codigo_empleado'], 4);
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
            $siguienteNum = $maxNum + 1;
            $codigo_empleado = sprintf('EMP-%03d', $siguienteNum);

            // Cifrado seguro de contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Inserción en la base de datos (con cambio de clave obligatorio = 1)
            $stIns = $pdo->prepare('
                INSERT INTO usuarios (codigo_empleado, nombre, usuario, password_hash, rol, activo, debe_cambiar_password) 
                VALUES (?, ?, ?, ?, ?, 1, 1)
            ');
            $stIns->execute([$codigo_empleado, $nombre, $usuario, $password_hash, $rol]);
            $nuevo_id = (int)$pdo->lastInsertId();

            // Auditoría en log_acciones
            registrar_log(
                'ALTA_USUARIO',
                "Alta de empleado {$codigo_empleado} ('{$usuario}', rol: {$rol}) por Administrador",
                $nuevo_id
            );

            echo json_encode([
                'ok'              => true,
                'mensaje'         => "Empleado {$codigo_empleado} ({$nombre}) registrado con éxito.",
                'codigo_empleado' => $codigo_empleado,
                'id_empleado'     => $nuevo_id
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ============================================================
        // 3. EDITAR USUARIO
        // ============================================================
        case 'editar_usuario':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['ok' => false, 'error' => 'Método no permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $id_empleado = (int)($params['id_empleado'] ?? 0);
            $nombre = trim($params['nombre'] ?? '');
            $rol = trim($params['rol'] ?? '');
            $activo = isset($params['activo']) ? (int)$params['activo'] : 1;

            if ($id_empleado <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'ID de empleado inválido.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'El nombre completo debe tener entre 3 y 100 caracteres.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Consultar usuario actual objetivo
            $stUser = $pdo->prepare('SELECT id_empleado, codigo_empleado, usuario, rol, activo FROM usuarios WHERE id_empleado = ?');
            $stUser->execute([$id_empleado]);
            $userTarget = $stUser->fetch(PDO::FETCH_ASSOC);

            if (!$userTarget) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'El empleado solicitado no existe.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Regla de seguridad (decisiones.md secc. 15):
            // El Admin no puede desactivarse a sí mismo ni cambiar su propio rol.
            if ($id_empleado === $current_admin_id) {
                if ($rol !== 'ADMIN') {
                    http_response_code(403);
                    echo json_encode(['ok' => false, 'error' => 'Por seguridad del sistema, no puedes modificar tu propio rol de Administrador.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                if ($activo !== 1) {
                    http_response_code(403);
                    echo json_encode(['ok' => false, 'error' => 'Por seguridad del sistema, no puedes desactivar tu propia cuenta.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            } else {
                // Para otros empleados: validar que el rol sea uno válido
                if (!in_array($rol, ['VENDEDOR_INTERNO', 'SUPERVISOR_COMERCIAL', 'ADMIN'], true)) {
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'error' => 'Rol no válido.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $activo = ($activo === 1) ? 1 : 0;
            }

            // Ejecutar actualización
            $stUpd = $pdo->prepare('UPDATE usuarios SET nombre = ?, rol = ?, activo = ? WHERE id_empleado = ?');
            $stUpd->execute([$nombre, $rol, $activo, $id_empleado]);

            // Si el admin editó su propio nombre, actualizar en sesión
            if ($id_empleado === $current_admin_id) {
                $_SESSION['u']['nombre'] = $nombre;
            }

            // Registrar en auditoría
            $detalles = sprintf(
                "Edición de empleado %s (%s): Nombre='%s', Rol='%s', Activo=%d",
                $userTarget['codigo_empleado'],
                $userTarget['usuario'],
                $nombre,
                $rol,
                $activo
            );
            registrar_log('EDICION_USUARIO', $detalles, $id_empleado);

            echo json_encode([
                'ok'      => true,
                'mensaje' => "Datos de {$userTarget['codigo_empleado']} actualizados correctamente."
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ============================================================
        // 4. ACTIVAR / DESACTIVAR USUARIO (TOGGLE)
        // ============================================================
        case 'toggle_activo':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['ok' => false, 'error' => 'Método no permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $id_empleado = (int)($params['id_empleado'] ?? 0);
            $nuevo_activo = isset($params['activo']) ? (int)$params['activo'] : 0;
            $nuevo_activo = ($nuevo_activo === 1) ? 1 : 0;

            if ($id_empleado <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'ID de empleado inválido.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Regla de seguridad: no desactivar su propia cuenta
            if ($id_empleado === $current_admin_id) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'No puedes cambiar el estado de tu propia cuenta de Administrador.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stUser = $pdo->prepare('SELECT id_empleado, codigo_empleado, usuario, activo FROM usuarios WHERE id_empleado = ?');
            $stUser->execute([$id_empleado]);
            $u = $stUser->fetch(PDO::FETCH_ASSOC);

            if (!$u) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Empleado no encontrado.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stUpd = $pdo->prepare('UPDATE usuarios SET activo = ? WHERE id_empleado = ?');
            $stUpd->execute([$nuevo_activo, $id_empleado]);

            $accion_log = ($nuevo_activo === 1) ? 'ACTIVAR_USUARIO' : 'DESACTIVAR_USUARIO';
            $detalle_log = sprintf(
                "Cuenta %s para %s (%s). %s",
                ($nuevo_activo === 1 ? 'reactivada' : 'desactivada'),
                $u['codigo_empleado'],
                $u['usuario'],
                ($nuevo_activo === 0 ? 'Sesión invalidada para próximas peticiones.' : '')
            );
            registrar_log($accion_log, $detalle_log, $id_empleado);

            echo json_encode([
                'ok'      => true,
                'activo'  => $nuevo_activo,
                'mensaje' => ($nuevo_activo === 1)
                    ? "Cuenta de {$u['codigo_empleado']} reactivada con éxito."
                    : "Cuenta de {$u['codigo_empleado']} desactivada. Su sesión ha sido invalidada inmediatamente."
            ], JSON_UNESCAPED_UNICODE);
            exit;

        // ============================================================
        // 5. RESETEAR CONTRASEÑA
        // ============================================================
        case 'reset_password':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['ok' => false, 'error' => 'Método no permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $id_empleado = (int)($params['id_empleado'] ?? 0);
            $nueva_password = $params['nueva_password'] ?? '';

            if ($id_empleado <= 0) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'ID de empleado inválido.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (mb_strlen($nueva_password) < 8) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'La nueva contraseña debe contener al menos 8 caracteres.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stUser = $pdo->prepare('SELECT id_empleado, codigo_empleado, usuario FROM usuarios WHERE id_empleado = ?');
            $stUser->execute([$id_empleado]);
            $u = $stUser->fetch(PDO::FETCH_ASSOC);

            if (!$u) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Empleado no encontrado.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $nuevo_hash = password_hash($nueva_password, PASSWORD_DEFAULT);

            // Al resetear la contraseña, marcar cambio obligatorio (debe_cambiar_password = 1), desbloquear y reiniciar intentos fallidos
            $stUpd = $pdo->prepare('
                UPDATE usuarios 
                SET password_hash = ?, debe_cambiar_password = 1, intentos_fallidos = 0, bloqueado_hasta = NULL 
                WHERE id_empleado = ?
            ');
            $stUpd->execute([$nuevo_hash, $id_empleado]);

            // Auditoría (nunca registrar contraseñas en texto plano)
            registrar_log(
                'RESETEO_PASSWORD',
                "Reseteo de contraseña y desbloqueo de cuenta para {$u['codigo_empleado']} ({$u['usuario']})",
                $id_empleado
            );

            echo json_encode([
                'ok'      => true,
                'mensaje' => "Contraseña de {$u['codigo_empleado']} actualizada correctamente. La cuenta está lista para iniciar sesión."
            ], JSON_UNESCAPED_UNICODE);
            exit;

        default:
            http_response_code(400);
            echo json_encode([
                'ok'    => false,
                'error' => 'Acción no especificada o desconocida en api.php.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
    }

} catch (PDOException $e) {
    error_log('Error PDO en api.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Ocurrió un error interno en la base de datos al procesar la solicitud.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    error_log('Error general en api.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Ocurrió un error interno al procesar la solicitud. Inténtalo nuevamente.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
