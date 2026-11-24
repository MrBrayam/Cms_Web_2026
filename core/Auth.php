<?php
/**
 * Autenticacion, gestion de sesiones y control de acceso basado en roles (RBAC: admin / editor / author).
 * Incluye bloqueo temporal tras intentos fallidos y registro de auditoria.
 */

class Auth
{
    private static ?array $currentUser = null;

    public static function user(): ?array
    {
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }
        Security::startSession();
        $uid = $_SESSION['user_id'] ?? null;
        if (!$uid) {
            return null;
        }
        $user = Database::fetchOne(
            'SELECT id, username, email, display_name, role, created_at FROM users WHERE id = ?',
            [(int) $uid]
        );
        if (!$user) {
            unset($_SESSION['user_id']);
            return null;
        }
        self::$currentUser = $user;
        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function hasRole(array $roles): bool
    {
        $u = self::user();
        return $u !== null && in_array($u['role'], $roles, true);
    }

    public static function isAdmin(): bool
    {
        return self::hasRole(['admin']);
    }

    public static function isEditorOrAdmin(): bool
    {
        return self::hasRole(['admin', 'editor']);
    }

    public static function canManagePost(array $post): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        if (in_array($u['role'], ['admin', 'editor'], true)) {
            return true;
        }
        return (int) $post['author_id'] === (int) $u['id'];
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . Helpers::url('/admin/login'));
            exit;
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        if (!self::hasRole($roles)) {
            Helpers::audit('access_denied', 'Intento de acceso sin permiso a ruta restringida.');
            http_response_code(403);
            exit('403 Acceso Denegado: Tu rol no tiene permisos para realizar esta accion.');
        }
    }

    public static function attemptLogin(string $usernameOrEmail, string $password): array
    {
        $user = Database::fetchOne(
            'SELECT * FROM users WHERE username = ? OR email = ?',
            [trim($usernameOrEmail), trim($usernameOrEmail)]
        );

        if (!$user) {
            Helpers::audit('login_failed', 'Usuario inexistente: ' . substr($usernameOrEmail, 0, 50), null);
            return ['ok' => false, 'error' => 'Credenciales invalidas.'];
        }

        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            return ['ok' => false, 'error' => 'Cuenta bloqueada temporalmente por multiples intentos fallidos. Espera unos minutos.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $attempts = (int) $user['failed_attempts'] + 1;
            $lockedUntil = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 300) : null;
            Database::execute(
                'UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?',
                [$attempts, $lockedUntil, (int) $user['id']]
            );
            Helpers::audit('login_failed', 'Contrasena incorrecta para usuario ID ' . $user['id'], (int) $user['id']);
            return ['ok' => false, 'error' => 'Credenciales invalidas.'];
        }

        Database::execute('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?', [(int) $user['id']]);
        Security::startSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        self::$currentUser = null;
        Helpers::audit('login_success', 'Inicio de sesion exitoso', (int) $user['id']);
        return ['ok' => true];
    }

    public static function logout(): void
    {
        $u = self::user();
        if ($u) {
            Helpers::audit('logout', 'Cierre de sesion', (int) $u['id']);
        }
        Security::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        self::$currentUser = null;
    }
}
