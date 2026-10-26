<?php
/** Fase 4: consulta de stock y ubicación, disponible para los tres roles. */
require_once __DIR__ . '/includes/auth.php';
requerir_rol(['ADMIN', 'SUPERVISOR_COMERCIAL', 'VENDEDOR_INTERNO']);
$page_title = 'Stock y Ubicaciones · Innovatrónica S.A.';
require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/stock.css?v=<?= (int)filemtime(__DIR__ . '/assets/css/stock.css') ?>">
<main class="container stock-page">
  <section class="dashboard-hero" aria-labelledby="stock-titulo">
    <div><h1 class="dashboard-hero-title" id="stock-titulo">Stock y ubicaciones</h1>
    <p class="dashboard-hero-sub">Disponibilidad de componentes para el equipo comercial.</p></div>
    <button type="button" class="btn btn-outline" id="stock-recargar">Actualizar stock</button>
  </section>
  <p class="stock-nota">Disponible = físico − comprometido. Se actualiza cada 15 segundos mientras esta página está visible.</p>
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
      <select class="form-input" id="stock-nivel"><option value="">Todos los niveles</option><option value="NORMAL">Normal</option><option value="CRITICO">Crítico</option><option value="QUIEBRE">Quiebre total</option><option value="INCONSISTENTE">Revisar inconsistencia</option></select></div>
    <button type="button" class="btn btn-outline" id="stock-limpiar">Limpiar filtros</button>
  </section>
  <section class="stock-listado" aria-label="Disponibilidad por componente">
    <p id="stock-estado" role="status" aria-live="polite">Cargando stock…</p>
    <div class="stock-tabla-wrap">
      <table class="data-table" id="stock-tabla" aria-busy="true">
        <caption class="stock-sr">Stock físico, comprometido y disponible de los componentes</caption>
        <thead><tr><th>Componente / SKU</th><th>Categoría</th><th>Ubicación</th><th>Físico</th><th>Comprometido</th><th>Disponible</th><th>Mínimo</th><th>Estado</th></tr></thead>
        <tbody id="stock-filas"></tbody>
      </table>
    </div>
    <nav class="stock-paginacion" aria-label="Páginas de stock">
      <button class="btn btn-outline" type="button" id="stock-anterior" disabled>Anterior</button>
      <span id="stock-pagina">1 / 1</span>
      <button class="btn btn-outline" type="button" id="stock-siguiente" disabled>Siguiente</button>
    </nav>
  </section>
</main>
<script src="assets/js/stock.js?v=<?= (int)filemtime(__DIR__ . '/assets/js/stock.js') ?>" defer></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
