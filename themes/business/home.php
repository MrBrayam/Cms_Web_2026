<?php if (empty($searchQuery) && ($currentPage === 1)): ?>
    <section class="bz-hero">
        <div class="bz-container">
            <div class="bz-hero-inner">
                <span class="bz-hero-eyebrow"><?php echo Security::e($siteTitle); ?> &middot; Corporativo</span>
                <h1><?php echo Security::e(Helpers::option('hero_title', 'Soluciones Digitales y Gestion de Contenido Propio')); ?></h1>
                <p><?php echo Security::e(Helpers::option('hero_subtitle', $siteTagline)); ?></p>
                <div class="bz-hero-actions">
                    <a class="bz-btn" href="#bz-blog">Explorar publicaciones</a>
                    <a class="bz-btn bz-btn-outline" href="<?php echo Security::e(Helpers::url('/pagina/contacto')); ?>">Solicitar informaci&oacute;n</a>
                </div>
            </div>
        </div>
    </section>

    <section class="bz-section" style="background:#ffffff;border-bottom:1px solid #e2e8f0;">
        <div class="bz-container">
            <div class="bz-section-header">
                <h2>Pilares de Nuestra Plataforma</h2>
                <p>CMS propio modular con gesti&oacute;n editorial, plugins .zip y seguridad OWASP.</p>
            </div>
            <div class="bz-grid-3">
                <div class="bz-card">
                    <div class="bz-icon">01</div>
                    <h3>Gesti&oacute;n Editorial &amp; Roles</h3>
                    <p>Flujo organizado entre administradores, editores y autores con categor&iacute;as, etiquetas y moderaci&oacute;n.</p>
                </div>
                <div class="bz-card">
                    <div class="bz-icon">02</div>
                    <h3>Plugins .zip &amp; SEO</h3>
                    <p>Instalaci&oacute;n autom&aacute;tica con ZipArchive + plugin.json, meta tags OpenGraph, sitemap y estad&iacute;sticas.</p>
                </div>
                <div class="bz-card">
                    <div class="bz-icon">03</div>
                    <h3>Multimedia &amp; Contacto</h3>
                    <p>Galer&iacute;as organizadas por &aacute;lbumes, difusi&oacute;n en redes sociales y formularios personalizables.</p>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<section id="bz-blog" class="bz-section">
    <div class="bz-container">
        <div class="bz-section-header">
            <h2>&Uacute;ltimas Publicaciones</h2>
            <p>Novedades, art&iacute;culos t&eacute;cnicos y comunicados institucionales.</p>
        </div>
        <div class="bz-grid-3">
            <?php foreach ($posts as $p): ?>
                <article class="bz-card">
                    <?php if (!empty($p['featured_image'])): ?>
                        <a class="bz-post-cover" href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>">
                            <img src="<?php echo Security::e(Helpers::url($p['featured_image'])); ?>" alt="<?php echo Security::e($p['title']); ?>">
                        </a>
                    <?php endif; ?>
                    <div style="font-size:0.82rem;color:#64748b;margin-bottom:0.4rem;">
                        <?php echo Security::e(substr((string) $p['created_at'], 0, 10)); ?> &middot; <?php echo Security::e($p['author_name']); ?>
                    </div>
                    <h3 style="margin:0 0 0.5rem;"><a href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>" style="color:#0b3b60;"><?php echo Security::e($p['title']); ?></a></h3>
                    <p style="color:#475569;flex:1;"><?php echo Security::e($p['excerpt'] ?: Helpers::excerpt($p['content'], 18)); ?></p>
                    <a href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>" style="font-weight:600;">Leer m&aacute;s &rarr;</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
