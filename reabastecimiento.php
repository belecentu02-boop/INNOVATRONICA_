<?php
/** Fase 4: alertas para administración y supervisión. */
require_once __DIR__ . '/includes/auth.php';
requerir_rol(['ADMIN', 'SUPERVISOR_COMERCIAL']);
$page_title = 'Alertas de Reabastecimiento · Innovatrónica S.A.';
require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/stock.css?v=<?= (int)filemtime(__DIR__ . '/assets/css/stock.css') ?>">
<main class="container stock-page">
  <section class="dashboard-hero" aria-labelledby="stock-titulo">
    <div><h1 class="dashboard-hero-title" id="stock-titulo">Alertas de reabastecimiento</h1>
    <p class="dashboard-hero-sub">Priorizá los componentes que necesitan reposición.</p></div>
    <button type="button" class="btn btn-outline" id="stock-recargar">Actualizar alertas</button>
  </section>
  <p class="stock-nota">Solo componentes con disponible ≤ mínimo. Sugerido a reponer = (mínimo × 2) − disponible. Los saldos negativos requieren revisar la inconsistencia antes de reponer. Se actualiza cada 15 segundos mientras esta página está visible.</p>
  <p class="stock-nota" id="stock-actualizacion">Todavía no se consultaron los datos.</p>
  <noscript><div class="alert alert-warning">Activá JavaScript para consultar el stock.</div></noscript>
  <div class="alert alert-danger" role="alert" id="stock-error" hidden></div>
  <a href="login.php" class="btn btn-outline" id="stock-sesion" hidden>Volver a ingresar</a>
  <section class="stock-resumen" aria-label="Totales de los productos filtrados">
    <article><h2>Productos</h2><strong id="stock-total-productos">—</strong></article>
    <article><h2>Unidades físicas</h2><strong id="stock-total-fisico">—</strong></article>
    <article><h2>Comprometidas</h2><strong id="stock-total-comprometido">—</strong></article>
    <article><h2>Disponibles</h2><strong id="stock-total-disponible">—</strong></article>
  </section>
  <p class="stock-nota">Los totales corresponden a todos los resultados de los filtros, no solo a esta página.</p>
  <section class="stock-filtros" aria-label="Filtros de disponibilidad">
    <div class="form-group"><label class="form-label" for="stock-busqueda">Buscar componente</label>
      <input type="search" class="form-input" id="stock-busqueda" maxlength="120" placeholder="SKU, nombre, categoría o ubicación" autocomplete="off"></div>
    <div class="form-group"><label class="form-label" for="stock-categoria">Categoría</label>
      <select class="form-input" id="stock-categoria"><option value="">Todas las categorías</option></select></div>
    <div class="form-group"><label class="form-label" for="stock-nivel">Disponibilidad</label>
      <select class="form-input" id="stock-nivel"><option value="">Todas las alertas</option><option value="CRITICO">Crítico</option><option value="QUIEBRE">Quiebre total</option><option value="INCONSISTENTE">Revisar inconsistencia</option></select></div>
    <button type="button" class="btn btn-outline" id="stock-limpiar">Limpiar filtros</button>
  </section>
  <section class="stock-listado" aria-label="Disponibilidad por componente">
    <p id="stock-estado" role="status" aria-live="polite">Cargando alertas…</p>
    <div class="stock-tabla-wrap">
      <table class="data-table" id="stock-tabla" aria-busy="true">
        <caption class="stock-sr">Componentes en alerta y cantidad sugerida a reponer</caption>
        <thead><tr><th>Componente / SKU</th><th>Categoría</th><th>Ubicación</th><th>Físico</th><th>Comprometido</th><th>Disponible</th><th>Mínimo</th><th>Sugerido a reponer</th><th>Estado</th></tr></thead>
        <tbody id="stock-filas"></tbody>
      </table>
    </div>
    <nav class="stock-paginacion" aria-label="Páginas de alertas">
      <button class="btn btn-outline" type="button" id="stock-anterior" disabled>Anterior</button>
      <span id="stock-pagina">1 / 1</span>
      <button class="btn btn-outline" type="button" id="stock-siguiente" disabled>Siguiente</button>
    </nav>
  </section>
</main>
<script src="assets/js/reabastecimiento.js?v=<?= (int)filemtime(__DIR__ . '/assets/js/reabastecimiento.js') ?>" defer></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
