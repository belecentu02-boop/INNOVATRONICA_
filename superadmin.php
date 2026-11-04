<?php
require_once __DIR__ . '/includes/auth.php';
requerir_login();
requerir_rol(['SUPERADMIN']);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
$u = usuario_actual();
$pdo = db();
function sa_count(PDO $pdo, string $sql): int {
    try { return (int)$pdo->query($sql)->fetchColumn(); }
    catch (PDOException $e) { error_log('SUPERADMIN métricas: '.$e->getMessage()); return 0; }
}
$stats = [
    'usuarios' => sa_count($pdo, "SELECT COUNT(*) FROM usuarios WHERE rol <> 'SUPERADMIN'"),
    'activos' => sa_count($pdo, "SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND rol <> 'SUPERADMIN'"),
    'productos' => sa_count($pdo, 'SELECT COUNT(*) FROM productos'),
    'reservas' => sa_count($pdo, 'SELECT COUNT(*) FROM reservas'),
    'logs' => sa_count($pdo, 'SELECT COUNT(*) FROM log_acciones'),
];
$actividad = [];
try {
    $stmt = $pdo->query('SELECT l.accion, l.detalles, l.fecha, u.usuario FROM log_acciones l LEFT JOIN usuarios u ON u.id_empleado = l.id_empleado ORDER BY l.fecha DESC, l.id DESC LIMIT 8');
    $actividad = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('SUPERADMIN auditoría: '.$e->getMessage()); }
$section = $_GET['seccion'] ?? 'resumen';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $section !== 'personal') { http_response_code(405); exit('Método no permitido'); }
$sections = ['resumen'=>'Resumen general','personal'=>'Personal','auditoria'=>'Auditoría','seguridad'=>'Seguridad','operaciones'=>'Operaciones'];
if (!array_key_exists($section, $sections)) $section = 'resumen';
?>
<?php
$page_title = 'Centro de Control · Innovatrónica S.A.';
$es_portal_empleados = true;

// Procesar acciones POST antes de generar cualquier HTML.
// Procesar acciones POST antes de generar cualquier HTML.
$seccion = $_GET['seccion'] ?? 'resumen';

if ($seccion === 'personal') {
    if (!defined('SA_PERSONAL_ALLOWED')) {
        define('SA_PERSONAL_ALLOWED', true);
    }

    $sa_personal_procesar = true;
    require __DIR__ . '/superadmin_personal.php';
       $sa_personal_procesar = false;
}

// Cargar la cabecera institucional después de procesar los formularios.
require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="assets/css/superadmin.css?v=<?= filemtime(__DIR__ . '/assets/css/superadmin.css') ?>">
<link rel="stylesheet" href="assets/css/superadmin-legibilidad.css?v=1">
<link rel="stylesheet" href="assets/css/superadmin-consultas.css?v=<?= (int)filemtime(__DIR__ . '/assets/css/superadmin-consultas.css') ?>">
<link rel="stylesheet" href="assets/css/superadmin-monitor.css?v=1">
<link rel="stylesheet" href="assets/css/superadmin-personal-avanzado.css?v=1">
<script src="assets/js/superadmin-roles.js?v=1"></script>
<main class="employee-portal sa-portal" id="main-content">
  <div class="portal-context"><span class="portal-context-line" aria-hidden="true"></span> INNOVATRÓNICA S.A. <span class="portal-context-separator">/</span> CENTRO DE CONTROL <span class="portal-context-separator">/</span> SUPERADMIN</div>
  <section class="portal-welcome"><div><span class="portal-eyebrow sa-eyebrow">ADMINISTRACIÓN GENERAL</span><h1>Centro de Control</h1><p>Hola, <?= h($u['nombre'] ?? 'Superadministrador') ?>. Supervisá la actividad y el estado de la plataforma.</p></div><div class="portal-company-label"><span></span> Acceso privilegiado</div></section>
  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'password_actualizada'): ?><div class="alert alert-success" role="status">Tu contraseña se actualizó correctamente.</div><?php endif; ?>
  <nav class="sa-tabs" aria-label="Secciones del centro de control">
  <?php foreach ($sections as $key => $label): ?><a class="sa-tab <?= $section === $key ? 'sa-tab-active' : '' ?>" href="superadmin.php?seccion=<?= h($key) ?>" <?= $section === $key ? 'aria-current="page"' : '' ?>><?= h($label) ?></a><?php endforeach; ?>
  </nav>
  <?php if ($section === 'resumen'): ?>
  <section aria-labelledby="sa-indicators"><div class="portal-section-heading"><h2 id="sa-indicators">Indicadores de la empresa</h2><p>Datos actuales del sistema</p></div>
  <div class="sa-stats">
  <?php foreach ([['Empleados registrados',$stats['usuarios'],'PERSONAL'],['Cuentas activas',$stats['activos'],'ACCESOS'],['Productos en catálogo',$stats['productos'],'INVENTARIO'],['Reservas registradas',$stats['reservas'],'OPERACIONES']] as $metric): ?>
  <article class="sa-stat"><span class="portal-eyebrow"><?= h($metric[2]) ?></span><strong><?= number_format($metric[1],0,',','.') ?></strong><span><?= h($metric[0]) ?></span></article>
  <?php endforeach; ?></div></section>
  <div class="sa-columns">
  <section class="sa-panel" aria-labelledby="sa-audit-title"><div class="sa-panel-head"><div><span class="portal-eyebrow">AUDITORÍA</span><h2 id="sa-audit-title">Actividad reciente</h2></div><span class="portal-future-status"><?= number_format($stats['logs'],0,',','.') ?> eventos</span></div>
  <?php if (!$actividad): ?><p class="sa-empty">No hay eventos registrados.</p><?php else: ?><div class="sa-activity">
  <?php foreach ($actividad as $event): ?><div class="sa-event"><span class="sa-event-mark" aria-hidden="true"></span><div class="sa-event-body"><strong><?= h($event['accion']) ?></strong><p><?= h($event['usuario'] ?: 'Sistema') ?> · <?= h(mb_strimwidth((string)($event['detalles'] ?? ''),0,100,'…','UTF-8')) ?></p></div><time><?= h($event['fecha']) ?></time></div><?php endforeach; ?>
  </div><?php endif; ?></section>
  <aside class="sa-panel sa-side" aria-labelledby="sa-status-title"><div class="sa-panel-head"><div><span class="portal-eyebrow">ESTADO GENERAL</span><h2 id="sa-status-title">Plataforma</h2></div></div><div class="sa-status"><span>Base de datos</span><strong>Conectada</strong></div><div class="sa-status"><span>Registros de auditoría</span><strong><?= number_format($stats['logs'],0,',','.') ?></strong></div><div class="sa-status"><span>Perfil de acceso</span><strong>SUPERADMIN</strong></div><div class="sa-notice"><strong>Próximas funciones</strong><p>Gestión privilegiada de personal, sesiones y controles de seguridad. Se habilitarán tras implementar permisos y auditoría.</p></div></aside>
  </div>
  <?php elseif ($section === 'personal'): ?>
  <?php require __DIR__ . '/superadmin_personal.php'; ?>
  <?php // El módulo de personal ya fue cargado antes de la cabecera. ?>
  <?php elseif ($section === 'auditoria'): ?>
    <?php define('SA_PANEL_ALLOWED', true); require __DIR__ . '/superadmin_auditoria.php'; ?>
  <?php elseif ($section === 'seguridad'): ?>
    <?php define('SA_PANEL_ALLOWED', true); require __DIR__ . '/superadmin_seguridad.php'; ?>
  <?php elseif ($section === 'operaciones'): ?>
    <?php define('SA_PANEL_ALLOWED', true); require __DIR__ . '/superadmin_operaciones.php'; ?>
  <?php else: ?>
    <section class="sa-panel"><p>Sección no disponible.</p></section>
  <?php endif; ?>
  <!-- Agregar dentro de <main class="employee-portal sa-portal"> de superadmin.php,
     inmediatamente ANTES de <p class="portal-access-note">.
     Este bloque es visible en todas las pestañas y no cambia permisos operativos. -->
