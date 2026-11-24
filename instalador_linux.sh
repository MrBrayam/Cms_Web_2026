#!/usr/bin/env bash
# ==============================================================================
# Instalador de BlogCMS Propio (Sin WordPress) para Linux / Kubuntu (KDE Plasma)
# Soporta MySQL/MariaDB Local (3306), XAMPP Linux (/opt/lampp) y PostgreSQL (5432)
# ==============================================================================

set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$PROJECT_DIR/config/config.php"

check_port() {
    (echo >/dev/tcp/"$1"/"$2") >/dev/null 2>&1 && echo "ACTIVO" || echo "DETENIDO"
}

XAMPP_PORT=3307
if [ -f "/opt/lampp/etc/my.cnf" ]; then
    P=$(grep -E '^[[:space:]]*port[[:space:]]*=' /opt/lampp/etc/my.cnf | head -n 1 | awk -F= '{print $2}' | tr -d '[:space:]')
    [ -n "$P" ] && XAMPP_PORT="$P"
fi

STATUS_LOCAL=$(check_port 127.0.0.1 3306)
STATUS_XAMPP=$(check_port 127.0.0.1 "$XAMPP_PORT")
STATUS_PGSQL=$(check_port 127.0.0.1 5432)

PHP_BIN=""
if command -v php >/dev/null 2>&1; then
    PHP_BIN="$(command -v php)"
elif [ -x "/opt/lampp/bin/php" ]; then
    PHP_BIN="/opt/lampp/bin/php"
fi

if [ -z "$PHP_BIN" ]; then
    echo "Error: No se encontro PHP. En Kubuntu instala con:"
    echo "  sudo apt update && sudo apt install -y php php-mysql php-pgsql php-zip php-gd php-mbstring php-curl mariadb-server"
    exit 1
fi

echo "========================================================"
echo "  Instalador BlogCMS Propio - Kubuntu / Linux"
echo "========================================================"
echo "Motores detectados:"
echo "  1) MySQL / MariaDB Local (127.0.0.1:3306) -> [$STATUS_LOCAL]"
echo "  2) MySQL XAMPP /opt/lampp (127.0.0.1:$XAMPP_PORT) -> [$STATUS_XAMPP]"
echo "  3) PostgreSQL (127.0.0.1:5432) -> [$STATUS_PGSQL]"
echo "--------------------------------------------------------"

ENGINE_CHOICE=""
if [ -n "${DISPLAY:-}" ] && command -v kdialog >/dev/null 2>&1; then
    ENGINE_CHOICE=$(kdialog --title "Instalador BlogCMS - Kubuntu" \
        --radiolist "Selecciona una accion o motor de Base de Datos:" \
        "web" "Abrir Asistente Web Visual en el Navegador (Recomendado)" on \
        "mysql_local" "Instalar con MySQL / MariaDB Local (3306) - [$STATUS_LOCAL]" off \
        "mysql_xampp" "Instalar con MySQL de XAMPP /opt/lampp ($XAMPP_PORT) - [$STATUS_XAMPP]" off \
        "pgsql" "Instalar con PostgreSQL (5432) - [$STATUS_PGSQL]" off \
        "reinstall" "Reinstalar BlogCMS desde cero (usa config/config.php)" off \
        "uninstall" "Desinstalar BlogCMS (borra tablas/BD y config)" off 2>/dev/null || true)
fi

if [ -z "$ENGINE_CHOICE" ]; then
    echo "Opciones:"
    echo "  [1] Instalar con MySQL / MariaDB Local (3306)"
    echo "  [2] Instalar con MySQL XAMPP ($XAMPP_PORT)"
    echo "  [3] Instalar con PostgreSQL (5432)"
    echo "  [4] Iniciar servidor web y abrir Asistente Visual (http://localhost:8080/instalador.php)"
    echo "  [5] Reinstalar BlogCMS desde cero (usa config/config.php actual)"
    echo "  [6] Desinstalar BlogCMS (elimina tablas/BD, config/config.php y .htaccess)"
    read -r -p "Elige [1-6, por defecto 4]: " OPT
    case "${OPT:-4}" in
        1) ENGINE_CHOICE="mysql_local" ;;
        2) ENGINE_CHOICE="mysql_xampp" ;;
        3) ENGINE_CHOICE="pgsql" ;;
        5) ENGINE_CHOICE="reinstall" ;;
        6) ENGINE_CHOICE="uninstall" ;;
        *) ENGINE_CHOICE="web" ;;
    esac
