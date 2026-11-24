<?php
/**
 * Plugin: SEO Optimizer (Meta tags, OpenGraph y Sitemap XML automatico)
 */

PluginManager::addAction('head_meta', function (array $ctx) {
    $cfg       = PluginManager::getSettings('seo-optimizer');
    $robots    = $cfg['default_robots'] ?? 'index, follow';
    $siteTitle = Helpers::option('site_title', 'Bloc-FISI');
    $target    = $ctx['seoTarget'] ?? null;
    $type      = $ctx['seoType'] ?? 'website';

    $title = $ctx['pageTitle'] ?? $siteTitle;
    $desc  = Helpers::option('site_tagline', '');
    $img   = '';
    $path  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    if (is_array($target)) {
        if (!empty($target['meta_description'])) {
            $desc = $target['meta_description'];
        } elseif (!empty($target['excerpt'])) {
            $desc = $target['excerpt'];
        }
        if (!empty($target['featured_image'])) {
            $img = Helpers::url($target['featured_image']);
        }
    }

    $canonical = Helpers::url($path);
    echo '<meta name="description" content="' . Security::e($desc) . '">' . "\n";
    echo '<meta name="robots" content="' . Security::e($robots) . '">' . "\n";
    echo '<link rel="canonical" href="' . Security::e($canonical) . '">' . "\n";
    echo '<meta property="og:title" content="' . Security::e($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . Security::e($desc) . '">' . "\n";
    echo '<meta property="og:type" content="' . Security::e($type) . '">' . "\n";
    echo '<meta property="og:url" content="' . Security::e($canonical) . '">' . "\n";
    if ($img !== '') {
        echo '<meta property="og:image" content="' . Security::e($img) . '">' . "\n";
    }
});

PluginManager::registerRoute('GET', '/sitemap.xml', function () {
    $cfg          = PluginManager::getSettings('seo-optimizer');
    $includePages = !empty($cfg['include_pages']);

    header('Content-Type: application/xml; charset=utf-8');
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    echo "  <url><loc>" . Security::e(Helpers::url('/')) . "</loc><priority>1.0</priority></url>\n";

    $posts = Database::fetchAll('SELECT slug, updated_at FROM posts WHERE status = ? ORDER BY updated_at DESC', ['publish']);
    foreach ($posts as $p) {
        $dt = substr((string) $p['updated_at'], 0, 10);
        echo "  <url><loc>" . Security::e(Helpers::url('/post/' . $p['slug'])) . "</loc><lastmod>" . Security::e($dt) . "</lastmod><priority>0.8</priority></url>\n";
    }

    if ($includePages) {
        $pages = Database::fetchAll('SELECT slug, updated_at FROM pages WHERE status = ?', ['publish']);
        foreach ($pages as $pg) {
            $dt = substr((string) $pg['updated_at'], 0, 10);
            echo "  <url><loc>" . Security::e(Helpers::url('/pagina/' . $pg['slug'])) . "</loc><lastmod>" . Security::e($dt) . "</lastmod><priority>0.7</priority></url>\n";
        }
    }

    $cats = Database::fetchAll('SELECT slug FROM categories');
    foreach ($cats as $c) {
        echo "  <url><loc>" . Security::e(Helpers::url('/categoria/' . $c['slug'])) . "</loc><priority>0.5</priority></url>\n";
    }
    echo "</urlset>\n";
});

PluginManager::addFilter('admin_menu_items', function (array $items) {
    $items[] = ['slug' => 'seo-optimizer', 'label' => 'SEO Optimizer'];
    return $items;
});

PluginManager::addAction('admin_panel_seo-optimizer', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        $settings = [
            'default_robots' => ($_POST['default_robots'] ?? 'index, follow') === 'noindex, nofollow' ? 'noindex, nofollow' : 'index, follow',
            'include_pages'  => isset($_POST['include_pages']),
        ];
        PluginManager::saveSettings('seo-optimizer', $settings);
        Helpers::flash('ok', 'Configuracion SEO guardada.');
        Helpers::redirect('/admin/plugin-panel?slug=seo-optimizer');
    }
    $cfg = PluginManager::getSettings('seo-optimizer');
    ?>
    <div class="page-header">
        <h1>SEO Optimizer</h1>
        <a class="btn" target="_blank" href="<?php echo Security::e(Helpers::url('/sitemap.xml')); ?>">Abrir /sitemap.xml &rarr;</a>
    </div>
    <div class="card">
        <form method="post">
            <?php echo Security::csrfField(); ?>
            <div class="form-row">
                <label>Directiva Robots por defecto</label>
                <select name="default_robots">
                    <option value="index, follow" <?php echo ($cfg['default_robots'] ?? '') === 'index, follow' ? 'selected' : ''; ?>>index, follow (Indexar sitio)</option>
                    <option value="noindex, nofollow" <?php echo ($cfg['default_robots'] ?? '') === 'noindex, nofollow' ? 'selected' : ''; ?>>noindex, nofollow</option>
                </select>
            </div>
            <div class="form-row">
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="include_pages" value="1" <?php echo !empty($cfg['include_pages']) ? 'checked' : ''; ?>>
                    <span>Incluir paginas estaticas en /sitemap.xml</span>
                </label>
            </div>
            <button class="btn" type="submit">Guardar Configuracion SEO</button>
        </form>
    </div>
    <?php
});
