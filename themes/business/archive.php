<div class="bz-container" style="padding-top:2.5rem;">
    <h1 style="color:#0b3b60;"><?php echo Security::e($archiveTitle); ?></h1>
    <div class="bz-grid-3">
        <?php foreach ($posts as $p): ?>
            <article class="bz-card">
                <?php if (!empty($p['featured_image'])): ?>
                    <a class="bz-post-cover" href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>">
                        <img src="<?php echo Security::e(Helpers::url($p['featured_image'])); ?>" alt="">
                    </a>
                <?php endif; ?>
                <h3 style="margin:0 0 0.5rem;"><a href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>" style="color:#0b3b60;"><?php echo Security::e($p['title']); ?></a></h3>
                <p style="color:#475569;flex:1;"><?php echo Security::e($p['excerpt'] ?: Helpers::excerpt($p['content'], 18)); ?></p>
                <a href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>">Leer m&aacute;s &rarr;</a>
            </article>
        <?php endforeach; ?>
    </div>
</div>
