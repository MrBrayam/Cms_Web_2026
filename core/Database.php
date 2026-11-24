<?php
/**
 * Capa de acceso a Base de Datos mediante PDO (MySQL/MariaDB y PostgreSQL).
 * Todas las consultas utilizan sentencias preparadas para prevenir SQL Injection (OWASP A03).
 */

class Database
{
    private static ?PDO $pdo = null;
    private static string $driver = 'mysql';

    public static function connect(array $cfg): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        self::$driver = $cfg['db_driver'] ?? 'mysql';
        $host = $cfg['db_host'] ?? '127.0.0.1';
        $port = (int) ($cfg['db_port'] ?? (self::$driver === 'pgsql' ? 5432 : 3306));
        $name = $cfg['db_name'] ?? 'blogcms_wp';
        $user = $cfg['db_user'] ?? 'root';
        $pass = $cfg['db_pass'] ?? '';

        if (self::$driver === 'pgsql') {
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $name);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        }

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new RuntimeException('La base de datos no ha sido inicializada.');
        }
        return self::$pdo;
    }

    public static function driver(): string
    {
        return self::$driver;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $idx => $val) {
            $paramKey = is_int($idx) ? $idx + 1 : $idx;
            $type = is_int($val) ? PDO::PARAM_INT : (is_bool($val) ? PDO::PARAM_BOOL : (is_null($val) ? PDO::PARAM_NULL : PDO::PARAM_STR));
            $stmt->bindValue($paramKey, $val, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row !== false ? $row : null;
    }

    public static function fetchValue(string $sql, array $params = [], $default = null)
    {
        $val = self::query($sql, $params)->fetchColumn();
        return $val !== false ? $val : $default;
    }

    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    public static function lastInsertId(?string $sequence = null): int
    {
        if (self::$driver === 'pgsql' && $sequence !== null) {
            return (int) self::pdo()->lastInsertId($sequence);
        }
        return (int) self::pdo()->lastInsertId();
    }

    public static function upsertOption(string $name, string $value): void
    {
        $exists = self::fetchValue('SELECT COUNT(*) FROM options WHERE option_name = ?', [$name], 0);
        if ((int) $exists > 0) {
            self::execute('UPDATE options SET option_value = ? WHERE option_name = ?', [$value, $name]);
        } else {
            self::execute('INSERT INTO options (option_name, option_value) VALUES (?, ?)', [$name, $value]);
        }
    }
}
