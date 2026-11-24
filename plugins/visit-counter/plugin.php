<?php
/**
 * Plugin: Contador de Visitas y Estadisticas
 */

PluginManager::addAction('page_visit', function (?int $postId, string $path) {
    Security::startSession();
    if (!isset($_SESSION['_visited_paths'])) {
        $_SESSION['_visited_paths'] = [];
    }
    if (!isset($_SESSION['_visited_paths'][$path])) {
        $_SESSION['_visited_paths'][$path] = true;
        if ($postId !== null && $postId > 0) {
            Database::execute('UPDATE posts SET views = views + 1 WHERE id = ?', [$postId]);
        }
        $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        $ua     = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
        Database::execute(
            'INSERT INTO visit_logs (post_id, page_path, ip_hash, user_agent) VALUES (?, ?, ?, ?)',
            [$postId, substr($path, 0, 250), $ipHash, $ua]
        );
    }
});

PluginManager::addFilter('after_post_content', function (string $html, array $post) {
    $cfg = PluginManager::getSettings('visit-counter');
    if (empty($cfg['show_badge'])) {
        return $html;
    }
    $badge = '<p style="font-size:0.86rem;color:#64748b;margin-top:1rem;"><strong>Lecturas registradas:</strong> ' . (int) ($post['views'] ?? 0) . ' visitas</p>';
    return $badge . $html;
}, 5);

PluginManager::addFilter('admin_menu_items', function (array $items) {
    $items[] = ['slug' => 'visit-counter', 'label' => 'Estadisticas Visitas'];
    return $items;
});

PluginManager::addAction('admin_panel_visit-counter', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        PluginManager::saveSettings('visit-counter', ['show_badge' => isset($_POST['show_badge'])]);
        Helpers::flash('ok', 'Preferencia de contador actualizada.');
        Helpers::redirect('/admin/plugin-panel?slug=visit-counter');
    }

    $cfg        = PluginManager::getSettings('visit-counter');
    $totalHits  = (int) Database::fetchValue('SELECT COUNT(*) FROM visit_logs', [], 0);
    $totalViews = (int) Database::fetchValue('SELECT COALESCE(SUM(views), 0) FROM posts', [], 0);
    $topPosts   = Database::fetchAll('SELECT title, slug, views FROM posts ORDER BY views DESC LIMIT 10');
    $maxViews   = max(1, (int) ($topPosts[0]['views'] ?? 1));
    $recent     = Database::fetchAll('SELECT page_path, visited_at FROM visit_logs ORDER BY id DESC LIMIT 12');
    ?>
    <div class="page-header"><h1>Contador de Visitas y Estad&iacute;sticas</h1></div>

    <div class="grid-stats">
        <div class="stat-box"><div class="stat-num"><?php echo $totalViews; ?></div><div class="stat-lbl">Lecturas totales en art&iacute;culos</div></div>
        <div class="stat-box"><div class="stat-num"><?php echo $totalHits; ?></div><div class="stat-lbl">Sesiones / P&aacute;ginas registradas</div></div>
    </div>

    <div class="card">
        <form method="post">
            <?php echo Security::csrfField(); ?>
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="show_badge" value="1" <?php echo !empty($cfg['show_badge']) ? 'checked' : ''; ?>>
                <span>Mostrar contador de lecturas en cada art&iacute;culo</span>
            </label>
            <button class="btn btn-sm" type="submit" style="margin-top:10px;">Guardar</button>
        </form>
    </div>

    <div class="grid-2">
        <div class="card">
            <h2 style="margin-top:0;">Art&iacute;culos M&aacute;s Visitados</h2>
            <?php foreach ($topPosts as $tp): $pct = (int) round(((int) $tp['views'] / $maxViews) * 100); ?>
                <div style="margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px;">
                        <span><?php echo Security::e($tp['title']); ?></span>
                        <strong><?php echo (int) $tp['views']; ?></strong>
                    </div>
                    <div style="background:#e2e8f0;height:8px;border-radius:4px;overflow:hidden;">
                        <div style="background:#2271b1;height:100%;width:<?php echo $pct; ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">&Uacute;ltimas Visitas Registradas</h2>
            <table class="data-table">
                <thead><tr><th>Ruta</th><th>Fecha</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><code><?php echo Security::e($r['page_path']); ?></code></td>
                        <td><small><?php echo Security::e($r['visited_at']); ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
});
