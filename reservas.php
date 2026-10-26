<?php
require_once __DIR__ . '/includes/auth.php';
requerir_rol(['ADMIN','SUPERVISOR_COMERCIAL','VENDEDOR_INTERNO']);
$es_vendedor = es_vendedor();
$page_title = ($es_vendedor ? 'Mis reservas' : 'Todas las reservas') . ' · Innovatrónica S.A.';
require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/stock.css?v=<?= (int)filemtime(__DIR__ . '/assets/css/stock.css') ?>">
<link rel="stylesheet" href="assets/css/reservas.css?v=<?= (int)filemtime(__DIR__ . '/assets/css/reservas.css') ?>">
<main class="container stock-page reservas-page">
  <section class="dashboard-hero" aria-labelledby="reservas-titulo">
    <div><h1 class="dashboard-hero-title" id="reservas-titulo"><?= $es_vendedor ? 'Mis reservas' : 'Todas las reservas' ?></h1>
    <p class="dashboard-hero-sub">Consultá el estado y la vigencia de las reservas comerciales.</p></div>
    <button type="button" class="btn btn-outline" id="reservas-recargar">Actualizar</button>
  </section>
  <p class="stock-nota">Consulta automática cada 15 segundos mientras la página está visible. La creación y las autorizaciones se incorporarán en los siguientes bloques.</p>
  <p class="stock-nota" id="reservas-actualizacion">Todavía no se consultaron los datos.</p>
  <noscript><div class="alert alert-warning">Activá JavaScript para consultar las reservas.</div></noscript>
  <div class="alert alert-danger" id="reservas-error" role="alert" hidden></div>
  <a class="btn btn-outline" id="reservas-sesion" href="login.php" hidden>Volver a ingresar</a>
  <section class="stock-filtros reservas-filtros" aria-label="Filtros de reservas">
    <div class="form-group"><label class="form-label" for="reservas-busqueda">Buscar</label><input class="form-input" type="search" id="reservas-busqueda" maxlength="120" placeholder="ID de reserva, SKU o componente" autocomplete="off"></div>
    <div class="form-group"><label class="form-label" for="reservas-estado">Estado</label><select class="form-input" id="reservas-estado"><option value="">Todos los estados</option><option value="ACTIVA">Activa</option><option value="PENDIENTE_AUTORIZACION">Pendiente de autorización</option><option value="CONFIRMADA">Confirmada</option><option value="FACTURADA">Facturada</option><option value="EXPIRADA">Expirada</option><option value="RECHAZADA">Rechazada</option><option value="LIBERADA">Liberada</option></select></div>
    <button type="button" class="btn btn-outline" id="reservas-limpiar">Limpiar filtros</button>
  </section>
  <section aria-label="Listado de reservas" class="stock-listado reservas-listado">
    <p id="reservas-mensaje" role="status" aria-live="polite">Cargando reservas…</p>
    <div id="reservas-lista" aria-busy="true"></div>
    <nav class="stock-paginacion" aria-label="Páginas de reservas"><button class="btn btn-outline" type="button" id="reservas-anterior" disabled>Anterior</button><span id="reservas-pagina">1 / 1</span><button class="btn btn-outline" type="button" id="reservas-siguiente" disabled>Siguiente</button></nav>
  </section>
</main>
<script src="assets/js/reservas.js?v=<?= (int)filemtime(__DIR__ . '/assets/js/reservas.js') ?>" defer></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
