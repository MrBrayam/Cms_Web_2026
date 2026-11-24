<article>
    <div class="mb-meta">
        <?php if (!empty($post['category_name'])): ?>
            <a class="mb-badge" href="<?php echo Security::e(Helpers::url('/categoria/' . $post['category_slug'])); ?>"><?php echo Security::e($post['category_name']); ?></a>
        <?php endif; ?>
        <span><?php echo Security::e(substr((string) $post['created_at'], 0, 10)); ?></span>
        <span>&middot;</span>
        <span>Por <?php echo Security::e($post['author_name']); ?></span>
        <span>&middot;</span>
        <span><?php echo Security::e(Helpers::readingTime($post['content'])); ?></span>
    </div>
    <h1 class="mb-title" style="font-size:2.3rem;"><?php echo Security::e($post['title']); ?></h1>
    <?php if (!empty($post['featured_image'])): ?>
        <div class="mb-cover"><img src="<?php echo Security::e(Helpers::url($post['featured_image'])); ?>" alt="<?php echo Security::e($post['title']); ?>"></div>
    <?php endif; ?>
    <div><?php echo $renderedContent; ?></div>

    <?php if (!empty($tags)): ?>
        <div class="mb-tags">
            <?php foreach ($tags as $t): ?>
                <a href="<?php echo Security::e(Helpers::url('/etiqueta/' . $t['slug'])); ?>">#<?php echo Security::e($t['name']); ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>

<section id="comments" class="mb-comments">
    <?php if (!empty($flash)): ?>
        <div class="mb-flash <?php echo $flash['type'] === 'ok' ? 'mb-flash-ok' : 'mb-flash-err'; ?>"><?php echo Security::e($flash['message']); ?></div>
    <?php endif; ?>
    <h3>Comentarios (<?php echo count($comments); ?>)</h3>
    <?php foreach ($comments as $c): ?>
        <div class="mb-comment-item">
            <div style="font-size:0.84rem;color:#78716c;margin-bottom:4px;"><strong><?php echo Security::e($c['author_name']); ?></strong> &middot; <?php echo Security::e(substr((string) $c['created_at'], 0, 16)); ?></div>
            <div><?php echo Security::e($c['content']); ?></div>
        </div>
    <?php endforeach; ?>

    <form method="post" action="<?php echo Security::e(Helpers::url('/comentario/enviar')); ?>" style="margin-top:1.5rem;">
        <?php echo Security::csrfField(); ?>
        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
        <h4>Deja un comentario</h4>
        <div class="mb-form-row"><label>Nombre</label><input type="text" name="author_name" required></div>
        <div class="mb-form-row"><label>Correo electr&oacute;nico</label><input type="email" name="author_email" required></div>
        <div class="mb-form-row"><label>Comentario</label><textarea name="content" rows="4" required></textarea></div>
        <button class="mb-btn" type="submit">Publicar comentario</button>
    </form>
</section>
