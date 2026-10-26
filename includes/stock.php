<?php
/** Consulta de disponibilidad y mantenimiento de vencimientos. */
if (!defined('INNOVATRONICA_STOCK_API')) {
    http_response_code(403);
    exit('Acceso directo prohibido.');
}
class StockError extends RuntimeException {}
function stock_texto(array $params, string $campo, int $max): string {
    $valor = $params[$campo] ?? '';
    if (!is_string($valor) || mb_strlen($valor, 'UTF-8') > $max
        || preg_match('/[\x00-\x1F\x7F]/', $valor)) {
        throw new StockError('Filtro inválido: ' . $campo . '.');
    }
    return trim($valor);
}
function stock_expirar(PDO $pdo): int {
    $pdo->beginTransaction();
    $cantidad = 0;
    try {
        $st = $pdo->prepare("SELECT id_reserva, estado FROM reservas WHERE estado IN ('ACTIVA', 'PENDIENTE_AUTORIZACION') AND fecha_vencimiento <= NOW() ORDER BY id_reserva FOR UPDATE");
        $st->execute();
        $vencidas = $st->fetchAll(PDO::FETCH_ASSOC);
        $actualizar = $pdo->prepare("UPDATE reservas SET estado = 'EXPIRADA' WHERE id_reserva = ? AND estado = ? AND estado IN ('ACTIVA', 'PENDIENTE_AUTORIZACION') AND fecha_vencimiento <= NOW()");
        $log = $pdo->prepare('INSERT INTO log_acciones (id_empleado, accion, detalles, ip) VALUES (NULL, ?, ?, ?)');
        foreach ($vencidas as $reserva) {
            $actualizar->execute([$reserva['id_reserva'], $reserva['estado']]);
            if ($actualizar->rowCount() === 1) {
                $log->execute(['RESERVA_EXPIRADA', 'La reserva ' . $reserva['id_reserva'] . ' venció automáticamente al consultar stock. Estado anterior: ' . $reserva['estado'] . '.', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
                ++$cantidad;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return $cantidad;
}
function stock_listar(PDO $pdo, array $params, bool $solo_alertas = false): array {
    // Validate before housekeeping: malformed queries must not modify any data.
    $q = stock_texto($params, 'q', 120);
    $categoria = stock_texto($params, 'categoria', 60);
    $estado = stock_texto($params, 'estado', 20);
    if (!in_array($estado, ['', 'NORMAL', 'CRITICO', 'QUIEBRE', 'INCONSISTENTE'], true)) {
        throw new StockError('Estado de stock inválido.');
    }
    $pagina = $params['pagina'] ?? '1';
    if ((!is_string($pagina) && !is_int($pagina)) || !preg_match('/^[0-9]{1,10}$/D', (string)$pagina)
        || (float)$pagina > 2147483647 || (int)$pagina < 1) {
        throw new StockError('Página inválida.');
    }
    stock_expirar($pdo);
    // All figures and pages are read within the same database transaction.
    $pdo->beginTransaction();
    try {
        $base = " FROM (SELECT p.sku, p.nombre, p.categoria, p.ficha_tecnica, p.ubicacion_deposito,
            i.stock_fisico, i.umbral_minimo, COALESCE(r.comprometido, 0) AS stock_comprometido,
            i.stock_fisico - COALESCE(r.comprometido, 0) AS stock_disponible
            FROM productos p INNER JOIN inventario i ON i.sku = p.sku
            LEFT JOIN (SELECT sku, SUM(cantidad) AS comprometido FROM reservas
                WHERE estado IN ('ACTIVA', 'PENDIENTE_AUTORIZACION', 'CONFIRMADA') GROUP BY sku) r ON r.sku = p.sku) s";
        $estadoSql = "CASE WHEN s.stock_disponible < 0 THEN 'INCONSISTENTE' WHEN s.stock_disponible = 0 THEN 'QUIEBRE' WHEN s.stock_disponible <= s.umbral_minimo THEN 'CRITICO' ELSE 'NORMAL' END";
        $where = " WHERE (? = '' OR s.sku LIKE ? ESCAPE '!' OR s.nombre LIKE ? ESCAPE '!' OR s.categoria LIKE ? ESCAPE '!' OR s.ubicacion_deposito LIKE ? ESCAPE '!') AND (? = '' OR s.categoria = ?) AND (? = '' OR (" . $estadoSql . ") = ?)";
        if ($solo_alertas) $where .= ' AND s.stock_disponible <= s.umbral_minimo';
        $busqueda = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
        $valores = [$q, $busqueda, $busqueda, $busqueda, $busqueda, $categoria, $categoria, $estado, $estado];
        $st = $pdo->prepare('SELECT COUNT(*) AS productos, COALESCE(SUM(s.stock_fisico),0) AS fisico, COALESCE(SUM(s.stock_comprometido),0) AS comprometido, COALESCE(SUM(s.stock_disponible),0) AS disponible' . $base . $where);
        $st->execute($valores);
        $resumen = $st->fetch(PDO::FETCH_ASSOC);
        foreach ($resumen as &$valor) $valor = (int)$valor;
        unset($valor);
        $total = $resumen['productos'];
        $paginas = max(1, (int)ceil($total / 8));
        $pagina = min((int)$pagina, $paginas);
        $orden = $solo_alertas ? ' ORDER BY CASE WHEN s.stock_disponible < 0 THEN 0 WHEN s.stock_disponible = 0 THEN 1 ELSE 2 END, s.stock_disponible, s.nombre, s.sku' : ' ORDER BY s.nombre, s.sku';
        $st = $pdo->prepare('SELECT s.*, (s.umbral_minimo * 2 - s.stock_disponible) AS cantidad_sugerida, (' . $estadoSql . ') AS estado_stock' . $base . $where . $orden . ' LIMIT ? OFFSET ?');
        foreach ($valores as $i => $valor) $st->bindValue($i + 1, $valor, PDO::PARAM_STR);
        $st->bindValue(10, 8, PDO::PARAM_INT);
        $st->bindValue(11, ($pagina - 1) * 8, PDO::PARAM_INT);
        $st->execute();
        $productos = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($productos as &$p) {
            foreach (['stock_fisico', 'stock_comprometido', 'stock_disponible', 'umbral_minimo', 'cantidad_sugerida'] as $campo) $p[$campo] = (int)$p[$campo];
        }
        unset($p);
        $st = $pdo->prepare('SELECT DISTINCT categoria FROM productos ORDER BY categoria');
        $st->execute();
        $categorias = $st->fetchAll(PDO::FETCH_COLUMN);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true, 'productos' => $productos, 'categorias' => $categorias, 'resumen' => $resumen,
        'pagina' => $pagina, 'paginas' => $paginas, 'por_pagina' => 8, 'total' => $total, 'actualizado_en' => date('c')];
}
