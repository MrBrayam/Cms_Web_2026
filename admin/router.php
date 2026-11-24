<?php
/**
 * Enrutador del Panel de Administracion (/admin/...)
 */

$sub = substr($uriPath, strlen('/admin'));
$sub = $sub === '' ? '/' : $sub;

if ($sub === '/login') {
    require BLOGCMS_ROOT . '/admin/login.php';
    exit;
}

if ($sub === '/logout') {
    Auth::logout();
    Helpers::redirect('/admin/login');
}

Auth::requireLogin();

require_once BLOGCMS_ROOT . '/admin/modules_content.php';
require_once BLOGCMS_ROOT . '/admin/modules_system.php';

$renderAdmin = static function (string $title, string $nav, callable $bodyFn): void {
    $adminTitle = $title;
    $activeNav  = $nav;
    ob_start();
    $bodyFn();
    $contentHtml = ob_get_clean();
    include BLOGCMS_ROOT . '/admin/layout.php';
    exit;
};

switch ($sub) {
    case '/':
        $renderAdmin('Escritorio', 'dashboard', 'admin_view_dashboard');
        break;
    case '/posts':
        $renderAdmin('Entradas (Articulos)', 'posts', 'admin_view_posts');
        break;
    case '/posts/nuevo':
    case '/posts/editar':
        $renderAdmin($sub === '/posts/nuevo' ? 'Nuevo Articulo' : 'Editar Articulo', 'posts', 'admin_view_post_form');
        break;
    case '/categories':
        Auth::requireRole(['admin', 'editor']);
        $renderAdmin('Categorias y Etiquetas', 'categories', 'admin_view_categories');
        break;
    case '/pages':
        Auth::requireRole(['admin', 'editor']);
        $renderAdmin('Paginas Estaticas', 'pages', 'admin_view_pages');
        break;
    case '/comments':
        Auth::requireRole(['admin', 'editor']);
        $renderAdmin('Moderacion de Comentarios', 'comments', 'admin_view_comments');
        break;
    case '/media':
        $renderAdmin('Biblioteca de Medios', 'media', 'admin_view_media');
        break;
    case '/themes':
        Auth::requireRole(['admin']);
        $renderAdmin('Temas y Plantillas (.zip)', 'themes', 'admin_view_themes');
        break;
    case '/menus':
        Auth::requireRole(['admin']);
        $renderAdmin('Menu Dinamico Configurable', 'menus', 'admin_view_menus');
        break;
    case '/plugins':
        Auth::requireRole(['admin']);
        $renderAdmin('Plugins y Subida (.zip)', 'plugins', 'admin_view_plugins');
        break;
    case '/users':
        Auth::requireRole(['admin']);
        $renderAdmin('Usuarios y Roles', 'users', 'admin_view_users');
        break;
    case '/settings':
        Auth::requireRole(['admin']);
        $renderAdmin('Ajustes Generales y Auditoria', 'settings', 'admin_view_settings');
        break;
    case '/plugin-panel':
        Auth::requireRole(['admin']);
        $renderAdmin('Modulo de Plugin', 'plugin-panel', 'admin_view_plugin_panel');
        break;
    default:
        http_response_code(404);
        echo '404 Ruta de administracion no encontrada.';
        exit;
}
