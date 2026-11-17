#!/usr/bin/env bash
# ==============================================================================
# Instalador Ejecutable de BlogCMS (WordPress) para Linux / Kubuntu (KDE Plasma)
# UNSM - Ingenieria Web | Grupo 1
# Soporta interfaz grafica KDE (kdialog), Zenity o modo interactivo en terminal.
# Detecta automaticamente MySQL/MariaDB Local (3306), XAMPP (/opt/lampp) y PostgreSQL (5432).
# ==============================================================================

set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SQL_FILE="$PROJECT_DIR/database/blogcms_wp.sql"
CONFIG_FILE="$PROJECT_DIR/wp-config.php"

check_port() {
    local host="$1"
    local port="$2"
    (echo >/dev/tcp/"$host"/"$port") >/dev/null 2>&1 && echo "ACTIVO" || echo "DETENIDO"
}

# Detectar puerto de XAMPP en Linux (/opt/lampp/etc/my.cnf)
XAMPP_PORT=3307
if [ -f "/opt/lampp/etc/my.cnf" ]; then
    DETECTED_P=$(grep -E '^[[:space:]]*port[[:space:]]*=' /opt/lampp/etc/my.cnf | head -n 1 | awk -F= '{print $2}' | tr -d '[:space:]')
    if [ -n "$DETECTED_P" ]; then
        XAMPP_PORT="$DETECTED_P"
    fi
fi

STATUS_LOCAL=$(check_port 127.0.0.1 3306)
STATUS_XAMPP=$(check_port 127.0.0.1 "$XAMPP_PORT")
STATUS_PGSQL=$(check_port 127.0.0.1 5432)

# Buscar binario PHP disponible (sistema o XAMPP Linux)
PHP_BIN=""
if command -v php >/dev/null 2>&1; then
    PHP_BIN="$(command -v php)"
elif [ -x "/opt/lampp/bin/php" ]; then
    PHP_BIN="/opt/lampp/bin/php"
fi

if [ -z "$PHP_BIN" ]; then
    echo "Error: No se encontro PHP instalado."
    echo "En Kubuntu puedes instalarlo con: sudo apt update && sudo apt install -y php php-mysql php-zip php-gd php-intl php-curl php-mbstring php-xml"
    exit 1
fi

echo "========================================================"
echo "  Instalador de BlogCMS (WordPress) - Linux / Kubuntu"
echo "========================================================"
echo "Motores detectados:"
echo "  1) MySQL / MariaDB Local (127.0.0.1:3306) -> [$STATUS_LOCAL]"
echo "  2) MySQL de XAMPP /opt/lampp (127.0.0.1:$XAMPP_PORT) -> [$STATUS_XAMPP]"
echo "  3) PostgreSQL (127.0.0.1:5432) -> [$STATUS_PGSQL]"
echo "--------------------------------------------------------"

# Si estamos en Kubuntu con entorno grafico KDE y kdialog disponible, ofrecer selector grafico
ENGINE_CHOICE=""
if [ -n "${DISPLAY:-}" ] && command -v kdialog >/dev/null 2>&1; then
    ENGINE_CHOICE=$(kdialog --title "Instalador BlogCMS - Kubuntu" \
        --radiolist "Selecciona el motor de Base de Datos detectado:" \
        "mysql_local" "MySQL / MariaDB Local (Puerto 3306) - [$STATUS_LOCAL]" on \
        "mysql_xampp" "MySQL de XAMPP /opt/lampp (Puerto $XAMPP_PORT) - [$STATUS_XAMPP]" off \
        "pgsql" "PostgreSQL (Puerto 5432) - [$STATUS_PGSQL]" off \
        "web" "Abrir Asistente Web Visual en el Navegador (instalador.php)" off 2>/dev/null || true)
fi

if [ -z "$ENGINE_CHOICE" ]; then
    echo "Selecciona como deseas instalar:"
    echo "  [1] MySQL / MariaDB Local (Puerto 3306)"
    echo "  [2] MySQL de XAMPP Linux (Puerto $XAMPP_PORT)"
    echo "  [3] PostgreSQL (Puerto 5432)"
    echo "  [4] Levantar servidor web en http://localhost:8080/instalador.php (Asistente Web)"
    read -r -p "Opcion [1-4, por defecto 4]: " OPT
    case "${OPT:-4}" in
        1) ENGINE_CHOICE="mysql_local" ;;
        2) ENGINE_CHOICE="mysql_xampp" ;;
        3) ENGINE_CHOICE="pgsql" ;;
        *) ENGINE_CHOICE="web" ;;
    esac
fi

if [ "$ENGINE_CHOICE" = "web" ]; then
    echo "Iniciando servidor web de BlogCMS en http://localhost:8080 ..."
    if command -v xdg-open >/dev/null 2>&1; then
        (sleep 1 && xdg-open "http://localhost:8080/instalador.php") >/dev/null 2>&1 &
    fi
    exec "$PHP_BIN" -S localhost:8080 -t "$PROJECT_DIR"
