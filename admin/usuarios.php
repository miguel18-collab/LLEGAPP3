<?php
/**
 * ADMIN · Gestión de usuarios (listado, roles, activación, eliminación)
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$admin = requerirAdmin();
$titulo = 'Usuarios';
$seccionActiva = 'usuarios';

// ---------- ACCIONES POST ----------
$msg = null; $msgTipo = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validarCSRF()) {
    $accion = $_POST['accion'] ?? '';
    $userId = $_POST['user_id'] ?? '';

    try {
        switch ($accion) {
            case 'cambiar_rol':
                $nuevoRol = in_array($_POST['rol'] ?? '', ['admin', 'usuario', 'conductor'], true) ? $_POST['rol'] : 'usuario';
                if ($userId === $admin['id']) throw new RuntimeException('No puedes cambiar tu propio rol.');
                dbq('UPDATE users SET role = ? WHERE id = ?', [$nuevoRol, $userId]);
                $msg = "Rol actualizado a '$nuevoRol'.";
                break;

            case 'alternar_activo':
                if ($userId === $admin['id']) throw new RuntimeException('No puedes desactivar tu propia cuenta.');
                dbq('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$userId]);
                $activo = (bool)dbVal('SELECT is_active FROM users WHERE id = ?', [$userId]);
                $msg = $activo ? 'Usuario activado.' : 'Usuario desactivado.';
                $msgTipo = $activo ? 'success' : 'info';
                break;

            case 'reset_password':
                $temporal = 'Llegapp' . random_int(1000, 9999);
                dbq('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($temporal, PASSWORD_BCRYPT), $userId]);
                $msg = "Contraseña temporal asignada: $temporal (comunícala al usuario).";
                break;

            case 'eliminar':
                if ($userId === $admin['id']) throw new RuntimeException('No puedes eliminar tu propia cuenta.');
                dbq('DELETE FROM users WHERE id = ?', [$userId]);
                $msg = 'Usuario eliminado junto con sus datos.';
                $msgTipo = 'info';
                break;
        }
    } catch (RuntimeException $e) {
        $msg = $e->getMessage(); $msgTipo = 'error';
    }
}

// ---------- LISTADO ----------
$q = trim($_GET['q'] ?? '');
$filtroRol = $_GET['rol'] ?? '';
$pagina = max(1, (int)($_GET['p'] ?? 1));

$where = ['1=1']; $params = [];
if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%";
}
if (in_array($filtroRol, ['admin', 'usuario', 'conductor'], true)) {
    $where[] = 'role = ?'; $params[] = $filtroRol;
}
$whereSql = implode(' AND ', $where);

$total = (int)dbVal("SELECT COUNT(*) FROM users WHERE $whereSql", $params);
$totalPaginas = max(1, ceil($total / REGISTROS_POR_PAGINA));
$offset = ($pagina - 1) * REGISTROS_POR_PAGINA;

$usuarios = dbAll("
    SELECT u.*,
           (SELECT COUNT(*) FROM favorites f WHERE f.user_id = u.id) AS total_favoritos,
           (SELECT COUNT(*) FROM user_alerts ua WHERE ua.user_id = u.id) AS total_alertas
    FROM users u WHERE $whereSql
    ORDER BY u.created_at DESC LIMIT " . REGISTROS_POR_PAGINA . " OFFSET $offset
", $params);

require __DIR__ . '/_layout.php';
?>

<div class="flex-between" style="margin-bottom:20px">
    <div>
        <h1 class="page-title">👥 Usuarios</h1>
        <p class="page-subtitle"><?= $total ?> cuentas registradas</p>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert <?= $msgTipo === 'error' ? 'alert-danger' : ($msgTipo === 'info' ? 'alert-info' : 'alert-success') ?>"><?= e($msg) ?></div>
<?php endif; ?>

<form method="get" action="" class="flex-between" style="margin-bottom:16px">
    <div class="search-box" style="flex:1;max-width:380px">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="search" class="input" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o correo…">
    </div>
    <select class="select" name="rol" style="max-width:170px" onchange="this.form.submit()">
        <option value="">Todos los roles</option>
        <option value="admin" <?= $filtroRol === 'admin' ? 'selected' : '' ?>>Administradores</option>
        <option value="usuario" <?= $filtroRol === 'usuario' ? 'selected' : '' ?>>Usuarios</option>
        <option value="conductor" <?= $filtroRol === 'conductor' ? 'selected' : '' ?>>Conductores</option>
    </select>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th>Usuario</th><th>Rol</th><th>Estado</th><th>Favoritos</th><th>Alertas</th><th>Registro</th><th style="text-align:right">Acciones</th>
        </tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td>
                    <div style="display:flex;gap:10px;align-items:center">
                        <span class="avatar-btn" style="--av:<?= e($u['avatar_color'] ?: colorAvatar($u['email'])) ?>;width:34px;height:34px;font-size:12px"><?= e(inicialesNombre($u['name'])) ?></span>
                        <div>
                            <b><?= e($u['name']) ?></b>
                            <p style="font-size:12px;color:var(--text-muted)"><?= e($u['email']) ?></p>
                        </div>
                    </div>
                </td>
                <td>
                    <form method="post" style="display:flex;gap:6px;align-items:center">
                        <?= csrfField() ?>
                        <input type="hidden" name="accion" value="cambiar_rol">
                        <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                        <select class="select" name="rol" style="padding:5px 8px;font-size:13px;width:auto"
                                onchange="this.form.submit()" <?= $u['id'] === $admin['id'] ? 'disabled' : '' ?>>
                            <?php foreach (['admin', 'usuario', 'conductor'] as $rol): ?>
                            <option value="<?= $rol ?>" <?= $u['role'] === $rol ? 'selected' : '' ?>><?= ucfirst($rol) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td><span class="badge <?= (int)$u['is_active'] ? 'badge-success' : 'badge-neutral' ?>"><?= (int)$u['is_active'] ? 'Activo' : 'Inactivo' ?></span></td>
                <td><?= (int)$u['total_favoritos'] ?> ★</td>
                <td><?= (int)$u['total_alertas'] ?> 🔔</td>
                <td style="font-size:12.5px;color:var(--text-muted);white-space:nowrap"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <form method="post" style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                        <?php if ($u['id'] !== $admin['id']): ?>
                        <button type="submit" name="accion" value="alternar_activo" class="btn btn-ghost btn-sm" title="<?= (int)$u['is_active'] ? 'Desactivar' : 'Activar' ?>">
                            <?= (int)$u['is_active'] ? '⏸' : '▶' ?>
                        </button>
                        <button type="submit" name="accion" value="reset_password" class="btn btn-ghost btn-sm" title="Resetear contraseña">🔑</button>
                        <button type="submit" name="accion" value="eliminar" class="btn btn-danger btn-sm" title="Eliminar"
                                onclick="return confirm('¿Eliminar a <?= e($u['name']) ?> y todos sus datos?')">🗑</button>
                        <?php else: ?>
                        <span class="badge badge-info">Tú</span>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPaginas > 1): ?>
<nav class="pagination">
    <?php for ($i = 1; $i <= min($totalPaginas, 15); $i++): ?>
    <a href="?p=<?= $i ?>&q=<?= urlencode($q) ?>&rol=<?= e($filtroRol) ?>" class="page-link <?= $i === $pagina ? 'current' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