fi

if [ "$ENGINE_CHOICE" = "reinstall" ]; then
    if [ ! -f "$CONFIG_FILE" ]; then
        echo "No existe config/config.php. Usa la opcion de instalacion normal."
        exit 1
    fi
    "$PHP_BIN" -r '
    $root = $argv[1];
    $cfg = require "$root/config/config.php";
    $drv = $cfg["db_driver"]; $host = $cfg["db_host"]; $port = (int)$cfg["db_port"]; $db = $cfg["db_name"];
    $dsn = ($drv === "pgsql") ? "pgsql:host=$host;port=$port;dbname=$db" : "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $cfg["db_user"], $cfg["db_pass"], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $sqlFile = ($drv === "pgsql") ? "$root/database/schema_pgsql.sql" : "$root/database/schema_mysql.sql";
    $sql = preg_replace("/^\s*--.*$/m", "", file_get_contents($sqlFile));
    foreach (array_filter(array_map("trim", explode(";\n", $sql))) as $q) { $pdo->exec($q); }
    $pdo->prepare("UPDATE options SET option_value = ? WHERE option_name = ?")->execute([$cfg["app_url"], "site_url"]);
    if (class_exists("ZipArchive")) {
        foreach ([["$root/packages/plugins/*.zip", "$root/plugins"], ["$root/packages/themes/*.zip", "$root/themes"]] as [$g, $d]) {
            foreach (glob($g) ?: [] as $zf) { $z = new ZipArchive(); if ($z->open($zf) === true) { $z->extractTo($d); $z->close(); } }
        }
    }
    echo "BlogCMS reinstalado desde cero exitosamente.\n";
    ' "$PROJECT_DIR"
    exit 0
fi

