<?php
/**
 * LLEGAPP 3 · Landing principal
 */
require_once __DIR__ . '/config/configuracion.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . APP_BASE . '/pages/mapa.php');
    exit;
}

require_once __DIR__ . '/includes/rutas.php';

try {
    $stats = estadisticasSistema();
    $rutas = array_slice(obtenerRutas(), 0, 4);
    $alertas = alertasSistemaActivas();
} catch (Exception $e) {
    $stats = ['rutas' => 0, 'paradas' => 0, 'buses' => 0, 'pasajeros' => 0];
    $rutas = []; $alertas = [];
}

$pageTitle = APP_NAME . ' · ' . APP_TAGLINE;
$dataPage = 'landing';
require __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="hero">
    <div class="container">
        <span class="chip" style="margin-bottom:18px"><span class="live-dot"></span> Flota en tiempo real</span>
        <h1>Tu bus, <em>al minuto</em>,<br>sin esperas a ciegas</h1>
        <p class="lead">LlegAPP rastrea la flota de transporte urbano de Medellín en tiempo real:
            mira dónde va tu bus, planifica tu viaje y recibe alertas antes de que llegue a tu parada.</p>
        <div class="hero-actions">
            <a href="<?= APP_BASE ?>/pages/mapa.php" class="btn btn-primary" style="padding:14px 30px;font-size:16px">
                <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Ver mapa en vivo
            </a>
            <a href="<?= APP_BASE ?>/pages/rutas.php" class="btn btn-ghost" style="padding:14px 26px;font-size:16px">
                Explorar rutas
            </a>
        </div>
    </div>
</section>

<!-- ESTADÍSTICAS -->
<section class="section container">
    <div class="card stats-band">
        <div class="stat-box"><div class="num" data-contar="<?= (int)$stats['rutas'] ?>">0</div><div class="cap">Rutas activas</div></div>
        <div class="stat-box"><div class="num" data-contar="<?= (int)$stats['paradas'] ?>">0</div><div class="cap">Paradas cubiertas</div></div>
        <div class="stat-box"><div class="num" data-contar="<?= (int)$stats['buses'] ?>">0</div><div class="cap">Buses operando</div></div>
        <div class="stat-box"><div class="num" data-contar="<?= (int)$stats['pasajeros'] ?>">0</div><div class="cap">Pasajeros a bordo</div></div>
    </div>
</section>

<!-- INCIDENCIAS ACTIVAS -->
<?php if ($alertas): ?>
<section class="container" style="margin-top:-6px">
    <?php foreach ($alertas as $a): ?>
    <div class="alert <?= ['critica'=>'alert-danger','alta'=>'alert-danger','media'=>'alert-info','info'=>'alert-info'][$a['severity']] ?? 'alert-info' ?>">
        <?= $a['route_id'] ? "<strong>[{$a['route_id']}]</strong> " : '' ?><?= e($a['title']) ?> — <?= e($a['message']) ?>
    </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- CARACTERÍSTICAS -->
<section class="section container">
    <div class="grid grid-3">
        <div class="card card-hover feature-card">
            <div class="feature-icon">🗺️</div>
            <h3 style="font-size:17px;margin-bottom:6px">Mapa en tiempo real</h3>
            <p style="font-size:14px;color:var(--text-muted)">Sigue cada bus sobre el recorrido con actualización fluida cada 3 segundos. Toca un bus para ver velocidad, ocupación y próxima parada.</p>
        </div>
        <div class="card card-hover feature-card">
            <div class="feature-icon">🧭</div>
            <h3 style="font-size:17px;margin-bottom:6px">Planificador de viajes</h3>
            <p style="font-size:14px;color:var(--text-muted)">Dinos a dónde vas y te decimos qué ruta tomar, dónde hacer transbordo y cuánto demorarás, con horarios estimados.</p>
        </div>
        <div class="card card-hover feature-card">
            <div class="feature-icon">🔔</div>
            <h3 style="font-size:17px;margin-bottom:6px">Alertas inteligentes</h3>
            <p style="font-size:14px;color:var(--text-muted)">Configura avisos en tus paradas: te notificamos cuando el bus esté a pocas paradas de llegar. Nunca más corras tras el bus.</p>
        </div>
    </div>
</section>

<!-- RUTAS DESTACADAS -->
<?php if ($rutas): ?>
<section class="section container">
    <div class="flex-between" style="margin-bottom:18px">
        <div>
            <h2 style="font-size:22px;letter-spacing:-.02em">Rutas destacadas</h2>
            <p style="color:var(--text-muted);font-size:14px">Las líneas principales del sistema</p>
        </div>
        <a href="<?= APP_BASE ?>/pages/rutas.php" class="btn btn-ghost btn-sm">Ver todas →</a>
    </div>
    <div class="grid grid-2">
        <?php foreach ($rutas as $r): ?>
        <a href="<?= APP_BASE ?>/pages/detalle_ruta.php?ruta=<?= urlencode($r['route_id']) ?>" class="card card-hover" style="--route-color:<?= e($r['color']) ?>">
            <div class="flex-between">
                <div class="flex-between" style="gap:12px">
                    <span class="route-dot" style="--route-color:<?= e($r['color']) ?>"><?= e($r['short_name'] ?: $r['route_id']) ?></span>
                    <strong><?= e($r['name']) ?></strong>
                </div>
                <span class="badge badge-success">● Activa</span>
            </div>
            <p style="font-size:13.5px;color:var(--text-muted);margin-top:10px"><?= e($r['description']) ?></p>
            <div style="display:flex;gap:14px;margin-top:12px;font-size:12.5px;color:var(--text-muted)">
                <span>🚏 <?= (int)$r['total_paradas'] ?> paradas</span>
                <span>🚌 <?= (int)$r['total_buses'] ?> buses en línea</span>
                <span>⏱️ Cada <?= (int)$r['frequency_min'] ?> min</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- CTA REGISTRO -->
<section class="section container">
    <div class="demo-note flex-between">
        <div>
            <strong>Crea tu cuenta gratis</strong>
            <p style="color:var(--text-muted);font-size:13.5px;margin-top:3px">Guarda favoritos, crea alertas y consulta tu historial de viajes.</p>
        </div>
        <div style="display:flex;gap:10px">
            <a href="<?= APP_BASE ?>/pages/registro.php" class="btn btn-primary btn-sm">Registrarme</a>
            <a href="<?= APP_BASE ?>/pages/login.php" class="btn btn-ghost btn-sm">Ya tengo cuenta</a>
        </div>
    </div>
</section>

<script>
// Contador animado de estadísticas
document.addEventListener('DOMContentLoaded', () => {
    $$('[data-contar]').forEach(el => {
        const objetivo = parseInt(el.dataset.contar) || 0;
        const inicio = performance.now();
        const paso = t => {
            const k = Math.min(1, (t - inicio) / 1100);
            el.textContent = Math.round(objetivo * (1 - Math.pow(1 - k, 3))).toLocaleString('es-CO');
            if (k < 1) requestAnimationFrame(paso);
        };
        requestAnimationFrame(paso);
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
