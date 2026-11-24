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
<div class="mg-topbar">
    <div class="mg-container mg-topbar-inner">
        <span>Edici&oacute;n Digital &middot; <?php echo date('d/m/Y'); ?></span>
        <span><a href="<?php echo Security::e(Helpers::url('/admin')); ?>" style="color:#f8fafc;">Panel Admin</a></span>
    </div>
</div>
<header class="mg-header">
    <div class="mg-container">
        <div class="mg-masthead">
            <div>
                <a class="mg-brand" href="<?php echo Security::e(Helpers::url('/')); ?>"><?php echo Security::e($siteTitle); ?></a>
                <p class="mg-tagline"><?php echo Security::e($siteTagline); ?></p>
            </div>
            <form method="get" action="<?php echo Security::e(Helpers::url('/')); ?>" class="mg-search">
                <input type="search" name="q" placeholder="Buscar art&iacute;culos..." value="<?php echo Security::e($searchQuery ?? ''); ?>">
                <button type="submit">Buscar</button>
            </form>
        </div>
        <nav class="mg-nav" aria-label="Menu principal">
            <ul>
                <?php foreach ($primaryMenu as $item): ?>
                    <li><a href="<?php echo Security::e(Helpers::url($item['url'])); ?>"><?php echo Security::e($item['label']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
</header>
<main class="mg-main"><div class="mg-container">
