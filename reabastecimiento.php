<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Módulo: Interfaz de Alertas de Reabastecimiento (Próximamente - Fase 4)
 */

$page_title = 'Alertas de Reabastecimiento · Innovatrónica S.A.';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">
  <div class="coming-soon-card">
    <div class="coming-soon-badge-fase">
      <span>🚀</span>
      <span>Fase 4: Catálogo y Alertas de Reabastecimiento</span>
    </div>

    <span class="coming-soon-icon">⚠️</span>

    <h1 class="coming-soon-title">Alertas de Reabastecimiento Crítico</h1>

    <p class="coming-soon-desc">
      Panel especializado para detectar tempranamente componentes electrónicos cuyo Stock Disponible ha perforado el umbral de seguridad ($S_D \le U_{min}$) o se encuentran en quiebre total ($S_D = 0$).
    </p>

    <div class="coming-soon-specs">
      <div class="coming-soon-specs-title">Previsto para la Fase 4</div>
      <ul class="coming-soon-specs-list">
        <li>Cálculo automatizado de cantidad sugerida a reponer: $(U_{min} \times 2) - S_D$.</li>
        <li>Diferenciación visual entre stock crítico y quiebre total.</li>
        <li>Filtro directo por categoría, ubicación física y nivel de urgencia.</li>
        <li>Reporte de componentes con alta demanda comprometida.</li>
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
