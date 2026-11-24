<?php
/**
 * Front Controller & Router principal de BlogCMS (Propia Arquitectura PHP)
 */

define('BLOGCMS_ROOT', __DIR__);

require_once BLOGCMS_ROOT . '/core/Security.php';
require_once BLOGCMS_ROOT . '/core/Database.php';
require_once BLOGCMS_ROOT . '/core/Helpers.php';
require_once BLOGCMS_ROOT . '/core/Auth.php';
require_once BLOGCMS_ROOT . '/core/PluginManager.php';
require_once BLOGCMS_ROOT . '/core/ThemeManager.php';

Security::sendSecurityHeaders();

$configFile = BLOGCMS_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    header('Location: /instalador.php');
    exit;
}

$BLOGCMS_CONFIG = require $configFile;
if (!empty($BLOGCMS_CONFIG['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

try {
    Database::connect($BLOGCMS_CONFIG);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Error de conexion a la base de datos. Ejecuta /instalador.php para configurar la conexion.');
}

Security::startSession();
PluginManager::loadActivePlugins();
PluginManager::doAction('init');

$method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uriPath = '/' . trim($uriPath, '/');
if ($uriPath !== '/' && str_ends_with($uriPath, '/')) {
    $uriPath = rtrim($uriPath, '/');
}

// 1. Verificar rutas registradas por Plugins activos (ej: /sitemap.xml, /contacto/enviar)
$pluginHandler = PluginManager::matchRoute($method, $uriPath);
if ($pluginHandler !== null) {
    $pluginHandler();
    exit;
}

// 2. Rutas del Panel de Administracion (/admin/...)
if ($uriPath === '/admin' || str_starts_with($uriPath, '/admin/')) {
    require BLOGCMS_ROOT . '/admin/router.php';
    exit;
}

// 3. Envio de comentarios (Modulo Comentarios con Moderacion)
if ($uriPath === '/comentario/enviar' && $method === 'POST') {
    Security::requireCsrf();
    $postId  = (int) ($_POST['post_id'] ?? 0);
    $name    = trim((string) ($_POST['author_name'] ?? ''));
    $email   = trim((string) ($_POST['author_email'] ?? ''));
    $content = trim((string) ($_POST['content'] ?? ''));

    $post = Database::fetchOne('SELECT id, slug FROM posts WHERE id = ? AND status = ?', [$postId, 'publish']);
    if (!$post) {
        http_response_code(404);
        exit('Articulo no encontrado.');
    }
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $content === '') {
        Helpers::flash('err', 'Completa tu nombre, un correo valido y tu comentario.');
        Helpers::redirect('/post/' . $post['slug'] . '#comments');
    }

    $needsMod = Helpers::option('comment_moderation', '1') === '1';
    $status   = $needsMod ? 'pending' : 'approved';
    Database::execute(
        'INSERT INTO comments (post_id, author_name, author_email, content, status) VALUES (?, ?, ?, ?, ?)',
        [$postId, substr($name, 0, 100), substr($email, 0, 120), substr($content, 0, 2000), $status]
    );

    Helpers::flash('ok', $needsMod ? 'Comentario enviado. Queda pendiente de aprobacion por moderacion.' : 'Comentario publicado.');
    Helpers::redirect('/post/' . $post['slug'] . '#comments');
}

// 4. Inicio / Busqueda (Home)
if ($uriPath === '/') {
    PluginManager::doAction('page_visit', null, '/');
    $perPage = max(1, (int) Helpers::option('posts_per_page', '9'));
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $offset  = ($page - 1) * $perPage;
    $search  = trim((string) ($_GET['q'] ?? ''));

    if ($search !== '') {
        $like       = '%' . $search . '%';
        $totalPosts = (int) Database::fetchValue('SELECT COUNT(*) FROM posts WHERE status = ? AND (title LIKE ? OR content LIKE ?)', ['publish', $like, $like], 0);
        $posts      = Database::fetchAll(
            'SELECT p.*, u.display_name AS author_name, c.name AS category_name, c.slug AS category_slug
             FROM posts p
             JOIN users u ON u.id = p.author_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = ? AND (p.title LIKE ? OR p.content LIKE ?)
             ORDER BY p.created_at DESC, p.id DESC LIMIT ? OFFSET ?',
            ['publish', $like, $like, $perPage, $offset]
        );
    } else {
        $totalPosts = (int) Database::fetchValue('SELECT COUNT(*) FROM posts WHERE status = ?', ['publish'], 0);
        $posts      = Database::fetchAll(
            'SELECT p.*, u.display_name AS author_name, c.name AS category_name, c.slug AS category_slug
             FROM posts p
             JOIN users u ON u.id = p.author_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = ?
             ORDER BY p.created_at DESC, p.id DESC LIMIT ? OFFSET ?',
            ['publish', $perPage, $offset]
        );
    }

    $totalPages = max(1, (int) ceil($totalPosts / $perPage));
    ThemeManager::render('home', [
        'pageTitle'   => Helpers::option('site_title', 'Bloc-FISI') . ($search !== '' ? ' - Busqueda: ' . $search : ''),
        'posts'       => $posts,
        'currentPage' => $page,
        'totalPages'  => $totalPages,
        'searchQuery' => $search,
    ]);
    exit;
}

// 5. Vista individual de Articulo (/post/{slug})
if (preg_match('#^/post/([a-zA-Z0-9_-]+)$#', $uriPath, $m)) {
    $post = Database::fetchOne(
        'SELECT p.*, u.display_name AS author_name, c.name AS category_name, c.slug AS category_slug
         FROM posts p
         JOIN users u ON u.id = p.author_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.slug = ? AND p.status = ?',
        [$m[1], 'publish']
    );
    if (!$post) {
        http_response_code(404);
        ThemeManager::render('404', ['pageTitle' => '404 - Articulo no encontrado']);
        exit;
    }

    PluginManager::doAction('page_visit', (int) $post['id'], $uriPath);
    // Refrescar views tras posible incremento por plugin visit-counter
    $post['views'] = (int) Database::fetchValue('SELECT views FROM posts WHERE id = ?', [(int) $post['id']], $post['views']);

    $tags = Database::fetchAll(
        'SELECT t.* FROM tags t JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name ASC',
        [(int) $post['id']]
    );
    $comments = Database::fetchAll(
        'SELECT * FROM comments WHERE post_id = ? AND status = ? ORDER BY created_at ASC',
        [(int) $post['id'], 'approved']
    );

    $renderedContent = Helpers::renderShortcodes($post['content']);
    $renderedContent = PluginManager::applyFilters('after_post_content', $renderedContent, $post, $tags);

    ThemeManager::render('post', [
        'pageTitle'       => ($post['meta_title'] !== '' ? $post['meta_title'] : $post['title']) . ' - ' . Helpers::option('site_title', 'Bloc-FISI'),
        'seoTarget'       => $post,
        'seoType'         => 'article',
        'post'            => $post,
        'renderedContent' => $renderedContent,
        'tags'            => $tags,
        'comments'        => $comments,
        'flash'           => Helpers::pullFlash(),
    ]);
    exit;
}

// 6. Archivo por Categoria (/categoria/{slug})
if (preg_match('#^/categoria/([a-zA-Z0-9_-]+)$#', $uriPath, $m)) {
    $cat = Database::fetchOne('SELECT * FROM categories WHERE slug = ?', [$m[1]]);
    if (!$cat) {
        http_response_code(404);
        ThemeManager::render('404', ['pageTitle' => '404 - Categoria no encontrada']);
        exit;
    }
    PluginManager::doAction('page_visit', null, $uriPath);
    $posts = Database::fetchAll(
        'SELECT p.*, u.display_name AS author_name, c.name AS category_name, c.slug AS category_slug
         FROM posts p
         JOIN users u ON u.id = p.author_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.category_id = ? AND p.status = ?
         ORDER BY p.created_at DESC',
        [(int) $cat['id'], 'publish']
    );
    ThemeManager::render('archive', [
        'pageTitle'   => 'Categoria: ' . $cat['name'] . ' - ' . Helpers::option('site_title', 'Bloc-FISI'),
        'archiveTitle'=> 'Categoria: ' . $cat['name'],
        'archiveDesc' => $cat['description'] ?? '',
        'posts'       => $posts,
    ]);
    exit;
}

// 7. Archivo por Etiqueta (/etiqueta/{slug})
if (preg_match('#^/etiqueta/([a-zA-Z0-9_-]+)$#', $uriPath, $m)) {
    $tag = Database::fetchOne('SELECT * FROM tags WHERE slug = ?', [$m[1]]);
    if (!$tag) {
        http_response_code(404);
        ThemeManager::render('404', ['pageTitle' => '404 - Etiqueta no encontrada']);
        exit;
    }
    PluginManager::doAction('page_visit', null, $uriPath);
    $posts = Database::fetchAll(
        'SELECT p.*, u.display_name AS author_name, c.name AS category_name, c.slug AS category_slug
         FROM posts p
         JOIN users u ON u.id = p.author_id
         LEFT JOIN categories c ON c.id = p.category_id
         JOIN post_tags pt ON pt.post_id = p.id
         WHERE pt.tag_id = ? AND p.status = ?
         ORDER BY p.created_at DESC',
        [(int) $tag['id'], 'publish']
    );
    ThemeManager::render('archive', [
        'pageTitle'   => 'Etiqueta: #' . $tag['name'] . ' - ' . Helpers::option('site_title', 'Bloc-FISI'),
        'archiveTitle'=> 'Etiqueta: #' . $tag['name'],
        'archiveDesc' => 'Articulos etiquetados con #' . $tag['name'],
        'posts'       => $posts,
    ]);
    exit;
}

// 8. Pagina Estatica (/pagina/{slug})
if (preg_match('#^/pagina/([a-zA-Z0-9_-]+)$#', $uriPath, $m)) {
    $pageRow = Database::fetchOne('SELECT * FROM pages WHERE slug = ? AND status = ?', [$m[1], 'publish']);
    if (!$pageRow) {
        http_response_code(404);
        ThemeManager::render('404', ['pageTitle' => '404 - Pagina no encontrada']);
        exit;
    }
    PluginManager::doAction('page_visit', null, $uriPath);
    $renderedContent = Helpers::renderShortcodes($pageRow['content']);
    ThemeManager::render('page', [
        'pageTitle'       => $pageRow['title'] . ' - ' . Helpers::option('site_title', 'Bloc-FISI'),
        'seoTarget'       => $pageRow,
        'seoType'         => 'website',
        'page'            => $pageRow,
        'renderedContent' => $renderedContent,
        'flash'           => Helpers::pullFlash(),
    ]);
    exit;
}

// 9. 404 por defecto
http_response_code(404);
ThemeManager::render('404', ['pageTitle' => '404 - Pagina no encontrada']);
