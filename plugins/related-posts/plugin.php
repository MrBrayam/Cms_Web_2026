<?php
/**
 * Plugin: Posts Relacionados por Etiquetas
 */

PluginManager::addFilter('after_post_content', function (string $html, array $post, array $tags) {
    if (empty($tags)) {
        return $html;
    }
    $cfg    = PluginManager::getSettings('related-posts');
    $limit  = max(1, min(6, (int) ($cfg['limit'] ?? 3)));
    $tagIds = array_map(fn($t) => (int) $t['id'], $tags);
    $ph     = implode(',', array_fill(0, count($tagIds), '?'));

    $params = array_merge([(int) $post['id'], 'publish'], $tagIds, [$limit]);
    $sql    = "SELECT p.id, p.title, p.slug, p.featured_image, p.created_at, COUNT(pt.tag_id) AS shared_tags
               FROM posts p
               JOIN post_tags pt ON pt.post_id = p.id
               WHERE p.id <> ? AND p.status = ? AND pt.tag_id IN ($ph)
               GROUP BY p.id, p.title, p.slug, p.featured_image, p.created_at
               ORDER BY shared_tags DESC, p.created_at DESC
               LIMIT ?";
    $related = Database::fetchAll($sql, $params);
    if (empty($related)) {
        return $html;
    }

    $box  = '<section class="bcms-related-box" style="margin:2rem 0;padding:1.25rem;border:1px solid #cbd5e1;border-radius:10px;background:#f8fafc;color:#1e293b;">';
    $box .= '<h3 style="margin:0 0 1rem;font-size:1.05rem;">Art&iacute;culos Relacionados por Etiquetas</h3>';
    $box .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">';
    foreach ($related as $r) {
        $box .= '<a href="' . Security::e(Helpers::url('/post/' . $r['slug'])) . '" style="display:block;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;background:#fff;color:#0f172a;text-decoration:none;">';
        if (!empty($r['featured_image'])) {
            $box .= '<img src="' . Security::e(Helpers::url($r['featured_image'])) . '" alt="" style="width:100%;height:110px;object-fit:cover;">';
        }
        $box .= '<div style="padding:10px;"><strong style="display:block;font-size:0.9rem;line-height:1.3;">' . Security::e($r['title']) . '</strong>';
        $box .= '<small style="color:#64748b;">' . (int) $r['shared_tags'] . ' etiqueta(s) en com&uacute;n</small></div></a>';
    }
    $box .= '</div></section>';

    return $html . $box;
}, 15);

PluginManager::addFilter('admin_menu_items', function (array $items) {
    $items[] = ['slug' => 'related-posts', 'label' => 'Posts Relacionados'];
    return $items;
});

PluginManager::addAction('admin_panel_related-posts', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        $limit = max(1, min(6, (int) ($_POST['limit'] ?? 3)));
        PluginManager::saveSettings('related-posts', ['limit' => $limit]);
        Helpers::flash('ok', 'Configuracion de posts relacionados guardada.');
        Helpers::redirect('/admin/plugin-panel?slug=related-posts');
    }
    $cfg = PluginManager::getSettings('related-posts');
    ?>
    <div class="page-header"><h1>Posts Relacionados por Etiquetas</h1></div>
    <div class="card">
        <form method="post">
            <?php echo Security::csrfField(); ?>
            <div class="form-row">
                <label>Cantidad m&aacute;xima de art&iacute;culos relacionados (1-6)</label>
                <input type="number" name="limit" min="1" max="6" value="<?php echo (int) ($cfg['limit'] ?? 3); ?>" style="max-width:140px;">
            </div>
            <button class="btn" type="submit">Guardar</button>
        </form>
    </div>
    <?php
});
