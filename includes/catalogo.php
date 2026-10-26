<?php
/** Fase 4.1: operaciones de catálogo; acceso únicamente desde api.php. */
if (!defined('INNOVATRONICA_CATALOGO_API')) {
    http_response_code(403);
    exit('Acceso directo prohibido.');
}

class CatalogoError extends RuntimeException {
    public $estado;
    public function __construct(string $mensaje, int $estado = 422) {
        parent::__construct($mensaje);
        $this->estado = $estado;
    }
}

function catalogo_texto(array $datos, string $campo, int $maximo, bool $requerido = true): string {
    $valor = $datos[$campo] ?? '';
    if (!is_string($valor)) {
        throw new CatalogoError('El campo ' . $campo . ' debe ser texto.');
    }
    $valor = trim($valor);
    if (($requerido && $valor === '') || mb_strlen($valor, 'UTF-8') > $maximo
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $valor)) {
        throw new CatalogoError('Revisa el campo ' . $campo . ' (máximo ' . $maximo . ' caracteres).');
    }
    return $valor;
}

function catalogo_entero($valor, int $minimo = 0): int {
    if ((!is_string($valor) && !is_int($valor)) || !preg_match('/^[0-9]{1,10}$/D', (string)$valor)
        || (float)$valor > 2147483647 || (int)$valor < $minimo) {
        throw new CatalogoError('La cantidad debe ser un entero entre ' . $minimo . ' y 2147483647.');
    }
    return (int)$valor;
}

function catalogo_validar(array $datos): array {
    $sku = strtoupper(catalogo_texto($datos, 'sku', 30));
    $ubicacion = strtoupper(catalogo_texto($datos, 'ubicacion_deposito', 30));
    if (!preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/D', $sku)) {
        throw new CatalogoError('SKU inválido. Usa letras, números y guiones, por ejemplo ESP32-DEV-01.');
    }
    if (!preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/D', $ubicacion)) {
        throw new CatalogoError('Ubicación inválida. Usa un código como A1-01, sin espacios.');
    }
    return [
        'sku' => $sku,
        'nombre' => catalogo_texto($datos, 'nombre', 120),
        'categoria' => catalogo_texto($datos, 'categoria', 60),
        'ficha_tecnica' => catalogo_texto($datos, 'ficha_tecnica', 255, false),
        'ubicacion_deposito' => $ubicacion,
        'umbral_minimo' => catalogo_entero($datos['umbral_minimo'] ?? '')
    ];
}

function catalogo_listar(PDO $pdo, array $params): array {
    $q = catalogo_texto($params, 'q', 120, false);
    $categoria = catalogo_texto($params, 'categoria', 60, false);
    $pagina = catalogo_entero($params['pagina'] ?? 1, 1);
    // Escape LIKE metacharacters: searches treat %, _ and ! as literal text.
    $busqueda = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
    $where = " WHERE (? = '' OR p.sku LIKE ? ESCAPE '!' OR p.nombre LIKE ? ESCAPE '!' OR p.categoria LIKE ? ESCAPE '!' OR p.ubicacion_deposito LIKE ? ESCAPE '!') AND (? = '' OR p.categoria = ?)";
    $valores = [$q, $busqueda, $busqueda, $busqueda, $busqueda, $categoria, $categoria];
    $conteo = $pdo->prepare('SELECT COUNT(*) FROM productos p INNER JOIN inventario i ON i.sku = p.sku' . $where);
    $conteo->execute($valores);
    $total = (int)$conteo->fetchColumn();
    $paginas = max(1, (int)ceil($total / 8));
    $pagina = min($pagina, $paginas);
    $st = $pdo->prepare('SELECT p.sku, p.nombre, p.categoria, p.ficha_tecnica, p.ubicacion_deposito, i.stock_fisico, i.umbral_minimo FROM productos p INNER JOIN inventario i ON i.sku = p.sku' . $where . ' ORDER BY p.nombre, p.sku LIMIT ? OFFSET ?');
    foreach ($valores as $indice => $valor) {
        $st->bindValue($indice + 1, $valor, PDO::PARAM_STR);
    }
    $st->bindValue(8, 8, PDO::PARAM_INT);
    $st->bindValue(9, ($pagina - 1) * 8, PDO::PARAM_INT);
    $st->execute();
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($filas as &$fila) {
        $fila['stock_fisico'] = (int)$fila['stock_fisico'];
        $fila['umbral_minimo'] = (int)$fila['umbral_minimo'];
    }
    unset($fila);
    $cat = $pdo->prepare('SELECT DISTINCT categoria FROM productos ORDER BY categoria');
    $cat->execute();
    return ['ok' => true, 'productos' => $filas, 'categorias' => $cat->fetchAll(PDO::FETCH_COLUMN),
        'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas, 'por_pagina' => 8];
}

function catalogo_guardar(PDO $pdo, array $datos, int $actor, bool $crear): array {
    $p = catalogo_validar($datos);
    // Existing stock may only be changed by the inventory operations of phase 6.
    if (array_key_exists('stock_fisico', $datos) || array_key_exists('stock_inicial', $datos)) {
        throw new CatalogoError('El catálogo no modifica stock físico. Los productos nuevos comienzan en cero.');
    }
    $pdo->beginTransaction();
    try {
        if ($crear) {
            $st = $pdo->prepare('INSERT INTO productos (sku, nombre, categoria, ficha_tecnica, ubicacion_deposito) VALUES (?, ?, ?, ?, ?)');
            $st->execute([$p['sku'], $p['nombre'], $p['categoria'], $p['ficha_tecnica'] ?: null, $p['ubicacion_deposito']]);
            $st = $pdo->prepare('INSERT INTO inventario (sku, stock_fisico, umbral_minimo) VALUES (?, 0, ?)');
            $st->execute([$p['sku'], $p['umbral_minimo']]);
            $accion = 'ALTA_PRODUCTO';
        } else {
            $st = $pdo->prepare('SELECT p.sku FROM productos p INNER JOIN inventario i ON i.sku = p.sku WHERE p.sku = ? FOR UPDATE');
            $st->execute([$p['sku']]);
            if (!$st->fetchColumn()) {
                throw new CatalogoError('Producto no encontrado. Recarga el listado.', 404);
            }
            $st = $pdo->prepare('UPDATE productos SET nombre = ?, categoria = ?, ficha_tecnica = ?, ubicacion_deposito = ? WHERE sku = ?');
            $st->execute([$p['nombre'], $p['categoria'], $p['ficha_tecnica'] ?: null, $p['ubicacion_deposito'], $p['sku']]);
            $st = $pdo->prepare('UPDATE inventario SET umbral_minimo = ? WHERE sku = ?');
            $st->execute([$p['umbral_minimo'], $p['sku']]);
            $accion = 'EDICION_PRODUCTO';
        }
        // Audit inside the same transaction; failure rolls the whole change back.
        $st = $pdo->prepare('INSERT INTO log_acciones (id_empleado, accion, detalles, ip) VALUES (?, ?, ?, ?)');
        $st->execute([$actor, $accion, json_encode($p, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) {
            throw new CatalogoError('Ya existe un producto con ese SKU.', 409);
        }
        throw $e;
    }
    return ['ok' => true, 'mensaje' => $crear ? 'Producto creado con stock físico en cero.' : 'Producto actualizado. El stock físico se conservó.', 'sku' => $p['sku']];
}
