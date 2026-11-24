<?php
/**
 * Asistente de Instalacion Web — BlogCMS Propio (Sin WordPress)
 * Soporta MySQL Local (3306), MySQL/MariaDB XAMPP (3307) y PostgreSQL (5432) mediante PDO.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

define('BLOGCMS_ROOT', __DIR__);
define('BLOGCMS_CONFIG_FILE', BLOGCMS_ROOT . '/config/config.php');
define('BLOGCMS_SQL_MYSQL', BLOGCMS_ROOT . '/database/schema_mysql.sql');
define('BLOGCMS_SQL_PGSQL', BLOGCMS_ROOT . '/database/schema_pgsql.sql');

function blogcms_detect_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/instalador.php';
    $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $scheme . '://' . $host . ($dir ? $dir : '');
}

function blogcms_is_port_open(string $host, int $port, float $timeout = 0.35): bool
{
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

function blogcms_detect_db_engines(): array
{
    $xamppPort = 3307;
    foreach (['C:/xampp/mysql/bin/my.ini', '/opt/lampp/etc/my.cnf'] as $ini) {
        if (is_readable($ini)) {
            $raw = file_get_contents($ini);
            if (preg_match('/^\s*port\s*=\s*(\d+)/m', (string) $raw, $m)) {
                $xamppPort = (int) $m[1];
                break;
            }
        }
    }

    $localOpen = blogcms_is_port_open('127.0.0.1', 3306);
    $xamppOpen = blogcms_is_port_open('127.0.0.1', $xamppPort);
    $pgsqlOpen = blogcms_is_port_open('127.0.0.1', 5432);

    return [
        'mysql_local' => [
            'id'     => 'mysql_local',
            'driver' => 'mysql',
            'name'   => 'MySQL / MariaDB Local',
            'host'   => '127.0.0.1',
            'port'   => 3306,
            'user'   => 'root',
            'open'   => $localOpen,
            'status' => $localOpen ? 'ACTIVO (Puerto 3306)' : 'Detenido (Puerto 3306)',
        ],
        'mysql_xampp' => [
            'id'     => 'mysql_xampp',
            'driver' => 'mysql',
            'name'   => 'MySQL / MariaDB de XAMPP',
            'host'   => '127.0.0.1',
            'port'   => $xamppPort,
            'user'   => 'root',
            'open'   => $xamppOpen,
            'status' => $xamppOpen ? "ACTIVO (Puerto $xamppPort)" : "Detenido (Puerto $xamppPort)",
        ],
        'pgsql'       => [
            'id'     => 'pgsql',
            'driver' => 'pgsql',
            'name'   => 'PostgreSQL',
            'host'   => '127.0.0.1',
            'port'   => 5432,
            'user'   => 'postgres',
            'open'   => $pgsqlOpen,
            'status' => $pgsqlOpen ? 'ACTIVO (Puerto 5432)' : 'Detenido (Puerto 5432)',
        ],
    ];
}

function blogcms_check_requirements(): array
{
    $checks = [
        ['label' => 'PHP >= 8.0 (Actual: ' . PHP_VERSION . ')', 'ok' => version_compare(PHP_VERSION, '8.0.0', '>='), 'required' => true],
        ['label' => 'PDO MySQL (pdo_mysql)', 'ok' => extension_loaded('pdo_mysql'), 'required' => false],
        ['label' => 'PDO PostgreSQL (pdo_pgsql)', 'ok' => extension_loaded('pdo_pgsql'), 'required' => false],
        ['label' => 'ZipArchive (Subida de Plugins y Temas .zip — Lamina 8)', 'ok' => class_exists('ZipArchive'), 'required' => true],
        ['label' => 'Extensiones GD, mbstring, fileinfo y openssl', 'ok' => extension_loaded('gd') && extension_loaded('mbstring') && extension_loaded('fileinfo') && extension_loaded('openssl'), 'required' => true],
        ['label' => 'Permisos de escritura en /config/', 'ok' => is_writable(BLOGCMS_ROOT . '/config'), 'required' => true],
        ['label' => 'Esquemas SQL duales (schema_mysql.sql y schema_pgsql.sql)', 'ok' => is_readable(BLOGCMS_SQL_MYSQL) && is_readable(BLOGCMS_SQL_PGSQL), 'required' => true],
    ];

    $ready = (extension_loaded('pdo_mysql') || extension_loaded('pdo_pgsql'));
    foreach ($checks as $c) {
        if ($c['required'] && !$c['ok']) {
            $ready = false;
        }
    }
    return ['items' => $checks, 'ready' => $ready];
}

function blogcms_build_config(string $driver, string $host, int $port, string $name, string $user, string $pass, string $appUrl): string
{
    $esc    = static fn(string $v): string => addcslashes($v, "\\'");
    $secret = bin2hex(random_bytes(32));
    return "<?php\n"
        . "/**\n * Configuracion generada por instalador.php (BlogCMS Propio)\n */\n"
        . "return [\n"
        . "    'db_driver'  => '" . $esc($driver) . "',\n"
        . "    'db_host'    => '" . $esc($host) . "',\n"
        . "    'db_port'    => " . $port . ",\n"
        . "    'db_name'    => '" . $esc($name) . "',\n"
        . "    'db_user'    => '" . $esc($user) . "',\n"
        . "    'db_pass'    => '" . $esc($pass) . "',\n"
        . "    'app_url'    => '" . $esc(rtrim($appUrl, '/')) . "',\n"
        . "    'app_secret' => '" . $secret . "',\n"
        . "    'debug'      => false,\n"
        . "];\n";
}

