<?php
/**
 * Plugin: Galeria de Imagenes con Albumes ([galeria])
 */

PluginManager::addFilter('content_render', function (string $content) {
    if (!str_contains($content, '[galeria]')) {
        return $content;
    }

    $albums = Database::fetchAll('SELECT * FROM gallery_albums ORDER BY id ASC');
    if (empty($albums)) {
        return str_replace('[galeria]', '<p><em>No hay albumes publicados todavia.</em></p>', $content);
    }

    $selectedSlug = trim((string) ($_GET['album'] ?? ''));
    $html = '<div class="bcms-gallery-wrap" style="margin:1.5rem 0;">';

    if ($selectedSlug !== '') {
        $album = Database::fetchOne('SELECT * FROM gallery_albums WHERE slug = ?', [$selectedSlug]);
        if ($album) {
            $images = Database::fetchAll('SELECT * FROM gallery_images WHERE album_id = ? ORDER BY sort_order ASC, id ASC', [(int) $album['id']]);
            $html .= '<p><a href="?">&larr; Volver a todos los &aacute;lbumes</a></p>';
            $html .= '<h3>' . Security::e($album['title']) . '</h3>';
            $html .= '<p>' . Security::e($album['description']) . '</p>';
            $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin-top:1rem;">';
            foreach ($images as $img) {
                $html .= '<figure style="margin:0;border:1px solid #cbd5e1;border-radius:8px;overflow:hidden;background:#fff;color:#1e293b;">';
                $html .= '<img src="' . Security::e(Helpers::url($img['image_path'])) . '" alt="' . Security::e($img['title']) . '" style="width:100%;height:175px;object-fit:cover;">';
                $html .= '<figcaption style="padding:8px 10px;font-size:0.86rem;"><strong>' . Security::e($img['title']) . '</strong><br><span style="color:#64748b;">' . Security::e($img['caption']) . '</span></figcaption>';
                $html .= '</figure>';
            }
            $html .= '</div></div>';
            return str_replace('[galeria]', $html, $content);
        }
    }

    $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">';
    foreach ($albums as $al) {
        $count = (int) Database::fetchValue('SELECT COUNT(*) FROM gallery_images WHERE album_id = ?', [(int) $al['id']], 0);
        $cover = $al['cover_image'] !== '' ? Helpers::url($al['cover_image']) : Helpers::url('/uploads/gallery/campus-1.png');
        $html .= '<div style="border:1px solid #cbd5e1;border-radius:10px;overflow:hidden;background:#fff;color:#1e293b;">';
        $html .= '<img src="' . Security::e($cover) . '" alt="' . Security::e($al['title']) . '" style="width:100%;height:180px;object-fit:cover;">';
        $html .= '<div style="padding:14px;">';
        $html .= '<h3 style="margin:0 0 6px;font-size:1.1rem;">' . Security::e($al['title']) . '</h3>';
        $html .= '<p style="margin:0 0 10px;font-size:0.88rem;color:#475569;">' . Security::e($al['description']) . '</p>';
        $html .= '<a href="?album=' . urlencode($al['slug']) . '" style="font-weight:700;">Ver &aacute;lbum (' . $count . ' fotos) &rarr;</a>';
        $html .= '</div></div>';
    }
    $html .= '</div></div>';

    return str_replace('[galeria]', $html, $content);
});

PluginManager::addFilter('admin_menu_items', function (array $items) {
    $items[] = ['slug' => 'image-gallery', 'label' => 'Galeria y Albumes'];
    return $items;
});

PluginManager::addAction('admin_panel_image-gallery', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        $act = $_POST['action'] ?? '';
        if ($act === 'create_album') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $desc  = trim((string) ($_POST['description'] ?? ''));
            $cover = Helpers::uploadImage($_FILES['cover_file'] ?? [], 'gallery') ?? '/uploads/gallery/campus-1.png';
            if ($title !== '') {
                Database::execute('INSERT INTO gallery_albums (title, slug, description, cover_image) VALUES (?, ?, ?, ?)', [$title, Helpers::slugify($title), $desc, $cover]);
                Helpers::flash('ok', 'Album creado.');
            }
        } elseif ($act === 'add_image') {
            $albumId = (int) ($_POST['album_id'] ?? 0);
            $title   = trim((string) ($_POST['title'] ?? 'Foto'));
            $caption = trim((string) ($_POST['caption'] ?? ''));
            $path    = Helpers::uploadImage($_FILES['photo_file'] ?? [], 'gallery');
            if ($albumId > 0 && $path) {
                Database::execute('INSERT INTO gallery_images (album_id, title, image_path, caption) VALUES (?, ?, ?, ?)', [$albumId, $title, $path, $caption]);
                Helpers::flash('ok', 'Imagen agregada al album.');
            } else {
                Helpers::flash('err', 'Selecciona un album y una imagen valida.');
            }
        } elseif ($act === 'delete_album') {
            Database::execute('DELETE FROM gallery_albums WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
            Helpers::flash('ok', 'Album eliminado.');
        }
        Helpers::redirect('/admin/plugin-panel?slug=image-gallery');
    }

    $albums = Database::fetchAll('SELECT a.*, COUNT(i.id) AS total_imgs FROM gallery_albums a LEFT JOIN gallery_images i ON i.album_id = a.id GROUP BY a.id ORDER BY a.id ASC');
    ?>
    <div class="page-header"><h1>Galer&iacute;a de Im&aacute;genes con &Aacute;lbumes</h1><code>Shortcode: [galeria]</code></div>
    <div class="grid-2">
        <div class="card">
            <h2 style="margin-top:0;">Crear &Aacute;lbum</h2>
            <form method="post" enctype="multipart/form-data">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="create_album">
                <div class="form-row"><label>T&iacute;tulo del &Aacute;lbum</label><input type="text" name="title" required></div>
                <div class="form-row"><label>Descripci&oacute;n</label><textarea name="description" style="min-height:65px;"></textarea></div>
                <div class="form-row"><label>Portada</label><input type="file" name="cover_file" accept="image/*"></div>
                <button class="btn" type="submit">Crear &Aacute;lbum</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Subir Foto a un &Aacute;lbum</h2>
            <form method="post" enctype="multipart/form-data">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="add_image">
                <div class="form-row"><label>&Aacute;lbum</label>
                    <select name="album_id">
                        <?php foreach ($albums as $al): ?><option value="<?php echo (int) $al['id']; ?>"><?php echo Security::e($al['title']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>T&iacute;tulo de la foto</label><input type="text" name="title" required></div>
                <div class="form-row"><label>Leyenda</label><input type="text" name="caption"></div>
                <div class="form-row"><label>Imagen</label><input type="file" name="photo_file" accept="image/*" required></div>
                <button class="btn" type="submit">Subir Imagen</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">&Aacute;lbumes Registrados</h2>
        <table class="data-table">
            <thead><tr><th>&Aacute;lbum</th><th>Slug</th><th>Fotos</th><th>Acci&oacute;n</th></tr></thead>
            <tbody>
            <?php foreach ($albums as $al): ?>
                <tr>
                    <td><strong><?php echo Security::e($al['title']); ?></strong><br><small><?php echo Security::e($al['description']); ?></small></td>
                    <td><code><?php echo Security::e($al['slug']); ?></code></td>
                    <td><?php echo (int) $al['total_imgs']; ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="action" value="delete_album">
                            <input type="hidden" name="id" value="<?php echo (int) $al['id']; ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Borrar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
});
