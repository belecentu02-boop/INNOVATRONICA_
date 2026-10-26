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
        $sql = 'SELECT r.id_reserva, r.sku, p.nombre AS producto, r.cantidad, r.estado, r.creada_en, r.fecha_vencimiento, r.id_empleado, r.motivo_rechazo, u.nombre AS empleado' . $base . $where . ' ORDER BY r.creada_en DESC, r.id_reserva DESC LIMIT ? OFFSET ?';
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

/** Crea una reserva propia; el bloqueo del inventario serializa las solicitudes del SKU. */
function reservas_crear(PDO $pdo, array $params, int $actor, string $rol): array {
    if ($actor < 1 || !in_array($rol, ['ADMIN', 'SUPERVISOR_COMERCIAL', 'VENDEDOR_INTERNO'], true)) throw new ReservasError('No autorizado.');
    $sku = $params['sku'] ?? ''; $cantidad = $params['cantidad'] ?? ''; $horas = $params['horas'] ?? '24';
    if (!is_string($sku) || trim($sku) === '' || mb_strlen($sku, 'UTF-8') > 30 || preg_match('/[\x00-\x1F\x7F]/', $sku)) throw new ReservasError('Ingresá un SKU válido.');
    $sku = trim($sku);
    if ((!is_string($cantidad) && !is_int($cantidad)) || !preg_match('/^[0-9]{1,10}$/D', (string)$cantidad) || (float)$cantidad > 2147483647 || (int)$cantidad < 1) throw new ReservasError('La cantidad debe ser un número entero mayor a cero.');
    if ((!is_string($horas) && !is_int($horas)) || !preg_match('/^[0-9]{1,3}$/D', (string)$horas) || (int)$horas < 1 || (int)$horas > 720) throw new ReservasError('La vigencia debe ser de 1 a 720 horas.');
    $cantidad=(int)$cantidad; $horas=(int)$horas;
    if ($rol === 'VENDEDOR_INTERNO' && $horas !== 24) throw new ReservasError('Los vendedores deben usar la vigencia de 24 horas.');
    stock_expirar($pdo);
    $pdo->beginTransaction();
    try {
        $st=$pdo->prepare('SELECT stock_fisico, umbral_minimo FROM inventario WHERE sku = ? FOR UPDATE'); $st->execute([$sku]); $inv=$st->fetch(PDO::FETCH_ASSOC);
        if (!$inv) throw new ReservasError('No se encontró el SKU en inventario.');
        $st=$pdo->prepare("SELECT COALESCE(SUM(cantidad),0) FROM reservas WHERE sku = ? AND estado IN ('ACTIVA', 'PENDIENTE_AUTORIZACION', 'CONFIRMADA')"); $st->execute([$sku]);
        $disponible=(int)$inv['stock_fisico']-(int)$st->fetchColumn();
        if ($cantidad > $disponible) throw new ReservasError('La cantidad solicitada supera el stock disponible actual.');
        $estado=($rol==='VENDEDOR_INTERNO' && $disponible-$cantidad <= (int)$inv['umbral_minimo']) ? 'PENDIENTE_AUTORIZACION' : 'ACTIVA';
        $st=$pdo->prepare('SELECT NOW()'); $st->execute();$ahora=$st->fetchColumn();
        $creada=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $ahora, new DateTimeZone('America/Argentina/Buenos_Aires'));
        if ($creada===false) throw new RuntimeException('Hora de base inválida.');
        $vencimiento=$creada->modify('+' . $horas . ' hours')->format('Y-m-d H:i:s');
        $id='RES-'.$creada->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(6)));
        $st=$pdo->prepare('INSERT INTO reservas (id_reserva, sku, cantidad, id_empleado, creada_en, fecha_vencimiento, estado) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$id,$sku,$cantidad,$actor,$ahora,$vencimiento,$estado]);
        $st=$pdo->prepare('INSERT INTO log_acciones (id_empleado, accion, detalles, ip) VALUES (?, ?, ?, ?)');
        $st->execute([$actor,'RESERVA_CREADA',"Reserva {$id}, SKU {$sku}, cantidad {$cantidad}, estado {$estado}.",$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
        $pdo->commit();
        return ['ok'=>true,'id_reserva'=>$id,'estado'=>$estado,'fecha_vencimiento'=>$vencimiento,'mensaje'=>$estado==='PENDIENTE_AUTORIZACION'?'Reserva creada y pendiente de autorización.':'Reserva creada correctamente.'];
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

/** Resuelve solicitudes pendientes vigentes. */
function reservas_resolver(PDO $pdo, array $params, int $actor, string $rol): array {
    if ($actor < 1 || !in_array($rol, ['ADMIN', 'SUPERVISOR_COMERCIAL'], true)) throw new ReservasError('No autorizado.');
    $id=$params['id_reserva']??''; $accion=$params['accion']??''; $motivo=$params['motivo']??'';
    if (!is_string($id) || $id==='' || mb_strlen($id,'UTF-8')>30 || preg_match('/[\x00-\x1F\x7F]/',$id)) throw new ReservasError('Identificador de reserva inválido.');
    if (!is_string($accion) || !in_array($accion,['aprobar','rechazar'],true)) throw new ReservasError('Acción inválida.');
    if (!is_string($motivo) || mb_strlen($motivo,'UTF-8')>255 || preg_match('/[\x00-\x1F\x7F]/',$motivo)) throw new ReservasError('Motivo inválido.');
    $motivo=trim($motivo);
    if ($accion==='rechazar' && $motivo==='') throw new ReservasError('Ingresá un motivo de rechazo.');
    stock_expirar($pdo);
    // La lectura inicial solo determina el SKU. Se verifica nuevamente tras bloquear.
    $st=$pdo->prepare('SELECT sku FROM reservas WHERE id_reserva = ?');$st->execute([$id]);$sku=$st->fetchColumn();
    if ($sku===false) throw new ReservasError('La reserva no existe.');
    $pdo->beginTransaction();
    try {
        // Mantener el mismo orden que creación: inventario y luego reserva.
        $st=$pdo->prepare('SELECT stock_fisico FROM inventario WHERE sku = ? FOR UPDATE');$st->execute([$sku]);$fisico=$st->fetchColumn();
        if ($fisico===false) throw new ReservasError('Inventario no encontrado.');
        $st=$pdo->prepare('SELECT sku, estado, fecha_vencimiento FROM reservas WHERE id_reserva = ? FOR UPDATE');$st->execute([$id]);$r=$st->fetch(PDO::FETCH_ASSOC);
        if (!$r || $r['sku']!==$sku || $r['estado']!=='PENDIENTE_AUTORIZACION') throw new ReservasError('La reserva ya no está pendiente. Actualizá el listado.');
        if ($accion==='aprobar') {
            $st=$pdo->prepare("SELECT COALESCE(SUM(cantidad),0) FROM reservas WHERE sku = ? AND estado IN ('ACTIVA','PENDIENTE_AUTORIZACION','CONFIRMADA')");$st->execute([$sku]);
            if ((int)$st->fetchColumn()>(int)$fisico) throw new ReservasError('El inventario presenta una inconsistencia. Revisá el stock antes de aprobar.');
            $st=$pdo->prepare("UPDATE reservas SET estado='ACTIVA', autorizado_por=?, fecha_vencimiento=DATE_ADD(NOW(), INTERVAL 24 HOUR), motivo_rechazo=NULL WHERE id_reserva=? AND estado='PENDIENTE_AUTORIZACION' AND fecha_vencimiento > NOW()");
            $st->execute([$actor,$id]);$estado='ACTIVA';$evento='RESERVA_APROBADA';$texto='Reserva aprobada. La vigencia se reinició a 24 horas.';
        } else {
            $st=$pdo->prepare("UPDATE reservas SET estado='RECHAZADA', autorizado_por=?, motivo_rechazo=? WHERE id_reserva=? AND estado='PENDIENTE_AUTORIZACION' AND fecha_vencimiento > NOW()");
            $st->execute([$actor,$motivo,$id]);$estado='RECHAZADA';$evento='RESERVA_RECHAZADA';$texto='Reserva rechazada. Las unidades dejaron de estar comprometidas.';
        }
        if ($st->rowCount()!==1) throw new ReservasError('La reserva venció o ya fue resuelta. Actualizá el listado.');
        $st=$pdo->prepare('INSERT INTO log_acciones (id_empleado, accion, detalles, ip) VALUES (?, ?, ?, ?)');
        $st->execute([$actor,$evento,'Reserva '.$id.($accion==='rechazar'?'. Motivo: '.$motivo:'.'),$_SERVER['REMOTE_ADDR']??'127.0.0.1']);
        $pdo->commit();
        return ['ok'=>true,'id_reserva'=>$id,'estado'=>$estado,'mensaje'=>$texto];
    } catch (Throwable $e) {if ($pdo->inTransaction()) $pdo->rollBack();throw $e;}
}