function blogcms_split_sql(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $stmts = [];
    $buf   = '';
    $inStr = false;
    $len   = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        if ($ch === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) {
            // Manejar comilla escapada en SQL ''
            if ($inStr && $i + 1 < $len && $sql[$i + 1] === "'") {
                $buf .= "''";
                $i++;
                continue;
            }
            $inStr = !$inStr;
        }
        if ($ch === ';' && !$inStr) {
            $t = trim($buf);
            if ($t !== '') {
                $stmts[] = $t;
            }
            $buf = '';
        } else {
            $buf .= $ch;
        }
    }
    $t = trim($buf);
    if ($t !== '') {
        $stmts[] = $t;
    }
    return $stmts;
}

if (isset($_GET['action']) && $_GET['action'] === 'test_db' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $engine = $_POST['db_engine'] ?? 'mysql_local';
    $driver = ($engine === 'pgsql') ? 'pgsql' : 'mysql';
    $host   = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
    $port   = (int) ($_POST['db_port'] ?? ($driver === 'pgsql' ? 5432 : 3306));
    $user   = trim((string) ($_POST['db_user'] ?? ($driver === 'pgsql' ? 'postgres' : 'root')));
    $pass   = (string) ($_POST['db_pass'] ?? '');

    if (!extension_loaded($driver === 'pgsql' ? 'pdo_pgsql' : 'pdo_mysql')) {
        echo json_encode(['ok' => false, 'message' => "La extension PHP pdo_$driver no esta habilitada en php.ini."]);
        exit;
    }

    try {
        $dsn = $driver === 'pgsql'
            ? "pgsql:host=$host;port=$port;dbname=postgres"
            : "mysql:host=$host;port=$port;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 4]);
        $ver = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        echo json_encode(['ok' => true, 'message' => "Conexion exitosa a $driver v$ver ($host:$port)."]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'message' => 'Error de conexion: ' . $e->getMessage()]);
    }
    exit;
}

$step       = isset($_GET['step']) ? max(1, min(4, (int) $_GET['step'])) : 1;
$reqs       = blogcms_check_requirements();
$engines    = blogcms_detect_db_engines();
$baseUrl    = blogcms_detect_base_url();
$hasConfig  = is_file(BLOGCMS_CONFIG_FILE);
$errorMsg   = '';
$successMsg = '';
$installLog = [];

function blogcms_restore_packages(): int
{
    $restored = 0;
    if (!class_exists('ZipArchive')) {
        return 0;
    }
    $sets = [
        [BLOGCMS_ROOT . '/packages/plugins/*.zip', BLOGCMS_ROOT . '/plugins'],
        [BLOGCMS_ROOT . '/packages/themes/*.zip', BLOGCMS_ROOT . '/themes'],
    ];
    foreach ($sets as [$glob, $dest]) {
        foreach (glob($glob) ?: [] as $zipPath) {
            $z = new ZipArchive();
            if ($z->open($zipPath) === true) {
                $z->extractTo($dest);
                $z->close();
                $restored++;
            }
        }
    }
    return $restored;
}

