<?php
if (!defined('SA_PANEL_ALLOWED') || !isset($pdo) || !es_superadmin()) {
    http_response_code(403); exit('Acceso denegado');
}

$sa_acciones = [''=>'Todas','LOGIN_EXITOSO'=>'Inicio de sesión','LOGIN_FALLIDO'=>'Acceso fallido','SUPERADMIN_'=>'Acciones SUPERADMIN','RESERVA_'=>'Reservas'];
$sa_filtro = (string)($_GET['accion'] ?? '');
if (!array_key_exists($sa_filtro,$sa_acciones)) $sa_filtro = '';
$sa_busqueda = trim((string)($_GET['buscar'] ?? ''));
$sa_busqueda = mb_substr($sa_busqueda,0,80);
$sa_pagina = max(1,min(10000,(int)($_GET['pagina'] ?? 1)));
$sa_limite = 25; $sa_offset = ($sa_pagina-1)*$sa_limite;
$where=[]; $params=[];
if ($sa_filtro !== '') {
    if (str_ends_with($sa_filtro,'_')) { $where[]='l.accion LIKE :accion'; $params[':accion']=$sa_filtro.'%'; }
    else { $where[]='l.accion = :accion'; $params[':accion']=$sa_filtro; }
}
if ($sa_busqueda !== '') {
    $where[]='(l.accion LIKE :busca1 OR l.detalles LIKE :busca2 OR u.usuario LIKE :busca3)';
    foreach ([':busca1',':busca2',':busca3'] as $k) $params[$k]='%'.$sa_busqueda.'%';
}
$sqlWhere=$where?' WHERE '.implode(' AND ',$where):'';
$sa_logs=[]; $sa_total=0; $sa_error=false;
try {
    $st=$pdo->prepare('SELECT COUNT(*) FROM log_acciones l LEFT JOIN usuarios u ON u.id_empleado=l.id_empleado'.$sqlWhere);
    $st->execute($params); $sa_total=(int)$st->fetchColumn();
    $st=$pdo->prepare('SELECT l.id,l.fecha,l.accion,l.detalles,l.ip,u.usuario FROM log_acciones l LEFT JOIN usuarios u ON u.id_empleado=l.id_empleado'.$sqlWhere.' ORDER BY l.fecha DESC,l.id DESC LIMIT 25 OFFSET '.(int)$sa_offset);
    $st->execute($params); $sa_logs=$st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { error_log('SA auditoria: '.$e->getMessage()); $sa_error=true; }
?>
<section class="sa-monitor">
<div class="portal-section-heading"><h2>Auditoría del sistema</h2><p>Historial de acciones registradas</p></div>
<div class="sa-panel">
<form method="get" action="superadmin.php" class="sa-filter">
<input type="hidden" name="seccion" value="auditoria">
<label>Tipo de evento <select name="accion"><?php foreach($sa_acciones as $v=>$label): ?><option value="<?= h($v) ?>" <?= $sa_filtro===$v?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
<label>Buscar <input name="buscar" type="search" maxlength="80" value="<?= h($sa_busqueda) ?>" placeholder="Acción, usuario o detalle"></label>
<button class="sa-action-button" type="submit">Filtrar</button>
<a href="superadmin.php?seccion=auditoria">Limpiar</a>
</form>
<p class="sa-meta"><?= number_format($sa_total,0,',','.') ?> eventos encontrados · 25 por página</p>
<?php if($sa_error): ?><p role="alert">No se pudo consultar la auditoría.</p><?php elseif(!$sa_logs): ?><p>No hay eventos para estos filtros.</p><?php else: ?>
<div class="sa-personal-scroll"><table class="sa-personal-table"><thead><tr><th>Fecha</th><th>Evento</th><th>Usuario</th><th>Dirección IP</th><th>Detalle</th></tr></thead><tbody>
<?php foreach($sa_logs as $l): ?><tr><td><?= h($l['fecha']) ?></td><td><strong><?= h($l['accion']) ?></strong></td><td><?= h($l['usuario'] ?? 'Sistema / eliminado') ?></td><td><?= h($l['ip'] ?? '—') ?></td><td class="sa-details"><?= h($l['detalles'] ?? '') ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<div class="sa-pages"><?php if($sa_pagina>1): ?><a class="sa-action-button" href="superadmin.php?<?= h(http_build_query(['seccion'=>'auditoria','accion'=>$sa_filtro,'buscar'=>$sa_busqueda,'pagina'=>$sa_pagina-1])) ?>">Anterior</a><?php endif; ?>
<span>Página <?= $sa_pagina ?> de <?= max(1,(int)ceil($sa_total/$sa_limite)) ?></span>
<?php if($sa_pagina*$sa_limite<$sa_total): ?><a class="sa-action-button" href="superadmin.php?<?= h(http_build_query(['seccion'=>'auditoria','accion'=>$sa_filtro,'buscar'=>$sa_busqueda,'pagina'=>$sa_pagina+1])) ?>">Siguiente</a><?php endif; ?></div>
</div></section>
