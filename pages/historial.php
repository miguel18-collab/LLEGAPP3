<?php
/**
 * LLEGAPP 3 · Historial de actividad del usuario
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = requerirAuth();

$pagina = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pagina - 1) * REGISTROS_POR_PAGINA;

$total = (int)dbVal('SELECT COUNT(*) FROM trip_history WHERE user_id = ?', [$currentUser['id']]);
$totalPaginas = max(1, (int)ceil($total / REGISTROS_POR_PAGINA));

$registros = dbAll('
    SELECT h.*, r.name AS route_name, r.color, r.short_name
    FROM trip_history h LEFT JOIN routes r ON r.route_id = h.route_id
    WHERE h.user_id = ?
    ORDER BY h.created_at DESC LIMIT ? OFFSET ?
', [$currentUser['id'], REGISTROS_POR_PAGINA, $offset]);

// Estadísticas personales
$stats = [
    'vistos'    => (int)dbVal("SELECT COUNT(*) FROM trip_history WHERE user_id = ? AND action = 'viewed'", [$currentUser['id']]),
    'seguidos'  => (int)dbVal("SELECT COUNT(*) FROM trip_history WHERE user_id = ? AND action = 'tracked'", [$currentUser['id']]),
    'planificados' => (int)dbVal("SELECT COUNT(*) FROM trip_history WHERE user_id = ? AND action = 'planned'", [$currentUser['id']]),
    'alertas_creadas' => (int)dbVal("SELECT COUNT(*) FROM trip_history WHERE user_id = ? AND action = 'alert_created'", [$currentUser['id']]),
];
$rutaFavorita = dbOne('
    SELECT r.short_name, r.name, r.color, COUNT(*) AS usos
    FROM trip_history h JOIN routes r ON r.route_id = h.route_id
    WHERE h.user_id = ?
    GROUP BY h.route_id ORDER BY usos DESC LIMIT 1
', [$currentUser['id']]);

$pageTitle = 'Historial · ' . APP_NAME;
$dataPage = 'historial';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header container">
    <h1 class="page-title">🕘 Mi historial</h1>
    <p class="page-subtitle"><?= $total ?> actividades registradas</p>
</div>

<section class="section container">
    <div class="grid grid-4" style="margin-bottom:22px">
        <div class="card kpi-card"><div class="kpi-icon" style="background:var(--primary-light)">👁️</div><div><div class="kpi-value"><?= $stats['vistos'] ?></div><div class="kpi-label">Rutas consultadas</div></div></div>
        <div class="card kpi-card"><div class="kpi-icon" style="background:#dbeafe">🚌</div><div><div class="kpi-value"><?= $stats['seguidos'] ?></div><div class="kpi-label">Buses seguidos</div></div></div>
        <div class="card kpi-card"><div class="kpi-icon" style="background:#fef3c7">🧭</div><div><div class="kpi-value"><?= $stats['planificados'] ?></div><div class="kpi-label">Viajes planificados</div></div></div>
        <div class="card kpi-card"><div class="kpi-icon" style="background:#fee2e2">🔔</div><div><div class="kpi-value"><?= $stats['alertas_creadas'] ?></div><div class="kpi-label">Alertas creadas</div></div></div>
    </div>

    <?php if ($rutaFavorita): ?>
    <div class="demo-note" style="margin-bottom:20px">
        Tu ruta más consultada es <span class="route-dot" style="--route-color:<?= e($rutaFavorita['color']) ?>;display:inline-flex;width:auto;padding:2px 10px"><?= e($rutaFavorita['short_name']) ?></span>
        <strong><?= e($rutaFavorita['name']) ?></strong> con <?= (int)$rutaFavorita['usos'] ?> visitas.
        <a href="<?= APP_BASE ?>/pages/detalle_ruta.php?ruta=<?= urlencode($rutaFavorita['short_name']) ?>" style="color:var(--primary);font-weight:600">Ver en vivo →</a>
    </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Fecha</th><th>Acción</th><th>Ruta</th><th>Detalle</th></tr>
            </thead>
            <tbody>
                <?php if (!$registros): ?>
                    <tr><td colspan="4">
                        <div class="list-empty">
                            <div class="emoji">📭</div>
                            <strong>Sin actividad todavía</strong>
                            <p>Explora el mapa y tus acciones aparecerán aquí.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($registros as $r): ?>
                <tr>
                    <td style="white-space:nowrap"><?= tiempoRelativo($r['created_at']) ?></td>
                    <td>
                        <?php $acciones = ['viewed' => ['👁️ Consultó', 'badge-info'], 'tracked' => ['🚌 Siguió bus', 'badge-success'], 'planned' => ['🧭 Planificó', 'badge-warning'], 'alert_created' => ['🔔 Alerta', 'badge-danger']];
                              [$txt, $cls] = $acciones[$r['action']] ?? ['•', 'badge-neutral']; ?>
                        <span class="badge <?= $cls ?>"><?= $txt ?></span>
                    </td>
                    <td>
                        <?php if ($r['route_id']): ?>
                            <a href="<?= APP_BASE ?>/pages/detalle_ruta.php?ruta=<?= urlencode($r['route_id']) ?>"
                               style="--route-color:<?= e($r['color'] ?? '#999') ?>">
                                <span class="route-pill" style="--route-color:<?= e($r['color'] ?? '#999') ?>">
                                    <span class="route-dot" style="--route-color:<?= e($r['color'] ?? '#999') ?>"><?= e($r['short_name']) ?></span>
                                    <?= e($r['route_name']) ?>
                                </span></a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td style="font-size:13px;color:var(--text-muted)">
                        <?= e(trim(($r['origin_stop'] ?: '') . ($r['origin_stop'] && $r['dest_stop'] ? ' → ' : '') . ($r['dest_stop'] ?: '')) ?: '—') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPaginas > 1): ?>
    <nav class="pagination" aria-label="Paginación">
        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <a href="?p=<?= $i ?>" class="page-link <?= $i === $pagina ? 'current' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