fi

DB_HOST="127.0.0.1"
DB_PORT=3306
DB_USER="root"
if [ "$ENGINE_CHOICE" = "mysql_xampp" ]; then
    DB_PORT="$XAMPP_PORT"
elif [ "$ENGINE_CHOICE" = "pgsql" ]; then
    DB_PORT=5432
    DB_USER="postgres"
fi

read -r -p "Usuario de BD [$DB_USER]: " INPUT_USER
DB_USER="${INPUT_USER:-$DB_USER}"
read -r -s -p "Contrasena de BD (Enter si esta vacia): " DB_PASS
echo ""
read -r -p "Nombre de la Base de Datos [blogcms_wp]: " INPUT_DB
DB_NAME="${INPUT_DB:-blogcms_wp}"
read -r -p "URL del sitio [http://localhost:8080]: " INPUT_URL
SITE_URL="${INPUT_URL:-http://localhost:8080}"
read -r -p "Tema inicial (magazine/business/portfolio/minimal-blog) [magazine]: " INPUT_THEME
THEME_SLUG="${INPUT_THEME:-magazine}"

"$PHP_BIN" -r '
$host = $argv[1];
$port = (int)$argv[2];
$user = $argv[3];
$pass = $argv[4];
$db   = $argv[5];
$url  = rtrim($argv[6], "/");
$thm  = $argv[7];
$root = $argv[8];
$sqlF = $root . "/database/blogcms_wp.sql";

mysqli_report(MYSQLI_REPORT_OFF);
$m = @new mysqli($host, $user, $pass, "", $port);
if ($m->connect_errno) {
    fwrite(STDERR, "Error conectando a MySQL ($host:$port): " . $m->connect_error . "\n");
    exit(1);
}
$m->set_charset("utf8mb4");
$m->query("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$m->select_db($db);

if (is_readable($sqlF)) {
    $raw = file_get_contents($sqlF);
    $clean = preg_replace("/^CREATE DATABASE .*?;\s*$/m", "", $raw);
    $clean = preg_replace("/^USE `[^`]+`;\s*$/m", "", $clean);
    if ($m->multi_query($clean)) {
        do { if ($r = $m->store_result()) { $r->free(); } } while ($m->more_results() && $m->next_result());
    }
    echo "Base de datos importada desde database/blogcms_wp.sql\n";
}

$stmt = $m->prepare("UPDATE wp_options SET option_value = ? WHERE option_name = ?");
if ($stmt) {
    foreach (["siteurl" => $url, "home" => $url, "template" => $thm, "stylesheet" => $thm] as $k => $v) {
        $stmt->bind_param("ss", $v, $k);
        $stmt->execute();
    }
    $stmt->close();
}
$u_url = $m->real_escape_string($url);
$m->query("UPDATE wp_posts SET post_content = REPLACE(post_content, \"http://blogcms.localhost\", \"$u_url\"), guid = REPLACE(guid, \"http://blogcms.localhost\", \"$u_url\")");
$m->close();

$keys = ["AUTH_KEY","SECURE_AUTH_KEY","LOGGED_IN_KEY","NONCE_KEY","AUTH_SALT","SECURE_AUTH_SALT","LOGGED_IN_SALT","NONCE_SALT","WP_SECRETS_KEY"];
$salts = "";
foreach ($keys as $k) { $salts .= "define( \x27$k\x27, \x27" . bin2hex(random_bytes(32)) . "\x27 );\n"; }
$hp = ($port === 3306) ? $host : "$host:$port";
$cfg = "<?php\ndefine( \x27DB_NAME\x27, \x27" . addslashes($db) . "\x27 );\ndefine( \x27DB_USER\x27, \x27" . addslashes($user) . "\x27 );\ndefine( \x27DB_PASSWORD\x27, \x27" . addslashes($pass) . "\x27 );\ndefine( \x27DB_HOST\x27, \x27$hp\x27 );\ndefine( \x27DB_CHARSET\x27, \x27utf8mb4\x27 );\ndefine( \x27DB_COLLATE\x27, \x27\x27 );\n$salts\$table_prefix = \x27wp_\x27;\ndefine( \x27WP_DEBUG\x27, false );\ndefine( \x27FS_METHOD\x27, \x27direct\x27 );\nif ( ! defined( \x27ABSPATH\x27 ) ) { define( \x27ABSPATH\x27, __DIR__ . \x27/\x27 ); }\nrequire_once ABSPATH . \x27wp-settings.php\x27;\n";
file_put_contents($root . "/wp-config.php", $cfg);
echo "Archivo wp-config.php generado correctamente.\n";
' "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" "$DB_NAME" "$SITE_URL" "$THEME_SLUG" "$PROJECT_DIR"

echo "========================================================"
echo " Instalacion completada en Kubuntu / Linux!"
echo " Sitio disponible en: $SITE_URL"
echo "========================================================"
