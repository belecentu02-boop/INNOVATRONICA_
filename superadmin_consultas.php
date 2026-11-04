<?php
require_once __DIR__ . '/includes/auth.php';
requerir_rol(['SUPERADMIN']);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
$modulos = [
 'stock'=>'Stock y disponibilidad',
 'catalogo'=>'Catálogo de productos',
 'reservas'=>'Reservas comerciales',
 'reabastecimiento'=>'Reabastecimiento',
 'inventario'=>'Movimientos de inventario',
];
$modulo = (string)($_GET['modulo'] ?? 'stock');
if (!isset($modulos[$modulo])) { http_response_code(404); exit('Módulo no disponible'); }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); header('Allow: GET'); exit('Método no permitido'); }
$buscar = trim((string)($_GET['buscar'] ?? ''));
$buscar = mb_substr($buscar,0,80);
$pagina = max(1,min(10000,(int)($_GET['pagina'] ?? 1)));
$limite=30; $offset=($pagina-1)*$limite;
$datos=[];$total=0;$error=false;
$pdo=db();
try {
 $where='';$params=[];
 if ($modulo==='stock' || $modulo==='catalogo' || $modulo==='reabastecimiento') {
   $where=$modulo==='reabastecimiento' ? ' WHERE (i.stock_fisico - COALESCE(c.comprometido,0)) <= i.umbral_minimo' : ' WHERE 1=1';
   if ($buscar!=='') { $where.=" AND (p.sku LIKE :q1 OR p.nombre LIKE :q2 OR p.categoria LIKE :q3 OR p.ubicacion_deposito LIKE :q4)"; foreach([':q1',':q2',':q3',':q4'] as $key)$params[$key]='%'.$buscar.'%'; }
   $from=" FROM productos p INNER JOIN inventario i ON i.sku=p.sku LEFT JOIN (
       SELECT sku,SUM(cantidad) AS comprometido FROM reservas
       WHERE estado IN ('ACTIVA','PENDIENTE_AUTORIZACION','CONFIRMADA')
       GROUP BY sku
     ) c ON c.sku=p.sku";
   $query="SELECT p.sku,p.nombre,p.categoria,p.ficha_tecnica,p.ubicacion_deposito,i.stock_fisico,i.umbral_minimo,COALESCE(c.comprometido,0) AS comprometido,(i.stock_fisico-COALESCE(c.comprometido,0)) AS disponible";
   $count="SELECT COUNT(*)".$from.$where;
   $sql=$query.$from.$where." ORDER BY p.nombre ASC,p.sku ASC LIMIT 30 OFFSET ".$offset;
 } elseif($modulo==='reservas') {
   $from=" FROM reservas r LEFT JOIN usuarios u ON u.id_empleado=r.id_empleado LEFT JOIN usuarios a ON a.id_empleado=r.autorizado_por";
   $where=' WHERE 1=1';
   if($buscar!==''){ $where.=" AND (r.id_reserva LIKE :q1 OR r.sku LIKE :q2 OR r.estado LIKE :q3 OR u.usuario LIKE :q4)"; foreach([':q1',':q2',':q3',':q4'] as $key)$params[$key]='%'.$buscar.'%'; }
   $count="SELECT COUNT(*)".$from.$where;
   $sql="SELECT r.id_reserva,r.sku,r.cantidad,r.estado,r.creada_en,r.fecha_vencimiento,u.usuario,a.usuario AS autorizador".$from.$where." ORDER BY r.creada_en DESC,r.id_reserva DESC LIMIT 30 OFFSET ".$offset;
 } else {
   $from=" FROM movimientos_stock m LEFT JOIN usuarios u ON u.id_empleado=m.id_empleado";
   $where=' WHERE 1=1';
   if($buscar!==''){ $where.=" AND (m.sku LIKE :q1 OR m.tipo LIKE :q2 OR m.motivo LIKE :q3 OR u.usuario LIKE :q4)"; foreach([':q1',':q2',':q3',':q4'] as $key)$params[$key]='%'.$buscar.'%'; }
   $count="SELECT COUNT(*)".$from.$where;
   $sql="SELECT m.fecha,m.sku,m.tipo,m.cantidad,m.stock_anterior,m.stock_nuevo,m.motivo,u.usuario".$from.$where." ORDER BY m.fecha DESC,m.id DESC LIMIT 30 OFFSET ".$offset;
 }
 $st=$pdo->prepare($count);$st->execute($params);$total=(int)$st->fetchColumn();
 $st=$pdo->prepare($sql);$st->execute($params);$datos=$st->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){ error_log('Supervision consulta '.$modulo.': '.$e->getMessage()); $error=true; }
