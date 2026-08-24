<?php
/**
 * LLEGAPP 3 · Favoritos del usuario
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = requerirAuth();

$favoritos = dbAll('
    SELECT f.*, r.name AS route_name, r.color, r.emoji, r.short_name,
           s.name AS stop_name
    FROM favorites f
    LEFT JOIN routes r ON f.item_type = "ruta" AND r.route_id = f.item_key
    LEFT JOIN stops  s ON f.item_type = "parada" AND s.id = f.item_key
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
', [$currentUser['id']]);

$pageTitle = 'Favoritos · ' . APP_NAME;
$dataPage = 'favoritos';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header container">
    <h1 class="page-title">⭐ Mis favoritos</h1>
    <p class="page-subtitle">Tus rutas y paradas guardadas</p>
</div>

<section class="section container">
    <?php if (!$favoritos): ?>
        <div class="card list-empty">
            <div class="emoji">⭐</div>
            <strong>Aún no tienes favoritos</strong>
            <p>Explora las rutas y toca la estrella para guardarlas aquí.</p>
            <a href="<?= APP_BASE ?>/pages/rutas.php" class="btn btn-primary btn-sm" style="margin-top:14px">Explorar rutas</a>
        </div>
    <?php else: ?>
    <div class="grid grid-3">
        <?php foreach ($favoritos as $f): ?>
            <?php if ($f['item_type'] === 'ruta'): ?>
            <article class="card card-hover" style="--route-color:<?= e($f['color'] ?? '#00A651') ?>">
                <div class="flex-between">
                    <span class="route-dot" style="--route-color:<?= e($f['color'] ?? '#00A651') ?>"><?= e($f['short_name'] ?: $f['item_key']) ?></span>
                    <button type="button" class="icon-btn btnQuitarFav" data-clave="<?= (int)$f['id'] ?>" title="Quitar"
                            style="color:var(--danger)">✕</button>
                </div>
                <strong style="display:block;margin-top:10px"><?= e($f['emoji']) ?> <?= e($f['route_name']) ?></strong>
                <div class="flex-between" style="margin-top:14px">
                    <a href="<?= APP_BASE ?>/pages/detalle_ruta.php?ruta=<?= urlencode($f['item_key']) ?>" class="btn btn-secondary btn-sm">Ver en vivo →</a>
                    <a href="<?= APP_BASE ?>/pages/mapa.php?ruta=<?= urlencode($f['item_key']) ?>" class="btn btn-ghost btn-sm">Mapa</a>
                </div>
            </article>
            <?php else: ?>
            <article class="card card-hover">
                <div class="flex-between">
                    <span style="font-size:26px">🚏</span>
                    <button type="button" class="icon-btn btnQuitarFav" data-clave="<?= (int)$f['id'] ?>" title="Quitar"
                            style="color:var(--danger)">✕</button>
                </div>
                <strong style="display:block;margin-top:10px"><?= e($f['stop_name'] ?: $f['label']) ?></strong>
                <p style="font-size:12.5px;color:var(--text-muted);margin-top:3px">Parada guardada</p>
                <button type="button" class="btn btn-ghost btn-sm btnVerParada" data-stop="<?= (int)$f['item_key'] ?>"
                        style="margin-top:14px">Ver próximos buses</button>
                <div id="eta-parada-<?= (int)$f['item_key'] ?>" style="font-size:13px;margin-top:8px"></div>
            </article>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    $$('.btnQuitarFav').forEach(btn => btn.addEventListener('click', async () => {
        const data = await api(`${APP.base}/api/favoritos.php?id=${btn.dataset.clave}`, { method: 'DELETE' });
        if (data?.success) { toast('Eliminado de favoritos', '', 'success', 2000); btn.closest('.card').remove(); }
    }));

    // ETAs de paradas favoritas al cargar
    document.querySelectorAll('.btnVerParada').forEach(btn => btn.addEventListener('click', async () => {
        const cont = document.getElementById(`eta-parada-${btn.dataset.stop}`);
        const data = await api(`${APP.base}/api/eta.php?parada=${btn.dataset.stop}`);
        if (!data?.success) return;
        cont.innerHTML = data.data.buses.slice(0, 3).map(b =>
            `<div style="display:flex;justify-content:space-between;padding:4px 0">
                <span><b>${b.bus_code}</b></span><span>en <b>${b.eta_min} min</b></span>
            </div>`).join('') || '<em style="color:var(--text-muted);font-size:12px">Sin buses próximos ahora.</em>';
    }));
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
