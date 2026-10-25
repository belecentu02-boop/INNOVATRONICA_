<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Módulo: Interfaz de Alertas de Reabastecimiento (Próximamente - Fase 4)
 */

$page_title = 'Alertas de Reabastecimiento · Innovatrónica S.A.';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container module-page">
  <div class="coming-soon-card">
    <div class="coming-soon-badge-fase">
      <span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 3h6M10 3v5l-4 5v2h12v-2l-4-5V3M12 15v6"/></svg></span>
      <span>En desarrollo · Fase 4: Catálogo y Alertas de Reabastecimiento</span>
    </div>

    <span class="coming-soon-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" ><path d="m10.3 3.9-8.1 14A2 2 0 0 0 3.9 21h16.2a2 2 0 0 0 1.7-3.1l-8.1-14a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg></span>

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
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m12 5-7 7 7 7M5 12h14"/></svg> Volver al Dashboard
      </a>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
