<h1 style="color:#fff;margin-bottom:1.5rem;"><?php echo Security::e($archiveTitle); ?></h1>
<div class="pf-grid">
    <?php foreach ($posts as $p): ?>
        <article class="pf-card">
            <img src="<?php echo Security::e(Helpers::url($p['featured_image'] ?: '/uploads/covers/cover-8.png')); ?>" alt="">
            <div class="pf-card-ribbon"><?php echo Security::e($p['title']); ?></div>
            <div class="pf-overlay">
                <h2 style="margin:0 0 0.45rem;font-size:1.22rem;color:#fff;"><?php echo Security::e($p['title']); ?></h2>
                <a class="pf-btn" href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>">Ver proyecto &rarr;</a>
            </div>
        </article>
    <?php endforeach; ?>
</div>
