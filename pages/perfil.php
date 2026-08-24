<?php
/**
 * LLEGAPP 3 · Perfil de usuario
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = requerirAuth();

$preferencias = dbOne('SELECT * FROM user_preferences WHERE user_id = ?', [$currentUser['id']])
    ?? ['theme' => 'auto', 'notifications_enabled' => 1, 'notify_stops_ahead' => 2];

$msg = $_SESSION['perfil_msg'] ?? null;
$tipo = $_SESSION['perfil_tipo'] ?? 'success';
unset($_SESSION['perfil_msg'], $_SESSION['perfil_tipo']);

$pageTitle = 'Mi perfil · ' . APP_NAME;
$dataPage = 'perfil';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header container">
    <h1 class="page-title">Mi perfil</h1>
    <p class="page-subtitle">Gestiona tu cuenta y preferencias</p>
</div>

<section class="section container">
    <?php if ($msg): ?><div class="alert alert-<?= $tipo === 'error' ? 'danger' : 'success' ?>"><?= e($msg) ?></div><?php endif; ?>

    <div class="grid" style="grid-template-columns:repeat(auto-fit, minmax(min(340px,100%),1fr))">

        <!-- Datos personales -->
        <div class="card">
            <div style="display:flex;gap:14px;align-items:center;margin-bottom:18px">
                <span class="avatar-btn" style="width:56px;height:56px;font-size:20px;--av:<?= e($currentUser['avatar_color'] ?: colorAvatar($currentUser['email'])) ?>">
                    <?= e(inicialesNombre($currentUser['name'])) ?>
                </span>
                <div>
                    <strong style="font-size:16px"><?= e($currentUser['name']) ?></strong>
                    <p style="font-size:13px;color:var(--text-muted)"><?= e($currentUser['email']) ?></p>
                    <span class="badge <?= $currentUser['role'] === 'admin' ? 'badge-danger' : 'badge-info' ?>">
                        <?= ['admin' => 'Administrador', 'usuario' => 'Usuario', 'conductor' => 'Conductor'][$currentUser['role']] ?? $currentUser['role'] ?>
                    </span>
                </div>
            </div>

            <form method="post" action="<?= APP_BASE ?>/api/perfil.php" id="formPerfil">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="field">
                    <label class="label" for="name">Nombre completo</label>
                    <input class="input" type="text" id="name" name="name" value="<?= e($currentUser['name']) ?>" required minlength="3">
                </div>
                <div class="field">
                    <label class="label" for="phone">Teléfono</label>
                    <input class="input" type="tel" id="phone" name="phone" value="<?= e($currentUser['phone'] ?? '') ?>" placeholder="+57 300 000 0000">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Guardar cambios</button>
            </form>
        </div>

        <!-- Seguridad -->
        <div class="card">
            <strong style="font-size:15px">🔒 Cambiar contraseña</strong>
            <form method="post" action="<?= APP_BASE ?>/api/perfil.php" id="formPassword" style="margin-top:14px">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="accion" value="password">
                <div class="field">
                    <label class="label" for="pass_actual">Contraseña actual</label>
                    <input class="input" type="password" id="pass_actual" name="password_actual" required autocomplete="current-password">
                </div>
                <div class="field">
                    <label class="label" for="pass_nuevo">Nueva contraseña</label>
                    <input class="input" type="password" id="pass_nuevo" name="password_nuevo" required minlength="8" autocomplete="new-password">
                    <p class="field-hint">Mín. 8 caracteres con letras y números.</p>
                </div>
                <button type="submit" class="btn btn-ghost btn-block">Actualizar contraseña</button>
            </form>
        </div>

        <!-- Preferencias -->
        <div class="card">
            <strong style="font-size:15px">⚙️ Preferencias de la app</strong>
            <form method="post" action="<?= APP_BASE ?>/api/perfil.php" id="formPrefs" style="margin-top:14px">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="accion" value="preferencias">

                <div class="field">
                    <label class="label" for="pref_tema">Tema visual</label>
                    <select class="select" id="pref_tema" name="theme">
                        <option value="auto" <?= $preferencias['theme'] === 'auto' ? 'selected' : '' ?>>Automático (según sistema)</option>
                        <option value="light" <?= $preferencias['theme'] === 'light' ? 'selected' : '' ?>>Claro</option>
                        <option value="dark" <?= $preferencias['theme'] === 'dark' ? 'selected' : '' ?>>Oscuro</option>
                    </select>
                </div>

                <div class="field flex-between">
                    <label class="label" style="margin:0">Notificaciones activadas</label>
                    <input type="checkbox" name="notifications_enabled" value="1"
                           <?= (int)$preferencias['notifications_enabled'] ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--primary)">
                </div>

                <div class="field">
                    <label class="label" for="pref_antelacion">Antelación de avisos</label>
                    <select class="select" id="pref_antelacion" name="notify_stops_ahead">
                        <option value="1" <?= (int)$preferencias['notify_stops_ahead'] === 1 ? 'selected' : '' ?>>1 parada antes</option>
                        <option value="2" <?= (int)$preferencias['notify_stops_ahead'] == 2 ? 'selected' : '' ?>>2 paradas antes</option>
                        <option value="3" <?= (int)$preferencias['notify_stops_ahead'] == 3 ? 'selected' : '' ?>>3 paradas antes</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-secondary btn-block">Guardar preferencias</button>
            </form>
        </div>
    </div>

    <!-- Zona peligrosa -->
    <div class="card" style="margin-top:18px;border-color:rgba(239,68,68,.35)">
        <strong style="color:var(--danger);font-size:14px">⚠️ Zona de riesgo</strong>
        <div class="flex-between" style="margin-top:8px">
            <p style="font-size:13.5px;color:var(--text-muted)">Eliminar tu cuenta borra favoritos, alertas e historial. Esta acción no se puede deshacer.</p>
            <button type="button" class="btn btn-danger btn-sm" id="btnEliminarCuenta">Eliminar mi cuenta</button>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    function manejarFormulario(formId, okMsg) {
        $(formId).addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const datos = Object.fromEntries(fd.entries());
            if (datos.notifications_enabled !== undefined) {
                datos.notifications_enabled = fd.get('notifications_enabled') === '1';
            }
            const data = await api(`${APP.base}/api/perfil.php`, { method: 'POST', body: JSON.stringify(datos) });
            if (data?.success) toast('Listo', data.message || okMsg, 'success');
            else toast('Error', data?.message || 'No se pudo guardar.', 'error');
        });
    }

    manejarFormulario('#formPerfil');
    manejarFormulario('#formPassword');
    manejarFormulario('#formPrefs');

    $('#btnEliminarCuenta').addEventListener('click', async () => {
        if (!confirm('¿Seguro que deseas eliminar tu cuenta permanentemente?')) return;
        if (!confirm('Última confirmación: esta acción es irreversible.')) return;
        const data = await api(`${APP.base}/api/perfil.php`, { method: 'DELETE' });
        if (data?.success) location.href = `${APP.base}/index.php`;
    });
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
