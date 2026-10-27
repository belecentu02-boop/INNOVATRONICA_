<?php
require_once __DIR__.'/includes/auth.php';requerir_rol(['ADMIN']);$page_title='Ajustar stock · Innovatrónica S.A.';require_once __DIR__.'/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/stock.css?v=<?= (int)filemtime(__DIR__.'/assets/css/stock.css') ?>">
<link rel="stylesheet" href="assets/css/ajustes.css?v=<?= (int)filemtime(__DIR__.'/assets/css/ajustes.css') ?>">
<main class="container stock-page ajustes-page">
 <section class="dashboard-hero"><div><h1 class="dashboard-hero-title">Ajustar stock físico</h1><p class="dashboard-hero-sub">Registrá ingresos y correcciones de inventario.</p></div><a class="btn btn-outline" href="catalogo.php">Volver al catálogo</a></section>
 <p class="stock-nota">Consultá el componente antes de ingresar el nuevo total físico. El movimiento y su motivo quedan registrados.</p>
 <div id="ajuste-mensaje" class="alert" role="status" hidden></div>
 <section class="ajuste-panel"><form id="consulta-form"><label class="form-label" for="consulta-sku">SKU del componente</label><div class="ajuste-buscar"><input class="form-input" id="consulta-sku" maxlength="30" required autocomplete="off"><button type="submit" class="btn btn-outline" id="consulta-boton">Consultar</button></div></form>
 <div id="ajuste-datos" hidden><div class="ajuste-resumen"><h2 id="ajuste-nombre"></h2><p>SKU: <strong id="ajuste-sku"></strong></p><p>Stock físico actual: <strong id="ajuste-fisico"></strong> · Comprometido: <strong id="ajuste-comprometido"></strong></p></div>
 <form id="ajuste-form" novalidate><input type="hidden" name="csrf_token" value="<?= h(generar_csrf()) ?>"><input type="hidden" name="sku"><input type="hidden" name="stock_esperado">
 <div class="form-group"><label class="form-label" for="ajuste-tipo">Tipo de movimiento</label><select class="form-input" id="ajuste-tipo" name="tipo"><option value="INGRESO">Ingreso por compra</option><option value="AJUSTE">Corrección de inventario</option></select></div>
 <div class="form-group"><label class="form-label" for="ajuste-nuevo">Nuevo total de stock físico</label><input class="form-input" id="ajuste-nuevo" name="stock_nuevo" type="number" min="0" max="2147483647" step="1" required><small>Ingresá el total resultante, no la diferencia.</small></div>
 <div class="form-group"><label class="form-label" for="ajuste-motivo">Motivo</label><textarea class="form-input" id="ajuste-motivo" name="motivo" maxlength="255" rows="3" required></textarea></div>
 <button type="submit" id="ajuste-guardar" class="btn btn-primary">Revisar ajuste</button></form></div></section>
 <dialog id="ajuste-dialogo" class="ajuste-dialogo" aria-labelledby="ajuste-dialogo-titulo"><h2 id="ajuste-dialogo-titulo">Confirmar movimiento</h2><p id="ajuste-confirmacion"></p><div class="ajuste-acciones"><button class="btn btn-outline" id="ajuste-cancelar" type="button">Cancelar</button><button class="btn btn-primary" id="ajuste-confirmar" type="button">Confirmar</button></div></dialog>
 <noscript>Activá JavaScript para ajustar el stock.</noscript>
</main><script src="assets/js/ajustes.js?v=<?= (int)filemtime(__DIR__.'/assets/js/ajustes.js') ?>" defer></script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