if [ "$ENGINE_CHOICE" = "uninstall" ]; then
    read -r -p "Confirmas desinstalar BlogCMS? (s/N): " CONF
    if [ "$CONF" != "s" ] && [ "$CONF" != "S" ]; then
        echo "Desinstalacion cancelada."
        exit 0
    fi
    read -r -p "Eliminar tambien la base de datos completa? (s/N): " DROP_DB
    "$PHP_BIN" -r '
    $root = $argv[1]; $dropDb = ($argv[2] === "s" || $argv[2] === "S");
    $cfgFile = "$root/config/config.php";
    if (is_file($cfgFile)) {
        $cfg = require $cfgFile;
        $drv = $cfg["db_driver"]; $host = $cfg["db_host"]; $port = (int)$cfg["db_port"]; $db = $cfg["db_name"];
        if ($dropDb) {
            $dsn = ($drv === "pgsql") ? "pgsql:host=$host;port=$port;dbname=postgres" : "mysql:host=$host;port=$port;charset=utf8mb4";
            $p = new PDO($dsn, $cfg["db_user"], $cfg["db_pass"], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $p->exec($drv === "pgsql" ? "DROP DATABASE IF EXISTS \"$db\" WITH (FORCE)" : "DROP DATABASE IF EXISTS `$db`");
        } else {
            $dsn = ($drv === "pgsql") ? "pgsql:host=$host;port=$port;dbname=$db" : "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
            $p = new PDO($dsn, $cfg["db_user"], $cfg["db_pass"], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $tables = ["audit_logs","visit_logs","contact_messages","gallery_images","gallery_albums","media","themes","plugins","menus","comments","pages","post_tags","posts","tags","categories","users","options"];
            if ($drv === "mysql") $p->exec("SET FOREIGN_KEY_CHECKS = 0");
            foreach ($tables as $t) $p->exec($drv === "pgsql" ? "DROP TABLE IF EXISTS \"$t\" CASCADE" : "DROP TABLE IF EXISTS `$t`");
            if ($drv === "mysql") $p->exec("SET FOREIGN_KEY_CHECKS = 1");
        }
        unlink($cfgFile);
    }
    if (is_file("$root/.htaccess")) unlink("$root/.htaccess");
    echo "BlogCMS desinstalado limpiamente.\n";
    ' "$PROJECT_DIR" "${DROP_DB:-N}"
    exit 0
fi

if [ "$ENGINE_CHOICE" = "web" ]; then
    echo "Iniciando BlogCMS en http://localhost:8080 ..."
    if command -v xdg-open >/dev/null 2>&1; then
        (sleep 1 && xdg-open "http://localhost:8080/instalador.php") >/dev/null 2>&1 &
    fi
    exec "$PHP_BIN" -S localhost:8080 -t "$PROJECT_DIR" "$PROJECT_DIR/index.php"
fi

DRIVER="mysql"
DB_HOST="127.0.0.1"
DB_PORT=3306
DB_USER="root"
if [ "$ENGINE_CHOICE" = "mysql_xampp" ]; then
    DB_PORT="$XAMPP_PORT"
elif [ "$ENGINE_CHOICE" = "pgsql" ]; then
    DRIVER="pgsql"
    DB_PORT=5432
    DB_USER="postgres"
fi

read -r -p "Usuario BD [$DB_USER]: " IN_U; DB_USER="${IN_U:-$DB_USER}"
read -r -s -p "Contrasena BD (Enter si vacia): " DB_PASS; echo ""
read -r -p "Nombre BD [blogcms_wp]: " IN_D; DB_NAME="${IN_D:-blogcms_wp}"
read -r -p "URL del sitio [http://localhost:8080]: " IN_URL; SITE_URL="${IN_URL:-http://localhost:8080}"
read -r -p "Tema inicial (magazine/business/portfolio/minimal-blog) [magazine]: " IN_T; THEME="${IN_T:-magazine}"

"$PHP_BIN" -r '
[$driver, $host, $port, $user, $pass, $db, $url, $theme, $root] = array_slice($argv, 1);
$port = (int)$port;
$url = rtrim($url, "/");
$adminDsn = ($driver === "pgsql") ? "pgsql:host=$host;port=$port;dbname=postgres" : "mysql:host=$host;port=$port;charset=utf8mb4";
$rootPdo = new PDO($adminDsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($driver === "pgsql") {
    $st = $rootPdo->prepare("SELECT 1 FROM pg_database WHERE datname = ?");
    $st->execute([$db]);
    if (!$st->fetchColumn()) $rootPdo->exec("CREATE DATABASE \"$db\" ENCODING \x27UTF8\x27");
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    $sqlFile = "$root/database/schema_pgsql.sql";
} else {
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $sqlFile = "$root/database/schema_mysql.sql";
}
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sql = preg_replace("/^\s*--.*$/m", "", file_get_contents($sqlFile));
foreach (array_filter(array_map("trim", explode(";\n", $sql))) as $q) { $pdo->exec($q); }
$up = $pdo->prepare("UPDATE options SET option_value = ? WHERE option_name = ?");
foreach (["site_url" => $url, "active_theme" => $theme] as $k => $v) $up->execute([$v, $k]);
$pdo->exec("UPDATE themes SET is_active = 0");
$pdo->prepare("UPDATE themes SET is_active = 1 WHERE slug = ?")->execute([$theme]);

$cfg = "<?php\nreturn [\n    \x27db_driver\x27 => \x27$driver\x27,\n    \x27db_host\x27 => \x27$host\x27,\n    \x27db_port\x27 => $port,\n    \x27db_name\x27 => \x27$db\x27,\n    \x27db_user\x27 => \x27$user\x27,\n    \x27db_pass\x27 => \x27" . addcslashes($pass, "\\\x27") . "\x27,\n    \x27app_url\x27 => \x27$url\x27,\n    \x27app_secret\x27 => \x27" . bin2hex(random_bytes(32)) . "\x27,\n    \x27debug\x27 => false,\n];\n";
file_put_contents("$root/config/config.php", $cfg);
echo "Instalacion completada ($driver -> $db) y config/config.php generado.\n";
' "$DRIVER" "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" "$DB_NAME" "$SITE_URL" "$THEME" "$PROJECT_DIR"
