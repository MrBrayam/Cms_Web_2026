<?php
/**
 * PluginManager — Implementacion del flujo de Subida e Instalacion de Plugins (Lamina 8):
 * 1. Admin sube el .zip desde el panel
 * 2. PHP valida el archivo (tipo, tamano, estructura y path traversal)
 * 3. Se extrae con ZipArchive a /plugins/nombre/
 * 4. Se lee plugin.json para obtener la metadata
 * 5. Se registra en la BD como plugin instalado
 * 6. PluginManager lo carga en cada peticion
 */

class PluginManager
{
    private static array $actions = [];
    private static array $filters = [];
    private static array $loaded  = [];
    private static array $routes  = [];

    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        self::$actions[$hook][$priority][] = $callback;
    }

    public static function doAction(string $hook, ...$args): void
    {
        if (empty(self::$actions[$hook])) {
            return;
        }
        ksort(self::$actions[$hook]);
        foreach (self::$actions[$hook] as $callbacks) {
            foreach ($callbacks as $cb) {
                $cb(...$args);
            }
        }
    }

    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        self::$filters[$hook][$priority][] = $callback;
    }

    public static function applyFilters(string $hook, $value, ...$args)
    {
        if (empty(self::$filters[$hook])) {
            return $value;
        }
        ksort(self::$filters[$hook]);
        foreach (self::$filters[$hook] as $callbacks) {
            foreach ($callbacks as $cb) {
                $value = $cb($value, ...$args);
            }
        }
        return $value;
    }

    public static function registerRoute(string $method, string $path, callable $handler): void
    {
        self::$routes[strtoupper($method) . ':' . $path] = $handler;
    }

    public static function matchRoute(string $method, string $path): ?callable
    {
        $key = strtoupper($method) . ':' . $path;
        return self::$routes[$key] ?? null;
    }

    public static function loadActivePlugins(): void
    {
        $active = Database::fetchAll('SELECT slug, entry_file, settings_json FROM plugins WHERE is_active = 1 ORDER BY id ASC');
        foreach ($active as $p) {
            $slug  = basename((string) $p['slug']);
            $entry = basename((string) $p['entry_file']);
            $path  = BLOGCMS_ROOT . '/plugins/' . $slug . '/' . $entry;
            if (is_file($path) && !isset(self::$loaded[$slug])) {
                self::$loaded[$slug] = true;
                include_once $path;
            }
        }
    }

    public static function isLoaded(string $slug): bool
    {
        return isset(self::$loaded[$slug]);
    }

    public static function getSettings(string $slug): array
    {
        $raw = Database::fetchValue('SELECT settings_json FROM plugins WHERE slug = ?', [$slug], '{}');
        $arr = json_decode((string) $raw, true);
        return is_array($arr) ? $arr : [];
    }

    public static function saveSettings(string $slug, array $settings): void
    {
        Database::execute('UPDATE plugins SET settings_json = ? WHERE slug = ?', [json_encode($settings, JSON_UNESCAPED_UNICODE), $slug]);
    }

    public static function installFromZip(array $file): array
    {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'error' => 'La extension PHP ZipArchive no esta habilitada.'];
        }
        if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'No se recibio ningun archivo .zip valido.'];
        }
        if (($file['size'] ?? 0) > 15 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'El paquete .zip excede el limite maximo de 15 MB.'];
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return ['ok' => false, 'error' => 'Formato invalido: solo se permiten paquetes .zip.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            return ['ok' => false, 'error' => 'No se pudo abrir el archivo .zip.'];
        }

        // Buscar plugin.json en raiz del zip o dentro de una unica carpeta contenedora
        $manifestIndex = -1;
        $prefixToStrip = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', $zip->getNameIndex($i));
            if (str_contains($name, '..') || str_starts_with($name, '/')) {
                $zip->close();
                return ['ok' => false, 'error' => 'Paquete rechazado por seguridad (path traversal detectado).'];
            }
            if ($name === 'plugin.json') {
                $manifestIndex = $i;
                $prefixToStrip = '';
                break;
            }
            if (preg_match('#^([a-zA-Z0-9_-]+)/plugin\.json$#', $name, $m)) {
                $manifestIndex = $i;
                $prefixToStrip = $m[1] . '/';
                break;
            }
        }

        if ($manifestIndex === -1) {
            $zip->close();
            return ['ok' => false, 'error' => 'Estructura invalida: el .zip debe contener plugin.json.'];
        }

        $metaRaw = $zip->getFromIndex($manifestIndex);
        $meta    = json_decode((string) $metaRaw, true);
        if (!is_array($meta) || empty($meta['slug']) || empty($meta['name']) || empty($meta['entry'])) {
            $zip->close();
            return ['ok' => false, 'error' => 'plugin.json invalido: requiere campos slug, name y entry.'];
        }

        $slug  = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $meta['slug']));
        $entry = basename((string) $meta['entry']);
        if ($slug === '' || pathinfo($entry, PATHINFO_EXTENSION) !== 'php') {
            $zip->close();
            return ['ok' => false, 'error' => 'Slug o archivo entry en plugin.json no validos.'];
        }

        $targetDir = BLOGCMS_ROOT . '/plugins/' . $slug;
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

        if (!is_file($targetDir . '/' . $entry)) {
            return ['ok' => false, 'error' => "El paquete no contiene el archivo principal '$entry'."];
        }

        $existing = Database::fetchOne('SELECT id FROM plugins WHERE slug = ?', [$slug]);
        $name     = (string) $meta['name'];
        $version  = (string) ($meta['version'] ?? '1.0.0');
        $desc     = (string) ($meta['description'] ?? '');
        $author   = (string) ($meta['author'] ?? 'BlogCMS');
        $defaults = isset($meta['default_settings']) && is_array($meta['default_settings']) ? json_encode($meta['default_settings']) : '{}';

        if ($existing) {
            Database::execute(
                'UPDATE plugins SET name = ?, version = ?, description = ?, author = ?, entry_file = ? WHERE slug = ?',
                [$name, $version, $desc, $author, $entry, $slug]
            );
        } else {
            Database::execute(
                'INSERT INTO plugins (slug, name, version, description, author, entry_file, is_active, settings_json) VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
                [$slug, $name, $version, $desc, $author, $entry, $defaults]
            );
        }

        Helpers::audit('plugin_install_zip', "Plugin instalado via .zip: $slug v$version");
        return ['ok' => true, 'slug' => $slug, 'name' => $name, 'version' => $version];
    }

    public static function toggle(string $slug): bool
    {
        $row = Database::fetchOne('SELECT is_active FROM plugins WHERE slug = ?', [$slug]);
        if (!$row) {
            return false;
        }
        $next = ((int) $row['is_active'] === 1) ? 0 : 1;
        Database::execute('UPDATE plugins SET is_active = ? WHERE slug = ?', [$next, $slug]);
        Helpers::audit('plugin_toggle', "Plugin $slug -> is_active=$next");
        return true;
    }

    public static function deletePlugin(string $slug): bool
    {
        $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($slug));
        if ($slug === '') {
            return false;
        }
        Database::execute('DELETE FROM plugins WHERE slug = ?', [$slug]);
        self::removeDir(BLOGCMS_ROOT . '/plugins/' . $slug);
        Helpers::audit('plugin_delete', "Plugin eliminado: $slug");
        return true;
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($dir);
    }
}
