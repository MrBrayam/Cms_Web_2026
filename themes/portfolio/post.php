<div class="pf-article-wrap">
    <article>
        <h1 style="color:#fff;font-size:2.1rem;margin:0 0 0.6rem;"><?php echo Security::e($post['title']); ?></h1>
        <p style="color:#94a3b8;font-size:0.9rem;margin:0 0 1.4rem;">
            <?php echo Security::e(substr((string) $post['created_at'], 0, 10)); ?> &middot; Por <?php echo Security::e($post['author_name']); ?>
            <?php if (!empty($post['category_name'])): ?> &middot; <a href="<?php echo Security::e(Helpers::url('/categoria/' . $post['category_slug'])); ?>"><?php echo Security::e($post['category_name']); ?></a><?php endif; ?>
        </p>
        <?php if (!empty($post['featured_image'])): ?>
            <div style="border-radius:10px;overflow:hidden;margin-bottom:1.5rem;"><img src="<?php echo Security::e(Helpers::url($post['featured_image'])); ?>" alt=""></div>
        <?php endif; ?>
        <div><?php echo $renderedContent; ?></div>
        <?php if (!empty($tags)): ?>
            <p style="margin-top:1.5rem;color:#94a3b8;"><strong>Etiquetas:</strong>
                <?php foreach ($tags as $i => $t): ?>
                    <?php if ($i > 0) echo ', '; ?><a href="<?php echo Security::e(Helpers::url('/etiqueta/' . $t['slug'])); ?>">#<?php echo Security::e($t['name']); ?></a>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </article>

    <section id="comments" style="margin-top:2.25rem;padding-top:1.5rem;border-top:1px solid rgba(148,163,184,0.2);">
        <?php if (!empty($flash)): ?>
            <div class="pf-flash <?php echo $flash['type'] === 'ok' ? 'pf-flash-ok' : 'pf-flash-err'; ?>"><?php echo Security::e($flash['message']); ?></div>
        <?php endif; ?>
        <h3>Comentarios (<?php echo count($comments); ?>)</h3>
        <?php foreach ($comments as $c): ?>
            <div style="background:rgba(15,23,42,0.55);border:1px solid rgba(148,163,184,0.2);border-radius:8px;padding:1rem;margin-bottom:0.85rem;">
                <div style="font-size:0.84rem;color:#94a3b8;margin-bottom:4px;"><strong><?php echo Security::e($c['author_name']); ?></strong> &middot; <?php echo Security::e(substr((string) $c['created_at'], 0, 16)); ?></div>
                <div><?php echo Security::e($c['content']); ?></div>
            </div>
        <?php endforeach; ?>
        <form method="post" action="<?php echo Security::e(Helpers::url('/comentario/enviar')); ?>" style="margin-top:1.25rem;">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
            <h4>Deja un comentario</h4>
            <div class="pf-form-row"><label>Nombre</label><input type="text" name="author_name" required></div>
            <div class="pf-form-row"><label>Correo electr&oacute;nico</label><input type="email" name="author_email" required></div>
            <div class="pf-form-row"><label>Comentario</label><textarea name="content" rows="4" required></textarea></div>
            <button class="pf-btn" type="submit">Publicar comentario</button>
        </form>
    </section>
</div>
