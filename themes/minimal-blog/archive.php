<div style="margin-bottom:2rem;padding-bottom:1rem;border-bottom:1px solid #e7e5e4;">
    <h1 class="mb-title"><?php echo Security::e($archiveTitle); ?></h1>
    <?php if (!empty($archiveDesc)): ?><p style="color:#78716c;margin:0;"><?php echo Security::e($archiveDesc); ?></p><?php endif; ?>
</div>
<?php if (empty($posts)): ?>
    <p>No hay art&iacute;culos en esta secci&oacute;n.</p>
<?php else: ?>
    <?php foreach ($posts as $p): ?>
        <article class="mb-post">
            <h2 class="mb-title"><a href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>"><?php echo Security::e($p['title']); ?></a></h2>
            <p><?php echo Security::e($p['excerpt'] ?: Helpers::excerpt($p['content'])); ?></p>
            <a href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>">Leer art&iacute;culo &rarr;</a>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
