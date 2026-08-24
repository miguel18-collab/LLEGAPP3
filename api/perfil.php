<?php
/**
 * API · Perfil de usuario
 * POST   /api/perfil.php  {name, phone} | {accion:"password", ...} | {accion:"preferencias", ...}
 * DELETE /api/perfil.php  → elimina la cuenta
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$currentUser = requerirAuth();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        requerirCSRF();
        dbq('DELETE FROM users WHERE id = ?', [$currentUser['id']]);
        cerrarSesion();
        jsonResponse(null, 200, 'Cuenta eliminada');
    }

    requireMethod('POST');
    requerirCSRF();

    $accion = input('accion', 'datos');

    // ---------- Datos personales ----------
    if ($accion === 'datos') {
        $name = trim((string)input('name', ''));
        $phone = trim((string)input('phone', ''));

        if (mb_strlen($name) < 3) jsonError('El nombre debe tener al menos 3 caracteres.', 422);

        dbq('UPDATE users SET name = ?, phone = ? WHERE id = ?', [$name, $phone ?: null, $currentUser['id']]);
        $_SESSION['user_name'] = $name;
        jsonResponse(null, 200, 'Datos actualizados correctamente.');
    }

    // ---------- Contraseña ----------
    if ($accion === 'password') {
        $actual = (string)input('password_actual', '');
        $nueva  = (string)input('password_nuevo', '');

        $hash = dbVal('SELECT password_hash FROM users WHERE id = ?', [$currentUser['id']], '');
        if (!password_verify($actual, $hash)) {
            jsonError('La contraseña actual es incorrecta.', 422);
        }
        if (!passwordSegura($nueva)) {
            jsonError('La nueva contraseña debe tener mínimo 8 caracteres, con letras y números.', 422);
        }
        if (password_verify($nueva, $hash)) {
            jsonError('La nueva contraseña no puede ser igual a la actual.', 422);
        }

        dbq('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($nueva, PASSWORD_BCRYPT), $currentUser['id']]);
        logMessage('info', 'Contraseña actualizada', ['user' => $currentUser['email']]);
        jsonResponse(null, 200, 'Contraseña actualizada.');
    }

    // ---------- Preferencias ----------
    if ($accion === 'preferencias') {
        $tema = in_array(input('theme'), ['light', 'dark', 'auto'], true) ? input('theme') : 'auto';
        $notifs = !empty(input('notifications_enabled')) ? 1 : 0;
        $ahead = min(3, max(1, (int)input('notify_stops_ahead', 2)));

        if ($tema !== 'auto') {
            setcookie('theme', $tema, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
        }

        dbq(
            'INSERT INTO user_preferences (user_id, theme, notifications_enabled, notify_stops_ahead)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE theme = VALUES(theme), notifications_enabled = VALUES(notifications_enabled),
                                     notify_stops_ahead = VALUES(notify_stops_ahead)',
            [$currentUser['id'], $tema, $notifs, $ahead]
        );
        jsonResponse(null, 200, 'Preferencias guardadas.');
    }

    jsonError('Acción desconocida', 422);
} catch (Exception $e) {
    logMessage('error', 'Error perfil', ['msg' => $e->getMessage()]);
    jsonError('Error interno', 500);
}
