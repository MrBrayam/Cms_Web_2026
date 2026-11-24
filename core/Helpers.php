<?php
/**
 * Funciones auxiliares globales del CMS BlogCMS.
 */

class Helpers
{
    private static array $optionsCache = [];

    public static function baseUrl(): string
    {
        global $BLOGCMS_CONFIG;
        if (!empty($BLOGCMS_CONFIG['app_url'])) {
            return rtrim($BLOGCMS_CONFIG['app_url'], '/');
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    public static function url(string $path = '/'): string
    {
        $path = '/' . ltrim($path, '/');
        return self::baseUrl() . ($path === '/' ? '/' : $path);
    }

    public static function redirect(string $path): void
    {
        $target = preg_match('/^https?:\/\//i', $path) ? $path : self::url($path);
        header('Location: ' . $target);
        exit;
    }

    public static function option(string $name, string $default = ''): string
    {
        if (isset(self::$optionsCache[$name])) {
            return self::$optionsCache[$name];
        }
        $val = Database::fetchValue('SELECT option_value FROM options WHERE option_name = ?', [$name], null);
        if ($val === null) {
            return $default;
        }
        self::$optionsCache[$name] = (string) $val;
        return self::$optionsCache[$name];
    }

    public static function setOption(string $name, string $value): void
    {
        Database::upsertOption($name, $value);
        self::$optionsCache[$name] = $value;
    }

    public static function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $map  = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
        ];
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string) $text, '-');
        return $text !== '' ? $text : 'elemento-' . time();
    }

    public static function excerpt(string $html, int $words = 24): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
        $parts = explode(' ', $plain);
        if (count($parts) <= $words) {
            return $plain;
        }
        return implode(' ', array_slice($parts, 0, $words)) . '...';
    }

    public static function readingTime(string $html): string
    {
        $words = str_word_count(strip_tags($html));
        $mins  = max(1, (int) ceil($words / 200));
        return $mins . ' min de lectura';
    }

    public static function flash(string $type, string $message): void
    {
        Security::startSession();
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
    }

    public static function pullFlash(): ?array
    {
        Security::startSession();
        if (empty($_SESSION['_flash'])) {
            return null;
        }
        $f = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $f;
    }

    public static function sanitizeHtml(string $html): string
    {
        // Permite etiquetas editoriales seguras y elimina scripts/eventos inline peligrosos
        $allowed = '<p><br><h2><h3><h4><strong><b><em><i><u><ul><ol><li><blockquote><code><pre><a><img><hr>';
        $clean   = strip_tags($html, $allowed);
        $clean   = preg_replace('/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string) $clean);
        $clean   = preg_replace('/href\s*=\s*(["\'])\s*javascript:.*?\1/i', 'href="#"', (string) $clean);
        return (string) $clean;
    }

    public static function renderShortcodes(string $content): string
    {
        return PluginManager::applyFilters('content_render', $content);
    }

    public static function menuItems(string $location = 'primary'): array
    {
        return Database::fetchAll(
            'SELECT * FROM menus WHERE location = ? ORDER BY sort_order ASC, id ASC',
            [$location]
        );
    }

    public static function audit(string $action, string $details, ?int $userId = null): void
    {
        try {
            $uid = $userId;
            if ($uid === null && isset($_SESSION['user_id'])) {
                $uid = (int) $_SESSION['user_id'];
            }
            $ip = substr($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 0, 45);
            Database::execute(
                'INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)',
                [$uid, $action, substr($details, 0, 500), $ip]
            );
        } catch (Throwable $e) {
            // Evitar romper flujo principal si falla auditoria
        }
    }

    public static function uploadImage(array $file, string $subDir = 'covers'): ?string
    {
        if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return null;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        if (!isset($extMap[$mime])) {
            return null;
        }
        $targetDir = BLOGCMS_ROOT . '/uploads/' . trim($subDir, '/');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $filename = bin2hex(random_bytes(8)) . '.' . $extMap[$mime];
        $dest     = $targetDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return null;
        }
        return '/uploads/' . trim($subDir, '/') . '/' . $filename;
    }
}
