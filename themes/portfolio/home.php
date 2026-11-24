<section style="margin-bottom:2rem;">
    <h1 style="font-size:2.3rem;margin:0 0 0.4rem;color:#fff;">Proyectos &amp; Publicaciones</h1>
    <p style="color:#94a3b8;margin:0;"><?php echo Security::e($siteTagline); ?> &mdash; Pasa el cursor sobre cada tarjeta para ver el detalle.</p>
</section>

<?php if (!empty($categories)): ?>
    <div class="pf-filters">
        <button type="button" class="pf-filter-btn is-active" data-filter="all">Todos</button>
        <?php foreach ($categories as $cat): if ((int) $cat['post_count'] === 0) continue; ?>
            <button type="button" class="pf-filter-btn" data-filter="<?php echo Security::e($cat['slug']); ?>"><?php echo Security::e($cat['name']); ?></button>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="pf-grid" id="pf-grid">
    <?php foreach ($posts as $p): ?>
        <article class="pf-card" data-cat="<?php echo Security::e($p['category_slug'] ?? ''); ?>">
            <img src="<?php echo Security::e(Helpers::url($p['featured_image'] ?: '/uploads/covers/cover-8.png')); ?>" alt="<?php echo Security::e($p['title']); ?>">
            <div class="pf-card-ribbon"><?php echo Security::e($p['title']); ?></div>
            <div class="pf-overlay">
                <span style="font-size:0.74rem;text-transform:uppercase;letter-spacing:0.08em;font-weight:700;color:#67e8f9;margin-bottom:0.35rem;"><?php echo Security::e($p['category_name'] ?? 'Proyecto'); ?></span>
                <h2 style="margin:0 0 0.45rem;font-size:1.22rem;color:#fff;"><?php echo Security::e($p['title']); ?></h2>
                <p style="font-size:0.86rem;color:#cbd5e1;margin:0 0 0.9rem;"><?php echo Security::e($p['excerpt'] ?: Helpers::excerpt($p['content'], 14)); ?></p>
                <a class="pf-btn" href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>">Ver proyecto &rarr;</a>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<script>
(function () {
    var btns = document.querySelectorAll('.pf-filter-btn');
    var cards = document.querySelectorAll('#pf-grid .pf-card');
    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            btns.forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            var f = btn.getAttribute('data-filter');
            cards.forEach(function (card) {
                if (f === 'all' || card.getAttribute('data-cat') === f) {
                    card.classList.remove('is-hidden');
                } else {
                    card.classList.add('is-hidden');
                }
            });
        });
    });
})();
</script>
