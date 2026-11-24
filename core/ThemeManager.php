<?php
/**
 * ThemeManager — Gestion, instalacion via .zip (theme.json + ZipArchive), activacion y renderizado de temas propios.
 */

class ThemeManager
{
    public static function activeSlug(): string
    {
        $slug = Helpers::option('active_theme', 'magazine');
        $dir  = BLOGCMS_ROOT . '/themes/' . basename($slug);
        if (is_dir($dir) && is_file($dir . '/home.php')) {
            return basename($slug);
        }
        return 'magazine';
    }

    public static function activePath(): string
    {
        return BLOGCMS_ROOT . '/themes/' . self::activeSlug();
    }

    public static function assetUrl(string $relPath): string
    {
        return Helpers::url('/themes/' . self::activeSlug() . '/' . ltrim($relPath, '/'));
    }

    public static function render(string $template, array $data = []): void
    {
        $themePath = self::activePath();
        $viewFile  = $themePath . '/' . basename($template, '.php') . '.php';
        if (!is_file($viewFile)) {
            $viewFile = $themePath . '/home.php';
        }
        extract($data, EXTR_SKIP);
        $siteTitle   = Helpers::option('site_title', 'Bloc-FISI');
        $siteTagline = Helpers::option('site_tagline', 'Portal Academico de Ingenieria Web - UNSM');
        $primaryMenu = Helpers::menuItems('primary');
        $footerMenu  = Helpers::menuItems('footer');
        $categories  = Database::fetchAll('SELECT c.*, COUNT(p.id) AS post_count FROM categories c LEFT JOIN posts p ON p.category_id = c.id AND p.status = ? GROUP BY c.id ORDER BY c.name ASC', ['publish']);

        include $themePath . '/header.php';
        include $viewFile;
        include $themePath . '/footer.php';
    }

    public static function activate(string $slug): bool
    {
        $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($slug));
        if (!is_dir(BLOGCMS_ROOT . '/themes/' . $slug)) {
            return false;
        }
        Database::execute('UPDATE themes SET is_active = 0', []);
        Database::execute('UPDATE themes SET is_active = 1 WHERE slug = ?', [$slug]);
        Helpers::setOption('active_theme', $slug);
        Helpers::audit('theme_activate', "Tema activado: $slug");
        return true;
    }

    public static function installFromZip(array $file): array
    {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'error' => 'La extension PHP ZipArchive no esta habilitada.'];
        }
        if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'No se recibio archivo .zip valido.'];
        }
        if (($file['size'] ?? 0) > 15 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'El tema .zip supera los 15 MB permitidos.'];
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return ['ok' => false, 'error' => 'Solo se aceptan paquetes .zip.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            return ['ok' => false, 'error' => 'No se pudo abrir el archivo .zip.'];
        }

        $manifestIndex = -1;
        $prefixToStrip = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', $zip->getNameIndex($i));
            if (str_contains($name, '..') || str_starts_with($name, '/')) {
                $zip->close();
                return ['ok' => false, 'error' => 'Paquete rechazado por seguridad.'];
            }
            if ($name === 'theme.json') {
                $manifestIndex = $i;
                $prefixToStrip = '';
                break;
            }
            if (preg_match('#^([a-zA-Z0-9_-]+)/theme\.json$#', $name, $m)) {
                $manifestIndex = $i;
                $prefixToStrip = $m[1] . '/';
                break;
            }
        }

        if ($manifestIndex === -1) {
            $zip->close();
            return ['ok' => false, 'error' => 'Estructura invalida: el .zip debe contener theme.json.'];
        }

        $meta = json_decode((string) $zip->getFromIndex($manifestIndex), true);
        if (!is_array($meta) || empty($meta['slug']) || empty($meta['name'])) {
            $zip->close();
            return ['ok' => false, 'error' => 'theme.json invalido: requiere slug y name.'];
        }

        $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $meta['slug']));
        if ($slug === '') {
            $zip->close();
            return ['ok' => false, 'error' => 'Slug de tema invalido.'];
        }

        $targetDir = BLOGCMS_ROOT . '/themes/' . $slug;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $origName = str_replace('\\', '/', $zip->getNameIndex($i));
            if ($prefixToStrip !== '' && !str_starts_with($origName, $prefixToStrip)) {
                continue;
            }
            $relName = $prefixToStrip !== '' ? substr($origName, strlen($prefixToStrip)) : $origName;
            $relName = ltrim($relName, '/');
            if ($relName === '' || str_ends_with($relName, '/')) {
                if ($relName !== '' && !is_dir($targetDir . '/' . $relName)) {
                    mkdir($targetDir . '/' . $relName, 0755, true);
                }
                continue;
            }
            $destFile = $targetDir . '/' . $relName;
            $destSub  = dirname($destFile);
            if (!is_dir($destSub)) {
                mkdir($destSub, 0755, true);
            }
            $stream = $zip->getStream($zip->getNameIndex($i));
            if ($stream) {
                file_put_contents($destFile, stream_get_contents($stream));
                fclose($stream);
            }
        }
        $zip->close();

        if (!is_file($targetDir . '/home.php') || !is_file($targetDir . '/style.css')) {
            return ['ok' => false, 'error' => 'El tema debe incluir style.css y home.php.'];
        }

        $existing = Database::fetchOne('SELECT id FROM themes WHERE slug = ?', [$slug]);
        $name     = (string) $meta['name'];
        $version  = (string) ($meta['version'] ?? '1.0.0');
        $desc     = (string) ($meta['description'] ?? '');
        $author   = (string) ($meta['author'] ?? 'BlogCMS');

        if ($existing) {
            Database::execute(
                'UPDATE themes SET name = ?, version = ?, description = ?, author = ? WHERE slug = ?',
                [$name, $version, $desc, $author, $slug]
            );
        } else {
            Database::execute(
                'INSERT INTO themes (slug, name, version, description, author, is_active) VALUES (?, ?, ?, ?, ?, 0)',
                [$slug, $name, $version, $desc, $author]
            );
        }

        Helpers::audit('theme_install_zip', "Tema instalado via .zip: $slug v$version");
        return ['ok' => true, 'slug' => $slug, 'name' => $name, 'version' => $version];
    }

    public static function deleteTheme(string $slug): bool
    {
        $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($slug));
        if ($slug === '' || $slug === self::activeSlug()) {
            return false;
        }
        Database::execute('DELETE FROM themes WHERE slug = ?', [$slug]);
        $dir = BLOGCMS_ROOT . '/themes/' . $slug;
        if (is_dir($dir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $item) {
                $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
            }
            rmdir($dir);
        }
        Helpers::audit('theme_delete', "Tema eliminado: $slug");
        return true;
    }
}
