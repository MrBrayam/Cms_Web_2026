<?php
/**
 * Plugin: Botones de Compartir en Redes Sociales
 */

PluginManager::addFilter('after_post_content', function (string $html, array $post) {
    $cfg      = PluginManager::getSettings('social-share');
    $networks = $cfg['networks'] ?? ['whatsapp', 'facebook', 'x', 'linkedin', 'copy'];
    if (empty($networks)) {
        return $html;
    }

    $postUrl = Helpers::url('/post/' . $post['slug']);
    $uEnc    = rawurlencode($postUrl);
    $tEnc    = rawurlencode($post['title']);

    $links = [
        'whatsapp' => ['WhatsApp', "https://api.whatsapp.com/send?text={$tEnc}%20{$uEnc}", '#16a34a'],
        'facebook' => ['Facebook', "https://www.facebook.com/sharer/sharer.php?u={$uEnc}", '#1d4ed8'],
        'x'        => ['X / Twitter', "https://twitter.com/intent/tweet?text={$tEnc}&url={$uEnc}", '#0f172a'],
        'linkedin' => ['LinkedIn', "https://www.linkedin.com/sharing/share-offsite/?url={$uEnc}", '#0369a1'],
    ];

    $box  = '<div class="bcms-share-box" style="margin:2rem 0 1.25rem;padding:1rem 1.2rem;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;color:#1e293b;">';
    $box .= '<strong style="display:block;margin-bottom:0.65rem;font-size:0.9rem;">Compartir este art&iacute;culo:</strong>';
    $box .= '<div style="display:flex;flex-wrap:wrap;gap:0.5rem;">';
    foreach ($networks as $net) {
        if (isset($links[$net])) {
            [$label, $href, $bg] = $links[$net];
            $box .= '<a href="' . Security::e($href) . '" target="_blank" rel="noopener noreferrer" style="background:' . $bg . ';color:#fff;padding:6px 12px;border-radius:6px;font-size:0.84rem;font-weight:600;text-decoration:none;">' . Security::e($label) . '</a>';
        }
    }
    if (in_array('copy', $networks, true)) {
        $box .= '<button type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(\'' . Security::e($postUrl) . '\');this.textContent=\'Enlace copiado!\';" style="background:#475569;color:#fff;border:none;padding:6px 12px;border-radius:6px;font-size:0.84rem;font-weight:600;cursor:pointer;">Copiar enlace</button>';
    }
    $box .= '</div></div>';

    return $html . $box;
}, 10);

PluginManager::addFilter('admin_menu_items', function (array $items) {
    $items[] = ['slug' => 'social-share', 'label' => 'Redes Sociales'];
    return $items;
});

PluginManager::addAction('admin_panel_social-share', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        $nets = $_POST['networks'] ?? [];
        PluginManager::saveSettings('social-share', ['networks' => array_values($nets)]);
        Helpers::flash('ok', 'Redes sociales guardadas.');
        Helpers::redirect('/admin/plugin-panel?slug=social-share');
    }
    $cfg  = PluginManager::getSettings('social-share');
    $nets = $cfg['networks'] ?? ['whatsapp', 'facebook', 'x', 'linkedin', 'copy'];
    ?>
    <div class="page-header"><h1>Botones de Compartir en Redes Sociales</h1></div>
    <div class="card">
        <form method="post">
            <?php echo Security::csrfField(); ?>
            <?php foreach (['whatsapp' => 'WhatsApp', 'facebook' => 'Facebook', 'x' => 'X / Twitter', 'linkedin' => 'LinkedIn', 'copy' => 'Copiar enlace'] as $k => $lbl): ?>
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <input type="checkbox" name="networks[]" value="<?php echo $k; ?>" <?php echo in_array($k, $nets, true) ? 'checked' : ''; ?>>
                    <span><?php echo Security::e($lbl); ?></span>
                </label>
            <?php endforeach; ?>
            <button class="btn" type="submit" style="margin-top:10px;">Guardar Redes Activas</button>
        </form>
    </div>
    <?php
});
