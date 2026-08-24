<?php
/**
 * LLEGAPP 3 · Registro de usuarios
 */
require_once dirname(__DIR__) . '/config/configuracion.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (estaAutenticado()) {
    header('Location: ' . APP_BASE . '/pages/mapa.php');
    exit;
}

$error = $_SESSION['registro_error'] ?? null;
$campos = $_SESSION['registro_campos'] ?? [];
unset($_SESSION['registro_error'], $_SESSION['registro_campos']);

$pageTitle = 'Crear cuenta · ' . APP_NAME;
$dataPage = 'registro';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="auth-layout">
    <div class="auth-card">
        <div class="auth-hero">
            <div style="font-size:40px;margin-bottom:8px">🎫</div>
            <h1 style="font-size:24px;letter-spacing:-.02em">Crea tu cuenta</h1>
            <p style="color:var(--text-muted);font-size:14px">Favoritos, alertas y planificador personalizado</p>
        </div>

        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <form method="post" action="<?= APP_BASE ?>/processes/registro.php" class="card" novalidate>
            <?= csrfField() ?>

            <div class="field">
                <label class="label" for="name">Nombre completo</label>
                <input class="input" type="text" id="name" name="name" required minlength="3" maxlength="100"
                       placeholder="Ej: María Gómez" value="<?= e($campos['name'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="label" for="email">Correo electrónico</label>
                <input class="input" type="email" id="email" name="email" required
                       placeholder="tu@correo.com" autocomplete="email" value="<?= e($campos['email'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="field">
                    <label class="label" for="password">Contraseña</label>
                    <input class="input" type="password" id="password" name="password" required minlength="8"
                           placeholder="Mín. 8 caracteres" autocomplete="new-password">
                    <p class="field-hint">Al menos una letra y un número.</p>
                </div>
                <div class="field">
                    <label class="label" for="password_confirm">Confirmar</label>
                    <input class="input" type="password" id="password_confirm" name="password_confirm" required
                           placeholder="Repite la clave" autocomplete="new-password">
                </div>
            </div>

            <div class="field">
                <label class="label" for="phone">Teléfono <span style="color:var(--text-muted);font-weight:400">(opcional)</span></label>
                <input class="input" type="tel" id="phone" name="phone" maxlength="30"
                       placeholder="+57 300 000 0000" value="<?= e($campos['phone'] ?? '') ?>">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Crear mi cuenta</button>

            <p style="text-align:center;font-size:13.5px;color:var(--text-muted);margin-top:16px">
                ¿Ya tienes cuenta? <a href="<?= APP_BASE ?>/pages/login.php" style="color:var(--primary);font-weight:600">Inicia sesión</a>
            </p>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
