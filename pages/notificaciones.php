<?php
/**
 * LLEGAPP 3 · Bandeja de notificaciones
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = requerirAuth();

$notificaciones = dbAll('
    SELECT * FROM notifications WHERE user_id = ?
    ORDER BY created_at DESC LIMIT 60
', [$currentUser['id']]);

$pageTitle = 'Notificaciones · ' . APP_NAME;
$dataPage = 'notificaciones';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header container">
    <div class="flex-between">
        <div>
            <h1 class="page-title">🔔 Notificaciones</h1>
            <p class="page-subtitle">Alertas y novedades del sistema</p>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" id="btnMarcarTodas">Marcar todas como leídas</button>
    </div>
</div>

<section class="section container">
    <div style="display:grid;gap:10px;max-width:760px">
        <?php if (!$notificaciones): ?>
            <div class="card list-empty">
                <div class="emoji">🔕</div>
                <strong>Sin notificaciones</strong>
                <p>Cuando un bus se acerque a tus paradas vigiladas, te avisaremos aquí.</p>
                <a href="<?= APP_BASE ?>/pages/alertas.php" class="btn btn-primary btn-sm" style="margin-top:14px">Configurar alertas</a>
            </div>
        <?php endif; ?>

        <?php foreach ($notificaciones as $n): ?>
        <article class="notif-item <?= (int)$n['is_read'] ? 'leida' : '' ?>" data-id="<?= (int)$n['id'] ?>">
            <span class="notif-icon"><?= e($n['icon']) ?></span>
            <div style="flex:1">
                <div class="flex-between">
                    <strong style="font-size:14px"><?= e($n['title']) ?></strong>
                    <small style="color:var(--text-muted);white-space:nowrap;font-size:11.5px"><?= tiempoRelativo($n['created_at']) ?></small>
                </div>
                <p style="font-size:13.5px;color:var(--text-muted)"><?= e($n['message']) ?></p>
                <?php if ($n['link_url']): ?>
                    <a href="<?= e($n['link_url']) ?>" class="btnVerNotif" data-id="<?= (int)$n['id'] ?>"
                       style="font-size:13px;color:var(--primary);font-weight:600;margin-top:4px;display:inline-block">Ver →</a>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    async function marcarLeida(id) {
        return api(`${APP.base}/api/notificaciones.php`, {
            method: 'PUT',
            body: JSON.stringify({ id }),
        });
    }

    $$('.btnVerNotif').forEach(a => a.addEventListener('click', () => marcarLeida(a.dataset.id)));

    document.querySelectorAll('.notif-item:not(.leida)').forEach(item => {
        item.style.cursor = 'pointer';
        item.addEventListener('click', async e => {
            if (e.target.closest('a')) return;
            const data = await marcarLeida(item.dataset.id);
            if (data?.success) { item.classList.add('leida'); $('#badgeNotif')?.remove(); }
        });
    });

    $('#btnMarcarTodas').addEventListener('click', async () => {
        const data = await api(`${APP.base}/api/notificaciones.php`, { method: 'PUT', body: JSON.stringify({ todas: true }) });
        if (data?.success) location.reload();
    });
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
