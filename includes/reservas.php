<?php
/** Lectura de reservas con control de visibilidad impuesto en el servidor. */
if (!defined('INNOVATRONICA_RESERVAS_API')) { http_response_code(403); exit('Acceso directo prohibido.'); }
class ReservasError extends RuntimeException {}
function reservas_listar(PDO $pdo, array $params, int $actor, string $rol): array {
    if ($actor < 1 || !in_array($rol, ['ADMIN', 'SUPERVISOR_COMERCIAL', 'VENDEDOR_INTERNO'], true)) throw new ReservasError('No autorizado.');
    $q = $params['q'] ?? ''; $estado = $params['estado'] ?? ''; $pagina = $params['pagina'] ?? '1';
    if (!is_string($q) || mb_strlen($q, 'UTF-8') > 120 || preg_match('/[\x00-\x1F\x7F]/', $q)) throw new ReservasError('Búsqueda inválida.');
    if (!is_string($estado) || !in_array($estado, ['', 'ACTIVA', 'PENDIENTE_AUTORIZACION', 'CONFIRMADA', 'FACTURADA', 'EXPIRADA', 'RECHAZADA', 'LIBERADA'], true)) throw new ReservasError('Estado inválido.');
    if ((!is_string($pagina) && !is_int($pagina)) || !preg_match('/^[0-9]{1,10}$/D', (string)$pagina) || (float)$pagina > 2147483647 || (int)$pagina < 1) throw new ReservasError('Página inválida.');
    $q = trim($q); $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
    // Antes de consultar, se aplica la rutina existente que audita las reservas vencidas.
    stock_expirar($pdo);
    $pdo->beginTransaction();
    try {
        $base = ' FROM reservas r INNER JOIN productos p ON p.sku = r.sku INNER JOIN usuarios u ON u.id_empleado = r.id_empleado';
        $where = " WHERE (? = '' OR r.estado = ?) AND (? = '' OR r.id_reserva LIKE ? ESCAPE '!' OR r.sku LIKE ? ESCAPE '!' OR p.nombre LIKE ? ESCAPE '!')";
        $valores = [$estado, $estado, $q, $like, $like, $like];
        if ($rol === 'VENDEDOR_INTERNO') { $where .= ' AND r.id_empleado = ?'; $valores[] = $actor; }
        $st = $pdo->prepare('SELECT COUNT(*)' . $base . $where); $st->execute($valores); $total = (int)$st->fetchColumn();
        $paginas = max(1, (int)ceil($total / 8)); $pagina = min((int)$pagina, $paginas);
        $sql = 'SELECT r.id_reserva, r.sku, p.nombre AS producto, r.cantidad, r.estado, r.creada_en, r.fecha_vencimiento, r.id_empleado, u.nombre AS empleado' . $base . $where . ' ORDER BY r.creada_en DESC, r.id_reserva DESC LIMIT ? OFFSET ?';
        $st = $pdo->prepare($sql);
        foreach ($valores as $i => $v) $st->bindValue($i + 1, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $st->bindValue(count($valores) + 1, 8, PDO::PARAM_INT); $st->bindValue(count($valores) + 2, ($pagina - 1) * 8, PDO::PARAM_INT); $st->execute();
        $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$fila) { $fila['cantidad'] = (int)$fila['cantidad']; $fila['id_empleado'] = (int)$fila['id_empleado']; }
        unset($fila);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    return ['ok' => true, 'reservas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas, 'por_pagina' => 8, 'hora_servidor' => date('c')];
}
