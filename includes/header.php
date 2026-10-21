<?php
/**
 * Innovatrónica S.A. - Sistema de Control de Stock
 * Plantilla de Cabecera y Navegación Dinámica por Rol (Fase 2)
 */

require_once __DIR__ . '/auth.php';

// Verificación obligatoria de autenticación
requerir_login();

$u = usuario_actual();
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
    'icon'  => '📊'
];

if (es_vendedor()) {
    // Vendedor: Stock · Mis Reservas
    $nav_items[] = [
        'id'    => 'nav-stock',
        'title' => 'Stock',
        'url'   => 'stock.php',
        'icon'  => '📦'
    ];
    $nav_items[] = [
        'id'    => 'nav-reservas',
        'title' => 'Mis Reservas',
        'url'   => 'reservas.php',
        'icon'  => '⏱️'
    ];
} elseif (es_supervisor()) {
    // Supervisor: Stock · Todas las Reservas · Alertas de Reabastecimiento
    $nav_items[] = [
        'id'    => 'nav-stock',
        'title' => 'Stock',
        'url'   => 'stock.php',
        'icon'  => '📦'
    ];
    $nav_items[] = [
        'id'    => 'nav-reservas',
        'title' => 'Todas las Reservas',
        'url'   => 'reservas.php',
        'icon'  => '📑'
    ];
    $nav_items[] = [
        'id'    => 'nav-reabastecimiento',
        'title' => 'Alertas de Reabastecimiento',
        'url'   => 'reabastecimiento.php',
        'icon'  => '⚠️'
    ];
} elseif (es_admin()) {
    // Admin: lo del supervisor + Gestión de Personal + Catálogo/Inventario
    $nav_items[] = [
        'id'    => 'nav-stock',
        'title' => 'Stock',
        'url'   => 'stock.php',
        'icon'  => '📦'
    ];
    $nav_items[] = [
        'id'    => 'nav-reservas',
        'title' => 'Todas las Reservas',
        'url'   => 'reservas.php',
        'icon'  => '📑'
    ];
    $nav_items[] = [
        'id'    => 'nav-reabastecimiento',
        'title' => 'Alertas de Reabastecimiento',
        'url'   => 'reabastecimiento.php',
        'icon'  => '⚠️'
    ];
    $nav_items[] = [
        'id'    => 'nav-usuarios',
        'title' => 'Gestión de Personal',
        'url'   => 'usuarios.php',
        'icon'  => '👥'
    ];
    $nav_items[] = [
        'id'    => 'nav-catalogo',
        'title' => 'Catálogo/Inventario',
        'url'   => 'catalogo.php',
        'icon'  => '🗂️'
    ];
}

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

      <!-- Panel de Usuario y Sesión -->
      <div class="app-user-panel">
        <div class="app-user-info">
          <strong><?= h($u['nombre']) ?></strong>
          <div class="app-user-meta">
            <span class="app-user-id" title="Legajo del empleado"><?= h($u['codigo_empleado']) ?></span>
            <span class="badge <?= $rol_badge_class ?>" id="current-user-role-badge">
              <span class="badge-dot"></span>
              <?= h($rol_label) ?>
            </span>
          </div>
        </div>
        <a href="logout.php" class="btn btn-outline btn-sm" id="btn-logout" title="Cerrar sesión actual">
          Cerrar Sesión
        </a>
      </div>

      <!-- Botón Menú Móvil -->
      <button class="nav-toggle" id="navToggle" aria-label="Abrir menú de navegación">
        ☰
      </button>
    </div>

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
  </header>
