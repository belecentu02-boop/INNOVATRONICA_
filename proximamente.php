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

<main class="container">
  <div class="coming-soon-card">
    <div class="coming-soon-badge-fase">
      <span>🚀</span>
      <span><?= h($fase) ?></span>
    </div>

    <span class="coming-soon-icon">📦</span>

    <h1 class="coming-soon-title"><?= h($modulo) ?></h1>

    <p class="coming-soon-desc">
      <?= h($descripcion) ?>
    </p>

    <div class="coming-soon-specs">
      <div class="coming-soon-specs-title">Estado del proyecto</div>
      <ul class="coming-soon-specs-list">
        <li>Actualmente nos encontramos en la <strong>Fase 2: Dashboard y Ambiente por Rol</strong>.</li>
        <li>La infraestructura de base de datos y autenticación (Fase 1) está activa y verificada.</li>
        <li>Este módulo mantendrá la navegación y el layout corporativo intacto sin generar errores 404.</li>
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
