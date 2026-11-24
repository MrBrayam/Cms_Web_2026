<?php
/**
 * Layout maestro del Panel de Administracion de BlogCMS (inspirado en wp-admin)
 */
$user = Auth::user();
$flash = Helpers::pullFlash();
$pendingComments = (int) Database::fetchValue('SELECT COUNT(*) FROM comments WHERE status = ?', ['pending'], 0);
$pluginPanels = PluginManager::applyFilters('admin_menu_items', []);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Security::e($adminTitle ?? 'Panel de Administracion'); ?> &mdash; BlogCMS</title>
    <style>
        :root {
            --sb-bg: #1d2327;
            --sb-hover: #2c3338;
            --sb-active: #2271b1;
            --bg: #f0f0f1;
            --card: #ffffff;
            --text: #1d2327;
            --muted: #646970;
            --border: #dcdcde;
            --primary: #2271b1;
            --primary-hover: #135e96;
            --ok: #00a32a;
            --err: #d63638;
            --warn: #dba617;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* Top Admin Bar */
        .wpbar { position: fixed; top: 0; left: 0; right: 0; height: 36px; background: var(--sb-bg); color: #f0f0f1; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; z-index: 50; font-size: 13px; border-bottom: 1px solid #111; }
        .wpbar a { color: #f0f0f1; font-weight: 600; }
        .wpbar-left, .wpbar-right { display: flex; align-items: center; gap: 16px; }
        .role-pill { background: var(--sb-active); color: #fff; padding: 2px 8px; border-radius: 999px; font-size: 11px; text-transform: uppercase; font-weight: 700; }

        /* Sidebar */
        .admin-shell { display: flex; padding-top: 36px; min-height: 100vh; }
        .sidebar { width: 220px; background: var(--sb-bg); color: #c3c4c7; flex-shrink: 0; padding: 12px 0; }
        .sidebar a { display: flex; align-items: center; justify-content: space-between; padding: 9px 16px; color: #f0f0f1; font-size: 13.5px; font-weight: 500; transition: background 0.12s; }
        .sidebar a:hover { background: var(--sb-hover); text-decoration: none; color: #72aee6; }
        .sidebar a.active { background: var(--sb-active); color: #ffffff; font-weight: 700; }
        .sidebar-sep { border: none; border-top: 1px solid #2c3338; margin: 8px 0; }
        .sidebar-heading { padding: 6px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: #8c8f94; }
        .badge-count { background: #d63638; color: #fff; border-radius: 999px; font-size: 11px; padding: 1px 7px; font-weight: 700; }

        /* Content */
        .content { flex: 1; padding: 24px 28px; min-width: 0; }
        .page-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        .page-header h1 { margin: 0; font-size: 22px; font-weight: 600; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 6px; padding: 18px 20px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .grid-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .stat-box { background: var(--card); border: 1px solid var(--border); border-left: 4px solid var(--primary); border-radius: 6px; padding: 14px 16px; }
        .stat-num { font-size: 26px; font-weight: 700; color: var(--text); }
        .stat-lbl { font-size: 12.5px; color: var(--muted); }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 18px; }

        /* Forms & Tables */
        table.data-table { width: 100%; border-collapse: collapse; background: var(--card); border: 1px solid var(--border); }
        table.data-table th, table.data-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: middle; }
        table.data-table th { background: #f6f7f7; font-size: 13px; color: #2c3338; }
        .btn { display: inline-block; background: var(--primary); color: #fff; border: none; padding: 7px 14px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; }
        .btn:hover { background: var(--primary-hover); text-decoration: none; color: #fff; }
        .btn-sec { background: #f6f7f7; color: #2c3338; border: 1px solid #8c8f94; }
        .btn-sec:hover { background: #e5e5e5; color: #1d2327; }
        .btn-danger { background: var(--err); }
        .btn-danger:hover { background: #b32d2e; }
        .btn-ok { background: var(--ok); }
        .btn-ok:hover { background: #008a20; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }

        .form-row { margin-bottom: 14px; }
        .form-row label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px; }
        input[type="text"], input[type="email"], input[type="password"], input[type="number"], input[type="url"], select, textarea {
            width: 100%; padding: 8px 10px; border: 1px solid #8c8f94; border-radius: 4px; font-family: inherit; font-size: 13.5px; background: #fff;
        }
        textarea { min-height: 150px; }

        .flash { padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; border-left: 4px solid; background: #fff; }
        .flash-ok { border-color: var(--ok); background: #edfaef; color: #0a4b19; }
        .flash-err { border-color: var(--err); background: #fcf0f1; color: #8a1f22; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 600; }
        .pill-ok { background: #d1fae5; color: #065f46; }
        .pill-warn { background: #fef3c7; color: #92400e; }
        .pill-muted { background: #e5e7eb; color: #374151; }

        @media (max-width: 768px) {
            .admin-shell { flex-direction: column; }
            .sidebar { width: 100%; display: flex; flex-wrap: wrap; padding: 6px; }
            .sidebar-sep, .sidebar-heading { display: none; }
        }
    </style>
</head>
<body>
<header class="wpbar">
    <div class="wpbar-left">
        <a href="<?php echo Security::e(Helpers::url('/')); ?>" target="_blank">&#127968; <?php echo Security::e(Helpers::option('site_title', 'Bloc-FISI')); ?> (Ver Sitio)</a>
        <a href="<?php echo Security::e(Helpers::url('/admin/posts/nuevo')); ?>">+ Nuevo Art&iacute;culo</a>
    </div>
    <div class="wpbar-right">
        <span>Hola, <strong><?php echo Security::e($user['display_name']); ?></strong></span>
        <span class="role-pill"><?php echo Security::e($user['role']); ?></span>
        <a href="<?php echo Security::e(Helpers::url('/admin/logout')); ?>">Cerrar sesi&oacute;n</a>
    </div>
</header>

<div class="admin-shell">
    <aside class="sidebar">
        <a href="<?php echo Security::e(Helpers::url('/admin')); ?>" class="<?php echo ($activeNav ?? '') === 'dashboard' ? 'active' : ''; ?>">Escritorio</a>
        <a href="<?php echo Security::e(Helpers::url('/admin/posts')); ?>" class="<?php echo ($activeNav ?? '') === 'posts' ? 'active' : ''; ?>">Entradas (Art&iacute;culos)</a>
        <?php if (Auth::isEditorOrAdmin()): ?>
            <a href="<?php echo Security::e(Helpers::url('/admin/categories')); ?>" class="<?php echo ($activeNav ?? '') === 'categories' ? 'active' : ''; ?>">Categor&iacute;as y Etiquetas</a>
            <a href="<?php echo Security::e(Helpers::url('/admin/pages')); ?>" class="<?php echo ($activeNav ?? '') === 'pages' ? 'active' : ''; ?>">P&aacute;ginas Est&aacute;ticas</a>
            <a href="<?php echo Security::e(Helpers::url('/admin/comments')); ?>" class="<?php echo ($activeNav ?? '') === 'comments' ? 'active' : ''; ?>">
                <span>Comentarios</span>
                <?php if ($pendingComments > 0): ?><span class="badge-count"><?php echo $pendingComments; ?></span><?php endif; ?>
            </a>
        <?php endif; ?>
        <a href="<?php echo Security::e(Helpers::url('/admin/media')); ?>" class="<?php echo ($activeNav ?? '') === 'media' ? 'active' : ''; ?>">Medios / Im&aacute;genes</a>

        <?php if (Auth::isAdmin()): ?>
            <hr class="sidebar-sep">
            <div class="sidebar-heading">Apariencia &amp; Extensi&oacute;n</div>
            <a href="<?php echo Security::e(Helpers::url('/admin/themes')); ?>" class="<?php echo ($activeNav ?? '') === 'themes' ? 'active' : ''; ?>">Temas (y subir .zip)</a>
            <a href="<?php echo Security::e(Helpers::url('/admin/menus')); ?>" class="<?php echo ($activeNav ?? '') === 'menus' ? 'active' : ''; ?>">Men&uacute; Din&aacute;mico</a>
            <a href="<?php echo Security::e(Helpers::url('/admin/plugins')); ?>" class="<?php echo ($activeNav ?? '') === 'plugins' ? 'active' : ''; ?>">Plugins (y subir .zip)</a>
            <a href="<?php echo Security::e(Helpers::url('/admin/users')); ?>" class="<?php echo ($activeNav ?? '') === 'users' ? 'active' : ''; ?>">Usuarios y Roles</a>
            <a href="<?php echo Security::e(Helpers::url('/admin/settings')); ?>" class="<?php echo ($activeNav ?? '') === 'settings' ? 'active' : ''; ?>">Ajustes y Auditor&iacute;a</a>

            <?php if (!empty($pluginPanels)): ?>
                <hr class="sidebar-sep">
                <div class="sidebar-heading">M&oacute;dulos de Plugins</div>
                <?php foreach ($pluginPanels as $pp): ?>
                    <a href="<?php echo Security::e(Helpers::url('/admin/plugin-panel?slug=' . urlencode($pp['slug']))); ?>"
                       class="<?php echo (($_GET['slug'] ?? '') === $pp['slug'] && ($activeNav ?? '') === 'plugin-panel') ? 'active' : ''; ?>">
                        <?php echo Security::e($pp['label']); ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </aside>

    <main class="content">
        <?php if ($flash): ?>
            <div class="flash <?php echo $flash['type'] === 'ok' ? 'flash-ok' : 'flash-err'; ?>">
                <?php echo Security::e($flash['message']); ?>
            </div>
        <?php endif; ?>
        <?php echo $contentHtml ?? ''; ?>
    </main>
</div>
</body>
</html>
