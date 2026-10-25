<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Plantilla de Cabecera y Navegación Dinámica por Rol (Fase 2)
 */

require_once __DIR__ . '/auth.php';

// Verificación obligatoria de autenticación
requerir_login();

$u = usuario_actual();
$csrf_token = generar_csrf();
$current_page = basename($_SERVER['PHP_SELF'] ?? 'index.php');

// Etiqueta y estilo del badge según el rol del usuario
$rol_badge_class = rol_badge_class();
$rol_label = rol_label();

/**
 * Definición de ítems del menú de navegación según el rol
 * Los enlaces a pantallas de fases futuras llevan a sus respectivas páginas con layout correcto.
 */
$nav_items = [];

// Acceso al Dashboard principal
$nav_items[] = [
    'id'    => 'nav-dashboard',
    'title' => 'Dashboard',
    'url'   => 'index.php',
    'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:block;"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>'
];

if (es_vendedor()) {
    // Vendedor: Stock · Mis Reservas
    $nav_items[] = [
        'id'    => 'nav-stock',
        'title' => 'Stock',
        'url'   => 'stock.php',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:block;"><path d="m12 3 9 5v8l-9 5-9-5V8l9-5Z"/><path d="m3 8 9 5 9-5M12 13v8M7.5 5.5l9 5"/></svg>'
    ];
    $nav_items[] = [
        'id'    => 'nav-reservas',
        'title' => 'Mis Reservas',
        'url'   => 'reservas.php',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:block;"><circle cx="12" cy="14" r="8"/><path d="M12 10v4l2 2M9 2h6M12 2v4M18 8l2-2"/></svg>'
    ];
} elseif (es_supervisor()) {
    // Supervisor: Stock · Todas las Reservas · Alertas de Reabastecimiento
    $nav_items[] = [
        'id'    => 'nav-stock',
        'title' => 'Stock',
        'url'   => 'stock.php',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:block;"><path d="m12 3 9 5v8l-9 5-9-5V8l9-5Z"/><path d="m3 8 9 5 9-5M12 13v8M7.5 5.5l9 5"/></svg>'
    ];
    $nav_items[] = [
        'id'    => 'nav-reservas',
        'title' => 'Todas las Reservas',
        'url'   => 'reservas.php',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:block;"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 12h6M9 17h4"/></svg>'
    ];
    $nav_items[] = [
        'id'    => 'nav-reabastecimiento',
        'title' => 'Alertas de Reabastecimiento',
        'url'   => 'reabastecimiento.php',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:block;"><path d="m10.3 3.9-8.1 14A2 2 0 0 0 3.9 21h16.2a2 2 0 0 0 1.7-3.1l-8.1-14a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>'
    ];
} elseif (es_admin()) {
    // Admin: lo del supervisor + Gestión de Personal + Catálogo/Inventario
    $nav_items[] = [
        'id'    => 'nav-stock',
        'title' => 'Stock',
        'url'   => 'stock.php',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:block;"><path d="m12 3 9 5v8l-9 5-9-5V8l9-5Z"/><path d="m3 8 9 5 9-5M12 13v8M7.5 5.5l9 5"/></svg>'
    ];
    $nav_items[] = [
        'id'    => 'nav-reservas',
        'title' => 'Todas las Reservas',
        'url'   => 'reservas.php',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:block;"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 12h6M9 17h4"/></svg>'
    ];
    $nav_items[] = [
        'id'    => 'nav-reabastecimiento',
        'title' => 'Alertas de Reabastecimiento',
        'url'   => 'reabastecimiento.php',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:block;"><path d="m10.3 3.9-8.1 14A2 2 0 0 0 3.9 21h16.2a2 2 0 0 0 1.7-3.1l-8.1-14a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>'
    ];
    $nav_items[] = [
        'id'    => 'nav-usuarios',
        'title' => 'Gestión de Personal',
        'url'   => 'usuarios.php',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:block;"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/></svg>'
    ];
    $nav_items[] = [
        'id'    => 'nav-catalogo',
        'title' => 'Catálogo/Inventario',
        'url'   => 'catalogo.php',
        'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:block;"><path d="M3 7V5a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v2M3 7h5l2 2h11l-2 12H3L1 7h2Z"/></svg>'
    ];
}

