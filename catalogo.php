<?php
/** Fase 4.1: gestión de catálogo, exclusiva para ADMIN. */
require_once __DIR__ . '/includes/auth.php';
requerir_rol(['ADMIN']);
$page_title = 'Catálogo e Inventario · Innovatrónica S.A.';
require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/catalogo.css?v=<?= (int)filemtime(__DIR__ . '/assets/css/catalogo.css') ?>">
<main class="container catalogo-page" id="catalogo-app">
  <section class="dashboard-hero" aria-labelledby="catalogo-titulo">
    <div>
      <h1 class="dashboard-hero-title" id="catalogo-titulo">Catálogo de componentes</h1>
      <p class="dashboard-hero-sub">Productos, fichas técnicas, stock mínimo y ubicaciones del depósito.</p>
    </div>
    <button type="button" class="btn btn-primary" id="catalogo-nuevo">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
      Nuevo producto
    </button>
  </section>
  <p class="catalogo-aviso">Los productos nuevos comienzan con stock físico en cero. Los ingresos y ajustes de existencias se habilitarán en el módulo de inventario.</p>
  <noscript><div class="alert alert-warning">Activá JavaScript para consultar y administrar el catálogo.</div></noscript>
  <div id="catalogo-mensaje" class="alert" role="alert" hidden></div>
  <section class="catalogo-filtros" aria-label="Filtros del catálogo">
    <div class="form-group">
      <label for="catalogo-busqueda" class="form-label">Buscar componente</label>
      <input type="search" id="catalogo-busqueda" class="form-input" placeholder="SKU, nombre, categoría o ubicación" maxlength="120" autocomplete="off">
    </div>
    <div class="form-group">
      <label for="catalogo-categoria" class="form-label">Categoría</label>
      <select id="catalogo-categoria" class="form-input"><option value="">Todas las categorías</option></select>
    </div>
    <button type="button" class="btn btn-outline" id="catalogo-limpiar">Limpiar filtros</button>
    <button type="button" class="btn btn-outline" id="catalogo-recargar">Actualizar lista</button>
  </section>
  <section class="catalogo-listado" aria-label="Productos registrados">
    <p id="catalogo-estado" role="status" aria-live="polite">Cargando catálogo…</p>
    <div class="catalogo-tabla-wrap">
      <table class="data-table" id="catalogo-tabla" aria-busy="true">
        <caption class="catalogo-sr">Catálogo de componentes electrónicos</caption>
        <thead><tr><th>Componente / SKU</th><th>Categoría</th><th>Ubicación</th><th>Stock físico</th><th>Mínimo</th><th>Acciones</th></tr></thead>
        <tbody id="catalogo-filas"></tbody>
      </table>
    </div>
    <nav class="catalogo-paginacion" aria-label="Páginas del catálogo">
      <button type="button" class="btn btn-outline" id="catalogo-anterior" disabled>Anterior</button>
      <span id="catalogo-pagina">1 / 1</span>
      <button type="button" class="btn btn-outline" id="catalogo-siguiente" disabled>Siguiente</button>
    </nav>
  </section>
  <dialog id="catalogo-dialogo" class="catalogo-dialogo" aria-labelledby="catalogo-form-titulo">
    <header class="catalogo-dialogo-header">
      <h2 id="catalogo-form-titulo">Nuevo producto</h2>
      <button type="button" class="btn btn-outline" id="catalogo-cerrar" aria-label="Cerrar formulario">Cerrar</button>
    </header>
    <form id="catalogo-form" method="post" action="api.php">
      <input type="hidden" name="csrf_token" value="<?= h(generar_csrf()) ?>">
      <input type="hidden" name="accion" id="catalogo-accion" value="crear_producto">
      <div id="catalogo-form-error" class="alert alert-danger" role="alert" hidden></div>
      <fieldset id="catalogo-campos" class="catalogo-campos">
        <legend class="catalogo-sr">Datos del componente</legend>
        <div class="form-group">
          <label class="form-label" for="producto-sku">SKU *</label>
          <input class="form-input" id="producto-sku" name="sku" required maxlength="30" pattern="[A-Za-z0-9]+(-[A-Za-z0-9]+)*" placeholder="ESP32-DEV-01" aria-describedby="catalogo-sku-ayuda">
          <small id="catalogo-sku-ayuda">Código único. Se guarda en mayúsculas y no se modifica después del alta.</small>
        </div>
        <div class="form-group">
          <label class="form-label" for="producto-nombre">Nombre *</label>
          <input class="form-input" id="producto-nombre" name="nombre" required maxlength="120">
        </div>
        <div class="form-group">
          <label class="form-label" for="producto-categoria">Categoría *</label>
          <input class="form-input" id="producto-categoria" name="categoria" required maxlength="60" list="catalogo-categorias">
          <datalist id="catalogo-categorias"></datalist>
        </div>
        <div class="form-group">
          <label class="form-label" for="producto-ubicacion">Ubicación *</label>
          <input class="form-input" id="producto-ubicacion" name="ubicacion_deposito" required maxlength="30" pattern="[A-Za-z0-9]+(-[A-Za-z0-9]+)*" placeholder="A1-01">
        </div>
        <div class="form-group">
          <label class="form-label" for="producto-minimo">Stock mínimo *</label>
          <input class="form-input" id="producto-minimo" name="umbral_minimo" type="number" min="0" max="2147483647" step="1" value="5" required>
        </div>
        <div class="form-group catalogo-ancho">
          <label class="form-label" for="producto-ficha">Ficha técnica resumida</label>
          <textarea class="form-input" id="producto-ficha" name="ficha_tecnica" maxlength="255" rows="3" placeholder="Características principales (máximo 255 caracteres)"></textarea>
        </div>
      </fieldset>
      <footer class="catalogo-dialogo-acciones">
        <button type="button" class="btn btn-outline" id="catalogo-cancelar">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="catalogo-guardar">Guardar producto</button>
      </footer>
    </form>
  </dialog>
</main>
<script src="assets/js/catalogo.js?v=<?= (int)filemtime(__DIR__ . '/assets/js/catalogo.js') ?>" defer></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
