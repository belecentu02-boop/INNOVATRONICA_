<?php
/** Portal de empleados: punto de entrada protegido a los módulos de la empresa. */
require_once __DIR__ . '/includes/auth.php';
requerir_login();
header('Cache-Control: no-store, private');

$page_title = 'Portal de empleados · Innovatrónica S.A.';
$es_portal_empleados = true;
require_once __DIR__ . '/includes/header.php';

$accesos_stock = [
    ['titulo' => 'Consultar stock', 'descripcion' => 'Disponibilidad y ubicación de componentes', 'url' => 'stock.php'],
    ['titulo' => es_vendedor() ? 'Mis reservas' : 'Todas las reservas', 'descripcion' => es_vendedor() ? 'Seguimiento de tus operaciones' : 'Seguimiento y gestión de operaciones', 'url' => 'reservas.php'],
];
if (es_admin() || es_supervisor()) {
    $accesos_stock[] = ['titulo' => 'Reabastecimiento', 'descripcion' => 'Componentes que requieren atención', 'url' => 'reabastecimiento.php'];
}
if (es_admin()) {
    $accesos_stock[] = ['titulo' => 'Catálogo e inventario', 'descripcion' => 'Productos y ajustes de existencias', 'url' => 'catalogo.php'];
}
?>

<main class="employee-portal" id="main-content">
  <div class="portal-context"><span class="portal-context-line" aria-hidden="true"></span> PORTAL DE EMPLEADOS <span class="portal-context-separator" aria-hidden="true">/</span> <span class="portal-context-detail">TU ESPACIO DE TRABAJO</span></div>

  <section class="portal-welcome" aria-labelledby="portal-title">
    <div>
      <h1 id="portal-title">Hola, <?= h($u['nombre']) ?>.</h1>
      <p>Tu equipo, tus herramientas. Todo desde un mismo lugar.</p>
    </div>
    <div class="portal-company-label"><span aria-hidden="true"></span> Innovatrónica S.A.</div>
  </section>

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'password_actualizada'): ?>
    <div class="alert alert-success" role="status">Tu contraseña se actualizó correctamente.</div>
  <?php endif; ?>

  <section aria-labelledby="modules-title">
    <div class="portal-section-heading">
      <h2 id="modules-title">Módulos de la empresa</h2>
      <p>Accedé a las herramientas de tu área.</p>
    </div>

    <div class="portal-module-layout">
      <article class="portal-stock-module" aria-labelledby="stock-module-title">
        <div class="portal-stock-topline">
          <span class="portal-module-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 9 5v8l-9 5-9-5V8l9-5Z"/><path d="m3 8 9 5 9-5M12 13v8M7.5 5.5l9 5"/></svg></span>
          <span class="portal-available"><span aria-hidden="true"></span> Disponible</span>
        </div>
        <div class="portal-stock-content">
          <span class="portal-eyebrow">ÁREA COMERCIAL</span>
          <h3 id="stock-module-title">Stock e inventario</h3>
          <p>La información que necesitás para cada operación. Consultá componentes, gestioná reservas y seguí la disponibilidad del inventario.</p>
        </div>
        <div class="portal-stock-bottom">
          <a href="index.php" class="portal-primary-link" id="enter-stock">Ingresar al módulo <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>
          <span class="portal-stock-note">Control de stock · Ventas internas</span>
        </div>
      </article>

      <aside class="portal-quick-access" aria-labelledby="quick-access-title">
        <div class="portal-quick-heading"><h3 id="quick-access-title">Accesos de tu área</h3><span class="portal-eyebrow">STOCK</span></div>
        <?php foreach ($accesos_stock as $acceso): ?>
          <a href="<?= h($acceso['url']) ?>" class="portal-quick-link">
            <span><strong><?= h($acceso['titulo']) ?></strong><span><?= h($acceso['descripcion']) ?></span></span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
          </a>
        <?php endforeach; ?>
        <?php if (es_admin()): ?>
          <a href="usuarios.php" class="portal-personnel-link">Gestionar personal <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M7 7h10v10"/></svg></a>
        <?php endif; ?>
      </aside>
    </div>

    <div class="portal-future-heading"><span class="portal-eyebrow">OTRAS ÁREAS</span><span>Espacios previstos para futuras implementaciones.</span></div>
    <div class="portal-future-grid">
      <article class="portal-future-module">
        <svg class="portal-future-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18M7 14l4-4 4 3 6-7M16 6h5v5"/></svg>
        <h3>Ventas y comercial</h3><p>Un espacio para la gestión comercial.</p><span class="portal-future-status">Próximamente</span>
      </article>
      <article class="portal-future-module">
        <svg class="portal-future-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 3h3l3 12h11l3-8H5M9 20h.01M18 20h.01"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
        <h3>Compras y proveedores</h3><p>Un espacio para conectar el abastecimiento.</p><span class="portal-future-status">Próximamente</span>
      </article>
      <article class="portal-future-module">
        <svg class="portal-future-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h3M8 18h3M15 15h1M15 18h1"/></svg>
        <h3>Administración</h3><p>Un espacio para los procesos administrativos.</p><span class="portal-future-status">Próximamente</span>
      </article>
    </div>
  </section>

  <p class="portal-access-note"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg> Tus accesos se ajustan a los permisos de tu cuenta.</p>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
