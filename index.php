<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Dashboard Principal con Métricas Dinámicas por Rol (Fase 2)
 */

require_once __DIR__ . '/includes/auth.php';

// Verificación obligatoria de autenticación
requerir_login();

$u = usuario_actual();
$pdo = db();

// ============================================================
// 1. CÁLCULO DE STOCK GENERAL DISPONIBLE
// Fórmula de decisiones.md: S_D = S_F - S_C
// S_C = Reservas en estado ACTIVA, PENDIENTE_AUTORIZACION o CONFIRMADA
// ============================================================
$stmtStock = $pdo->query("
    SELECT 
        COALESCE(SUM(i.stock_fisico), 0) AS total_fisico,
        COALESCE((
            SELECT SUM(r.cantidad) 
            FROM reservas r 
            WHERE r.estado IN ('ACTIVA', 'PENDIENTE_AUTORIZACION', 'CONFIRMADA')
        ), 0) AS total_comprometido,
        COUNT(i.sku) AS total_items
    FROM inventario i
");
$stockData = $stmtStock->fetch(PDO::FETCH_ASSOC) ?: ['total_fisico' => 0, 'total_comprometido' => 0, 'total_items' => 0];

$total_stock_fisico = (int)$stockData['total_fisico'];
$total_stock_comprometido = (int)$stockData['total_comprometido'];
$total_stock_disponible = max(0, $total_stock_fisico - $total_stock_comprometido);
$total_skus = (int)$stockData['total_items'];

// ============================================================
// 2. CÁLCULO DE PRODUCTOS EN ALERTA (S_D <= U_min)
// Quiebre total si S_D = 0; Stock Crítico si S_D <= U_min y S_D > 0
// ============================================================
$stmtAlertas = $pdo->query("
    SELECT 
        COUNT(*) AS total_alertas,
        COALESCE(SUM(CASE WHEN stock_disponible = 0 THEN 1 ELSE 0 END), 0) AS quiebre_total,
        COALESCE(SUM(CASE WHEN stock_disponible > 0 AND stock_disponible <= umbral_minimo THEN 1 ELSE 0 END), 0) AS stock_critico
    FROM (
        SELECT 
            i.sku,
            i.stock_fisico,
            i.umbral_minimo,
            (i.stock_fisico - COALESCE(SUM(r.cantidad), 0)) AS stock_disponible
        FROM inventario i
        LEFT JOIN reservas r 
            ON i.sku = r.sku 
            AND r.estado IN ('ACTIVA', 'PENDIENTE_AUTORIZACION', 'CONFIRMADA')
        GROUP BY i.sku, i.stock_fisico, i.umbral_minimo
    ) AS calc
    WHERE stock_disponible <= umbral_minimo
");
$alertasData = $stmtAlertas->fetch(PDO::FETCH_ASSOC) ?: ['total_alertas' => 0, 'quiebre_total' => 0, 'stock_critico' => 0];

$total_alertas = (int)$alertasData['total_alertas'];
$total_quiebres = (int)$alertasData['quiebre_total'];
$total_criticos = (int)$alertasData['stock_critico'];

// ============================================================
// 3. CÁLCULO DE RESERVAS ACTIVAS Y PENDIENTES
// Vendedor: ve únicamente sus propias reservas (WHERE id_empleado = ?)
// Supervisor y Admin: ven el acumulado de todo el equipo
// ============================================================
if (es_vendedor()) {
    $stmtReservas = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN estado = 'ACTIVA' THEN 1 ELSE 0 END), 0) AS activas,
            COALESCE(SUM(CASE WHEN estado = 'PENDIENTE_AUTORIZACION' THEN 1 ELSE 0 END), 0) AS pendientes
        FROM reservas
        WHERE id_empleado = ?
    ");
    $stmtReservas->execute([$u['id_empleado']]);
} else {
    $stmtReservas = $pdo->query("
        SELECT 
            COALESCE(SUM(CASE WHEN estado = 'ACTIVA' THEN 1 ELSE 0 END), 0) AS activas,
            COALESCE(SUM(CASE WHEN estado = 'PENDIENTE_AUTORIZACION' THEN 1 ELSE 0 END), 0) AS pendientes
        FROM reservas
    ");
}
$reservasData = $stmtReservas->fetch(PDO::FETCH_ASSOC) ?: ['activas' => 0, 'pendientes' => 0];

$total_reservas_activas = (int)$reservasData['activas'];
$total_reservas_pendientes = (int)$reservasData['pendientes'];

// ============================================================
// 4. CÁLCULO DE EMPLEADOS ACTIVOS (EXCLUSIVO ADMIN)
// ============================================================
$total_empleados_activos = 0;
if (es_admin()) {
    $stmtEmp = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1");
    $total_empleados_activos = (int)$stmtEmp->fetchColumn();
}

$page_title = 'Dashboard · Innovatrónica S.A.';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">

  <!-- Hero de Bienvenida y Contexto Operativo -->
  <section class="dashboard-hero" aria-labelledby="dashboard-heading">
    <div>
      <h1 class="dashboard-hero-title" id="dashboard-heading">
        Bienvenido, <?= h($u['nombre']) ?>
      </h1>
      <p class="dashboard-hero-sub">
        Panel de Control de Stock · Subdepartamento de Verificación y Disponibilidad (Área Comercial)
      </p>
    </div>

    <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
      <div class="dashboard-time-badge" title="Zona horaria del servidor">
        <span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
        <span><?= date('d/m/Y - H:i') ?> ART</span>
      </div>
      <span class="badge <?= rol_badge_class() ?>">
        <span class="badge-dot"></span>
        <?= h(rol_label()) ?>
      </span>
    </div>
  </section>

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'password_actualizada'): ?>
    <div class="alert alert-success" role="alert" style="margin-bottom: 1.5rem;">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
        <polyline points="22 4 12 14.01 9 11.01"/>
      </svg>
      <div>
        <strong>¡Contraseña actualizada con éxito!</strong> Tus nuevas credenciales de acceso se encuentran guardadas y operativas.
      </div>
    </div>
  <?php endif; ?>

  <!-- Sección de Métricas Principales (Tarjetas Dinámicas según Rol) -->
  <section aria-labelledby="metrics-heading">
    <div class="metrics-section-title">
      <span id="metrics-heading">Indicadores Operativos en Tiempo Real</span>
      <span style="font-size:0.8rem; font-weight:normal; color:var(--text-muted);">
        Actualizado contra base <code>innovatronica</code>
      </span>
    </div>

    <div class="metrics-grid">

      <!-- Tarjeta 1: Stock General Disponible (Todos los roles) -->
      <article class="metric-card metric-card-primary" id="card-stock-disponible">
        <div class="metric-header">
          <span class="metric-label">Stock General Disponible</span>
          <div class="metric-icon-box metric-icon-primary"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="m12 3 9 5v8l-9 5-9-5V8l9-5Z"/><path d="m3 8 9 5 9-5M12 13v8M7.5 5.5l9 5"/></svg></div>
        </div>
        <div class="metric-value-row">
          <span class="metric-value" id="val-stock-disponible"><?= number_format($total_stock_disponible, 0, ',', '.') ?></span>
          <span class="metric-unit">unidades</span>
        </div>
        <div>
          <span class="metric-formula" title="Stock Físico menos Stock Comprometido">
            S_D = <?= $total_stock_fisico ?> (físico) - <?= $total_stock_comprometido ?> (comprometido)
          </span>
        </div>
        <div class="metric-footer">
          <span><?= $total_skus ?> productos registrados</span>
          <span class="badge badge-success">Comercializable</span>
        </div>
      </article>

      <!-- Tarjeta 2: Productos en Alerta de Reabastecimiento (Todos los roles) -->
      <article class="metric-card metric-card-danger" id="card-alertas">
        <div class="metric-header">
          <span class="metric-label">Productos en Alerta</span>
          <div class="metric-icon-box metric-icon-danger"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="m10.3 3.9-8.1 14A2 2 0 0 0 3.9 21h16.2a2 2 0 0 0 1.7-3.1l-8.1-14a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg></div>
        </div>
        <div class="metric-value-row">
          <span class="metric-value" id="val-alertas"><?= $total_alertas ?></span>
          <span class="metric-unit">SKUs (S_D ≤ U_min)</span>
        </div>
        <div>
          <span class="metric-formula">
            <?= $total_quiebres ?> en quiebre total · <?= $total_criticos ?> críticos
          </span>
        </div>
        <div class="metric-footer">
          <span><?= $total_quiebres > 0 ? 'Quiebres detectados' : 'Sin quiebres totales' ?></span>
          <span class="badge <?= $total_alertas > 0 ? 'badge-danger' : 'badge-success' ?>">
            <?= $total_alertas > 0 ? 'Atención Requerida' : 'Nivel Óptimo' ?>
          </span>
        </div>
      </article>

      <!-- Tarjeta 3: Reservas Activas (Adaptada según Rol) -->
      <article class="metric-card metric-card-info" id="card-reservas-activas">
        <div class="metric-header">
          <span class="metric-label">
            <?= es_vendedor() ? 'Mis Reservas Activas' : 'Reservas Activas Globales' ?>
          </span>
          <div class="metric-icon-box metric-icon-info"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><circle cx="12" cy="14" r="8"/><path d="M12 10v4l2 2M9 2h6M12 2v4M18 8l2-2"/></svg></div>
        </div>
        <div class="metric-value-row">
          <span class="metric-value" id="val-reservas-activas"><?= $total_reservas_activas ?></span>
          <span class="metric-unit">vigentes</span>
        </div>
        <div>
          <span class="metric-formula">
            <?= es_vendedor() ? 'Asignadas a tu usuario' : 'Total equipo comercial' ?>
          </span>
        </div>
        <div class="metric-footer">
          <span>Temporizador de 24 hs</span>
          <span class="badge badge-warning">En Curso</span>
        </div>
      </article>

      <!-- Tarjeta 4: Solicitudes Pendientes de Autorización (Adaptada según Rol) -->
      <article class="metric-card metric-card-warning" id="card-reservas-pendientes">
        <div class="metric-header">
          <span class="metric-label">
            <?= es_vendedor() ? 'Mis Solicitudes Pendientes' : 'Pendientes de Autorización' ?>
          </span>
          <div class="metric-icon-box metric-icon-warning"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="M5 3h14M5 21h14M7 3v4l5 5-5 5v4M17 3v4l-5 5 5 5v4"/></svg></div>
        </div>
        <div class="metric-value-row">
          <span class="metric-value" id="val-reservas-pendientes"><?= $total_reservas_pendientes ?></span>
          <span class="metric-unit">solicitudes</span>
        </div>
        <div>
          <span class="metric-formula">
            <?= es_vendedor() ? 'Esperando aprobación de supervisor' : 'Stock crítico por autorizar' ?>
          </span>
        </div>
        <div class="metric-footer">
          <span><?= es_vendedor() ? 'En evaluación' : 'Requiere revisión' ?></span>
          <span class="badge <?= $total_reservas_pendientes > 0 ? 'badge-warning' : 'badge-success' ?>">
            <?= $total_reservas_pendientes > 0 ? 'Pendiente' : 'Al Día' ?>
          </span>
        </div>
      </article>

      <?php if (es_admin()): ?>
      <!-- Tarjeta 5: Empleados Activos (Exclusiva para Administrador) -->
      <article class="metric-card metric-card-purple" id="card-empleados-activos">
        <div class="metric-header">
          <span class="metric-label">Personal Activo Registrado</span>
          <div class="metric-icon-box metric-icon-purple"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/></svg></div>
        </div>
        <div class="metric-value-row">
          <span class="metric-value" id="val-empleados-activos"><?= $total_empleados_activos ?></span>
          <span class="metric-unit">usuarios</span>
        </div>
        <div>
          <span class="metric-formula">Exclusivo: Panel de Dirección</span>
        </div>
        <div class="metric-footer">
          <span>Vendedores y Supervisores</span>
          <span class="badge badge-admin">Gestión RRHH</span>
        </div>
      </article>
      <?php endif; ?>

    </div>
  </section>

  <!-- Atajos Operativos y Estado del Sistema -->
  <section style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.25rem;">
    
    <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-lg); padding:1.5rem; box-shadow:var(--shadow-sm);">
      <h2 style="font-size:1.05rem; font-weight:700; color:var(--text-main); margin-bottom:0.75rem; display:flex; align-items:center; gap:0.5rem;">
        <span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/></svg></span>
        <span>Atribuciones de tu Rol: <?= h(rol_label()) ?></span>
      </h2>
      <ul style="list-style:none; display:flex; flex-direction:column; gap:0.6rem; font-size:0.875rem; color:var(--text-muted);">
        <?php if (es_vendedor()): ?>
          <li>✓ Consulta de catálogo, stock disponible ($S_D$) y ubicación en estanterías.</li>
          <li>✓ Creación de reservas normales (vigencia estándar de 24 horas).</li>
          <li>✓ Solicitud de autorización ante reservas en stock crítico ($S_D \le U_{min}$).</li>
          <li>✓ Confirmación de tus reservas comerciales para derivación a facturación.</li>
        <?php elseif (es_supervisor()): ?>
          <li>✓ Supervisión global de todas las reservas del equipo comercial.</li>
          <li>✓ Autorización directa o rechazo de solicitudes en stock crítico.</li>
          <li>✓ Extensión manual de vigencia horaria o liberación anticipada de reservas.</li>
          <li>✓ Monitoreo del panel de alertas de reabastecimiento crítico.</li>
          <li>✓ Procesamiento de facturación y despacho (descuento de stock físico).</li>
        <?php elseif (es_admin()): ?>
          <li>✓ Máxima jerarquía: acceso total a reservas, inventario y facturación.</li>
          <li>✓ <strong>Exclusivo:</strong> Gestión de personal (alta/edición de vendedores y supervisores).</li>
          <li>✓ <strong>Exclusivo:</strong> Administración del catálogo, umbrales mínimos ($U_{min}$) y depósito.</li>
          <li>✓ <strong>Exclusivo:</strong> Ajustes directos de stock físico ($S_F$) por compras o auditoría.</li>
        <?php endif; ?>
      </ul>
    </div>

    <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-lg); padding:1.5rem; box-shadow:var(--shadow-sm);">
      <h2 style="font-size:1.05rem; font-weight:700; color:var(--text-main); margin-bottom:0.75rem; display:flex; align-items:center; gap:0.5rem;">
        <span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="M9 3h6M10 3v5l-4 5v2h12v-2l-4-5V3M12 15v6"/></svg></span>
        <span>Acceso Directo a Módulos del Sistema</span>
      </h2>
      <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem;">
        Usa la barra superior de navegación o estos accesos rápidos configurados según tus privilegios:
      </p>
      <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
        <a href="stock.php" class="btn btn-outline btn-sm"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="m12 3 9 5v8l-9 5-9-5V8l9-5Z"/><path d="m3 8 9 5 9-5M12 13v8M7.5 5.5l9 5"/></svg> Búsqueda de Stock</a>
        <a href="reservas.php" class="btn btn-outline btn-sm">
          <?= es_vendedor() ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><circle cx="12" cy="14" r="8"/><path d="M12 10v4l2 2M9 2h6M12 2v4M18 8l2-2"/></svg> Mis Reservas' : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 12h6M9 17h4"/></svg> Todas las Reservas' ?>
        </a>
        <?php if (!es_vendedor()): ?>
          <a href="reabastecimiento.php" class="btn btn-outline btn-sm"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="m10.3 3.9-8.1 14A2 2 0 0 0 3.9 21h16.2a2 2 0 0 0 1.7-3.1l-8.1-14a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg> Alertas Reabastecimiento</a>
        <?php endif; ?>
        <?php if (es_admin()): ?>
          <a href="usuarios.php" class="btn btn-outline btn-sm"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/></svg> Gestión de Personal</a>
          <a href="catalogo.php" class="btn btn-outline btn-sm"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="M3 7V5a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v2M3 7h5l2 2h11l-2 12H3L1 7h2Z"/></svg> Catálogo/Inventario</a>
        <?php endif; ?>
      </div>
    </div>

  </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