function blogcms_write_htaccess_file(): void
{
    $htRules = "Options -Indexes\nRedirectMatch 403 ^/(config|core|database|installer-src)(/.*)?\$\nRedirectMatch 403 ^/admin/.+\\.php\$\n\n<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteBase /\n    RewriteRule ^admin/?\$ index.php [L]\n    RewriteCond %{REQUEST_FILENAME} !-f\n    RewriteCond %{REQUEST_FILENAME} !-d\n    RewriteRule ^ index.php [L]\n</IfModule>\n";
    @file_put_contents(BLOGCMS_ROOT . '/.htaccess', $htRules);
}

// Accion: Reinstalar CMS desde cero usando la configuracion actual
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action_reinstall']) && $hasConfig) {
    try {
        $cfg    = require BLOGCMS_CONFIG_FILE;
        $driver = $cfg['db_driver'] ?? 'mysql';
        $host   = $cfg['db_host'] ?? '127.0.0.1';
        $port   = (int) ($cfg['db_port'] ?? ($driver === 'pgsql' ? 5432 : 3306));
        $dbName = $cfg['db_name'] ?? 'blogcms_wp';
        $user   = $cfg['db_user'] ?? 'root';
        $pass   = $cfg['db_pass'] ?? '';
        $appUrl = $cfg['app_url'] ?? $baseUrl;

        $dsn = $driver === 'pgsql'
            ? "pgsql:host=$host;port=$port;dbname=$dbName"
            : "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $sqlFile = ($driver === 'pgsql') ? BLOGCMS_SQL_PGSQL : BLOGCMS_SQL_MYSQL;
        foreach (blogcms_split_sql((string) file_get_contents($sqlFile)) as $st) {
            $pdo->exec($st);
        }
        $pdo->prepare('UPDATE options SET option_value = ? WHERE option_name = ?')->execute([$appUrl, 'site_url']);

        $restoredCount = blogcms_restore_packages();
        blogcms_write_htaccess_file();

        $installLog[] = "Base de datos '$dbName' ($driver) reinicializada desde cero con " . basename($sqlFile) . '.';
        $installLog[] = "Componentes restaurados desde packages/ ($restoredCount paquetes de plugins y temas).";
        $installLog[] = 'Archivo .htaccess regenerado correctamente.';
        $siteUrl      = $appUrl;
        $adminUser    = 'admin';
        $step         = 4;
    } catch (Throwable $e) {
        $errorMsg = 'Error al reinstalar: ' . $e->getMessage();
    }
}

