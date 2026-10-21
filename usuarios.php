<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Módulo: Gestión de Personal y Control de Acceso (Fase 3)
 * Exclusivo para rol ADMIN
 */

require_once __DIR__ . '/includes/auth.php';

// Verificación estricta en backend: solo ADMIN puede acceder a este módulo
requerir_rol(['ADMIN']);

$u_actual = usuario_actual();
$current_admin_id = (int)$u_actual['id_empleado'];
$csrf_token = generar_csrf();
$pdo = db();

// Consulta inicial de usuarios para renderizado seguro en servidor
$stmtUsers = $pdo->query("
    SELECT 
        id_empleado,
        codigo_empleado,
        nombre,
        usuario,
        rol,
        activo,
        debe_cambiar_password,
        DATE_FORMAT(creado_en, '%d/%m/%Y %H:%i') AS creado_en
    FROM usuarios 
    ORDER BY id_empleado ASC
");
$usuarios = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Métricas de personal para tarjetas ejecutivas
$total_personal = count($usuarios);
$total_activos = 0;
$total_inactivos = 0;
$total_vendedores = 0;
$total_supervisores = 0;

foreach ($usuarios as $usr) {
    if ((int)$usr['activo'] === 1) {
        $total_activos++;
    } else {
        $total_inactivos++;
    }
    if ($usr['rol'] === 'VENDEDOR_INTERNO') {
        $total_vendedores++;
    } elseif ($usr['rol'] === 'SUPERVISOR_COMERCIAL') {
        $total_supervisores++;
    }
}

$page_title = 'Gestión de Personal · Innovatrónica S.A.';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">

  <!-- Encabezado de la Sección -->
  <div class="dashboard-hero">
    <div>
      <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem;">
        <span class="badge badge-admin">Fase 3 · Exclusivo Administración</span>
      </div>
      <h1 class="dashboard-hero-title">Gestión de Personal y Cuentas de Acceso</h1>
      <p class="dashboard-hero-sub">
        Administración del equipo comercial, asignación de roles jerárquicos, control de estado y credenciales.
      </p>
    </div>
    <div>
      <button type="button" class="btn btn-primary" id="btn-abrir-alta">
        <span>➕</span>
        <span>Nuevo Empleado</span>
      </button>
    </div>
  </div>

  <!-- Métricas Rápidas de Personal -->
  <div class="metrics-grid">
    <div class="metric-card metric-card-purple">
      <div class="metric-header">
        <span class="metric-label">Total Empleados</span>
        <div class="metric-icon-box metric-icon-purple">👥</div>
      </div>
      <div class="metric-value-row">
        <span class="metric-value" id="kpi-total"><?= $total_personal ?></span>
        <span class="metric-unit">registrados</span>
      </div>
      <div class="metric-footer">
        <span>Legajos correlativos EMP-001 en adelante</span>
      </div>
    </div>

    <div class="metric-card metric-card-success">
      <div class="metric-header">
        <span class="metric-label">Cuentas Activas</span>
        <div class="metric-icon-box metric-icon-success">✔️</div>
      </div>
      <div class="metric-value-row">
        <span class="metric-value" id="kpi-activos"><?= $total_activos ?></span>
        <span class="metric-unit">habilitados</span>
      </div>
      <div class="metric-footer">
        <span>Con acceso operativo al sistema</span>
      </div>
    </div>

    <div class="metric-card metric-card-warning">
      <div class="metric-header">
        <span class="metric-label">Vendedores Internos</span>
        <div class="metric-icon-box metric-icon-warning">💼</div>
      </div>
      <div class="metric-value-row">
        <span class="metric-value" id="kpi-vendedores"><?= $total_vendedores ?></span>
        <span class="metric-unit">operadores</span>
      </div>
      <div class="metric-footer">
        <span>Búsqueda y reservas estándar</span>
      </div>
    </div>

    <div class="metric-card metric-card-info">
      <div class="metric-header">
        <span class="metric-label">Supervisores Comerciales</span>
        <div class="metric-icon-box metric-icon-info">🛡️</div>
      </div>
      <div class="metric-value-row">
        <span class="metric-value" id="kpi-supervisores"><?= $total_supervisores ?></span>
        <span class="metric-unit">autorizadores</span>
      </div>
      <div class="metric-footer">
        <span>Gestión de alertas y stock crítico</span>
      </div>
    </div>
  </div>

  <!-- Barra de Herramientas: Búsqueda y Filtros en Tiempo Real -->
  <div class="usuarios-toolbar">
    <div class="toolbar-filters">
      <div class="search-input-wrap">
        <span class="search-input-icon">🔍</span>
        <input 
          type="text" 
          id="filtro-busqueda" 
          class="form-input" 
          placeholder="Buscar por código, nombre o usuario..." 
          autocomplete="off"
        >
      </div>

      <select id="filtro-rol" class="toolbar-select">
        <option value="">Todos los Roles</option>
        <option value="VENDEDOR_INTERNO">Vendedores Internos</option>
        <option value="SUPERVISOR_COMERCIAL">Supervisores Comerciales</option>
        <option value="ADMIN">Administradores</option>
      </select>

      <select id="filtro-estado" class="toolbar-select">
        <option value="">Todos los Estados</option>
        <option value="1">Solo Activos</option>
        <option value="0">Solo Inactivos</option>
      </select>
    </div>

    <div style="font-size: 0.85rem; color: var(--text-muted);">
      Mostrando <strong id="conteo-filtrados"><?= count($usuarios) ?></strong> de <?= count($usuarios) ?> empleados
    </div>
  </div>

  <!-- Tabla de Personal -->
  <div class="table-card">
    <div class="table-responsive">
      <table class="data-table" id="tabla-usuarios">
        <thead>
          <tr>
            <th>Legajo</th>
            <th>Nombre y Usuario</th>
            <th>Rol Jerárquico</th>
            <th>Estado</th>
            <th>Fecha de Alta</th>
            <th style="text-align: right;">Acciones</th>
          </tr>
        </thead>
        <tbody id="tbody-usuarios">
          <?php foreach ($usuarios as $usr): 
            $es_propio = ((int)$usr['id_empleado'] === $current_admin_id);
            $es_activo = ((int)$usr['activo'] === 1);
            $debe_cambiar = ((int)($usr['debe_cambiar_password'] ?? 0) === 1);
          ?>
            <tr 
              data-id="<?= (int)$usr['id_empleado'] ?>"
              data-codigo="<?= h($usr['codigo_empleado']) ?>"
              data-nombre="<?= h($usr['nombre']) ?>"
              data-usuario="<?= h($usr['usuario']) ?>"
              data-rol="<?= h($usr['rol']) ?>"
              data-activo="<?= $es_activo ? '1' : '0' ?>"
              data-debe-cambiar="<?= $debe_cambiar ? '1' : '0' ?>"
            >
              <td>
                <span class="emp-code-badge">
                  <span>#</span>
                  <span><?= h($usr['codigo_empleado']) ?></span>
                </span>
                <?php if ($es_propio): ?>
                  <span class="badge-you" title="Sesión activa actual">Tú</span>
                <?php endif; ?>
              </td>

              <td>
                <div class="user-cell-meta">
                  <span class="user-cell-name"><?= h($usr['nombre']) ?></span>
                  <span class="user-cell-username">@<?= h($usr['usuario']) ?></span>
                </div>
              </td>

              <td>
                <span class="badge <?= rol_badge_class($usr['rol']) ?>">
                  <span class="badge-dot"></span>
                  <span><?= rol_label($usr['rol']) ?></span>
                </span>
              </td>

              <td>
                <?php if ($es_activo): ?>
                  <span class="badge-status badge-status-active">
                    <span class="status-dot"></span>
                    <span>Activo</span>
                  </span>
                <?php else: ?>
                  <span class="badge-status badge-status-inactive">
                    <span class="status-dot"></span>
                    <span>Inactivo</span>
                  </span>
                <?php endif; ?>
                <?php if ($debe_cambiar): ?>
                  <div style="margin-top: 0.35rem;">
                    <span class="badge badge-warning" style="font-size: 0.7rem; padding: 0.15rem 0.5rem;" title="El empleado tiene pendiente el cambio de contraseña obligatorio">
                      <span>⚠️ Clave pendiente</span>
                    </span>
                  </div>
                <?php endif; ?>
              </td>

              <td style="color: var(--text-muted); font-size: 0.8rem;">
                <?= h($usr['creado_en']) ?>
              </td>

              <td style="text-align: right;">
                <div class="table-actions" style="justify-content: flex-end;">
                  <!-- Botón Editar -->
                  <button 
                    type="button" 
                    class="btn-action btn-action-edit btn-trigger-edit" 
                    title="Editar información del empleado"
                  >
                    <span>✏️</span>
                    <span>Editar</span>
                  </button>

                  <!-- Botón Reset Clave -->
                  <button 
                    type="button" 
                    class="btn-action btn-action-key btn-trigger-reset" 
                    title="Resetear contraseña y desbloquear"
                  >
                    <span>🔑</span>
                    <span>Clave</span>
                  </button>

                  <!-- Botón Toggle Activar/Desactivar -->
                  <?php if ($es_propio): ?>
                    <button 
                      type="button" 
                      class="btn-action" 
                      disabled 
                      title="No puedes desactivar tu propia cuenta administradora"
                    >
                      <span>🔒</span>
                    </button>
                  <?php elseif ($es_activo): ?>
                    <button 
                      type="button" 
                      class="btn-action btn-action-danger btn-trigger-toggle" 
                      data-target-activo="0" 
                      title="Desactivar cuenta del empleado"
                    >
                      <span>⛔</span>
                      <span>Desactivar</span>
                    </button>
                  <?php else: ?>
                    <button 
                      type="button" 
                      class="btn-action btn-action-success btn-trigger-toggle" 
                      data-target-activo="1" 
                      title="Reactivar cuenta del empleado"
                    >
                      <span>✅</span>
                      <span>Activar</span>
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Mensaje sin resultados de filtro -->
    <div id="sin-resultados" style="display: none; padding: 2.5rem; text-align: center; color: var(--text-muted);">
      <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔍</div>
      <p style="font-weight: 600;">No se encontraron empleados con los criterios de búsqueda seleccionados.</p>
    </div>
</main>

<!-- ============================================================
     ESTILOS DIRECTOS DE RESPALDO PARA MODALES (Inmunidad a caché)
     ============================================================ -->
<style>
.custom-modal-backdrop {
  display: none;
  position: fixed !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  width: 100vw !important;
  height: 100vh !important;
  background-color: rgba(15, 23, 42, 0.72) !important;
  backdrop-filter: blur(4px) !important;
  -webkit-backdrop-filter: blur(4px) !important;
  z-index: 99999 !important;
  align-items: center !important;
  justify-content: center !important;
  padding: 1.5rem !important;
  overflow-y: auto !important;
}
.custom-modal-backdrop.is-open {
  display: flex !important;
}
.custom-modal {
  background: #ffffff !important;
  border: 1px solid #e2e8f0 !important;
  border-radius: 16px !important;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35) !important;
  width: 100% !important;
  max-width: 520px !important;
  position: relative !important;
  z-index: 100000 !important;
  margin: auto !important;
}
</style>

