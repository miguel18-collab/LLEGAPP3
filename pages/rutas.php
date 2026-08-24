<?php
/**
 * LLEGAPP 3 · Catálogo de rutas
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/rutas.php';

$currentUser = usuarioActual();
$busqueda = trim($_GET['q'] ?? '');
$rutas = obtenerRutas(false);

if ($busqueda !== '') {
    $q = mb_strtolower($busqueda);
    $rutas = array_filter($rutas, fn($r) =>
        str_contains(mb_strtolower($r['name']), $q)
        || str_contains(mb_strtolower($r['route_id']), $q)
        || str_contains(mb_strtolower($r['description'] ?? ''), $q)
    );
}

$favoritas = [];
if ($currentUser) {
    $favoritas = array_column(
        dbAll("SELECT item_key FROM favorites WHERE user_id = ? AND item_type = 'ruta'", [$currentUser['id']]),
        'item_key'
    );
}

$pageTitle = 'Rutas · ' . APP_NAME;
$dataPage = 'rutas';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header container">
    <h1 class="page-title">Rutas del sistema</h1>
    <p class="page-subtitle"><?= count($rutas) ?> líneas disponibles en Medellín</p>
</div>

<section class="section container">
    <div class="flex-between" style="margin-bottom:18px">
        <div class="search-box" style="flex:1;max-width:420px">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <form method="get" action="" style="display:contents">
                <input type="search" class="input" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar por nombre, código o descripción…">
            </form>
        </div>
        <a href="<?= APP_BASE ?>/pages/planificador.php" class="btn btn-secondary btn-sm">🧭 Planificar viaje</a>
    </div>

    <?php if (!$rutas): ?>
        <div class="card list-empty">
            <div class="emoji">🔍</div>
            <strong>Sin resultados</strong>
            <p>No hay rutas que coincidan con "<?= e($busqueda) ?>".</p>
            <a href="<?= APP_BASE ?>/pages/rutas.php" class="btn btn-ghost btn-sm" style="margin-top:14px">Limpiar búsqueda</a>
        </div>
    <?php else: ?>
    <div class="grid grid-2">
        <?php foreach ($rutas as $r): $esFav = in_array($r['route_id'], $favoritas); ?>
        <article class="card card-hover" style="--route-color:<?= e($r['color']) ?>">
            <div class="flex-between" style="align-items:flex-start">
                <div style="display:flex;gap:12px;align-items:center">
                    <span class="route-dot" style="--route-color:<?= e($r['color']) ?>;width:44px;height:44px;font-size:12px"><?= e($r['short_name'] ?: $r['route_id']) ?></span>
                    <div>
                        <strong><?= e($r['emoji']) ?> <?= e($r['name']) ?></strong>
                        <p style="font-size:12.5px;color:var(--text-muted)"><?= (int)$r['total_paradas'] ?> paradas · cada <?= (int)$r['frequency_min'] ?> min</p>
                    </div>
                </div>
                <?php if ($currentUser): ?>
                <button type="button" class="icon-btn btnFavorito <?= $esFav ? '' : '' ?>"
                        data-tipo="ruta" data-clave="<?= e($r['route_id']) ?>"
                        title="<?= $esFav ? 'Quitar de favoritos' : 'Añadir a favoritos' ?>"
                        style="font-size:20px;color:<?= $esFav ? '#f59e0b' : 'var(--text-muted)' ?>">
                    <?= $esFav ? '★' : '☆' ?>
                </button>
                <?php endif; ?>
            </div>

            <p style="font-size:13.5px;color:var(--text-muted);margin-top:10px;min-height:40px"><?= e($r['description']) ?></p>

            <div class="flex-between" style="margin-top:12px">
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <span class="badge <?= (int)$r['active'] ? 'badge-success' : 'badge-neutral' ?>"><?= (int)$r['active'] ? '● Activa' : 'Inactiva' ?></span>
                    <span class="badge badge-info">🚌 <?= (int)$r['total_buses'] ?> en línea</span>
                    <span class="badge badge-neutral">🕐 <?= date('H:i', strtotime($r['first_departure'])) ?>–<?= date('H:i', strtotime($r['last_departure'])) ?></span>
                </div>
                <a href="<?= APP_BASE ?>/pages/detalle_ruta.php?ruta=<?= urlencode($r['route_id']) ?>" class="btn btn-primary btn-sm">Ver detalle →</a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.body.dataset.auth = '<?= $currentUser ? "1" : "0" ?>';
    $$('.btnFavorito').forEach(btn => btn.addEventListener('click', () =>
        alternarFavorito(btn.dataset.tipo, btn.dataset.clave, btn)));
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
