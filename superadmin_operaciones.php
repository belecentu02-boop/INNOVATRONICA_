<?php
if (!defined('SA_PANEL_ALLOWED') || !isset($pdo) || !es_superadmin()) {
    http_response_code(403); exit('Acceso denegado');
}

$sa_ops=[];
try {
 $sa_ops['reservas']=$pdo->query('SELECT estado,COUNT(*) cantidad FROM reservas GROUP BY estado ORDER BY estado')->fetchAll(PDO::FETCH_ASSOC);
 $sa_ops['criticos']=$pdo->query('SELECT p.sku,p.nombre,i.stock_fisico,i.umbral_minimo FROM inventario i INNER JOIN productos p ON p.sku=i.sku WHERE i.stock_fisico<=i.umbral_minimo ORDER BY i.stock_fisico ASC,p.sku ASC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
 $sa_ops['movimientos']=$pdo->query('SELECT m.fecha,m.sku,m.tipo,m.cantidad,m.stock_anterior,m.stock_nuevo,u.usuario FROM movimientos_stock m LEFT JOIN usuarios u ON u.id_empleado=m.id_empleado ORDER BY m.fecha DESC,m.id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
 $sa_ops['ultimas']=$pdo->query('SELECT r.id_reserva,r.sku,r.cantidad,r.estado,r.creada_en,u.usuario FROM reservas r LEFT JOIN usuarios u ON u.id_empleado=r.id_empleado ORDER BY r.creada_en DESC,r.id_reserva DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){error_log('SA operaciones: '.$e->getMessage());$sa_ops=[];}
?>
<section class="sa-monitor"><div class="portal-section-heading"><h2>Supervisión de operaciones</h2><p>Consulta de inventario, reservas y movimientos</p></div>
<?php if(!$sa_ops): ?><div class="sa-panel">No se pudieron consultar las operaciones.</div><?php else: ?>
<div class="sa-panel"><h2>Reservas por estado</h2><div class="sa-monitor-stats">
<?php foreach($sa_ops['reservas'] as $r): ?><div class="sa-stat"><span class="portal-eyebrow"><?= h(str_replace('_',' ',$r['estado'])) ?></span><strong><?= (int)$r['cantidad'] ?></strong></div><?php endforeach; ?></div></div>
<div class="sa-panel"><h2>Inventario en umbral crítico o quiebre</h2><p class="sa-meta">Hasta 50 productos, ordenados por stock físico</p>
<?php if(!$sa_ops['criticos']): ?><p>Sin productos críticos.</p><?php else: ?><div class="sa-personal-scroll"><table class="sa-personal-table"><thead><tr><th>SKU</th><th>Producto</th><th>Stock físico</th><th>Umbral</th></tr></thead><tbody>
<?php foreach($sa_ops['criticos'] as $i): ?><tr><td><?= h($i['sku']) ?></td><td><?= h($i['nombre']) ?></td><td><?= (int)$i['stock_fisico'] ?></td><td><?= (int)$i['umbral_minimo'] ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<div class="sa-panel"><h2>Últimas reservas</h2><div class="sa-personal-scroll"><table class="sa-personal-table"><thead><tr><th>Fecha</th><th>Reserva</th><th>SKU</th><th>Cantidad</th><th>Estado</th><th>Creada por</th></tr></thead><tbody>
<?php foreach($sa_ops['ultimas'] as $r): ?><tr><td><?= h($r['creada_en']) ?></td><td><?= h($r['id_reserva']) ?></td><td><?= h($r['sku']) ?></td><td><?= (int)$r['cantidad'] ?></td><td><?= h($r['estado']) ?></td><td><?= h($r['usuario']??'—') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="sa-panel"><h2>Movimientos recientes de stock</h2><div class="sa-personal-scroll"><table class="sa-personal-table"><thead><tr><th>Fecha</th><th>SKU</th><th>Tipo</th><th>Cantidad</th><th>Anterior</th><th>Nuevo</th><th>Empleado</th></tr></thead><tbody>
<?php foreach($sa_ops['movimientos'] as $m): ?><tr><td><?= h($m['fecha']) ?></td><td><?= h($m['sku']) ?></td><td><?= h($m['tipo']) ?></td><td><?= (int)$m['cantidad'] ?></td><td><?= (int)$m['stock_anterior'] ?></td><td><?= (int)$m['stock_nuevo'] ?></td><td><?= h($m['usuario']??'—') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<p class="sa-meta">Supervisión de solo lectura. La facturación y las modificaciones de inventario permanecen bajo los permisos de los módulos operativos originales.</p>
<?php endif; ?></section>
