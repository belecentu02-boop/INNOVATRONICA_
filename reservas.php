<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Módulo: Gestión de Reservas y Temporizador (Próximamente - Fase 5)
 */

require_once __DIR__ . '/includes/auth.php';
requerir_login();

$u = usuario_actual();
$es_solo_vendedor = es_vendedor();
$page_title = $es_solo_vendedor ? 'Mis Reservas · Innovatrónica S.A.' : 'Todas las Reservas · Innovatrónica S.A.';

require_once __DIR__ . '/includes/header.php';
?>

<main class="container module-page">
  <div class="coming-soon-card">
    <div class="coming-soon-badge-fase">
      <span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 3h6M10 3v5l-4 5v2h12v-2l-4-5V3M12 15v6"/></svg></span>
      <span>En desarrollo · Fase 5: Reservas, Temporizador y Aprobaciones</span>
    </div>

    <span class="coming-soon-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" ><circle cx="12" cy="14" r="8"/><path d="M12 10v4l2 2M9 2h6M12 2v4M18 8l2-2"/></svg></span>

    <h1 class="coming-soon-title">
      <?= $es_solo_vendedor ? 'Mis Reservas Comerciales' : 'Gestión Integral de Reservas' ?>
    </h1>

    <p class="coming-soon-desc">
      <?= $es_solo_vendedor 
          ? 'En este módulo podrás consultar tus reservas activas, temporizadores regresivos en tiempo real y solicitar autorizaciones para reservas en stock crítico.' 
          : 'Panel centralizado para supervisar todas las reservas comerciales del equipo, autorizar o rechazar solicitudes críticas, extender vigencias o liberar unidades al stock general.' ?>
    </p>

    <div class="coming-soon-specs">
      <div class="coming-soon-specs-title">Previsto para la Fase 5</div>
      <ul class="coming-soon-specs-list">
        <li>Temporizador decreciente en vivo <code>HH:MM:SS</code> sincronizado con la hora del servidor.</li>
        <li>Ciclo de vida completo: <code>ACTIVA</code>, <code>PENDIENTE_AUTORIZACION</code>, <code>CONFIRMADA</code>, <code>FACTURADA</code>, <code>EXPIRADA</code>, <code>RECHAZADA</code>, <code>LIBERADA</code>.</li>
        <li>Modales integrados en la interfaz (sin <code>confirm()</code> ni <code>prompt()</code> nativos).</li>
        <li>Control de concurrencia mediante transacciones y bloqueos <code>SELECT ... FOR UPDATE</code>.</li>
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
