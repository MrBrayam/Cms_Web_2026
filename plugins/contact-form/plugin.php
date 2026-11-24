<?php
/**
 * Plugin: Formulario de Contacto Personalizable ([contacto])
 */

PluginManager::registerRoute('POST', '/contacto/enviar', function () {
    Security::requireCsrf();
    $cfg     = PluginManager::getSettings('contact-form');
    $fields  = $cfg['fields'] ?? ['name', 'email', 'subject', 'message'];
    $name    = trim((string) ($_POST['cf_name'] ?? 'Anonimo'));
    $email   = trim((string) ($_POST['cf_email'] ?? 'sin-correo@blogcms.localhost'));
    $subject = trim((string) ($_POST['cf_subject'] ?? 'Consulta desde el sitio'));
    $message = trim((string) ($_POST['cf_message'] ?? ''));
    $phone   = trim((string) ($_POST['cf_phone'] ?? ''));
    $backUrl = trim((string) ($_POST['cf_return'] ?? '/pagina/contacto'));

    if (in_array('email', $fields, true) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Helpers::flash('err', 'Ingresa un correo electronico valido.');
        Helpers::redirect($backUrl);
    }
    if ($message === '') {
        Helpers::flash('err', 'El campo de mensaje no puede estar vacio.');
        Helpers::redirect($backUrl);
    }

    $extra = $phone !== '' ? json_encode(['telefono' => $phone], JSON_UNESCAPED_UNICODE) : '{}';
    Database::execute(
        'INSERT INTO contact_messages (name, email, subject, message, extra_json) VALUES (?, ?, ?, ?, ?)',
        [substr($name, 0, 120), substr($email, 0, 140), substr($subject, 0, 200), substr($message, 0, 3000), $extra]
    );

    Helpers::flash('ok', (string) ($cfg['success_msg'] ?? 'Mensaje enviado correctamente.'));
    Helpers::redirect($backUrl);
});

PluginManager::addFilter('content_render', function (string $content) {
    if (!str_contains($content, '[contacto]')) {
        return $content;
    }

    $cfg    = PluginManager::getSettings('contact-form');
    $fields = $cfg['fields'] ?? ['name', 'email', 'subject', 'message'];
    $reqUri = parse_url($_SERVER['REQUEST_URI'] ?? '/pagina/contacto', PHP_URL_PATH) ?: '/pagina/contacto';

    $form = '<form method="post" action="' . Security::e(Helpers::url('/contacto/enviar')) . '" style="max-width:580px;padding:1.5rem;border:1px solid #cbd5e1;border-radius:10px;background:#ffffff;color:#1e293b;margin:1.25rem 0;">';
    $form .= Security::csrfField();
    $form .= '<input type="hidden" name="cf_return" value="' . Security::e($reqUri) . '">';

    if (in_array('name', $fields, true)) {
        $form .= '<div style="margin-bottom:12px;"><label style="display:block;font-weight:700;margin-bottom:4px;">Nombre completo</label><input type="text" name="cf_name" required style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;"></div>';
    }
    if (in_array('email', $fields, true)) {
        $form .= '<div style="margin-bottom:12px;"><label style="display:block;font-weight:700;margin-bottom:4px;">Correo electr&oacute;nico</label><input type="email" name="cf_email" required style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;"></div>';
    }
    if (in_array('phone', $fields, true)) {
        $form .= '<div style="margin-bottom:12px;"><label style="display:block;font-weight:700;margin-bottom:4px;">Tel&eacute;fono / WhatsApp</label><input type="text" name="cf_phone" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;"></div>';
    }
    if (in_array('subject', $fields, true)) {
        $form .= '<div style="margin-bottom:12px;"><label style="display:block;font-weight:700;margin-bottom:4px;">Asunto</label><input type="text" name="cf_subject" required style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;"></div>';
    }
    $form .= '<div style="margin-bottom:14px;"><label style="display:block;font-weight:700;margin-bottom:4px;">Mensaje</label><textarea name="cf_message" required style="width:100%;min-height:110px;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;"></textarea></div>';
    $form .= '<button type="submit" style="background:#0b3b60;color:#fff;border:none;padding:10px 18px;border-radius:6px;font-weight:700;cursor:pointer;">Enviar mensaje</button>';
    $form .= '</form>';

    return str_replace('[contacto]', $form, $content);
});

PluginManager::addFilter('admin_menu_items', function (array $items) {
    $items[] = ['slug' => 'contact-form', 'label' => 'Formulario Contacto'];
    return $items;
});

PluginManager::addAction('admin_panel_contact-form', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        $fields = $_POST['fields'] ?? ['name', 'email', 'message'];
        $msg    = trim((string) ($_POST['success_msg'] ?? 'Gracias por tu mensaje.'));
        PluginManager::saveSettings('contact-form', ['fields' => array_values($fields), 'success_msg' => $msg]);
        Helpers::flash('ok', 'Formulario personalizado guardado.');
        Helpers::redirect('/admin/plugin-panel?slug=contact-form');
    }

    $cfg      = PluginManager::getSettings('contact-form');
    $fields   = $cfg['fields'] ?? ['name', 'email', 'subject', 'message'];
    $messages = Database::fetchAll('SELECT * FROM contact_messages ORDER BY id DESC LIMIT 20');
    ?>
    <div class="page-header"><h1>Formulario de Contacto Personalizable</h1><code>Shortcode: [contacto]</code></div>
    <div class="grid-2">
        <div class="card">
            <h2 style="margin-top:0;">Personalizar Campos</h2>
            <form method="post">
                <?php echo Security::csrfField(); ?>
                <?php foreach (['name' => 'Nombre completo', 'email' => 'Correo electronico', 'phone' => 'Telefono / WhatsApp', 'subject' => 'Asunto'] as $k => $lbl): ?>
                    <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                        <input type="checkbox" name="fields[]" value="<?php echo $k; ?>" <?php echo in_array($k, $fields, true) ? 'checked' : ''; ?>>
                        <span><?php echo Security::e($lbl); ?></span>
                    </label>
                <?php endforeach; ?>
                <input type="hidden" name="fields[]" value="message">
                <div class="form-row" style="margin-top:12px;">
                    <label>Mensaje de confirmaci&oacute;n</label>
                    <input type="text" name="success_msg" value="<?php echo Security::e($cfg['success_msg'] ?? ''); ?>">
                </div>
                <button class="btn" type="submit">Guardar Personalizaci&oacute;n</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Mensajes Recibidos (<?php echo count($messages); ?>)</h2>
            <table class="data-table">
                <thead><tr><th>Remitente</th><th>Asunto / Mensaje</th><th>Fecha</th></tr></thead>
                <tbody>
                <?php foreach ($messages as $m): ?>
                    <tr>
                        <td><strong><?php echo Security::e($m['name']); ?></strong><br><small><?php echo Security::e($m['email']); ?></small></td>
                        <td><strong><?php echo Security::e($m['subject']); ?></strong><br><small><?php echo Security::e($m['message']); ?></small></td>
                        <td><small><?php echo Security::e($m['created_at']); ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
});