<!-- ============================================================
     MODALES PROPIOS (Sin confirm(), prompt() ni alert() nativos)
     ============================================================ -->

<!-- 1. MODAL: ALTA DE NUEVO EMPLEADO -->
<div class="custom-modal-backdrop" id="modal-alta" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="modal-alta-title">
  <div class="custom-modal">
    <div class="custom-modal-header">
      <h2 class="custom-modal-title" id="modal-alta-title">
        <span>➕</span>
        <span>Registrar Nuevo Empleado</span>
      </h2>
      <button type="button" class="custom-modal-close" data-close-modal="modal-alta" aria-label="Cerrar modal">&times;</button>
    </div>

    <form id="form-alta-usuario" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

      <div class="custom-modal-body">
        <div class="form-group">
          <label for="alta-nombre" class="form-label">Nombre Completo *</label>
          <input 
            type="text" 
            id="alta-nombre" 
            name="nombre" 
            class="form-input" 
            placeholder="Ej: Marcelo Fernández" 
            required 
            minlength="3" 
            maxlength="100"
          >
          <span class="form-helper-text">Nombre y apellido oficial del colaborador.</span>
        </div>

        <div class="form-group">
          <label for="alta-usuario" class="form-label">Nombre de Usuario *</label>
          <input 
            type="text" 
            id="alta-usuario" 
            name="usuario" 
            class="form-input" 
            placeholder="Ej: mfernandez" 
            required 
            pattern="[a-zA-Z0-9._-]{3,30}"
            title="Entre 3 y 30 caracteres alfanuméricos"
          >
          <span class="form-helper-text">Identificador único para inicio de sesión (letras minúsculas, números, puntos y guiones).</span>
        </div>

        <div class="form-group">
          <label for="alta-rol" class="form-label">Rol Asignado *</label>
          <select id="alta-rol" name="rol" class="toolbar-select" style="width: 100%;" required>
            <option value="VENDEDOR_INTERNO">Vendedor Interno (Ventas y Búsqueda de Stock)</option>
            <option value="SUPERVISOR_COMERCIAL">Supervisor Comercial (Autorización y Alertas)</option>
          </select>
          <span class="form-helper-text">Por política de seguridad (decisiones.md secc. 15), las cuentas con rol ADMIN se configuran solo a nivel de sistema.</span>
        </div>

        <div class="form-group">
          <label for="alta-password" class="form-label">Contraseña Inicial *</label>
          <input 
            type="password" 
            id="alta-password" 
            name="password" 
            class="form-input" 
            placeholder="Mínimo 8 caracteres" 
            required 
            minlength="8"
          >
          <span class="form-helper-text">Se encriptará mediante algoritmo BCrypt (password_hash).</span>
        </div>
      </div>

      <div class="custom-modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="modal-alta">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btn-submit-alta">
          <span>Registrar Empleado</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- 2. MODAL: EDITAR EMPLEADO -->
