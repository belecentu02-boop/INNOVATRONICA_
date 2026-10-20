<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Verificación de Fase 1: Autenticación, Conexión y Roles
 */

require_once __DIR__ . '/includes/auth.php';

// Bloquear acceso no autenticado (redirige a login.php)
requerir_login();

$u = usuario_actual();
$pdo = db();

// Estadísticas de verificación de la base de datos para la Fase 1
$total_usuarios = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$total_productos = (int)$pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn();
$total_quiebre = (int)$pdo->query('SELECT COUNT(*) FROM inventario WHERE stock_fisico = 0')->fetchColumn();
$total_criticos = (int)$pdo->query('SELECT COUNT(*) FROM inventario WHERE stock_fisico > 0 AND stock_fisico <= umbral_minimo')->fetchColumn();

// Clase y etiqueta según el rol
$rol_badge_class = 'badge-vendedor';
$rol_texto = 'Vendedor Interno';
if (es_admin()) {
    $rol_badge_class = 'badge-admin';
    $rol_texto = 'Administrador General';
} elseif (es_supervisor()) {
    $rol_badge_class = 'badge-supervisor';
    $rol_texto = 'Supervisor Comercial';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verificación Fase 1 · Innovatrónica S.A.</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

  <!-- Barra Superior -->
  <header class="app-header">
    <a href="index.php" class="app-brand">
      <div class="app-brand-badge">IN</div>
      <div>
        <div class="app-brand-name">Innovatrónica S.A.</div>
        <div style="font-size: 0.75rem; color: var(--text-muted);">Control de Stock · Ventas Internas</div>
      </div>
    </a>

    <div class="app-user-panel">
      <div class="app-user-info">
        <strong><?= h($u['nombre']) ?></strong>
        <div>
          <span style="font-size: 0.75rem; color: var(--text-muted); margin-right: 0.4rem;"><?= h($u['codigo_empleado']) ?></span>
          <span class="badge <?= $rol_badge_class ?>"><?= h($rol_texto) ?></span>
        </div>
      </div>
      <a href="logout.php" class="btn btn-outline btn-sm">Cerrar Sesión</a>
    </div>
  </header>

  <!-- Contenido de Verificación Fase 1 -->
  <main class="container">
    <div class="phase-card">
      <div class="phase-card-header">
        <div>
          <h1 class="phase-card-title">Fase 1: Infraestructura y Autenticación</h1>
          <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
            Base de datos unificada, conexión PDO segura, tokens CSRF y soporte para 3 roles.
          </p>
        </div>
        <div class="phase-status-pill">Fase 1 Completada</div>
      </div>

      <div class="alert alert-info" style="margin-bottom: 1.5rem;">
        <span>ℹ️</span>
        <span>
          Sesión iniciada correctamente como <strong><?= h($u['nombre']) ?></strong> 
          (Usuario: <code><?= h($u['usuario']) ?></code>, Legajo: <code><?= h($u['codigo_empleado']) ?></code>).
          El backend ha verificado su rol con éxito como <strong class="badge <?= $rol_badge_class ?>"><?= h($rol_texto) ?></strong>.
        </span>
      </div>

      <h3 style="font-size: 1.05rem; margin-bottom: 0.5rem; color: var(--text-main);">Estado de la Base de Datos (innovatronica)</h3>
      <div class="info-grid">
        <div class="info-box">
          <div class="info-box-label">Conexión MySQL</div>
          <div class="info-box-value" style="color: var(--success); font-size: 1rem;">Activa (PDO utf8mb4)</div>
          <small style="color: var(--text-muted);">Timezone: America/Argentina/Buenos_Aires</small>
        </div>

        <div class="info-box">
          <div class="info-box-label">Usuarios Registrados</div>
          <div class="info-box-value"><?= $total_usuarios ?></div>
          <small style="color: var(--text-muted);">Admin, Supervisor y Vendedor</small>
        </div>

        <div class="info-box">
          <div class="info-box-label">Catálogo Semilla</div>
          <div class="info-box-value"><?= $total_productos ?> productos</div>
          <small style="color: var(--text-muted);"><?= $total_criticos ?> críticos / <?= $total_quiebre ?> quiebre total</small>
        </div>
      </div>

      <div class="alert alert-warning" style="margin-top: 1.5rem;">
        <span>🔒</span>
        <span>
          <strong>Regla de Trabajo:</strong> Las Fases 2 a 6 (navegación según rol, catálogo, stock en tiempo real, reservas, autorizaciones y facturación) permanecen sin tocar hasta recibir confirmación explícita para avanzar.
        </span>
      </div>
    </div>
  </main>

</body>
</html>
