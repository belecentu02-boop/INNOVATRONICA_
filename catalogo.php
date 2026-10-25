<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Módulo: Catálogo e Inventario (Próximamente - Fase 4)
 * Exclusivo para rol ADMIN
 */

require_once __DIR__ . '/includes/auth.php';

// Verificación estricta en backend: solo ADMIN puede acceder a este módulo
requerir_rol(['ADMIN']);

$page_title = 'Catálogo e Inventario · Innovatrónica S.A.';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container module-page">
  <div class="coming-soon-card">
    <div class="coming-soon-badge-fase">
      <span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 3h6M10 3v5l-4 5v2h12v-2l-4-5V3M12 15v6"/></svg></span>
      <span>En desarrollo · Fase 4: Catálogo y Gestión de Inventario</span>
    </div>

    <span class="coming-soon-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" ><path d="M3 7V5a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v2M3 7h5l2 2h11l-2 12H3L1 7h2Z"/></svg></span>

    <h1 class="coming-soon-title">Administración de Catálogo e Inventario</h1>

    <p class="coming-soon-desc">
      Módulo administrativo para el alta y edición de componentes de electrónica, modificación de umbrales mínimos de seguridad ($U_{min}$), asignación de coordenadas de depósito y parámetros técnicos.
    </p>

    <div class="coming-soon-specs">
      <div class="coming-soon-specs-title">Previsto para la Fase 4</div>
      <ul class="coming-soon-specs-list">
        <li>Alta de nuevos productos con SKU normalizado (ej. <code>ESP32-CAM-01</code>).</li>
        <li>Definición y ajuste de Umbrales Mínimos ($U_{min}$) por componente.</li>
        <li>Asignación de coordenadas de pasillo/estantería en depósito (ej. <code>A1-01</code>).</li>
        <li>Mantenimiento de ficha técnica y especificaciones de hardware.</li>
      </ul>
    </div>

    <div>
      <a href="index.php" class="btn btn-primary" id="btn-back-dashboard">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m12 5-7 7 7 7M5 12h14"/></svg> Volver al Dashboard
      </a>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
