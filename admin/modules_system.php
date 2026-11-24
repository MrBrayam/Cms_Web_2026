<?php
/**
 * Modulos de sistema del Panel Admin:
 * Temas (.zip + switcher), Menu Dinamico Configurable, Plugins (.zip + PluginManager), Usuarios y Roles, Ajustes y Auditoria.
 */

function admin_view_themes(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        $act = $_POST['action'] ?? '';
        if ($act === 'activate') {
            $slug = (string) ($_POST['slug'] ?? '');
            if (ThemeManager::activate($slug)) {
                Helpers::flash('ok', "Tema '$slug' activado.");
            } else {
                Helpers::flash('err', 'No se pudo activar el tema.');
            }
        } elseif ($act === 'upload_zip' && isset($_FILES['theme_zip'])) {
            $res = ThemeManager::installFromZip($_FILES['theme_zip']);
            if ($res['ok']) {
                Helpers::flash('ok', "Tema '{$res['name']}' v{$res['version']} extraido con ZipArchive e instalado en /themes/{$res['slug']}/.");
            } else {
                Helpers::flash('err', $res['error']);
            }
        } elseif ($act === 'delete') {
            if (ThemeManager::deleteTheme((string) ($_POST['slug'] ?? ''))) {
                Helpers::flash('ok', 'Tema eliminado.');
            } else {
                Helpers::flash('err', 'No puedes eliminar el tema activo.');
            }
        }
        Helpers::redirect('/admin/themes');
    }

    $themes = Database::fetchAll('SELECT * FROM themes ORDER BY is_active DESC, name ASC');
    ?>
    <div class="page-header"><h1>Apariencia &mdash; Temas Propios</h1></div>

    <div class="card">
        <h2 style="margin-top:0;">Subir Plantilla / Tema (.zip) &mdash; Flujo L&aacute;mina 8</h2>
        <p style="color:#646970;">Sube un paquete <code>.zip</code> que contenga <code>theme.json</code>, <code>style.css</code> y plantillas PHP. Se validar&aacute; y extraer&aacute; con <code>ZipArchive</code> a <code>/themes/nombre/</code>.</p>
        <form method="post" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="upload_zip">
            <input type="file" name="theme_zip" accept=".zip" required style="max-width:340px;">
            <button class="btn" type="submit">Subir e Instalar Tema (.zip)</button>
        </form>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:18px;">
        <?php foreach ($themes as $t): $active = ((int) $t['is_active'] === 1); ?>
            <div class="card" style="margin:0;display:flex;flex-direction:column;justify-content:space-between;border-color:<?php echo $active ? '#2271b1' : '#dcdcde'; ?>;border-width:<?php echo $active ? '2px' : '1px'; ?>;">
                <div>
                    <?php if (is_file(BLOGCMS_ROOT . '/themes/' . $t['slug'] . '/screenshot.png')): ?>
                        <img src="<?php echo Security::e(Helpers::url('/themes/' . $t['slug'] . '/screenshot.png')); ?>" alt="" style="width:100%;height:160px;object-fit:cover;border-radius:4px;margin-bottom:12px;border:1px solid #e2e8f0;">
                    <?php endif; ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <h3 style="margin:0;"><?php echo Security::e($t['name']); ?></h3>
                        <?php if ($active): ?><span class="pill pill-ok">Activo</span><?php endif; ?>
                    </div>
                    <p style="font-size:12.5px;color:#646970;"><?php echo Security::e($t['description']); ?></p>
                </div>
                <div style="display:flex;gap:8px;margin-top:8px;">
                    <?php if (!$active): ?>
                        <form method="post">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="action" value="activate">
                            <input type="hidden" name="slug" value="<?php echo Security::e($t['slug']); ?>">
                            <button class="btn btn-sm" type="submit">Activar Tema</button>
                        </form>
                        <form method="post">
                            <?php echo Security::csrfField(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="slug" value="<?php echo Security::e($t['slug']); ?>">
                            <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('Eliminar tema?')">Eliminar</button>
                        </form>
                    <?php else: ?>
                        <a class="btn btn-sec btn-sm" target="_blank" href="<?php echo Security::e(Helpers::url('/')); ?>">Ver Portada</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

function admin_view_menus(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        $act = $_POST['action'] ?? '';
        if ($act === 'add') {
            $type     = $_POST['target_type'] ?? 'custom';
            $location = in_array($_POST['location'] ?? 'primary', ['primary', 'footer'], true) ? $_POST['location'] : 'primary';
            $order    = (int) ($_POST['sort_order'] ?? 10);
            $label    = trim((string) ($_POST['label'] ?? ''));
            $url      = trim((string) ($_POST['url'] ?? '/'));
            $targetId = null;

            if ($type === 'page') {
                $pg = Database::fetchOne('SELECT id, title, slug FROM pages WHERE id = ?', [(int) ($_POST['page_id'] ?? 0)]);
                if ($pg) {
                    $label = $label !== '' ? $label : $pg['title'];
                    $url   = '/pagina/' . $pg['slug'];
                    $targetId = (int) $pg['id'];
                }
            } elseif ($type === 'category') {
                $cat = Database::fetchOne('SELECT id, name, slug FROM categories WHERE id = ?', [(int) ($_POST['cat_id'] ?? 0)]);
                if ($cat) {
                    $label = $label !== '' ? $label : $cat['name'];
                    $url   = '/categoria/' . $cat['slug'];
                    $targetId = (int) $cat['id'];
                }
            }

            if ($label !== '') {
                Database::execute(
                    'INSERT INTO menus (label, url, target_type, target_id, location, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
                    [$label, $url, $type, $targetId, $location, $order]
                );
                Helpers::flash('ok', 'Elemento agregado al menu.');
            }
        } elseif ($act === 'delete') {
            Database::execute('DELETE FROM menus WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
            Helpers::flash('ok', 'Elemento eliminado del menu.');
        }
        Helpers::redirect('/admin/menus');
    }

    $items = Database::fetchAll('SELECT * FROM menus ORDER BY location ASC, sort_order ASC, id ASC');
    $pages = Database::fetchAll('SELECT id, title FROM pages ORDER BY title ASC');
    $cats  = Database::fetchAll('SELECT id, name FROM categories ORDER BY name ASC');
    ?>
    <div class="page-header"><h1>Men&uacute; Din&aacute;mico Configurable</h1></div>
    <div class="grid-2">
        <div class="card">
            <h2 style="margin-top:0;">Agregar Elemento al Men&uacute;</h2>
            <form method="post">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <label>Tipo de enlace</label>
                    <select name="target_type" id="menu_type" onchange="toggleMenuInputs(this.value)">
                        <option value="custom">Enlace personalizado</option>
                        <option value="page">P&aacute;gina est&aacute;tica</option>
                        <option value="category">Categor&iacute;a</option>
                    </select>
                </div>
                <div class="form-row" id="row_page" style="display:none;">
                    <label>Seleccionar P&aacute;gina</label>
                    <select name="page_id">
                        <?php foreach ($pages as $pg): ?><option value="<?php echo (int) $pg['id']; ?>"><?php echo Security::e($pg['title']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row" id="row_cat" style="display:none;">
                    <label>Seleccionar Categor&iacute;a</label>
                    <select name="cat_id">
                        <?php foreach ($cats as $c): ?><option value="<?php echo (int) $c['id']; ?>"><?php echo Security::e($c['name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Etiqueta visible (Opcional para p&aacute;gina/categor&iacute;a)</label><input type="text" name="label" placeholder="Ej. Blog"></div>
                <div class="form-row" id="row_url"><label>URL</label><input type="text" name="url" value="/"></div>
                <div class="form-row">
                    <label>Ubicaci&oacute;n</label>
                    <select name="location">
                        <option value="primary">Men&uacute; Principal (Cabecera)</option>
                        <option value="footer">Men&uacute; Pie de P&aacute;gina</option>
                    </select>
                </div>
                <div class="form-row"><label>Orden</label><input type="number" name="sort_order" value="10"></div>
                <button class="btn" type="submit">Agregar al Men&uacute;</button>
            </form>
            <script>
            function toggleMenuInputs(v) {
                document.getElementById('row_page').style.display = v === 'page' ? 'block' : 'none';
                document.getElementById('row_cat').style.display  = v === 'category' ? 'block' : 'none';
                document.getElementById('row_url').style.display  = v === 'custom' ? 'block' : 'none';
            }
            </script>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Estructura Actual del Men&uacute;</h2>
            <table class="data-table">
                <thead><tr><th>Ubicaci&oacute;n</th><th>Etiqueta</th><th>URL</th><th>Orden</th><th>Acci&oacute;n</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><span class="pill pill-muted"><?php echo Security::e($it['location']); ?></span></td>
                        <td><strong><?php echo Security::e($it['label']); ?></strong></td>
                        <td><code><?php echo Security::e($it['url']); ?></code></td>
                        <td><?php echo (int) $it['sort_order']; ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php echo Security::csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $it['id']; ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Quitar</button>
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

function admin_view_plugins(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        $act = $_POST['action'] ?? '';
        if ($act === 'upload_zip' && isset($_FILES['plugin_zip'])) {
            $res = PluginManager::installFromZip($_FILES['plugin_zip']);
            if ($res['ok']) {
                Helpers::flash('ok', "Plugin '{$res['name']}' v{$res['version']} validado, extraido con ZipArchive a /plugins/{$res['slug']}/, leido plugin.json y registrado en BD.");
            } else {
                Helpers::flash('err', $res['error']);
            }
        } elseif ($act === 'toggle') {
            PluginManager::toggle((string) ($_POST['slug'] ?? ''));
            Helpers::flash('ok', 'Estado del plugin actualizado.');
        } elseif ($act === 'delete') {
            PluginManager::deletePlugin((string) ($_POST['slug'] ?? ''));
            Helpers::flash('ok', 'Plugin desinstalado y eliminado de /plugins/.');
        }
        Helpers::redirect('/admin/plugins');
    }

    $plugins = Database::fetchAll('SELECT * FROM plugins ORDER BY id ASC');
    ?>
    <div class="page-header"><h1>Gestor de Plugins (PluginManager &mdash; L&aacute;mina 8)</h1></div>

    <div class="card">
        <h2 style="margin-top:0;">Subir e Instalar Plugin (.zip)</h2>
        <p style="color:#646970;margin-bottom:12px;">
            Flujo autom&aacute;tico: <strong>1)</strong> Sube <code>.zip</code> &rarr; <strong>2)</strong> PHP valida tipo/tama&ntilde;o/estructura &rarr;
            <strong>3)</strong> <code>ZipArchive</code> extrae a <code>/plugins/nombre/</code> &rarr; <strong>4)</strong> Lee <code>plugin.json</code> &rarr;
            <strong>5)</strong> Registra en BD &rarr; <strong>6)</strong> <code>PluginManager</code> lo carga en cada petici&oacute;n.
        </p>
        <form method="post" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="upload_zip">
            <input type="file" name="plugin_zip" accept=".zip" required style="max-width:360px;">
            <button class="btn" type="submit">Subir e Instalar Plugin (.zip)</button>
        </form>
    </div>

    <table class="data-table">
        <thead><tr><th>Plugin</th><th>Descripci&oacute;n</th><th>Versi&oacute;n / Autor</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($plugins as $pl): $active = ((int) $pl['is_active'] === 1); ?>
            <tr>
                <td>
                    <strong><?php echo Security::e($pl['name']); ?></strong><br>
                    <small style="color:#646970;">/plugins/<?php echo Security::e($pl['slug']); ?>/plugin.json</small>
                </td>
                <td><?php echo Security::e($pl['description']); ?></td>
                <td>v<?php echo Security::e($pl['version']); ?><br><small><?php echo Security::e($pl['author']); ?></small></td>
                <td><span class="pill <?php echo $active ? 'pill-ok' : 'pill-muted'; ?>"><?php echo $active ? 'Activo' : 'Inactivo'; ?></span></td>
                <td style="white-space:nowrap;">
                    <form method="post" style="display:inline;">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="slug" value="<?php echo Security::e($pl['slug']); ?>">
                        <button class="btn btn-sec btn-sm" type="submit"><?php echo $active ? 'Desactivar' : 'Activar'; ?></button>
                    </form>
                    <?php if ($active): ?>
                        <a class="btn btn-sm" href="<?php echo Security::e(Helpers::url('/admin/plugin-panel?slug=' . urlencode($pl['slug']))); ?>">Configurar</a>
                    <?php endif; ?>
                    <form method="post" style="display:inline;">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="slug" value="<?php echo Security::e($pl['slug']); ?>">
                        <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('Desinstalar y borrar archivos del plugin?')">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function admin_view_users(): void
{
    $me = Auth::user();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        $act = $_POST['action'] ?? '';
        if ($act === 'create') {
            $uname = trim((string) ($_POST['username'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $dname = trim((string) ($_POST['display_name'] ?? ''));
            $pass  = (string) ($_POST['password'] ?? '');
            $role  = in_array($_POST['role'] ?? 'author', ['admin', 'editor', 'author'], true) ? $_POST['role'] : 'author';
            if ($uname !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($pass) >= 6) {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                Database::execute(
                    'INSERT INTO users (username, email, password_hash, display_name, role) VALUES (?, ?, ?, ?, ?)',
                    [$uname, $email, $hash, $dname !== '' ? $dname : $uname, $role]
                );
                Helpers::audit('user_create', "Usuario $uname ($role) creado");
                Helpers::flash('ok', "Usuario '$uname' creado con rol '$role'.");
            } else {
                Helpers::flash('err', 'Datos invalidos (contrasena minima de 6 caracteres).');
            }
        } elseif ($act === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $me['id']) {
                Helpers::flash('err', 'No puedes eliminar tu propio usuario activo.');
            } else {
                Database::execute('DELETE FROM users WHERE id = ?', [$id]);
                Helpers::audit('user_delete', "Usuario ID $id eliminado");
                Helpers::flash('ok', 'Usuario eliminado.');
            }
        }
        Helpers::redirect('/admin/users');
    }

    $users = Database::fetchAll('SELECT id, username, email, display_name, role, created_at FROM users ORDER BY id ASC');
    ?>
    <div class="page-header"><h1>Usuarios y Roles (Admin / Editor / Autor)</h1></div>
    <div class="grid-2">
        <div class="card">
            <h2 style="margin-top:0;">Nuevo Usuario</h2>
            <form method="post">
                <?php echo Security::csrfField(); ?>
                <input type="hidden" name="action" value="create">
                <div class="form-row"><label>Usuario</label><input type="text" name="username" required></div>
                <div class="form-row"><label>Nombre visible</label><input type="text" name="display_name" required></div>
                <div class="form-row"><label>Correo</label><input type="email" name="email" required></div>
                <div class="form-row"><label>Contrase&ntilde;a (min. 6 caracteres, hash BCrypt)</label><input type="password" name="password" required minlength="6"></div>
                <div class="form-row">
                    <label>Rol</label>
                    <select name="role">
                        <option value="author">Autor (solo gestiona sus propias entradas)</option>
                        <option value="editor">Editor (modera entradas, categorias, paginas y comentarios)</option>
                        <option value="admin">Administrador (control total del CMS)</option>
                    </select>
                </div>
                <button class="btn" type="submit">Crear Usuario</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Usuarios Registrados</h2>
            <table class="data-table">
                <thead><tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Acci&oacute;n</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><strong><?php echo Security::e($u['username']); ?></strong><br><small><?php echo Security::e($u['email']); ?></small></td>
                        <td><?php echo Security::e($u['display_name']); ?></td>
                        <td><span class="pill pill-ok"><?php echo Security::e($u['role']); ?></span></td>
                        <td>
                            <?php if ((int) $u['id'] !== (int) $me['id']): ?>
                                <form method="post" style="display:inline;">
                                    <?php echo Security::csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">Borrar</button>
                                </form>
                            <?php else: ?>
                                <small>Tu cuenta</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function admin_view_settings(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requireCsrf();
        Helpers::setOption('site_title', trim((string) ($_POST['site_title'] ?? 'Bloc-FISI')));
        Helpers::setOption('site_tagline', trim((string) ($_POST['site_tagline'] ?? '')));
        Helpers::setOption('posts_per_page', (string) max(1, (int) ($_POST['posts_per_page'] ?? 9)));
        Helpers::setOption('comment_moderation', isset($_POST['comment_moderation']) ? '1' : '0');
        Helpers::setOption('hero_title', trim((string) ($_POST['hero_title'] ?? '')));
        Helpers::setOption('hero_subtitle', trim((string) ($_POST['hero_subtitle'] ?? '')));
        Helpers::audit('settings_update', 'Ajustes generales actualizados');
        Helpers::flash('ok', 'Ajustes guardados.');
        Helpers::redirect('/admin/settings');
    }

    $logs = Database::fetchAll('SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 15');
    ?>
    <div class="page-header"><h1>Ajustes Generales y Auditor&iacute;a de Seguridad</h1></div>
    <div class="grid-2">
        <div class="card">
            <h2 style="margin-top:0;">Configuraci&oacute;n del CMS</h2>
            <form method="post">
                <?php echo Security::csrfField(); ?>
                <div class="form-row"><label>T&iacute;tulo del sitio</label><input type="text" name="site_title" value="<?php echo Security::e(Helpers::option('site_title', 'Bloc-FISI')); ?>"></div>
                <div class="form-row"><label>Descripci&oacute;n corta</label><input type="text" name="site_tagline" value="<?php echo Security::e(Helpers::option('site_tagline', '')); ?>"></div>
                <div class="form-row"><label>Art&iacute;culos por p&aacute;gina</label><input type="number" name="posts_per_page" value="<?php echo Security::e(Helpers::option('posts_per_page', '9')); ?>"></div>
                <div class="form-row">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="comment_moderation" value="1" <?php echo Helpers::option('comment_moderation', '1') === '1' ? 'checked' : ''; ?>>
                        <span>Requerir aprobaci&oacute;n manual para comentarios (Moderaci&oacute;n)</span>
                    </label>
                </div>
                <div class="form-row"><label>Hero Title (Tema Business)</label><input type="text" name="hero_title" value="<?php echo Security::e(Helpers::option('hero_title', '')); ?>"></div>
                <div class="form-row"><label>Hero Subtitle (Tema Business)</label><textarea name="hero_subtitle" style="min-height:70px;"><?php echo Security::e(Helpers::option('hero_subtitle', '')); ?></textarea></div>
                <button class="btn" type="submit">Guardar Ajustes</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Registro de Auditor&iacute;a (OWASP A09)</h2>
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Acci&oacute;n</th><th>Detalle</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $lg): ?>
                    <tr>
                        <td><small><?php echo Security::e($lg['created_at']); ?></small></td>
                        <td><?php echo Security::e($lg['username'] ?? 'anonimo'); ?></td>
                        <td><code><?php echo Security::e($lg['action']); ?></code></td>
                        <td><small><?php echo Security::e($lg['details']); ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function admin_view_plugin_panel(): void
{
    $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($_GET['slug'] ?? '')));
    if ($slug === '' || !PluginManager::isLoaded($slug)) {
        echo '<div class="card"><h2>Plugin no activo o no encontrado</h2><p>Activa el plugin en <a href="' . Security::e(Helpers::url('/admin/plugins')) . '">Plugins</a> para ver su panel.</p></div>';
        return;
    }
    PluginManager::doAction('admin_panel_' . $slug);
}