$page_title=$modulos[$modulo].' · Supervisión · Innovatrónica S.A.';
$es_portal_empleados=true;
require_once __DIR__.'/includes/header.php';
function sa_url(string $modulo,string $buscar,int $pagina): string {
 return 'superadmin_consultas.php?'.http_build_query(['modulo'=>$modulo,'buscar'=>$buscar,'pagina'=>$pagina]);
}
?>
<link rel="stylesheet" href="assets/css/superadmin.css?v=<?= (int)filemtime(__DIR__.'/assets/css/superadmin.css') ?>">
<link rel="stylesheet" href="assets/css/superadmin-consultas.css?v=<?= (int)filemtime(__DIR__.'/assets/css/superadmin-consultas.css') ?>">
<main class="employee-portal sa-portal sa-consultas" id="main-content">
 <div class="portal-context">INNOVATRÓNICA S.A. / SUPERADMIN / SUPERVISIÓN</div>
 <section class="portal-welcome"><div><span class="portal-eyebrow sa-eyebrow">ACCESO DE SOLO LECTURA</span><h1><?= h($modulos[$modulo]) ?></h1><p>Información operativa en tiempo real, sin permisos para modificar registros.</p></div><div class="portal-company-label">Supervisión</div></section>
 <p><a class="sa-back" href="superadmin.php">← Volver al Centro de Control</a></p>
 <nav class="sa-tabs" aria-label="Áreas de supervisión">
 <?php foreach($modulos as $key=>$label): ?><a class="sa-tab <?= $key===$modulo?'sa-tab-active':'' ?>" href="<?= h(sa_url($key,'',1)) ?>" <?= $key===$modulo?'aria-current="page"':'' ?>><?= h($label) ?></a><?php endforeach; ?>
 </nav>
 <section class="sa-panel">
 <div class="sa-panel-head"><div><span class="portal-eyebrow">CONSULTA</span><h2><?= h($modulos[$modulo]) ?></h2></div><span class="portal-future-status"><?= number_format($total,0,',','.') ?> registros</span></div>
 <form class="sa-search" method="get" action="superadmin_consultas.php">
 <input type="hidden" name="modulo" value="<?= h($modulo) ?>">
 <label for="sa-search-input">Buscar por código, nombre o estado</label>
 <input id="sa-search-input" type="search" name="buscar" value="<?= h($buscar) ?>" maxlength="80" placeholder="Buscar registros">
 <button class="sa-action-button" type="submit">Buscar</button>
 <a href="<?= h(sa_url($modulo,'',1)) ?>">Limpiar</a>
 </form>
 <?php if($error): ?><p role="alert">No se pudo consultar la información. Revisá el registro de errores PHP.</p>
 <?php elseif(!$datos): ?><p>No se encontraron registros.</p>
 <?php else: ?>
 <div class="sa-personal-scroll"><table class="sa-personal-table">
 <?php if(in_array($modulo,['stock','catalogo','reabastecimiento'],true)): ?>
 <thead><tr><th>SKU / Componente</th><th>Categoría</th><th>Ubicación</th><th>Físico</th><th>Comprometido</th><th>Disponible</th><th>Mínimo</th><?php if($modulo==='catalogo'): ?><th>Ficha técnica</th><?php endif; ?></tr></thead><tbody>
 <?php foreach($datos as $d): ?><tr><td><strong><?= h($d['nombre']) ?></strong><small><?= h($d['sku']) ?></small></td><td><?= h($d['categoria']) ?></td><td><?= h($d['ubicacion_deposito']) ?></td><td><?= (int)$d['stock_fisico'] ?></td><td><?= (int)$d['comprometido'] ?></td><td><?= (int)$d['disponible'] ?></td><td><?= (int)$d['umbral_minimo'] ?></td><?php if($modulo==='catalogo'): ?><td><?= h($d['ficha_tecnica'] ?? '—') ?></td><?php endif; ?></tr><?php endforeach; ?>
 </tbody>
 <?php elseif($modulo==='reservas'): ?>
 <thead><tr><th>Reserva</th><th>SKU</th><th>Cantidad</th><th>Estado</th><th>Creada</th><th>Vencimiento</th><th>Empleado</th><th>Autorizó</th></tr></thead><tbody>
 <?php foreach($datos as $d): ?><tr><td><?= h($d['id_reserva']) ?></td><td><?= h($d['sku']) ?></td><td><?= (int)$d['cantidad'] ?></td><td><?= h($d['estado']) ?></td><td><?= h($d['creada_en']) ?></td><td><?= h($d['fecha_vencimiento']) ?></td><td><?= h($d['usuario']??'—') ?></td><td><?= h($d['autorizador']??'—') ?></td></tr><?php endforeach; ?></tbody>
 <?php else: ?>
 <thead><tr><th>Fecha</th><th>SKU</th><th>Tipo</th><th>Cantidad</th><th>Anterior</th><th>Nuevo</th><th>Empleado</th><th>Motivo</th></tr></thead><tbody>
 <?php foreach($datos as $d): ?><tr><td><?= h($d['fecha']) ?></td><td><?= h($d['sku']) ?></td><td><?= h($d['tipo']) ?></td><td><?= (int)$d['cantidad'] ?></td><td><?= (int)$d['stock_anterior'] ?></td><td><?= (int)$d['stock_nuevo'] ?></td><td><?= h($d['usuario']??'—') ?></td><td><?= h($d['motivo']) ?></td></tr><?php endforeach; ?></tbody>
 <?php endif; ?>
 </table></div>
 <?php endif; ?>
 <nav class="sa-pages" aria-label="Paginación"><?php if($pagina>1): ?><a class="sa-action-button" href="<?= h(sa_url($modulo,$buscar,$pagina-1)) ?>">Anterior</a><?php endif; ?><span>Página <?= $pagina ?> de <?= max(1,(int)ceil($total/$limite)) ?></span><?php if($pagina*$limite<$total): ?><a class="sa-action-button" href="<?= h(sa_url($modulo,$buscar,$pagina+1)) ?>">Siguiente</a><?php endif; ?></nav>
 <p class="sa-note">Esta pantalla solo realiza consultas SELECT. No dispone de formularios de modificación, autorización ni facturación.</p>
 </section>
</main>
<?php require_once __DIR__.'/includes/footer.php'; ?>
