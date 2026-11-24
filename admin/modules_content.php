<?php
/**
 * Modulos editoriales del Panel Admin:
 * Escritorio, Articulos, Categorias/Etiquetas, Paginas Estaticas, Comentarios con Moderacion y Medios.
 */

function admin_view_dashboard(): void
{
    $user = Auth::user();
    $postsCount    = (int) Database::fetchValue('SELECT COUNT(*) FROM posts WHERE status = ?', ['publish'], 0);
    $pagesCount    = (int) Database::fetchValue('SELECT COUNT(*) FROM pages WHERE status = ?', ['publish'], 0);
    $pendingCount  = (int) Database::fetchValue('SELECT COUNT(*) FROM comments WHERE status = ?', ['pending'], 0);
    $pluginsActive = (int) Database::fetchValue('SELECT COUNT(*) FROM plugins WHERE is_active = 1', [], 0);
    $totalViews    = (int) Database::fetchValue('SELECT COALESCE(SUM(views), 0) FROM posts', [], 0);
    $activeTheme   = ThemeManager::activeSlug();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_draft'])) {
        Security::requireCsrf();
        $title   = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        if ($title !== '' && $content !== '') {
            $slug = Helpers::slugify($title) . '-' . substr(bin2hex(random_bytes(3)), 0, 4);
            Database::execute(
                'INSERT INTO posts (title, slug, excerpt, content, status, author_id, category_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$title, $slug, Helpers::excerpt($content), '<p>' . Security::e($content) . '</p>', 'draft', (int) $user['id'], 1]
            );
            Helpers::flash('ok', 'Borrador rapido guardado.');
            Helpers::redirect('/admin');
        }
    }

    $recentPosts = Database::fetchAll('SELECT id, title, slug, status, views, created_at FROM posts ORDER BY created_at DESC LIMIT 5');
    $recentComments = Database::fetchAll('SELECT c.*, p.title AS post_title FROM comments c JOIN posts p ON p.id = c.post_id ORDER BY c.created_at DESC LIMIT 5');
    ?>
    <div class="page-header">
        <h1>Escritorio de <?php echo Security::e(Helpers::option('site_title', 'Bloc-FISI')); ?></h1>
        <span>Motor BD: <strong><?php echo Security::e(strtoupper(Database::driver())); ?></strong> &middot; Tema activo: <strong><?php echo Security::e($activeTheme); ?></strong></span>
    </div>

    <div class="grid-stats">
        <div class="stat-box"><div class="stat-num"><?php echo $postsCount; ?></div><div class="stat-lbl">Art&iacute;culos publicados</div></div>
        <div class="stat-box"><div class="stat-num"><?php echo $pagesCount; ?></div><div class="stat-lbl">P&aacute;ginas est&aacute;ticas</div></div>
        <div class="stat-box"><div class="stat-num"><?php echo $pendingCount; ?></div><div class="stat-lbl">Comentarios por moderar</div></div>
        <div class="stat-box"><div class="stat-num"><?php echo $pluginsActive; ?></div><div class="stat-lbl">Plugins activos</div></div>
        <div class="stat-box"><div class="stat-num"><?php echo $totalViews; ?></div><div class="stat-lbl">Visitas acumuladas</div></div>
    </div>

    <div class="grid-2">
        <div class="card">
            <h2 style="margin-top:0;">Borrador R&aacute;pido</h2>
            <form method="post" action="<?php echo Security::e(Helpers::url('/admin')); ?>">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="quick_draft" value="1">
                <div class="form-row">
                    <label for="qd_title">T&iacute;tulo</label>
                    <input type="text" id="qd_title" name="title" required placeholder="Idea para nuevo articulo...">
                </div>
                <div class="form-row">
                    <label for="qd_content">Contenido</label>
                    <textarea id="qd_content" name="content" style="min-height:95px;" required placeholder="Escribe un borrador rapido..."></textarea>
                </div>
                <button class="btn" type="submit">Guardar borrador</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Actividad Reciente</h2>
            <p><strong>&Uacute;ltimos art&iacute;culos:</strong></p>
            <ul>
                <?php foreach ($recentPosts as $rp): ?>
                    <li>
                        <a href="<?php echo Security::e(Helpers::url('/admin/posts/editar?id=' . (int) $rp['id'])); ?>"><?php echo Security::e($rp['title']); ?></a>
                        <span class="pill <?php echo $rp['status'] === 'publish' ? 'pill-ok' : 'pill-warn'; ?>"><?php echo Security::e($rp['status']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p><strong>&Uacute;ltimos comentarios:</strong></p>
            <ul>
                <?php foreach ($recentComments as $rc): ?>
                    <li>
                        <strong><?php echo Security::e($rc['author_name']); ?></strong> en <em><?php echo Security::e($rc['post_title']); ?></em>
                        <span class="pill <?php echo $rc['status'] === 'approved' ? 'pill-ok' : 'pill-warn'; ?>"><?php echo Security::e($rc['status']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php
}

function admin_view_posts(): void
{
    $user = Auth::user();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
        Security::requireCsrf();
        $id   = (int) ($_POST['id'] ?? 0);
        $post = Database::fetchOne('SELECT * FROM posts WHERE id = ?', [$id]);
        if (!$post || !Auth::canManagePost($post)) {
            Helpers::flash('err', 'No encontrado o sin permiso para eliminar este articulo.');
        } else {
            Database::execute('DELETE FROM posts WHERE id = ?', [$id]);
            Helpers::audit('post_delete', "Articulo ID $id eliminado");
            Helpers::flash('ok', 'Articulo eliminado.');
        }
        Helpers::redirect('/admin/posts');
    }

    if (Auth::isEditorOrAdmin()) {
        $posts = Database::fetchAll(
            'SELECT p.*, u.display_name AS author_name, c.name AS category_name
             FROM posts p JOIN users u ON u.id = p.author_id LEFT JOIN categories c ON c.id = p.category_id
             ORDER BY p.created_at DESC, p.id DESC'
        );
    } else {
        // Autor solo ve y gestiona sus propios articulos (OWASP A01 - control de acceso horizontal)
        $posts = Database::fetchAll(
            'SELECT p.*, u.display_name AS author_name, c.name AS category_name
             FROM posts p JOIN users u ON u.id = p.author_id LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.author_id = ?
             ORDER BY p.created_at DESC, p.id DESC',
            [(int) $user['id']]
        );
    }
    ?>
    <div class="page-header">
        <h1>Entradas (Art&iacute;culos)</h1>
        <a class="btn" href="<?php echo Security::e(Helpers::url('/admin/posts/nuevo')); ?>">+ Nuevo Art&iacute;culo</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>T&iacute;tulo</th>
                <th>Categor&iacute;a</th>
                <th>Autor</th>
                <th>Estado</th>
                <th>Visitas</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($posts as $p): ?>
            <tr>
                <td>
                    <strong><?php echo Security::e($p['title']); ?></strong><br>
                    <small style="color:#646970;">/post/<?php echo Security::e($p['slug']); ?></small>
                </td>
                <td><?php echo Security::e($p['category_name'] ?? 'Sin categoria'); ?></td>
                <td><?php echo Security::e($p['author_name']); ?></td>
                <td><span class="pill <?php echo $p['status'] === 'publish' ? 'pill-ok' : 'pill-warn'; ?>"><?php echo Security::e($p['status']); ?></span></td>
                <td><?php echo (int) $p['views']; ?></td>
                <td style="white-space:nowrap;">
                    <a class="btn btn-sec btn-sm" href="<?php echo Security::e(Helpers::url('/admin/posts/editar?id=' . (int) $p['id'])); ?>">Editar</a>
                    <?php if ($p['status'] === 'publish'): ?>
                        <a class="btn btn-sec btn-sm" target="_blank" href="<?php echo Security::e(Helpers::url('/post/' . $p['slug'])); ?>">Ver</a>
                    <?php endif; ?>
                    <form method="post" action="<?php echo Security::e(Helpers::url('/admin/posts')); ?>" style="display:inline;">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                        <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('Eliminar este articulo?')">Borrar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function admin_view_post_form(): void
{
    $user = Auth::user();
    $id   = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));
    $post = null;

    if ($id > 0) {
        $post = Database::fetchOne('SELECT * FROM posts WHERE id = ?', [$id]);
        if (!$post || !Auth::canManagePost($post)) {
            http_response_code(404);
            exit('404 Recurso no encontrado o acceso denegado.');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        $title      = trim((string) ($_POST['title'] ?? ''));
        $rawSlug    = trim((string) ($_POST['slug'] ?? ''));
        $slug       = Helpers::slugify($rawSlug !== '' ? $rawSlug : $title);
        $catId      = (int) ($_POST['category_id'] ?? 0) ?: null;
        $status     = in_array($_POST['status'] ?? 'publish', ['publish', 'draft'], true) ? $_POST['status'] : 'publish';
        $content    = Helpers::sanitizeHtml((string) ($_POST['content'] ?? ''));
        $excerpt    = trim((string) ($_POST['excerpt'] ?? '')) ?: Helpers::excerpt($content);
        $metaTitle  = trim((string) ($_POST['meta_title'] ?? ''));
        $metaDesc   = trim((string) ($_POST['meta_description'] ?? ''));
        $tagsInput  = trim((string) ($_POST['tags'] ?? ''));
        $featImg    = trim((string) ($_POST['featured_image'] ?? ($post['featured_image'] ?? '')));

        if (!empty($_FILES['image_file']['tmp_name'])) {
            $uploaded = Helpers::uploadImage($_FILES['image_file'], 'covers');
            if ($uploaded) {
                $featImg = $uploaded;
            }
        }

        if ($title === '' || $content === '') {
            Helpers::flash('err', 'Titulo y contenido son obligatorios.');
        } else {
            if ($post) {
                Database::execute(
                    'UPDATE posts SET title = ?, slug = ?, excerpt = ?, content = ?, featured_image = ?, status = ?, category_id = ?, meta_title = ?, meta_description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                    [$title, $slug, $excerpt, $content, $featImg, $status, $catId, $metaTitle, $metaDesc, (int) $post['id']]
                );
                $postId = (int) $post['id'];
                Helpers::audit('post_update', "Articulo ID $postId actualizado");
            } else {
                $existingSlug = Database::fetchValue('SELECT COUNT(*) FROM posts WHERE slug = ?', [$slug], 0);
                if ((int) $existingSlug > 0) {
                    $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
                }
                Database::execute(
                    'INSERT INTO posts (title, slug, excerpt, content, featured_image, status, author_id, category_id, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$title, $slug, $excerpt, $content, $featImg, $status, (int) $user['id'], $catId, $metaTitle, $metaDesc]
                );
                $postId = Database::lastInsertId('posts_id_seq');
                Helpers::audit('post_create', "Articulo ID $postId creado");
            }

            // Sincronizar etiquetas
            Database::execute('DELETE FROM post_tags WHERE post_id = ?', [$postId]);
            if ($tagsInput !== '') {
                $tagParts = array_unique(array_filter(array_map('trim', explode(',', $tagsInput))));
                foreach ($tagParts as $tname) {
                    $tslug = Helpers::slugify($tname);
                    $tid   = Database::fetchValue('SELECT id FROM tags WHERE slug = ?', [$tslug]);
                    if (!$tid) {
                        Database::execute('INSERT INTO tags (name, slug) VALUES (?, ?)', [$tname, $tslug]);
                        $tid = Database::lastInsertId('tags_id_seq');
                    }
                    Database::execute('INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)', [$postId, (int) $tid]);
                }
            }

            Helpers::flash('ok', 'Articulo guardado correctamente.');
            Helpers::redirect('/admin/posts');
        }
    }

    $categories = Database::fetchAll('SELECT * FROM categories ORDER BY name ASC');
    $currentTags = '';
    if ($post) {
        $tRows = Database::fetchAll('SELECT t.name FROM tags t JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ?', [(int) $post['id']]);
        $currentTags = implode(', ', array_column($tRows, 'name'));
    }
    ?>
    <div class="page-header">
        <h1><?php echo $post ? 'Editar Art&iacute;culo' : 'Nuevo Art&iacute;culo'; ?></h1>
        <a class="btn btn-sec" href="<?php echo Security::e(Helpers::url('/admin/posts')); ?>">&larr; Volver</a>
    </div>

    <form method="post" enctype="multipart/form-data" class="card">
        <?php echo Security::csrfField(); ?>
        <?php if ($post): ?><input type="hidden" name="id" value="<?php echo (int) $post['id']; ?>"><?php endif; ?>

        <div class="grid-2">
            <div>
                <div class="form-row">
                    <label for="title">T&iacute;tulo</label>
                    <input type="text" id="title" name="title" required value="<?php echo Security::e($post['title'] ?? ''); ?>">
                </div>
                <div class="form-row">
                    <label for="slug">Slug (URL amigable)</label>
                    <input type="text" id="slug" name="slug" value="<?php echo Security::e($post['slug'] ?? ''); ?>" placeholder="se-genera-automaticamente">
                </div>
                <div class="form-row">
                    <label for="content">Contenido (HTML permitido: p, h2, h3, strong, ul, a, img...)</label>
                    <textarea id="content" name="content" style="min-height:240px;" required><?php echo Security::e($post['content'] ?? ''); ?></textarea>
                </div>
                <div class="form-row">
                    <label for="excerpt">Extracto</label>
                    <textarea id="excerpt" name="excerpt" style="min-height:70px;"><?php echo Security::e($post['excerpt'] ?? ''); ?></textarea>
                </div>
            </div>

            <div>
                <div class="form-row">
                    <label for="status">Estado</label>
                    <select id="status" name="status">
                        <option value="publish" <?php echo ($post['status'] ?? 'publish') === 'publish' ? 'selected' : ''; ?>>Publicado</option>
                        <option value="draft" <?php echo ($post['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Borrador</option>
                    </select>
                </div>
                <div class="form-row">
                    <label for="category_id">Categor&iacute;a</label>
                    <select id="category_id" name="category_id">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int) $cat['id']; ?>" <?php echo (int) ($post['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo Security::e($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label for="tags">Etiquetas (separadas por coma)</label>
                    <input type="text" id="tags" name="tags" value="<?php echo Security::e($currentTags); ?>" placeholder="php, cms, seo">
                </div>
                <div class="form-row">
                    <label for="featured_image">URL Imagen Destacada</label>
                    <input type="text" id="featured_image" name="featured_image" value="<?php echo Security::e($post['featured_image'] ?? '/uploads/covers/cover-1.png'); ?>">
                </div>
                <div class="form-row">
                    <label for="image_file">O subir nueva imagen de portada (JPG/PNG/WebP)</label>
                    <input type="file" id="image_file" name="image_file" accept="image/*">
                </div>
                <hr>
                <div class="form-row">
                    <label for="meta_title">SEO Meta Title (Plugin SEO Optimizer)</label>
                    <input type="text" id="meta_title" name="meta_title" value="<?php echo Security::e($post['meta_title'] ?? ''); ?>">
                </div>
                <div class="form-row">
                    <label for="meta_description">SEO Meta Description</label>
                    <textarea id="meta_description" name="meta_description" style="min-height:65px;"><?php echo Security::e($post['meta_description'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <button class="btn" type="submit">Guardar Art&iacute;culo</button>
    </form>
    <?php
}

function admin_view_categories(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        $act = $_POST['action'] ?? '';
        if ($act === 'create_cat') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $desc = trim((string) ($_POST['description'] ?? ''));
            if ($name !== '') {
                Database::execute('INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)', [$name, Helpers::slugify($name), $desc]);
                Helpers::flash('ok', 'Categoria creada.');
            }
        } elseif ($act === 'delete_cat') {
            Database::execute('DELETE FROM categories WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
            Helpers::flash('ok', 'Categoria eliminada.');
        } elseif ($act === 'delete_tag') {
            Database::execute('DELETE FROM tags WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
            Helpers::flash('ok', 'Etiqueta eliminada.');
        }
        Helpers::redirect('/admin/categories');
    }

    $cats = Database::fetchAll('SELECT c.*, COUNT(p.id) AS total FROM categories c LEFT JOIN posts p ON p.category_id = c.id GROUP BY c.id ORDER BY c.name ASC');
    $tags = Database::fetchAll('SELECT * FROM tags ORDER BY name ASC');
    ?>
    <div class="page-header"><h1>Categor&iacute;as y Etiquetas</h1></div>
    <div class="grid-2">
        <div class="card">
            <h2 style="margin-top:0;">Nueva Categor&iacute;a</h2>
            <form method="post">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="create_cat">
                <div class="form-row"><label>Nombre</label><input type="text" name="name" required></div>
                <div class="form-row"><label>Descripci&oacute;n</label><textarea name="description" style="min-height:70px;"></textarea></div>
                <button class="btn" type="submit">A&ntilde;adir Categor&iacute;a</button>
            </form>
            <h3 style="margin-top:22px;">Etiquetas registradas</h3>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                <?php foreach ($tags as $t): ?>
                    <form method="post" style="display:inline-flex;align-items:center;gap:4px;background:#f1f5f9;padding:4px 8px;border-radius:4px;">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="delete_tag">
                        <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                        <span>#<?php echo Security::e($t['name']); ?></span>
                        <button type="submit" style="border:none;background:none;color:#d63638;cursor:pointer;font-weight:700;" title="Eliminar">&times;</button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Listado de Categor&iacute;as</h2>
            <table class="data-table">
                <thead><tr><th>Nombre</th><th>Slug</th><th>Posts</th><th>Acci&oacute;n</th></tr></thead>
                <tbody>
                <?php foreach ($cats as $c): ?>
                    <tr>
                        <td><strong><?php echo Security::e($c['name']); ?></strong></td>
                        <td><code><?php echo Security::e($c['slug']); ?></code></td>
                        <td><?php echo (int) $c['total']; ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php echo Security::csrfField(); ?>
                                <input type="hidden" name="action" value="delete_cat">
                                <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Borrar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function admin_view_pages(): void
{
    $user = Auth::user();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        $act = $_POST['action'] ?? '';
        if ($act === 'save') {
            $id      = (int) ($_POST['id'] ?? 0);
            $title   = trim((string) ($_POST['title'] ?? ''));
            $slug    = Helpers::slugify(trim((string) ($_POST['slug'] ?? '')) ?: $title);
            $content = Helpers::sanitizeHtml((string) ($_POST['content'] ?? ''));
            $status  = in_array($_POST['status'] ?? 'publish', ['publish', 'draft'], true) ? $_POST['status'] : 'publish';
            $meta    = trim((string) ($_POST['meta_description'] ?? ''));
            if ($id > 0) {
                Database::execute('UPDATE pages SET title = ?, slug = ?, content = ?, status = ?, meta_description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$title, $slug, $content, $status, $meta, $id]);
                Helpers::flash('ok', 'Pagina actualizada.');
            } else {
                Database::execute('INSERT INTO pages (title, slug, content, status, author_id, meta_description) VALUES (?, ?, ?, ?, ?, ?)', [$title, $slug, $content, $status, (int) $user['id'], $meta]);
                Helpers::flash('ok', 'Pagina creada.');
            }
        } elseif ($act === 'delete') {
            Database::execute('DELETE FROM pages WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
            Helpers::flash('ok', 'Pagina eliminada.');
        }
        Helpers::redirect('/admin/pages');
    }

    $editPage = null;
    if (!empty($_GET['edit'])) {
        $editPage = Database::fetchOne('SELECT * FROM pages WHERE id = ?', [(int) $_GET['edit']]);
    }
    $pages = Database::fetchAll('SELECT * FROM pages ORDER BY id DESC');
    ?>
    <div class="page-header"><h1>P&aacute;ginas Est&aacute;ticas</h1></div>
    <div class="grid-2">
        <div class="card">
            <h2 style="margin-top:0;"><?php echo $editPage ? 'Editar P&aacute;gina' : 'Nueva P&aacute;gina'; ?></h2>
            <form method="post">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="save">
                <?php if ($editPage): ?><input type="hidden" name="id" value="<?php echo (int) $editPage['id']; ?>"><?php endif; ?>
                <div class="form-row"><label>T&iacute;tulo</label><input type="text" name="title" required value="<?php echo Security::e($editPage['title'] ?? ''); ?>"></div>
                <div class="form-row"><label>Slug</label><input type="text" name="slug" value="<?php echo Security::e($editPage['slug'] ?? ''); ?>"></div>
                <div class="form-row"><label>Contenido (Shortcodes: <code>[galeria]</code>, <code>[contacto]</code>)</label><textarea name="content" required><?php echo Security::e($editPage['content'] ?? ''); ?></textarea></div>
                <div class="form-row"><label>Meta Descripci&oacute;n SEO</label><input type="text" name="meta_description" value="<?php echo Security::e($editPage['meta_description'] ?? ''); ?>"></div>
                <div class="form-row"><label>Estado</label>
                    <select name="status">
                        <option value="publish" <?php echo ($editPage['status'] ?? 'publish') === 'publish' ? 'selected' : ''; ?>>Publicada</option>
                        <option value="draft" <?php echo ($editPage['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Borrador</option>
                    </select>
                </div>
                <button class="btn" type="submit">Guardar P&aacute;gina</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">P&aacute;ginas Existentes</h2>
            <table class="data-table">
                <thead><tr><th>T&iacute;tulo</th><th>URL</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($pages as $pg): ?>
                    <tr>
                        <td><strong><?php echo Security::e($pg['title']); ?></strong></td>
                        <td><a target="_blank" href="<?php echo Security::e(Helpers::url('/pagina/' . $pg['slug'])); ?>">/pagina/<?php echo Security::e($pg['slug']); ?></a></td>
                        <td><span class="pill <?php echo $pg['status'] === 'publish' ? 'pill-ok' : 'pill-warn'; ?>"><?php echo Security::e($pg['status']); ?></span></td>
                        <td style="white-space:nowrap;">
                            <a class="btn btn-sec btn-sm" href="<?php echo Security::e(Helpers::url('/admin/pages?edit=' . (int) $pg['id'])); ?>">Editar</a>
                            <form method="post" style="display:inline;">
                                <?php echo Security::csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $pg['id']; ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Borrar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function admin_view_comments(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        $id  = (int) ($_POST['id'] ?? 0);
        $act = $_POST['action'] ?? '';
        if ($act === 'approve') {
            Database::execute('UPDATE comments SET status = ? WHERE id = ?', ['approved', $id]);
            Helpers::flash('ok', 'Comentario aprobado y visible en el sitio.');
        } elseif ($act === 'reject') {
            Database::execute('UPDATE comments SET status = ? WHERE id = ?', ['rejected', $id]);
            Helpers::flash('ok', 'Comentario rechazado.');
        } elseif ($act === 'delete') {
            Database::execute('DELETE FROM comments WHERE id = ?', [$id]);
            Helpers::flash('ok', 'Comentario eliminado.');
        }
        Helpers::redirect('/admin/comments');
    }

    $comments = Database::fetchAll(
        'SELECT c.*, p.title AS post_title, p.slug AS post_slug
         FROM comments c JOIN posts p ON p.id = c.post_id
         ORDER BY CASE WHEN c.status = \'pending\' THEN 0 ELSE 1 END, c.created_at DESC'
    );
    ?>
    <div class="page-header">
        <h1>Comentarios con Moderaci&oacute;n</h1>
        <span>Moderaci&oacute;n manual: <strong><?php echo Helpers::option('comment_moderation', '1') === '1' ? 'Activada' : 'Desactivada'; ?></strong></span>
    </div>
    <table class="data-table">
        <thead><tr><th>Autor</th><th>Comentario</th><th>Art&iacute;culo</th><th>Estado</th><th>Moderaci&oacute;n</th></tr></thead>
        <tbody>
        <?php foreach ($comments as $c): ?>
            <tr>
                <td><strong><?php echo Security::e($c['author_name']); ?></strong><br><small><?php echo Security::e($c['author_email']); ?></small></td>
                <td><?php echo Security::e($c['content']); ?></td>
                <td><a target="_blank" href="<?php echo Security::e(Helpers::url('/post/' . $c['post_slug'])); ?>"><?php echo Security::e($c['post_title']); ?></a></td>
                <td>
                    <span class="pill <?php echo $c['status'] === 'approved' ? 'pill-ok' : ($c['status'] === 'pending' ? 'pill-warn' : 'pill-muted'); ?>">
                        <?php echo Security::e($c['status']); ?>
                    </span>
                </td>
                <td style="white-space:nowrap;">
                    <?php if ($c['status'] !== 'approved'): ?>
                        <form method="post" style="display:inline;"><?php echo Security::csrfField(); ?><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-ok btn-sm" type="submit">Aprobar</button></form>
                    <?php endif; ?>
                    <?php if ($c['status'] !== 'rejected'): ?>
                        <form method="post" style="display:inline;"><?php echo Security::csrfField(); ?><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-sec btn-sm" type="submit">Rechazar</button></form>
                    <?php endif; ?>
                    <form method="post" style="display:inline;"><?php echo Security::csrfField(); ?><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" type="submit">Borrar</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function admin_view_media(): void
{
    $user = Auth::user();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['media_file']['tmp_name'])) {
        Security::requireCsrf();
        $url = Helpers::uploadImage($_FILES['media_file'], 'covers');
        if ($url) {
            Database::execute(
                'INSERT INTO media (filename, original_name, mime_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?)',
                [$url, basename((string) $_FILES['media_file']['name']), (string) ($_FILES['media_file']['type'] ?? 'image/png'), (int) ($_FILES['media_file']['size'] ?? 0), (int) $user['id']]
            );
            Helpers::flash('ok', 'Imagen subida: ' . $url);
        } else {
            Helpers::flash('err', 'Archivo rechazado. Solo JPG, PNG, WebP o GIF menores a 5 MB.');
        }
        Helpers::redirect('/admin/media');
    }

    $files = glob(BLOGCMS_ROOT . '/uploads/covers/*.{png,jpg,jpeg,webp,gif}', GLOB_BRACE) ?: [];
    ?>
    <div class="page-header"><h1>Biblioteca de Medios</h1></div>
    <div class="card">
        <form method="post" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <?php echo Security::csrfField(); ?>
            <input type="file" name="media_file" accept="image/*" required style="max-width:340px;">
            <button class="btn" type="submit">Subir Imagen</button>
        </form>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px;">
        <?php foreach ($files as $f): $rel = '/uploads/covers/' . basename($f); ?>
            <div class="card" style="padding:10px;margin:0;">
                <img src="<?php echo Security::e(Helpers::url($rel)); ?>" alt="" style="width:100%;height:130px;object-fit:cover;border-radius:4px;margin-bottom:8px;">
                <code style="font-size:11px;word-break:break-all;"><?php echo Security::e($rel); ?></code>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}