<div class="custom-modal-backdrop" id="modal-editar" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="modal-editar-title">
  <div class="custom-modal">
    <div class="custom-modal-header">
      <h2 class="custom-modal-title" id="modal-editar-title">
        <span>✏️</span>
        <span>Editar Datos del Empleado</span>
      </h2>
      <button type="button" class="custom-modal-close" data-close-modal="modal-editar" aria-label="Cerrar modal">&times;</button>
    </div>

    <form id="form-editar-usuario" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
      <input type="hidden" id="edit-id" name="id_empleado" value="">

      <div class="custom-modal-body">
        <div style="background: var(--bg-muted); padding: 0.75rem 1rem; border-radius: var(--radius-sm); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">LEGAJO</span>
            <strong id="edit-codigo-display" style="font-family: monospace;">EMP-000</strong>
          </div>
          <div>
            <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">USUARIO</span>
            <span id="edit-usuario-display" style="font-weight: 700;">@usuario</span>
          </div>
        </div>

        <div class="form-group">
          <label for="edit-nombre" class="form-label">Nombre Completo *</label>
          <input 
            type="text" 
            id="edit-nombre" 
            name="nombre" 
            class="form-input" 
            required 
            minlength="3" 
            maxlength="100"
          >
        </div>

        <div class="form-group">
          <label for="edit-rol" class="form-label">Rol Jerárquico *</label>
          <select id="edit-rol" name="rol" class="toolbar-select" style="width: 100%;" required>
            <option value="VENDEDOR_INTERNO">Vendedor Interno</option>
            <option value="SUPERVISOR_COMERCIAL">Supervisor Comercial</option>
            <option value="ADMIN" id="edit-opt-admin">Administrador General</option>
          </select>
          <span class="form-helper-text" id="edit-rol-helper"></span>
        </div>

        <div class="form-group">
          <label for="edit-activo" class="form-label">Estado de la Cuenta *</label>
          <select id="edit-activo" name="activo" class="toolbar-select" style="width: 100%;" required>
            <option value="1">Activo (Habilitado)</option>
            <option value="0">Inactivo (Desactivado)</option>
          </select>
          <span class="form-helper-text" id="edit-activo-helper">Si se desactiva, su sesión será invalidada en su próxima petición.</span>
        </div>

        <div id="edit-self-warning" class="modal-alert modal-alert-info" style="display: none;">
          <span>ℹ️</span>
          <span>Estás editando tu propia cuenta administradora. No puedes modificar tu rol ni desactivar tu propio acceso.</span>
        </div>
      </div>

      <div class="custom-modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="modal-editar">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btn-submit-editar">
          <span>Guardar Cambios</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- 3. MODAL: CONFIRMAR ACTIVAR / DESACTIVAR -->
