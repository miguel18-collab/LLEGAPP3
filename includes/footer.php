<?php
/**
 * LLEGAPP 3 - Footer común (nav inferior móvil + scripts globales)
 */
?>
</main>

<nav class="bottom-nav" aria-label="Navegación inferior" id="bottomNav">
    <a href="<?= APP_BASE ?>/pages/mapa.php" class="bottom-item">
        <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <span>Mapa</span>
    </a>
    <a href="<?= APP_BASE ?>/pages/rutas.php" class="bottom-item">
        <svg viewBox="0 0 24 24"><path d="M4 17l6-6-6-6M12 19h8"/></svg>
        <span>Rutas</span>
    </a>
    <a href="<?= APP_BASE ?>/pages/planificador.php" class="bottom-item bottom-fab">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M16.2 7.8l-2 6.3-6.4 2.1 2-6.3z"/></svg>
        <span>Planificar</span>
    </a>
    <a href="<?= APP_BASE ?>/pages/favoritos.php" class="bottom-item">
        <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
        <span>Favoritos</span>
    </a>
    <a href="<?= APP_BASE ?>/pages/alertas.php" class="bottom-item">
        <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>Alertas</span>
    </a>
</nav>

<footer class="app-footer">
    <div class="container footer-inner">
        <div class="footer-brand">
            <strong>Lleg<b>APP</b></strong>
            <p>Tu bus en tiempo real · Medellín</p>
        </div>
        <div class="footer-links">
            <a href="<?= APP_BASE ?>/pages/rutas.php">Rutas</a>
            <a href="<?= APP_BASE ?>/pages/planificador.php">Planificador</a>
            <a href="<?= APP_BASE ?>/pages/alertas.php">Alertas</a>
            <a href="<?= APP_BASE ?>/offline.php">Estado del servicio</a>
        </div>
        <div class="footer-meta">
            <span>v<?= APP_VERSION ?> © <?= date('Y') ?> LlegAPP</span>
            <span>Datos simulados con fines demostrativos</span>
        </div>
    </div>
</footer>

<div id="toastContainer" class="toast-container" aria-live="polite"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>window.CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;</script>
<script src="<?= APP_BASE ?>/assets/js/app.js?v=<?= APP_VERSION ?>"></script>
<?php if (!empty($pageScripts)) echo $pageScripts; ?>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('<?= APP_BASE ?>/sw.js')
            .catch(err => console.warn('[PWA] SW no registrado:', err));
    });
}
</script>
</body>
</html>
