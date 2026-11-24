<h1 class="mg-section-title"><?php echo Security::e($archiveTitle); ?></h1>
<?php if (empty($posts)): ?>
    <p>No hay publicaciones en esta categor&iacute;a o etiqueta.</p>
<?php else: ?>
    <div class="mg-grid-3">
        <?php foreach ($posts as $p): ?>
            <article class="mg-card">
                <a class="mg-card-cover" href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>">
                    <img src="<?php echo Security::e(Helpers::url($p['featured_image'] ?: '/uploads/covers/cover-2.png')); ?>" alt="">
                </a>
                <div class="mg-card-body">
                    <h3 class="mg-card-title"><a href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>"><?php echo Security::e($p['title']); ?></a></h3>
                    <p class="mg-card-excerpt"><?php echo Security::e($p['excerpt'] ?: Helpers::excerpt($p['content'], 18)); ?></p>
                    <div class="mg-meta"><span><?php echo Security::e($p['author_name']); ?></span><span><?php echo Security::e(substr((string) $p['created_at'], 0, 10)); ?></span></div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
