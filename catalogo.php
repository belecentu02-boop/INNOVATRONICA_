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

<main class="container">
  <div class="coming-soon-card">
    <div class="coming-soon-badge-fase">
      <span>🚀</span>
      <span>Fase 4: Catálogo y Gestión de Inventario</span>
    </div>

    <span class="coming-soon-icon">🗂️</span>

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
        ← Volver al Dashboard
      </a>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