<div class="custom-modal-backdrop" id="modal-toggle-estado" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="modal-toggle-title">
  <div class="custom-modal" style="max-width: 460px;">
    <div class="custom-modal-header">
      <h2 class="custom-modal-title" id="modal-toggle-title">
        <span id="toggle-header-icon">⛔</span>
        <span id="toggle-header-text">Desactivar Cuenta</span>
      </h2>
      <button type="button" class="custom-modal-close" data-close-modal="modal-toggle-estado" aria-label="Cerrar modal">&times;</button>
    </div>

    <form id="form-toggle-estado">
      <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
      <input type="hidden" id="toggle-id" name="id_empleado" value="">
      <input type="hidden" id="toggle-activo" name="activo" value="0">

      <div class="custom-modal-body">
        <div id="toggle-alert-box" class="modal-alert modal-alert-danger">
          <span id="toggle-icon-desc">⚠️</span>
          <div id="toggle-desc-text"></div>
        </div>
        <p style="font-size: 0.85rem; color: var(--text-muted);">
          Nota: Los usuarios nunca se eliminan físicamente de la base de datos para preservar el historial de reservas y auditorías (decisiones.md secc. 15).
        </p>
      </div>

      <div class="custom-modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="modal-toggle-estado">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btn-submit-toggle">
          <span id="toggle-btn-text">Confirmar</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- 4. MODAL: RESETEAR CONTRASEÑA -->
<div class="custom-modal-backdrop" id="modal-reset-clave" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="modal-reset-title">
  <div class="custom-modal" style="max-width: 480px;">
    <div class="custom-modal-header">
      <h2 class="custom-modal-title" id="modal-reset-title">
        <span>🔑</span>
        <span>Resetear Contraseña de Empleado</span>
      </h2>
      <button type="button" class="custom-modal-close" data-close-modal="modal-reset-clave" aria-label="Cerrar modal">&times;</button>
    </div>

    <form id="form-reset-clave" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
      <input type="hidden" id="reset-id" name="id_empleado" value="">

      <div class="custom-modal-body">
        <div style="background: var(--bg-muted); padding: 0.75rem 1rem; border-radius: var(--radius-sm);">
          <div style="font-size: 0.85rem;">
            Colaborador: <strong id="reset-nombre-display">Nombre</strong> (<span id="reset-codigo-display" style="font-family: monospace;">EMP-000</span>)
          </div>
          <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
            Usuario de acceso: <span id="reset-usuario-display" style="font-weight: 600;">@usuario</span>
          </div>
        </div>

        <div class="form-group">
          <label for="reset-nueva-password" class="form-label">Nueva Contraseña *</label>
          <input 
            type="password" 
            id="reset-nueva-password" 
            name="nueva_password" 
            class="form-input" 
            placeholder="Ingrese al menos 8 caracteres" 
            required 
            minlength="8"
          >
          <span class="form-helper-text">La nueva clave se almacenará encriptada y desbloqueará la cuenta si estaba bloqueada por intentos fallidos.</span>
        </div>

        <div class="modal-alert modal-alert-info">
          <span>🛡️</span>
          <span>El cambio se auditará en <code>log_acciones</code> sin registrar nunca la contraseña en texto plano.</span>
        </div>
      </div>

      <div class="custom-modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="modal-reset-clave">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btn-submit-reset">
          <span>Establecer Contraseña</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Contenedor Flotante de Notificaciones Toast -->
