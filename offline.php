<?php
/**
 * LLEGAPP 3 · Página offline (fallback del Service Worker)
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';
header('Cache-Control: no-store');

$pageTitle = 'Sin conexión · ' . APP_NAME;
$dataPage = 'offline';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="auth-layout">
    <div class="error-card">
        <div class="error-code">📡</div>
        <h1>Sin conexión</h1>
        <p>No hay internet en este momento. El mapa en vivo requiere conexión, pero tus páginas visitadas siguen disponibles desde la caché.</p>
        <button class="btn btn-primary" onclick="location.reload()">Reintentar conexión</button>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
