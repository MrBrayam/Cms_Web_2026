<?php if (!empty($searchQuery)): ?>
    <h1>Resultados para: &ldquo;<?php echo Security::e($searchQuery); ?>&rdquo;</h1>
<?php endif; ?>

<?php if (empty($posts)): ?>
    <p>No se encontraron art&iacute;culos para mostrar.</p>
<?php else: ?>
    <?php foreach ($posts as $p): ?>
        <article class="mb-post">
            <div class="mb-meta">
                <?php if (!empty($p['category_name'])): ?>
                    <a class="mb-badge" href="<?php echo Security::e(Helpers::url('/categoria/' . $p['category_slug'])); ?>"><?php echo Security::e($p['category_name']); ?></a>
                <?php endif; ?>
                <span><?php echo Security::e(substr((string) $p['created_at'], 0, 10)); ?></span>
                <span>&middot;</span>
                <span><?php echo Security::e($p['author_name']); ?></span>
                <span>&middot;</span>
                <span><?php echo Security::e(Helpers::readingTime($p['content'])); ?></span>
            </div>
            <h2 class="mb-title"><a href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>"><?php echo Security::e($p['title']); ?></a></h2>
            <?php if (!empty($p['featured_image'])): ?>
                <a class="mb-cover" href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>">
                    <img src="<?php echo Security::e(Helpers::url($p['featured_image'])); ?>" alt="<?php echo Security::e($p['title']); ?>">
                </a>
            <?php endif; ?>
            <p><?php echo Security::e($p['excerpt'] ?: Helpers::excerpt($p['content'])); ?></p>
            <a href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>"><strong>Leer art&iacute;culo &rarr;</strong></a>
        </article>
    <?php endforeach; ?>

    <div class="mb-pagination">
        <div><?php if ($currentPage > 1): ?><a href="?page=<?php echo $currentPage - 1; ?>">&larr; Recientes</a><?php endif; ?></div>
        <div><?php if ($currentPage < $totalPages): ?><a href="?page=<?php echo $currentPage + 1; ?>">Anteriores &rarr;</a><?php endif; ?></div>
    </div>
<?php endif; ?>