$es_password_obligatorio = debe_cambiar_password_actual();
$page_title = $page_title ?? 'Control de Stock · Innovatrónica S.A.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($page_title) ?></title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>

  <!-- Cabecera Superior -->
  <header class="app-header">
    <div class="app-header-top">
      <a href="index.php" class="app-brand" id="brand-link">
        <div class="app-brand-badge">IN</div>
        <div>
          <div class="app-brand-name">Innovatrónica S.A.</div>
          <div class="app-brand-sub">Control de Stock · Ventas Internas</div>
        </div>
      </a>

      <!-- Panel de Usuario y Menú Desplegable de Cuenta (ui-ux-innovatronica) -->
      <div class="app-user-panel">
        <?php if ($es_password_obligatorio): ?>
          <div class="app-user-info">
            <strong><?= h($u['nombre']) ?></strong>
            <div class="app-user-meta">
              <span class="app-user-id" title="Legajo del empleado"><?= h($u['codigo_empleado']) ?></span>
              <span class="badge badge-warning" id="current-user-role-badge">
                <span class="badge-dot"></span>
                Cambio obligatorio
              </span>
            </div>
          </div>
          <form method="POST" action="logout.php" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <button type="submit" class="btn btn-outline btn-sm user-dropdown-item-danger" id="btn-logout" title="Cerrar sesión actual">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              <span>Cerrar Sesión</span>
            </button>
          </form>
        <?php else: ?>
          <div class="user-dropdown-wrapper" id="userDropdownWrapper">
            <button type="button" class="user-menu-trigger" id="userMenuTrigger" aria-expanded="false" aria-haspopup="true" aria-label="Menú de usuario">
              <div class="user-avatar-initials">
                <?= h(mb_strtoupper(mb_substr($u['nombre'], 0, 1))) ?>
              </div>
              <div class="app-user-info">
                <div style="display: flex; align-items: center; gap: 0.35rem;">
                  <strong><?= h($u['nombre']) ?></strong>
                  <svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <div class="app-user-meta">
                  <span class="app-user-id" title="Legajo del empleado"><?= h($u['codigo_empleado']) ?></span>
                  <span class="badge <?= $rol_badge_class ?>" id="current-user-role-badge">
                    <span class="badge-dot"></span>
                    <?= h($rol_label) ?>
                  </span>
                </div>
              </div>
            </button>

            <!-- Menú Desplegable -->
            <div class="user-dropdown-menu" id="userDropdownMenu" role="menu" aria-label="Opciones de cuenta">
              <div class="user-dropdown-header">
                <div class="user-dropdown-header-name"><?= h($u['nombre']) ?></div>
                <div class="user-dropdown-header-sub">
                  <span style="font-family: monospace;"><?= h($u['codigo_empleado']) ?></span> · <?= h($rol_label) ?>
                </div>
              </div>

              <div class="user-dropdown-divider"></div>

              <a href="cambiar_password.php" class="user-dropdown-item <?= ($current_page === 'cambiar_password.php') ? 'active' : '' ?>" role="menuitem" id="link-cambiar-password">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <circle cx="7.5" cy="15.5" r="4.5"/>
                  <path d="m21 2-9.6 9.6"/>
                  <path d="m15.5 7.5 3 3L21 8l-2-2"/>
                </svg>
                <span>Cambiar Contraseña</span>
              </a>

              <form method="POST" action="logout.php" class="logout-form">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <button type="submit" class="user-dropdown-item user-dropdown-item-danger" role="menuitem" id="btn-logout" title="Cerrar sesión actual">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                  </svg>
                  <span>Cerrar Sesión</span>
                </button>
              </form>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!$es_password_obligatorio): ?>
          <!-- Botón Menú Móvil -->
          <button type="button" class="nav-toggle" id="navToggle" aria-controls="navMenu" aria-expanded="false" aria-label="Abrir menú de navegación">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:block;"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
          </button>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$es_password_obligatorio): ?>
      <!-- Barra de Navegación por Rol -->
      <nav class="app-nav-bar" aria-label="Navegación principal">
        <ul class="app-nav-menu" id="navMenu">
          <?php foreach ($nav_items as $item): ?>
            <?php $is_active = ($current_page === $item['url']); ?>
            <li class="nav-item">
              <a href="<?= h($item['url']) ?>" 
                 class="nav-link <?= $is_active ? 'active' : '' ?>" 
                 id="<?= h($item['id']) ?>"
                 <?= $is_active ? 'aria-current="page"' : '' ?>>
                <span class="nav-link-icon"><?= $item['icon'] ?></span>
                <span><?= h($item['title']) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
    <?php else: ?>
      <!-- Banner de bloqueo por cambio de contraseña obligatorio -->
      <div class="mandatory-pwd-banner">
        <div class="mandatory-pwd-banner-inner">
          <span class="mandatory-pwd-banner-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
          </span>
          <span>Actualización obligatoria de credenciales requerida antes de acceder a las operaciones del sistema.</span>
        </div>
      </div>
    <?php endif; ?>
  </header>

  <script>
    (function() {
      const trigger = document.getElementById('userMenuTrigger');
      const dropdown = document.getElementById('userDropdownMenu');
      if (trigger && dropdown) {
        trigger.addEventListener('click', function(e) {
          e.stopPropagation();
          const isOpen = dropdown.classList.toggle('is-open');
          trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        document.addEventListener('click', function(e) {
          if (!dropdown.contains(e.target) && !trigger.contains(e.target)) {
            dropdown.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
          }
        });
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape' && dropdown.classList.contains('is-open')) {
            dropdown.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus();
          }
        });
      }
    })();
  </script>
