<?php
/**
 * LLEGAPP 3 · Layout del panel de administración
 * Requiere: $titulo (string), $seccionActiva (string)
 */
$admin = requerirAdmin();
$menuAdmin = [
    ['id' => 'dashboard',     'url' => '/admin/dashboard.php',     'label' => 'Dashboard',    'icon' => '📊'],
    ['id' => 'rutas',         'url' => '/admin/rutas.php',         'label' => 'Rutas y paradas', 'icon' => '🛣️'],
    ['id' => 'buses',         'url' => '/admin/buses.php',         'label' => 'Flota',        'icon' => '🚌'],
    ['id' => 'usuarios',      'url' => '/admin/usuarios.php',      'label' => 'Usuarios',     'icon' => '👥'],
    ['id' => 'alertas',       'url' => '/admin/alertas.php',       'label' => 'Incidencias',  'icon' => '📢'],
    ['id' => 'estadisticas',  'url' => '/admin/estadisticas.php',  'label' => 'Estadísticas', 'icon' => '📈'],
];
$pageTitle = $titulo . ' · Admin · ' . APP_NAME;
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <div style="padding:18px 16px 10px">
            <span class="badge badge-danger">PANEL ADMIN</span>
            <p style="font-size:12.5px;color:var(--text-muted);margin-top:6px"><?= e($admin['name']) ?></p>
        </div>
        <?php foreach ($menuAdmin as $m): ?>
        <a href="<?= APP_BASE . $m['url'] ?>" class="admin-nav-item <?= ($seccionActiva ?? '') === $m['id'] ? 'activo' : '' ?>">
            <span><?= $m['icon'] ?></span> <?= $m['label'] ?>
        </a>
        <?php endforeach; ?>
        <hr style="margin:12px 16px">
        <a href="<?= APP_BASE ?>/pages/mapa.php" class="admin-nav-item"><span>🗺️</span> Ver app pública</a>
    </aside>

    <main class="admin-content">
