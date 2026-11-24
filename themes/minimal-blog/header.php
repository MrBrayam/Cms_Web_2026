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
<header class="mb-header">
    <div class="mb-container mb-header-inner">
        <div>
            <a class="mb-brand" href="<?php echo Security::e(Helpers::url('/')); ?>"><?php echo Security::e($siteTitle); ?></a>
            <?php if ($siteTagline !== ''): ?><p class="mb-tagline"><?php echo Security::e($siteTagline); ?></p><?php endif; ?>
        </div>
        <nav class="mb-nav" aria-label="Menu principal">
            <ul>
                <?php foreach ($primaryMenu as $item): ?>
                    <li><a href="<?php echo Security::e(Helpers::url($item['url'])); ?>"><?php echo Security::e($item['label']); ?></a></li>
                <?php endforeach; ?>
                <li><a href="<?php echo Security::e(Helpers::url('/admin')); ?>">Admin</a></li>
            </ul>
        </nav>
    </div>
</header>
<main class="mb-main"><div class="mb-container">