<section class="sa-panel" aria-labelledby="sa-areas-titulo" style="margin-top:1.5rem">
  <div class="sa-panel-head">
    <div><span class="portal-eyebrow">SUPERVISIÓN GENERAL</span>
    <h2 id="sa-areas-titulo">Áreas de la empresa</h2></div>
    <span class="portal-future-status">Solo lectura</span>
  </div>
  <p class="sa-personal-note">Consultá la información operativa sin crear, editar, autorizar ni facturar.</p>
  <div class="sa-areas-grid">
    <?php foreach ([
      ['stock','Stock y disponibilidad','Existencias físicas, comprometidas y disponibles'],
      ['catalogo','Catálogo','Productos, categorías, ubicaciones y fichas técnicas'],
      ['reservas','Reservas','Operaciones comerciales, vencimientos y estados'],
      ['reabastecimiento','Reabastecimiento','Componentes con disponibilidad crítica'],
      ['inventario','Movimientos de inventario','Ingresos, ajustes y facturación de stock']
    ] as $area): ?>
<a class="sa-area-link"
   href="superadmin_consultas.php?modulo=<?= h($area[0]) ?>">

    <div class="sa-area-header">

        <span class="sa-area-icon" aria-hidden="true">
            <?php
            $iconos = [
                'stock' => 'package',
                'catalogo' => 'cpu',
                'reservas' => 'clipboard',
                'reabastecimiento' => 'alert',
                'inventario' => 'warehouse'
            ];

            $icono = $iconos[$area[0]] ?? 'package';
            ?>

            <?php if ($icono === 'package'): ?>
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="m12 3 9 5v8l-9 5-9-5V8l9-5Z"/>
                    <path d="m3 8 9 5 9-5M12 13v8"/>
                </svg>

            <?php elseif ($icono === 'cpu'): ?>
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="5" y="5" width="14" height="14" rx="2"/>
                    <rect x="9" y="9" width="6" height="6"/>
                    <path d="M9 1v4M15 1v4M9 19v4M15 19v4
                             M19 9h4M19 15h4M1 9h4M1 15h4"/>
                </svg>

            <?php elseif ($icono === 'clipboard'): ?>
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="5" y="4" width="14" height="18" rx="2"/>
                    <path d="M9 4.5h6M9 11h6M9 15h6"/>
                </svg>

            <?php elseif ($icono === 'alert'): ?>
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="m12 3 10 18H2L12 3Z"/>
                    <path d="M12 9v5M12 18h.01"/>
                </svg>

            <?php elseif ($icono === 'warehouse'): ?>
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 21V8l9-5 9 5v13"/>
                    <path d="M3 10h18M8 21v-8h8v8"/>
                </svg>
            <?php endif; ?>
        </span>

        <strong><?= h($area[1]) ?></strong>
    </div>

    <span><?= h($area[2]) ?></span>
</a>

    <?php endforeach; ?>
  </div>
</section>

    <p class="portal-access-note">Centro de Control · Acceso restringido a SUPERADMIN.</p>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
