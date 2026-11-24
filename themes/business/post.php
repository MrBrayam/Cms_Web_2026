<div class="bz-container">
    <div class="bz-page-wrap">
        <article>
            <h1 style="color:#0b3b60;font-size:2.1rem;margin:0 0 0.6rem;"><?php echo Security::e($post['title']); ?></h1>
            <p style="color:#64748b;font-size:0.9rem;margin:0 0 1.25rem;">
                <?php echo Security::e(substr((string) $post['created_at'], 0, 10)); ?> &middot; Por <?php echo Security::e($post['author_name']); ?>
                <?php if (!empty($post['category_name'])): ?> &middot; <a href="<?php echo Security::e(Helpers::url('/categoria/' . $post['category_slug'])); ?>"><?php echo Security::e($post['category_name']); ?></a><?php endif; ?>
            </p>
            <?php if (!empty($post['featured_image'])): ?>
                <div style="margin:1.2rem 0;border-radius:8px;overflow:hidden;"><img src="<?php echo Security::e(Helpers::url($post['featured_image'])); ?>" alt=""></div>
            <?php endif; ?>
            <div><?php echo $renderedContent; ?></div>
            <?php if (!empty($tags)): ?>
                <p style="margin-top:1.5rem;"><strong>Etiquetas:</strong>
                    <?php foreach ($tags as $i => $t): ?>
                        <?php if ($i > 0) echo ', '; ?><a href="<?php echo Security::e(Helpers::url('/etiqueta/' . $t['slug'])); ?>">#<?php echo Security::e($t['name']); ?></a>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
        </article>

        <section id="comments" style="margin-top:2.25rem;padding-top:1.5rem;border-top:1px solid #e2e8f0;">
            <?php if (!empty($flash)): ?>
                <div class="bz-flash <?php echo $flash['type'] === 'ok' ? 'bz-flash-ok' : 'bz-flash-err'; ?>"><?php echo Security::e($flash['message']); ?></div>
            <?php endif; ?>
            <h3>Comentarios (<?php echo count($comments); ?>)</h3>
            <?php foreach ($comments as $c): ?>
                <div style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:1rem;margin-bottom:0.85rem;">
                    <div style="font-size:0.82rem;color:#64748b;margin-bottom:4px;"><strong><?php echo Security::e($c['author_name']); ?></strong> &middot; <?php echo Security::e(substr((string) $c['created_at'], 0, 16)); ?></div>
                    <div><?php echo Security::e($c['content']); ?></div>
                </div>
            <?php endforeach; ?>
            <form method="post" action="<?php echo Security::e(Helpers::url('/comentario/enviar')); ?>" style="margin-top:1.25rem;">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                <h4>Deja un comentario</h4>
                <div class="bz-form-row"><label>Nombre</label><input type="text" name="author_name" required></div>
                <div class="bz-form-row"><label>Correo electr&oacute;nico</label><input type="email" name="author_email" required></div>
                <div class="bz-form-row"><label>Comentario</label><textarea name="content" rows="4" required></textarea></div>
                <button class="bz-btn" type="submit">Publicar comentario</button>
            </form>
        </section>
    </div>
</div>
