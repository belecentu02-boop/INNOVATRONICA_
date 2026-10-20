<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Módulo: Gestión de Personal y Empleados (Próximamente - Fase 3)
 * Exclusivo para rol ADMIN
 */

require_once __DIR__ . '/includes/auth.php';

// Verificación estricta en backend: solo ADMIN puede acceder a este módulo
requerir_rol(['ADMIN']);

$page_title = 'Gestión de Personal · Innovatrónica S.A.';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">
  <div class="coming-soon-card">
    <div class="coming-soon-badge-fase">
      <span>🚀</span>
      <span>Fase 3: Gestión de Personal y Permisos (Exclusivo Admin)</span>
    </div>

    <span class="coming-soon-icon">👥</span>

    <h1 class="coming-soon-title">Gestión de Personal y Control de Acceso</h1>

    <p class="coming-soon-desc">
      Módulo exclusivo para la Dirección y Administración General. Permitirá la creación de nuevos empleados, asignación de roles (Vendedor / Supervisor), reseteo de credenciales y activación/desactivación de cuentas.
    </p>

    <div class="coming-soon-specs">
      <div class="coming-soon-specs-title">Previsto para la Fase 3</div>
      <ul class="coming-soon-specs-list">
        <li>Alta de empleados con generación de <code>codigo_empleado</code> único.</li>
        <li>Asignación de contraseñas con encriptación segura <code>password_hash</code> (BCrypt).</li>
        <li>Selector de roles: <code>VENDEDOR_INTERNO</code> o <code>SUPERVISOR_COMERCIAL</code>.</li>
        <li>Auditoría completa en <code>log_acciones</code> para cada cambio de usuario o contraseña.</li>
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
