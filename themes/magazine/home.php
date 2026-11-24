<?php if (!empty($searchQuery)): ?>
    <h1 class="mg-section-title">B&uacute;squeda: &ldquo;<?php echo Security::e($searchQuery); ?>&rdquo;</h1>
<?php endif; ?>

<?php if (empty($posts)): ?>
    <p>No hay publicaciones disponibles.</p>
<?php else: ?>
    <?php
    $showLead = empty($searchQuery) && ($currentPage === 1);
    $leadPost = $showLead ? array_shift($posts) : null;
    ?>

    <?php if ($leadPost): ?>
        <article class="mg-lead">
            <div class="mg-lead-media">
                <a href="<?php echo Security::e(Helpers::url('/post/' . $leadPost['slug'])); ?>">
                    <img src="<?php echo Security::e(Helpers::url($leadPost['featured_image'] ?: '/uploads/covers/cover-1.png')); ?>" alt="<?php echo Security::e($leadPost['title']); ?>">
                </a>
            </div>
            <div class="mg-lead-body">
                <?php if (!empty($leadPost['category_name'])): ?>
                    <a class="mg-badge" href="<?php echo Security::e(Helpers::url('/categoria/' . $leadPost['category_slug'])); ?>"><?php echo Security::e($leadPost['category_name']); ?></a>
                <?php endif; ?>
                <h2 class="mg-lead-title"><a href="<?php echo Security::e(Helpers::url('/post/' . $leadPost['slug'])); ?>"><?php echo Security::e($leadPost['title']); ?></a></h2>
                <p class="mg-card-excerpt"><?php echo Security::e($leadPost['excerpt'] ?: Helpers::excerpt($leadPost['content'])); ?></p>
                <div class="mg-meta"><span>Por <?php echo Security::e($leadPost['author_name']); ?></span><span><?php echo Security::e(substr((string) $leadPost['created_at'], 0, 10)); ?></span></div>
            </div>
        </article>
    <?php endif; ?>

    <?php if (!empty($posts)): ?>
        <h2 class="mg-section-title"><?php echo $showLead ? 'Edici&oacute;n en 3 Columnas' : 'Art&iacute;culos'; ?></h2>
        <div class="mg-grid-3">
            <?php foreach ($posts as $p): ?>
                <article class="mg-card">
                    <a class="mg-card-cover" href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>">
                        <img src="<?php echo Security::e(Helpers::url($p['featured_image'] ?: '/uploads/covers/cover-2.png')); ?>" alt="<?php echo Security::e($p['title']); ?>">
                    </a>
                    <div class="mg-card-body">
                        <?php if (!empty($p['category_name'])): ?>
                            <a class="mg-badge" href="<?php echo Security::e(Helpers::url('/categoria/' . $p['category_slug'])); ?>"><?php echo Security::e($p['category_name']); ?></a>
                        <?php endif; ?>
                        <h3 class="mg-card-title"><a href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>"><?php echo Security::e($p['title']); ?></a></h3>
                        <p class="mg-card-excerpt"><?php echo Security::e($p['excerpt'] ?: Helpers::excerpt($p['content'], 18)); ?></p>
                        <div class="mg-meta"><span><?php echo Security::e($p['author_name']); ?></span><span><?php echo Security::e(substr((string) $p['created_at'], 0, 10)); ?></span></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="mg-pagination">
        <div><?php if ($currentPage > 1): ?><a href="?page=<?php echo $currentPage - 1; ?>">&larr; P&aacute;gina anterior</a><?php endif; ?></div>
        <div><?php if ($currentPage < $totalPages): ?><a href="?page=<?php echo $currentPage + 1; ?>">Siguiente p&aacute;gina &rarr;</a><?php endif; ?></div>
    </div>
<?php endif; ?>
