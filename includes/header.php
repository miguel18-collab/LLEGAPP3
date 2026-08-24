<?php
/**
 * LLEGAPP 3 - Header común (navbar superior)
 * Variables esperadas: $pageTitle, $pageBodyClass, $pageScripts
 */
if (!defined('APP_NAME')) { require_once dirname(__DIR__) . '/config/configuracion.php'; }
require_once __DIR__ . '/auth.php';

$currentUser = usuarioActual();
$pageTitle = $pageTitle ?? APP_NAME . ' · ' . APP_TAGLINE;
$bodyClass = ($pageBodyClass ?? '') . (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? ' dark-theme' : ' light-theme');
$notifCount = 0;

if ($currentUser) {
    try {
        $notifCount = (int)dbVal('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$currentUser['id']]);
    } catch (Exception $e) { /* BD puede no estar instalada aún */ }
}

$menuPrincipal = [
    ['url' => '/pages/mapa.php',       'label' => 'Mapa',        'icon' => 'map'],
    ['url' => '/pages/rutas.php',      'label' => 'Rutas',       'icon' => 'route'],
    ['url' => '/pages/planificador.php','label' => 'Planificar', 'icon' => 'compass'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="description" content="LlegAPP 3 — Rastreo profesional de buses urbanos en tiempo real. Rutas, planificador de viajes y alertas de llegada.">
<meta name="theme-color" content="#00b25a">
<title><?= e($pageTitle) ?></title>
<link rel="manifest" href="<?= APP_BASE ?>/manifest.json">
<link rel="icon" type="image/svg+xml" href="<?= APP_BASE ?>/assets/img/favicon.svg">
<link rel="apple-touch-icon" href="<?= APP_BASE ?>/assets/img/icon-192.png">
<link rel="preconnect" href="https://unpkg.com">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/styles.css?v=<?= APP_VERSION ?>">
</head>
<body class="<?= e(trim($bodyClass)) ?>" data-base="<?= APP_BASE ?>" data-page="<?= e($GLOBALS['dataPage'] ?? '') ?>">

<a href="#contenido-principal" class="skip-link">Saltar al contenido</a>

<header class="topbar" id="topbar">
    <div class="topbar-inner container">
        <a href="<?= APP_BASE ?>/index.php" class="brand" aria-label="LlegAPP inicio">
            <span class="brand-logo" aria-hidden="true"><?= file_get_contents(dirname(__DIR__) . '/assets/img/logo.svg') ?: '' ?></span>
            <span class="brand-text">Lleg<b>APP</b><small>v<?= APP_VERSION ?></small></span>
        </a>

        <nav class="main-nav" aria-label="Navegación principal">
            <?php foreach ($menuPrincipal as $item): ?>
                <a href="<?= APP_BASE . $item['url'] ?>" class="nav-link"><?= $item['label'] ?></a>
            <?php endforeach; ?>
            <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                <a href="<?= APP_BASE ?>/admin/dashboard.php" class="nav-link nav-admin">Admin</a>
            <?php endif; ?>
        </nav>

        <div class="topbar-actions">
            <?php if ($currentUser): ?>
                <button type="button" class="icon-btn" id="btnNotificaciones" aria-label="Notificaciones" onclick="location.href='<?= APP_BASE ?>/pages/notificaciones.php'">
                    <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?php if ($notifCount > 0): ?><span class="badge-notif" id="badgeNotif"><?= $notifCount > 99 ? '99+' : $notifCount ?></span><?php endif; ?>
                </button>
                <div class="user-menu" id="userMenu">
                    <button type="button" class="avatar-btn" id="avatarBtn" aria-haspopup="true" aria-expanded="false"
                            style="--av:<?= e($currentUser['avatar_color'] ?: colorAvatar($currentUser['name'])) ?>">
                        <?= e(inicialesNombre($currentUser['name'])) ?>
                    </button>
                    <div class="dropdown-menu" role="menu" hidden>
                        <div class="dropdown-header">
                            <strong><?= e($currentUser['name']) ?></strong>
                            <span><?= e($currentUser['email']) ?></span>
                        </div>
                        <a role="menuitem" href="<?= APP_BASE ?>/pages/perfil.php">Mi perfil</a>
                        <a role="menuitem" href="<?= APP_BASE ?>/pages/favoritos.php">Favoritos</a>
                        <a role="menuitem" href="<?= APP_BASE ?>/pages/historial.php">Historial</a>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                        <a role="menuitem" href="<?= APP_BASE ?>/admin/dashboard.php">Panel de administración</a>
                        <?php endif; ?>
                        <hr>
                        <form method="post" action="<?= APP_BASE ?>/processes/logout.php">
                            <?= csrfField() ?>
                            <button type="submit" class="dropdown-logout">Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= APP_BASE ?>/pages/login.php" class="btn btn-ghost btn-sm">Ingresar</a>
                <a href="<?= APP_BASE ?>/pages/registro.php" class="btn btn-primary btn-sm">Crear cuenta</a>
            <?php endif; ?>
            <button type="button" class="icon-btn theme-toggle" id="themeToggle" aria-label="Cambiar tema">
                <svg viewBox="0 0 24 24" class="icon-sun"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                <svg viewBox="0 0 24 24" class="icon-moon"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
            <button type="button" class="icon-btn menu-toggle" id="menuToggle" aria-label="Abrir menú" aria-expanded="false">
                <svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>
        </div>
    </div>
    <div class="mobile-nav" id="mobileNav" hidden>
        <?php foreach ($menuPrincipal as $item): ?>
            <a href="<?= APP_BASE . $item['url'] ?>">🚌 <?= $item['label'] ?></a>
        <?php endforeach; ?>
        <a href="<?= APP_BASE ?>/pages/favoritos.php">⭐ Favoritos</a>
        <a href="<?= APP_BASE ?>/pages/historial.php">🕘 Historial</a>
        <?php if (!$currentUser): ?>
            <a href="<?= APP_BASE ?>/pages/login.php">Ingresar</a>
        <?php endif; ?>
    </div>
</header>

<main class="app-main" id="contenido-principal">
