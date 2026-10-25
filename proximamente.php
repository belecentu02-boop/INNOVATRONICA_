<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Pantalla "Próximamente" para Módulos de Fases Futuras
 */

$page_title = 'Próximamente · Innovatrónica S.A.';
require_once __DIR__ . '/includes/header.php';

$modulo = trim($_GET['modulo'] ?? 'Módulo en Construcción');
$fase = trim($_GET['fase'] ?? 'Fase Futura');
$descripcion = trim($_GET['desc'] ?? 'Esta funcionalidad se habilitará en las siguientes etapas del proyecto según el plan de trabajo.');
?>

<main class="container module-page">
  <div class="coming-soon-card">
    <div class="coming-soon-badge-fase">
      <span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 3h6M10 3v5l-4 5v2h12v-2l-4-5V3M12 15v6"/></svg></span>
      <span><?= h($fase) ?></span>
    </div>

    <span class="coming-soon-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" ><path d="m12 3 9 5v8l-9 5-9-5V8l9-5Z"/><path d="m3 8 9 5 9-5M12 13v8M7.5 5.5l9 5"/></svg></span>

    <h1 class="coming-soon-title"><?= h($modulo) ?></h1>

    <p class="coming-soon-desc">
      <?= h($descripcion) ?>
    </p>

    <div class="coming-soon-specs">
      <div class="coming-soon-specs-title">Estado del proyecto</div>
      <ul class="coming-soon-specs-list">
        <li>Los módulos se habilitan progresivamente según el plan de trabajo del proyecto.</li>
        <li>La infraestructura de base de datos y autenticación (Fase 1) está activa y verificada.</li>
        <li>Este módulo mantendrá la navegación y el layout corporativo intacto sin generar errores 404.</li>
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