<div id="toast-container" class="toast-container" aria-live="polite"></div>

<!-- ============================================================
     JAVASCRIPT: Control Dinámico de Usuarios, Modales y Filtros
     ============================================================ -->
<script>
(function() {
  'use strict';

  const CURRENT_ADMIN_ID = <?= $current_admin_id ?>;
  const CSRF_TOKEN = "<?= h($csrf_token) ?>";

  // ------------------------------------------------------------
  // Utilidad: Notificaciones Toast Flotantes
  // ------------------------------------------------------------
  function showToast(mensaje, tipo = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast toast-${tipo}`;
    
    const icon = document.createElement('span');
    icon.textContent = (tipo === 'success') ? '✅' : '⚠️';
    
    const text = document.createElement('span');
    text.textContent = mensaje;

    toast.appendChild(icon);
    toast.appendChild(text);
    container.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('toast-hiding');
      setTimeout(() => toast.remove(), 250);
    }, 4000);
  }

  // ------------------------------------------------------------
  // Utilidad: Control de Modales Accesibles (Escape / Clic fuera)
  // ------------------------------------------------------------
  function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.style.display = 'flex';
    void modal.offsetWidth; // Reflujo para animación suave
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    const firstInput = modal.querySelector('input:not([type="hidden"]), select');
    if (firstInput) {
      setTimeout(() => firstInput.focus(), 60);
    }
  }

  function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }

  // Cerrar modales con botones de cierre
  document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modalId = btn.getAttribute('data-close-modal');
      closeModal(modalId);
    });
  });

  // Cerrar con clic en el backdrop
  document.querySelectorAll('.custom-modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        closeModal(backdrop.id);
      }
    });
  });

  // Cerrar con tecla Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.custom-modal-backdrop.is-open').forEach(m => closeModal(m.id));
    }
  });

  // ------------------------------------------------------------
  // 1. Abrir Modal de Alta
  // ------------------------------------------------------------
  const btnAbrirAlta = document.getElementById('btn-abrir-alta');
  if (btnAbrirAlta) {
    btnAbrirAlta.addEventListener('click', () => {
      const form = document.getElementById('form-alta-usuario');
      if (form) form.reset();
      openModal('modal-alta');
    });
  }

  // ------------------------------------------------------------
  // 2. Submit Alta de Usuario
  // ------------------------------------------------------------
  const formAlta = document.getElementById('form-alta-usuario');
  if (formAlta) {
    formAlta.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('btn-submit-alta');
      btn.disabled = true;
      btn.textContent = 'Registrando...';

      const formData = new FormData(formAlta);
      formData.append('accion', 'crear_usuario');

      try {
        const res = await fetch('api.php', {
          method: 'POST',
          headers: { 'X-CSRF-Token': CSRF_TOKEN },
          body: formData
        });

        const data = await res.json();
        if (data.ok) {
          showToast(data.mensaje, 'success');
          closeModal('modal-alta');
          formAlta.reset();
          recargarListaUsuarios();
        } else {
          showToast(data.error || 'No se pudo crear el usuario.', 'error');
        }
      } catch (err) {
        showToast('Error de conexión con el servidor.', 'error');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Registrar Empleado';
      }
    });
  }

  // ------------------------------------------------------------
  // 3. Abrir Modal de Edición
  // ------------------------------------------------------------
  function attachRowEvents() {
    // Editar
    document.querySelectorAll('.btn-trigger-edit').forEach(btn => {
      btn.onclick = () => {
        const row = btn.closest('tr');
        const id = parseInt(row.dataset.id, 10);
        const codigo = row.dataset.codigo;
        const nombre = row.dataset.nombre;
        const usuario = row.dataset.usuario;
        const rol = row.dataset.rol;
        const activo = row.dataset.activo;

        document.getElementById('edit-id').value = id;
        document.getElementById('edit-codigo-display').textContent = codigo;
        document.getElementById('edit-usuario-display').textContent = '@' + usuario;
        document.getElementById('edit-nombre').value = nombre;
        document.getElementById('edit-rol').value = rol;
        document.getElementById('edit-activo').value = activo;

        const isSelf = (id === CURRENT_ADMIN_ID);
        const selfWarning = document.getElementById('edit-self-warning');
        const rolSelect = document.getElementById('edit-rol');
        const activoSelect = document.getElementById('edit-activo');

        if (isSelf) {
          selfWarning.style.display = 'flex';
          rolSelect.disabled = true;
          activoSelect.disabled = true;
        } else {
          selfWarning.style.display = 'none';
          rolSelect.disabled = false;
          activoSelect.disabled = false;
          // Si no es admin, no permitir elevar a admin
          const optAdmin = document.getElementById('edit-opt-admin');
          if (optAdmin) {
            optAdmin.style.display = (rol === 'ADMIN') ? '' : 'none';
          }
        }

        openModal('modal-editar');
      };
    });

    // Resetear Clave
    document.querySelectorAll('.btn-trigger-reset').forEach(btn => {
      btn.onclick = () => {
        const row = btn.closest('tr');
        const id = parseInt(row.dataset.id, 10);
        const codigo = row.dataset.codigo;
        const nombre = row.dataset.nombre;
        const usuario = row.dataset.usuario;

        document.getElementById('reset-id').value = id;
        document.getElementById('reset-codigo-display').textContent = codigo;
        document.getElementById('reset-nombre-display').textContent = nombre;
        document.getElementById('reset-usuario-display').textContent = '@' + usuario;
        document.getElementById('reset-nueva-password').value = '';

        openModal('modal-reset-clave');
      };
    });

    // Activar / Desactivar (Toggle)
    document.querySelectorAll('.btn-trigger-toggle').forEach(btn => {
      btn.onclick = () => {
        const row = btn.closest('tr');
        const id = parseInt(row.dataset.id, 10);
        const codigo = row.dataset.codigo;
        const nombre = row.dataset.nombre;
        const nuevoActivo = parseInt(btn.dataset.targetActivo, 10);

        document.getElementById('toggle-id').value = id;
        document.getElementById('toggle-activo').value = nuevoActivo;

        const title = document.getElementById('modal-toggle-title');
        const headerIcon = document.getElementById('toggle-header-icon');
        const headerText = document.getElementById('toggle-header-text');
        const descBox = document.getElementById('toggle-alert-box');
        const descText = document.getElementById('toggle-desc-text');
        const submitBtn = document.getElementById('btn-submit-toggle');
        const submitBtnText = document.getElementById('toggle-btn-text');

        if (nuevoActivo === 0) {
          headerIcon.textContent = '⛔';
          headerText.textContent = 'Desactivar Cuenta';
          descBox.className = 'modal-alert modal-alert-danger';
          descText.innerHTML = `¿Está seguro de desactivar a <strong>${nombre}</strong> (<code>${codigo}</code>)?<br><br>Su sesión será <strong>invalidada inmediatamente</strong> en la próxima petición. El empleado no podrá iniciar sesión hasta ser reactivado.`;
          submitBtn.className = 'btn btn-primary btn-action-danger';
          submitBtnText.textContent = 'Sí, Desactivar Cuenta';
        } else {
          headerIcon.textContent = '✅';
          headerText.textContent = 'Reactivar Cuenta';
          descBox.className = 'modal-alert modal-alert-info';
          descText.innerHTML = `¿Confirma la reactivación de la cuenta de <strong>${nombre}</strong> (<code>${codigo}</code>)? Podrá volver a operar con su usuario y contraseña.`;
          submitBtn.className = 'btn btn-primary';
          submitBtnText.textContent = 'Sí, Reactivar Cuenta';
        }

        openModal('modal-toggle-estado');
      };
    });
  }

  attachRowEvents();

  // ------------------------------------------------------------
  // 4. Submit Edición de Usuario
  // ------------------------------------------------------------
  const formEditar = document.getElementById('form-editar-usuario');
  if (formEditar) {
    formEditar.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('btn-submit-editar');
      btn.disabled = true;
      btn.textContent = 'Guardando...';

      const formData = new FormData(formEditar);
      formData.append('accion', 'editar_usuario');

      // Si estaban deshabilitados por ser cuenta propia, incluir valores originales
      const id = parseInt(document.getElementById('edit-id').value, 10);
      if (id === CURRENT_ADMIN_ID) {
        formData.set('rol', 'ADMIN');
        formData.set('activo', '1');
      }

      try {
        const res = await fetch('api.php', {
          method: 'POST',
          headers: { 'X-CSRF-Token': CSRF_TOKEN },
          body: formData
        });

        const data = await res.json();
        if (data.ok) {
          showToast(data.mensaje, 'success');
          closeModal('modal-editar');
          recargarListaUsuarios();
        } else {
          showToast(data.error || 'No se pudo actualizar.', 'error');
        }
      } catch (err) {
        showToast('Error de conexión con el servidor.', 'error');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar Cambios';
      }
    });
  }

  // ------------------------------------------------------------
  // 5. Submit Activar / Desactivar
  // ------------------------------------------------------------
  const formToggle = document.getElementById('form-toggle-estado');
  if (formToggle) {
    formToggle.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('btn-submit-toggle');
      btn.disabled = true;

      const formData = new FormData(formToggle);
      formData.append('accion', 'toggle_activo');

      try {
        const res = await fetch('api.php', {
          method: 'POST',
          headers: { 'X-CSRF-Token': CSRF_TOKEN },
          body: formData
        });

        const data = await res.json();
        if (data.ok) {
          showToast(data.mensaje, 'success');
          closeModal('modal-toggle-estado');
          recargarListaUsuarios();
        } else {
          showToast(data.error || 'No se pudo cambiar el estado.', 'error');
        }
      } catch (err) {
        showToast('Error de conexión con el servidor.', 'error');
      } finally {
        btn.disabled = false;
      }
    });
  }

  // ------------------------------------------------------------
  // 6. Submit Reset de Contraseña
  // ------------------------------------------------------------
  const formReset = document.getElementById('form-reset-clave');
  if (formReset) {
    formReset.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('btn-submit-reset');
      btn.disabled = true;
      btn.textContent = 'Actualizando...';

      const formData = new FormData(formReset);
      formData.append('accion', 'reset_password');

      try {
        const res = await fetch('api.php', {
          method: 'POST',
          headers: { 'X-CSRF-Token': CSRF_TOKEN },
          body: formData
        });

        const data = await res.json();
        if (data.ok) {
          showToast(data.mensaje, 'success');
          closeModal('modal-reset-clave');
          formReset.reset();
        } else {
          showToast(data.error || 'No se pudo resetear la clave.', 'error');
        }
      } catch (err) {
        showToast('Error de conexión con el servidor.', 'error');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Establecer Contraseña';
      }
    });
  }

  // ------------------------------------------------------------
  // 7. Filtros Reactivos en Tiempo Real (Buscador, Rol, Estado)
  // ------------------------------------------------------------
  const inputBusqueda = document.getElementById('filtro-busqueda');
  const selectRol = document.getElementById('filtro-rol');
  const selectEstado = document.getElementById('filtro-estado');
  const conteoFiltrados = document.getElementById('conteo-filtrados');
  const sinResultados = document.getElementById('sin-resultados');

  function aplicarFiltros() {
    const query = inputBusqueda.value.trim().toLowerCase();
    const filtroRolVal = selectRol.value;
    const filtroEstadoVal = selectEstado.value;

    const rows = document.querySelectorAll('#tbody-usuarios tr');
    let visibles = 0;

    rows.forEach(row => {
      const codigo = (row.dataset.codigo || '').toLowerCase();
      const nombre = (row.dataset.nombre || '').toLowerCase();
      const usuario = (row.dataset.usuario || '').toLowerCase();
      const rol = row.dataset.rol || '';
      const activo = row.dataset.activo || '';

      const matchTexto = (!query || codigo.includes(query) || nombre.includes(query) || usuario.includes(query));
      const matchRol = (!filtroRolVal || rol === filtroRolVal);
      const matchEstado = (filtroEstadoVal === '' || activo === filtroEstadoVal);

      if (matchTexto && matchRol && matchEstado) {
        row.style.display = '';
        visibles++;
      } else {
        row.style.display = 'none';
      }
    });

    if (conteoFiltrados) conteoFiltrados.textContent = visibles;
    if (sinResultados) {
      sinResultados.style.display = (visibles === 0) ? 'block' : 'none';
    }
  }

  if (inputBusqueda) inputBusqueda.addEventListener('input', aplicarFiltros);
  if (selectRol) selectRol.addEventListener('change', aplicarFiltros);
  if (selectEstado) selectEstado.addEventListener('change', aplicarFiltros);

  // ------------------------------------------------------------
  // 8. Recarga Dinámica de la Tabla vía api.php?accion=listar_usuarios
  // ------------------------------------------------------------
  async function recargarListaUsuarios() {
    try {
      const res = await fetch('api.php?accion=listar_usuarios');
      const data = await res.json();
      if (!data.ok || !Array.isArray(data.usuarios)) return;

      const tbody = document.getElementById('tbody-usuarios');
      tbody.innerHTML = '';

      let kpiTotal = data.usuarios.length;
      let kpiActivos = 0;
      let kpiVendedores = 0;
      let kpiSupervisores = 0;

      data.usuarios.forEach(u => {
        const isSelf = (parseInt(u.id_empleado, 10) === CURRENT_ADMIN_ID);
        const isActivo = (parseInt(u.activo, 10) === 1);

        if (isActivo) kpiActivos++;
        if (u.rol === 'VENDEDOR_INTERNO') kpiVendedores++;
        if (u.rol === 'SUPERVISOR_COMERCIAL') kpiSupervisores++;

        const tr = document.createElement('tr');
        tr.dataset.id = u.id_empleado;
        tr.dataset.codigo = u.codigo_empleado;
        tr.dataset.nombre = u.nombre;
        tr.dataset.usuario = u.usuario;
        tr.dataset.rol = u.rol;
        tr.dataset.activo = isActivo ? '1' : '0';

        // Rol badge class & label
        let rolClass = 'badge-vendedor';
        let rolLabel = 'Vendedor Interno';
        if (u.rol === 'ADMIN') {
          rolClass = 'badge-admin';
          rolLabel = 'Administrador General';
        } else if (u.rol === 'SUPERVISOR_COMERCIAL') {
          rolClass = 'badge-supervisor';
          rolLabel = 'Supervisor Comercial';
        }

        // Action buttons
        let toggleBtnHtml = '';
        if (isSelf) {
          toggleBtnHtml = `<button type="button" class="btn-action" disabled title="No puedes desactivar tu propia cuenta"><span>🔒</span></button>`;
        } else if (isActivo) {
          toggleBtnHtml = `<button type="button" class="btn-action btn-action-danger btn-trigger-toggle" data-target-activo="0" title="Desactivar cuenta"><span>⛔</span><span>Desactivar</span></button>`;
        } else {
          toggleBtnHtml = `<button type="button" class="btn-action btn-action-success btn-trigger-toggle" data-target-activo="1" title="Reactivar cuenta"><span>✅</span><span>Activar</span></button>`;
        }

        // Construir celdas de forma segura
        const tdCodigo = document.createElement('td');
        const codeBadge = document.createElement('span');
        codeBadge.className = 'emp-code-badge';
        codeBadge.innerHTML = `<span>#</span><span>${escapeHtml(u.codigo_empleado)}</span>`;
        tdCodigo.appendChild(codeBadge);
        if (isSelf) {
          const youBadge = document.createElement('span');
          youBadge.className = 'badge-you';
          youBadge.textContent = 'Tú';
          tdCodigo.appendChild(youBadge);
        }

        const tdNombre = document.createElement('td');
        tdNombre.innerHTML = `
          <div class="user-cell-meta">
            <span class="user-cell-name">${escapeHtml(u.nombre)}</span>
            <span class="user-cell-username">@${escapeHtml(u.usuario)}</span>
          </div>
        `;

        const tdRol = document.createElement('td');
        tdRol.innerHTML = `
          <span class="badge ${rolClass}">
            <span class="badge-dot"></span>
            <span>${rolLabel}</span>
          </span>
        `;

        const tdEstado = document.createElement('td');
        const debeCambiar = (parseInt(u.debe_cambiar_password, 10) === 1);
        let estadoHtml = isActivo
          ? `<span class="badge-status badge-status-active"><span class="status-dot"></span><span>Activo</span></span>`
          : `<span class="badge-status badge-status-inactive"><span class="status-dot"></span><span>Inactivo</span></span>`;
        if (debeCambiar) {
          estadoHtml += `<div style="margin-top: 0.35rem;"><span class="badge badge-warning" style="font-size: 0.7rem; padding: 0.15rem 0.5rem;" title="El empleado tiene pendiente el cambio de contraseña obligatorio"><span>⚠️ Clave pendiente</span></span></div>`;
        }
        tdEstado.innerHTML = estadoHtml;

        const tdFecha = document.createElement('td');
        tdFecha.style.color = 'var(--text-muted)';
        tdFecha.style.fontSize = '0.8rem';
        tdFecha.textContent = u.creado_en;

        const tdAcciones = document.createElement('td');
        tdAcciones.style.textAlign = 'right';
        tdAcciones.innerHTML = `
          <div class="table-actions" style="justify-content: flex-end;">
            <button type="button" class="btn-action btn-action-edit btn-trigger-edit" title="Editar empleado">
              <span>✏️</span><span>Editar</span>
            </button>
            <button type="button" class="btn-action btn-action-key btn-trigger-reset" title="Resetear contraseña">
              <span>🔑</span><span>Clave</span>
            </button>
            ${toggleBtnHtml}
          </div>
        `;

        tr.appendChild(tdCodigo);
        tr.appendChild(tdNombre);
        tr.appendChild(tdRol);
        tr.appendChild(tdEstado);
        tr.appendChild(tdFecha);
        tr.appendChild(tdAcciones);

        tbody.appendChild(tr);
      });

      // Actualizar contadores KPI
      const elKpiTotal = document.getElementById('kpi-total');
      const elKpiActivos = document.getElementById('kpi-activos');
      const elKpiVendedores = document.getElementById('kpi-vendedores');
      const elKpiSupervisores = document.getElementById('kpi-supervisores');
      if (elKpiTotal) elKpiTotal.textContent = kpiTotal;
      if (elKpiActivos) elKpiActivos.textContent = kpiActivos;
      if (elKpiVendedores) elKpiVendedores.textContent = kpiVendedores;
      if (elKpiSupervisores) elKpiSupervisores.textContent = kpiSupervisores;

      attachRowEvents();
      aplicarFiltros();
    } catch (err) {
      console.error('Error recargando usuarios:', err);
    }
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
