<?php
require_once __DIR__ . '/includes/auth.php';
requerir_rol(['ADMIN','SUPERVISOR_COMERCIAL','VENDEDOR_INTERNO']);
$es_vendedor = es_vendedor();
$page_title = ($es_vendedor ? 'Mis reservas' : 'Todas las reservas') . ' · Innovatrónica S.A.';
require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/stock.css?v=<?= (int)filemtime(__DIR__ . '/assets/css/stock.css') ?>">
<link rel="stylesheet" href="assets/css/reservas.css?v=<?= (int)filemtime(__DIR__ . '/assets/css/reservas.css') ?>">
<main class="container stock-page reservas-page" data-puede-autorizar="<?= $es_vendedor ? '0' : '1' ?>">
  <section class="dashboard-hero" aria-labelledby="reservas-titulo">
    <div><h1 class="dashboard-hero-title" id="reservas-titulo"><?= $es_vendedor ? 'Mis reservas' : 'Todas las reservas' ?></h1>
    <p class="dashboard-hero-sub">Consultá el estado y la vigencia de las reservas comerciales.</p></div>
    <div class="reserva-acciones"><button type="button" class="btn btn-primary" id="reserva-abrir">Nueva reserva</button>
    <button type="button" class="btn btn-outline" id="reservas-recargar">Actualizar</button></div>
  </section>
  <p class="stock-nota">Consulta automática cada 15 segundos mientras la página está visible. Las solicitudes críticas de vendedores requieren autorización.</p>
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
  <dialog id="reserva-dialogo" class="reserva-dialogo" aria-labelledby="reserva-form-titulo">
    <form id="reserva-form" method="post" novalidate>
      <div class="reserva-dialogo-head"><h2 id="reserva-form-titulo">Nueva reserva</h2><button type="button" class="btn btn-outline" id="reserva-cerrar" aria-label="Cerrar">Cerrar</button></div>
      <p>Consultá el SKU en Stock y verificá la disponibilidad antes de solicitar.</p>
      <input type="hidden" name="csrf_token" value="<?= h(generar_csrf()) ?>">
      <div class="form-group"><label class="form-label" for="reserva-sku">SKU del componente</label><input class="form-input" type="text" id="reserva-sku" name="sku" maxlength="30" required autocomplete="off"></div>
      <div class="form-group"><label class="form-label" for="reserva-cantidad">Cantidad</label><input class="form-input" type="number" id="reserva-cantidad" name="cantidad" min="1" max="2147483647" step="1" value="1" required></div>
      <?php if (!$es_vendedor): ?><div class="form-group"><label class="form-label" for="reserva-horas">Vigencia en horas</label><input class="form-input" type="number" id="reserva-horas" name="horas" min="1" max="720" step="1" value="24" required></div>
      <?php else: ?><input type="hidden" name="horas" value="24"><p>Vigencia: 24 horas.</p><?php endif; ?>
      <p id="reserva-form-error" class="alert alert-danger" role="alert" hidden></p>
      <div class="reserva-dialogo-footer"><a class="btn btn-outline" href="stock.php">Consultar stock</a><button type="submit" class="btn btn-primary" id="reserva-guardar">Crear reserva</button></div>
    </form>
  </dialog>
  <dialog id="reserva-resolver-dialogo" class="reserva-dialogo" aria-labelledby="reserva-resolver-titulo">
    <form id="reserva-resolver-form" novalidate>
      <div class="reserva-dialogo-head"><h2 id="reserva-resolver-titulo">Resolver reserva</h2><button type="button" class="btn btn-outline" id="reserva-resolver-cerrar">Cerrar</button></div>
      <p id="reserva-resolver-detalle"></p>
      <div class="form-group" id="reserva-motivo-grupo" hidden><label class="form-label" for="reserva-motivo">Motivo del rechazo</label><textarea class="form-input" id="reserva-motivo" maxlength="255" rows="3"></textarea></div>
      <div class="form-group" id="reserva-extension-grupo" hidden><label class="form-label" for="reserva-extension-horas">Horas adicionales</label><input class="form-input" type="number" id="reserva-extension-horas" min="1" max="720" step="1" value="24"></div>
      <p id="reserva-resolver-error" class="alert alert-danger" role="alert" hidden></p>
      <div class="reserva-dialogo-footer"><button type="button" class="btn btn-outline" id="reserva-resolver-cancelar">Cancelar</button><button type="submit" class="btn btn-primary" id="reserva-resolver-confirmar">Confirmar</button></div>
    </form>
  </dialog>
  <p id="reserva-exito" class="alert alert-success" role="status" hidden></p>
</main>
<script src="assets/js/reservas.js?v=<?= (int)filemtime(__DIR__ . '/assets/js/reservas.js') ?>" defer></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
