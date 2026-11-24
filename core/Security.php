<?php
/**
 * Utilidades de seguridad OWASP:
 * - Cabeceras HTTP de seguridad (CSP, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy)
 * - Sesiones seguras (HttpOnly, SameSite=Lax)
 * - Tokens anti-CSRF
 * - Codificacion de salida segun contexto (XSS)
 */

class Security
{
    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('BLOGCMS_SID');
        session_start();
    }

    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::e(self::csrfToken()) . '">';
    }

    public static function verifyCsrf(?string $token = null): bool
    {
        self::startSession();
        $candidate = $token ?? ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (empty($_SESSION['_csrf_token']) || !is_string($candidate)) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $candidate);
    }

    public static function requireCsrf(): void
    {
        if (!self::verifyCsrf()) {
            http_response_code(403);
            exit('Error 403: Token CSRF invalido o expirado.');
        }
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function escUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '#';
        }
        if (preg_match('/^(https?:\/\/|\/|#)/i', $url)) {
            return self::e($url);
        }
        return '#';
    }
}