// Accion: Desinstalar CMS completo (tablas/BD + config/config.php + .htaccess)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action_uninstall'])) {
    try {
        $dropDb = isset($_POST['drop_database']);
        if ($hasConfig) {
            $cfg    = require BLOGCMS_CONFIG_FILE;
            $driver = $cfg['db_driver'] ?? 'mysql';
            $host   = $cfg['db_host'] ?? '127.0.0.1';
            $port   = (int) ($cfg['db_port'] ?? ($driver === 'pgsql' ? 5432 : 3306));
            $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($cfg['db_name'] ?? 'blogcms_wp'));
            $user   = $cfg['db_user'] ?? 'root';
            $pass   = $cfg['db_pass'] ?? '';

            if ($dropDb && $dbName !== '') {
                $adminDsn = $driver === 'pgsql'
                    ? "pgsql:host=$host;port=$port;dbname=postgres"
                    : "mysql:host=$host;port=$port;charset=utf8mb4";
                $rootPdo = new PDO($adminDsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                if ($driver === 'pgsql') {
                    $rootPdo->exec('DROP DATABASE IF EXISTS "' . $dbName . '" WITH (FORCE)');
                } else {
                    $rootPdo->exec("DROP DATABASE IF EXISTS `$dbName`");
                }
            } elseif ($dbName !== '') {
                $dsn = $driver === 'pgsql'
                    ? "pgsql:host=$host;port=$port;dbname=$dbName"
                    : "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $tables = ['audit_logs','visit_logs','contact_messages','gallery_images','gallery_albums','media','themes','plugins','menus','comments','pages','post_tags','posts','tags','categories','users','options'];
                if ($driver === 'mysql') {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                }
                foreach ($tables as $t) {
                    $pdo->exec($driver === 'pgsql' ? "DROP TABLE IF EXISTS \"$t\" CASCADE" : "DROP TABLE IF EXISTS `$t`");
                }
                if ($driver === 'mysql') {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                }
            }
        }

        if (is_file(BLOGCMS_CONFIG_FILE)) {
            @unlink(BLOGCMS_CONFIG_FILE);
        }
        if (is_file(BLOGCMS_ROOT . '/.htaccess')) {
            @unlink(BLOGCMS_ROOT . '/.htaccess');
        }
        $hasConfig  = false;
        $successMsg = 'BlogCMS fue desinstalado correctamente (tablas' . ($dropDb ? '/BD' : '') . ', config/config.php y .htaccess eliminados). Ya puedes realizar una instalacion limpia.';
        $step       = 1;
    } catch (Throwable $e) {
        $errorMsg = 'Error al desinstalar: ' . $e->getMessage();
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['run_install'])) {
    $step       = 2;
    $engine     = $_POST['db_engine'] ?? 'mysql_local';
    $driver     = ($engine === 'pgsql') ? 'pgsql' : 'mysql';
    $host       = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
    $port       = (int) ($_POST['db_port'] ?? ($driver === 'pgsql' ? 5432 : 3306));
    $user       = trim((string) ($_POST['db_user'] ?? ($driver === 'pgsql' ? 'postgres' : 'root')));
    $pass       = (string) ($_POST['db_pass'] ?? '');
    $dbName     = preg_replace('/[^a-zA-Z0-9_]/', '', trim((string) ($_POST['db_name'] ?? 'blogcms_wp')));
    $dbMode     = $_POST['db_mode'] ?? 'import';
    $siteTitle  = trim((string) ($_POST['site_title'] ?? 'Bloc-FISI'));
    $siteUrl    = rtrim(trim((string) ($_POST['site_url'] ?? $baseUrl)), '/');
    $themeSlug  = trim((string) ($_POST['theme_slug'] ?? 'magazine'));
    $adminUser  = trim((string) ($_POST['admin_user'] ?? 'admin'));
    $adminName  = trim((string) ($_POST['admin_name'] ?? 'Administrador FISI'));
    $adminPass  = (string) ($_POST['admin_pass'] ?? '');

    if (!in_array($themeSlug, ['minimal-blog', 'magazine', 'portfolio', 'business'], true)) {
        $themeSlug = 'magazine';
    }

    try {
        if ($dbName === '') {
            throw new RuntimeException('Nombre de base de datos invalido.');
        }
        $adminDsn = $driver === 'pgsql'
            ? "pgsql:host=$host;port=$port;dbname=postgres"
            : "mysql:host=$host;port=$port;charset=utf8mb4";
        $rootPdo = new PDO($adminDsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        if ($driver === 'pgsql') {
            $exists = $rootPdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
            $exists->execute([$dbName]);
            if (!$exists->fetchColumn()) {
                $rootPdo->exec('CREATE DATABASE "' . $dbName . '" ENCODING \'UTF8\'');
            }
            $targetDsn = "pgsql:host=$host;port=$port;dbname=$dbName";
        } else {
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $targetDsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";
        }
        $installLog[] = "Base de datos '$dbName' ($driver) lista en $host:$port.";

        $pdo = new PDO($targetDsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        if ($dbMode === 'import') {
            $sqlFile = ($driver === 'pgsql') ? BLOGCMS_SQL_PGSQL : BLOGCMS_SQL_MYSQL;
            if (!is_readable($sqlFile)) {
                throw new RuntimeException("No se encontro $sqlFile");
            }
            $statements = blogcms_split_sql((string) file_get_contents($sqlFile));
            foreach ($statements as $st) {
                $pdo->exec($st);
            }
            $installLog[] = 'Esquema y datos iniciales importados desde ' . basename($sqlFile) . '.';
        }

        // Actualizar opciones del sitio y tema activo
        $upOpt = $pdo->prepare('UPDATE options SET option_value = ? WHERE option_name = ?');
        foreach (['site_title' => $siteTitle, 'site_url' => $siteUrl, 'active_theme' => $themeSlug] as $k => $v) {
            $upOpt->execute([$v, $k]);
        }
        $pdo->exec('UPDATE themes SET is_active = 0');
        $upThm = $pdo->prepare('UPDATE themes SET is_active = 1 WHERE slug = ?');
        $upThm->execute([$themeSlug]);
        $installLog[] = "Sitio '$siteTitle', URL ($siteUrl) y tema activo '$themeSlug' configurados.";

        if ($adminUser !== '') {
            $upUsr = $pdo->prepare('UPDATE users SET username = ?, display_name = ? WHERE id = 1');
            $upUsr->execute([$adminUser, $adminName !== '' ? $adminName : $adminUser]);
            if ($adminPass !== '') {
                $hash = password_hash($adminPass, PASSWORD_BCRYPT);
                $upPw = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = 1');
                $upPw->execute([$hash]);
                $installLog[] = "Credenciales de administrador ($adminUser) actualizadas con BCrypt.";
            }
        }

        if (@file_put_contents(BLOGCMS_CONFIG_FILE, blogcms_build_config($driver, $host, $port, $dbName, $user, $pass, $siteUrl)) === false) {
            throw new RuntimeException('No se pudo escribir config/config.php.');
        }
        blogcms_restore_packages();
        blogcms_write_htaccess_file();
        $installLog[] = 'Archivos config/config.php y .htaccess generados correctamente.';
        $step = 4;
    } catch (Throwable $e) {
        $errorMsg = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador BlogCMS Propio &mdash; UNSM</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #072842 0%, #0f172a 100%); color: #1e293b; min-height: 100vh; padding: 2rem 1rem; }
        .wrap { max-width: 820px; margin: 0 auto; }
        .head { color: #fff; text-align: center; margin-bottom: 1.5rem; }
        .badge { display: inline-block; background: rgba(2,132,199,0.25); border: 1px solid rgba(125,211,252,0.4); color: #bae6fd; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; padding: 0.25rem 0.85rem; border-radius: 999px; margin-bottom: 0.5rem; }
        .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1.4rem; }
        .step { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.14); color: #cbd5e1; padding: 0.7rem; border-radius: 10px; font-size: 0.88rem; font-weight: 600; text-align: center; }
        .step.active { background: #0284c7; color: #fff; border-color: #38bdf8; }
        .step.done { background: rgba(5,150,105,0.25); color: #a7f3d0; }
        .card { background: #fff; border-radius: 14px; padding: 2rem; box-shadow: 0 20px 45px rgba(0,0,0,0.35); }
        .check-item { display: flex; justify-content: space-between; align-items: center; padding: 0.7rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.5rem; font-size: 0.92rem; }
        .pill { padding: 0.2rem 0.65rem; border-radius: 999px; font-weight: 700; font-size: 0.76rem; }
        .pill-ok { background: #d1fae5; color: #065f46; }
        .pill-err { background: #fee2e2; color: #991b1b; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .full { grid-column: 1 / -1; }
        label { display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 0.3rem; color: #334155; }
        input, select { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 0.94rem; }
        .btn-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-top: 1.4rem; }
        .btn { display: inline-block; background: #0b3b60; color: #fff; border: none; padding: 0.7rem 1.35rem; border-radius: 8px; font-weight: 700; cursor: pointer; text-decoration: none; font-size: 0.94rem; }
        .btn:hover { background: #0284c7; }
        .btn-sec { background: #e2e8f0; color: #1e293b; }
        .alert { padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1.2rem; font-size: 0.9rem; }
        .alert-info { background: #e0f2fe; color: #075985; }
        .alert-err { background: #fee2e2; color: #991b1b; }
        .alert-ok { background: #d1fae5; color: #065f46; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 0.88rem; }
        th, td { border: 1px solid #e2e8f0; padding: 0.55rem 0.75rem; text-align: left; }
        th { background: #f8fafc; }
        @media (max-width: 640px) { .grid, .steps { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="wrap">
    <header class="head">
        <span class="badge">UNSM &middot; Ingenier&iacute;a Web &middot; CMS Propio</span>
        <h1>Instalador de BlogCMS (Sin WordPress)</h1>
        <p style="margin:0;color:#94a3b8;">Motor PHP PDO dual (MySQL Local, MySQL XAMPP y PostgreSQL) &middot; 6 M&oacute;dulos &middot; 6 Plugins &middot; 4 Temas</p>
    </header>

    <div class="steps">
        <div class="step <?php echo $step === 1 ? 'active' : 'done'; ?>">1. Requisitos PHP</div>
        <div class="step <?php echo $step === 2 ? 'active' : ($step > 2 ? 'done' : ''); ?>">2. Base de Datos (PDO)</div>
        <div class="step <?php echo $step === 4 ? 'active' : ''; ?>">3. Completado</div>
    </div>

    <div class="card">
        <?php if ($step === 1): ?>
            <h2 style="margin-top:0;color:#0b3b60;">Paso 1: Diagn&oacute;stico del Servidor</h2>
            <?php if ($errorMsg !== ''): ?><div class="alert alert-err"><?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <?php if ($successMsg !== ''): ?><div class="alert alert-ok"><?php echo htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <?php if ($hasConfig): ?>
                <div class="alert alert-info">
                    <strong>Instalaci&oacute;n activa detectada (<code>config/config.php</code>):</strong> Puedes abrir el <a href="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>/">sitio web</a>, continuar al Paso 2 para cambiar de motor de BD, o usar las acciones de mantenimiento:
                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;align-items:center;">
                        <form method="post" action="?step=1" style="display:inline;" onsubmit="return confirm('Esto reinicializara las tablas, restaurara los 6 plugins y 4 temas desde cero. Continuar?');">
                            <input type="hidden" name="action_reinstall" value="1">
                            <button type="submit" class="btn" style="padding:0.5rem 1rem;font-size:0.86rem;">Reinstalar CMS desde cero</button>
                        </form>
                        <form method="post" action="?step=1" style="display:inline-flex;gap:8px;align-items:center;flex-wrap:wrap;" onsubmit="return confirm('Se eliminaran las tablas del CMS, config/config.php y .htaccess. Confirmar desinstalacion?');">
                            <input type="hidden" name="action_uninstall" value="1">
                            <label style="display:inline-flex;align-items:center;gap:5px;margin:0;font-weight:600;font-size:0.82rem;color:#991b1b;">
                                <input type="checkbox" name="drop_database" value="1" style="width:auto;"> Eliminar BD completa
                            </label>
                            <button type="submit" class="btn" style="background:#dc2626;padding:0.5rem 1rem;font-size:0.86rem;">Desinstalar CMS</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <?php foreach ($reqs['items'] as $item): ?>
                <div class="check-item">
                    <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="pill <?php echo $item['ok'] ? 'pill-ok' : 'pill-err'; ?>"><?php echo $item['ok'] ? 'OK' : ($item['required'] ? 'FALTA' : 'OPCIONAL'); ?></span>
                </div>
            <?php endforeach; ?>
            <div class="btn-row">
                <a class="btn btn-sec" href="?step=1">Re-comprobar</a>
                <?php if ($reqs['ready']): ?>
                    <a class="btn" href="?step=2">Continuar al Paso 2 &rarr;</a>
                <?php else: ?>
                    <strong style="color:#dc2626;">Corrige los requisitos obligatorios para continuar.</strong>
                <?php endif; ?>
            </div>

        <?php elseif ($step === 2): ?>
            <h2 style="margin-top:0;color:#0b3b60;">Paso 2: Selecci&oacute;n de Motor de Base de Datos</h2>
            <?php if ($errorMsg !== ''): ?><div class="alert alert-err"><?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <div id="test-box" class="alert" style="display:none;"></div>

            <form method="post" action="?step=2" id="inst-form">
                <input type="hidden" name="run_install" value="1">
                <div class="grid">
                    <div class="full">
                        <label for="db_engine">Motor detectado autom&aacute;ticamente</label>
                        <select id="db_engine" name="db_engine" onchange="applyEngine()">
                            <?php foreach ($engines as $ek => $ei): ?>
                                <option value="<?php echo htmlspecialchars($ek, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-host="<?php echo htmlspecialchars($ei['host'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-port="<?php echo (int) $ei['port']; ?>"
                                        data-user="<?php echo htmlspecialchars($ei['user'], ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo ($ei['open'] && $ek === 'mysql_local') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ei['name'] . ' [' . $ei['host'] . ':' . $ei['port'] . '] — ' . $ei['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>Host</label><input type="text" id="db_host" name="db_host" value="127.0.0.1" required></div>
                    <div><label>Puerto</label><input type="number" id="db_port" name="db_port" value="3306" required></div>
                    <div><label>Usuario BD</label><input type="text" id="db_user" name="db_user" value="root" required></div>
                    <div><label>Contrase&ntilde;a BD</label><input type="password" id="db_pass" name="db_pass" placeholder="Vacia si no tiene clave"></div>
                    <div><label>Nombre de Base de Datos</label><input type="text" name="db_name" value="blogcms_wp" required></div>
                    <div>
                        <label>Inicializaci&oacute;n SQL</label>
                        <select name="db_mode">
                            <option value="import">Importar esquema + datos de muestra</option>
                            <option value="keep">Conservar tablas existentes</option>
                        </select>
                    </div>
                </div>
                <button type="button" class="btn btn-sec" onclick="testDb()" style="padding:0.45rem 0.9rem;font-size:0.85rem;">Probar Conexi&oacute;n</button>

                <hr style="border:none;border-top:1px solid #e2e8f0;margin:1.35rem 0;">

                <div class="grid">
                    <div><label>T&iacute;tulo del Sitio</label><input type="text" name="site_title" value="Bloc-FISI" required></div>
                    <div><label>URL Base</label><input type="text" name="site_url" value="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="full">
                        <label>Tema Inicial Activo</label>
                        <select name="theme_slug">
                            <option value="magazine">Magazine &mdash; 3 columnas con portadas grandes</option>
                            <option value="business">Business &mdash; Corporativo con Hero Banner</option>
                            <option value="portfolio">Portfolio &mdash; Grilla de proyectos con hover</option>
                            <option value="minimal-blog">Minimal Blog &mdash; Clean y tipogr&aacute;fico</option>
                        </select>
                    </div>
                    <div><label>Usuario Admin</label><input type="text" name="admin_user" value="admin" required></div>
                    <div><label>Nombre Visible Admin</label><input type="text" name="admin_name" value="Administrador FISI" required></div>
                    <div class="full"><label>Nueva Contrase&ntilde;a Admin (Opcional, por defecto AdminFisi2026!)</label><input type="password" name="admin_pass" placeholder="Dejar vacio para mantener AdminFisi2026!"></div>
                </div>

                <div class="btn-row">
                    <a class="btn btn-sec" href="?step=1">&larr; Atr&aacute;s</a>
                    <button type="submit" class="btn">Instalar BlogCMS Ahora &rarr;</button>
                </div>
            </form>
            <script>
            function applyEngine() {
                var s = document.getElementById('db_engine');
                var o = s.options[s.selectedIndex];
                document.getElementById('db_host').value = o.getAttribute('data-host');
                document.getElementById('db_port').value = o.getAttribute('data-port');
                document.getElementById('db_user').value = o.getAttribute('data-user');
            }
            function testDb() {
                var b = document.getElementById('test-box');
                b.style.display = 'block';
                b.className = 'alert alert-info';
                b.textContent = 'Probando conexion PDO...';
                fetch('?action=test_db', { method: 'POST', body: new FormData(document.getElementById('inst-form')) })
                    .then(function(r){ return r.json(); })
                    .then(function(d){ b.className = d.ok ? 'alert alert-ok' : 'alert alert-err'; b.textContent = d.message; })
                    .catch(function(e){ b.className = 'alert alert-err'; b.textContent = 'Error: ' + e; });
            }
            </script>

        <?php elseif ($step === 4): ?>
            <h2 style="margin-top:0;color:#059669;">&iexcl;BlogCMS Instalado Correctamente!</h2>
            <div class="alert alert-ok">
                <ul style="margin:0 0 0 1.2rem;padding:0;">
                    <?php foreach ($installLog as $l): ?><li><?php echo htmlspecialchars($l, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?>
                </ul>
            </div>
            <table>
                <thead><tr><th>Rol</th><th>Usuario</th><th>Contrase&ntilde;a</th></tr></thead>
                <tbody>
                    <tr><td>Administrador</td><td><code><?php echo htmlspecialchars($adminUser ?? 'admin', ENT_QUOTES, 'UTF-8'); ?></code></td><td><?php echo !empty($adminPass) ? '<em>La que definiste</em>' : '<code>AdminFisi2026!</code>'; ?></td></tr>
                    <tr><td>Editor</td><td><code>editor_fisi</code></td><td><code>EditorFisi2026!</code></td></tr>
                    <tr><td>Autor</td><td><code>autor_fisi</code></td><td><code>AutorFisi2026!</code></td></tr>
                </tbody>
            </table>
            <div class="btn-row">
                <a class="btn btn-sec" href="<?php echo htmlspecialchars($siteUrl ?? $baseUrl, ENT_QUOTES, 'UTF-8'); ?>/admin">Ir al Panel (/admin)</a>
                <a class="btn" href="<?php echo htmlspecialchars($siteUrl ?? $baseUrl, ENT_QUOTES, 'UTF-8'); ?>/">Ver Sitio Web &rarr;</a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
