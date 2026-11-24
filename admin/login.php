<?php
/**
 * Pantalla de inicio de sesion de BlogCMS (Admin / Editor / Autor)
 */
if (Auth::check()) {
    Helpers::redirect('/admin');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requireCsrf();
    $res = Auth::attemptLogin((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
    if ($res['ok']) {
        Helpers::redirect('/admin');
    }
    $error = $res['error'] ?? 'Error al iniciar sesion.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Panel &mdash; <?php echo Security::e(Helpers::option('site_title', 'Bloc-FISI')); ?></title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f0f0f1; font-family: system-ui, -apple-system, sans-serif; color: #1d2327; }
        .login-box { width: 100%; max-width: 390px; background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 28px; box-shadow: 0 6px 20px rgba(0,0,0,0.06); }
        h1 { margin: 0 0 6px; font-size: 22px; text-align: center; color: #0b3b60; }
        p.sub { margin: 0 0 20px; text-align: center; color: #646970; font-size: 13px; }
        label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px; }
        input[type="text"], input[type="password"] { width: 100%; box-sizing: border-box; padding: 9px 11px; border: 1px solid #8c8f94; border-radius: 4px; margin-bottom: 14px; font-size: 14px; }
        button { width: 100%; background: #2271b1; color: #fff; border: none; padding: 10px; border-radius: 4px; font-weight: 700; font-size: 14px; cursor: pointer; }
        button:hover { background: #135e96; }
        .err { background: #fcf0f1; border-left: 4px solid #d63638; padding: 9px 12px; margin-bottom: 14px; font-size: 13px; color: #8a1f22; }
        .roles-hint { margin-top: 18px; padding-top: 14px; border-top: 1px solid #e5e5e5; font-size: 12px; color: #646970; line-height: 1.6; }
        .back-link { display: block; text-align: center; margin-top: 14px; font-size: 13px; color: #2271b1; text-decoration: none; }
    </style>
</head>
<body>
<div class="login-box">
    <h1><?php echo Security::e(Helpers::option('site_title', 'Bloc-FISI')); ?></h1>
    <p class="sub">Panel de Administraci&oacute;n BlogCMS</p>

    <?php if ($error !== ''): ?>
        <div class="err"><?php echo Security::e($error); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo Security::e(Helpers::url('/admin/login')); ?>">
        <?php echo Security::csrfField(); ?>
        <label for="username">Usuario o Correo Electr&oacute;nico</label>
        <input type="text" id="username" name="username" required autofocus>

        <label for="password">Contrase&ntilde;a</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Acceder</button>
    </form>

    <div class="roles-hint">
        <strong>Cuentas de prueba por rol:</strong><br>
        Admin: <code>admin</code> / <code>AdminFisi2026!</code><br>
        Editor: <code>editor_fisi</code> / <code>EditorFisi2026!</code><br>
        Autor: <code>autor_fisi</code> / <code>AutorFisi2026!</code>
    </div>
    <a class="back-link" href="<?php echo Security::e(Helpers::url('/')); ?>">&larr; Volver al sitio p&uacute;blico</a>
</div>
</body>
</html>
