<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Módulo: Búsqueda de Stock y Ubicación en Depósito (Próximamente - Fase 4)
 */

$page_title = 'Stock y Ubicaciones · Innovatrónica S.A.';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">
  <div class="coming-soon-card">
    <div class="coming-soon-badge-fase">
      <span>🚀</span>
      <span>Fase 4: Catálogo y Stock en Tiempo Real</span>
    </div>

    <span class="coming-soon-icon">📦</span>

    <h1 class="coming-soon-title">Buscador y Visualización de Stock</h1>

    <p class="coming-soon-desc">
      Aquí se integrará la búsqueda reactiva de componentes por SKU, nombre, categoría y coordenadas de depósito (ej. <code>A1-01</code>), mostrando en vivo Stock Físico ($S_F$), Comprometido ($S_C$) y Disponible ($S_D$).
    </p>

    <div class="coming-soon-specs">
      <div class="coming-soon-specs-title">Previsto para la Fase 4</div>
      <ul class="coming-soon-specs-list">
        <li>Búsqueda instantánea en vivo sin recargar la página.</li>
        <li>Visualización gráfica de coordenadas de ubicación en estanterías de depósito.</li>
        <li>Cálculo en tiempo real: $S_D = S_F - S_C$.</li>
        <li>Indicador visual de stock normal, crítico ($S_D \le U_{min}$) y quiebre total ($S_D = 0$).</li>
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
