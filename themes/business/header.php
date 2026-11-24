<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Security::e($pageTitle ?? $siteTitle); ?></title>
    <?php PluginManager::doAction('head_meta', get_defined_vars()); ?>
    <link rel="stylesheet" href="<?php echo Security::e(ThemeManager::assetUrl('style.css')); ?>">
</head>
<body>
<header class="bz-header">
    <div class="bz-container bz-header-inner">
        <a class="bz-brand" href="<?php echo Security::e(Helpers::url('/')); ?>"><?php echo Security::e($siteTitle); ?></a>
        <div class="bz-nav-wrap">
            <nav class="bz-nav" aria-label="Menu principal">
                <ul>
                    <?php foreach ($primaryMenu as $item): ?>
                        <li><a href="<?php echo Security::e(Helpers::url($item['url'])); ?>"><?php echo Security::e($item['label']); ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="<?php echo Security::e(Helpers::url('/admin')); ?>">Admin</a></li>
                </ul>
            </nav>
            <a class="bz-btn" href="<?php echo Security::e(Helpers::url('/pagina/contacto')); ?>">Cont&aacute;ctanos</a>
        </div>
    </div>
</header>
<main>
