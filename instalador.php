<?php
/**
 * Asistente de Instalacion Web — BlogCMS (Tipo: WordPress)
 * UNSM — Ingenieria Web | Grupo 1
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '0' );

define( 'BLOGCMS_ROOT', __DIR__ );
define( 'BLOGCMS_SQL_FILE', BLOGCMS_ROOT . '/database/blogcms_wp.sql' );
define( 'BLOGCMS_CONFIG_FILE', BLOGCMS_ROOT . '/wp-config.php' );

/**
 * Autodetecta la URL base donde corre el proyecto.
 */
function blogcms_detect_base_url(): string {
	$scheme = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
	$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$script = $_SERVER['SCRIPT_NAME'] ?? '/instalador.php';
	$dir    = rtrim( str_replace( '\\', '/', dirname( $script ) ), '/' );
	return $scheme . '://' . $host . ( $dir ? $dir : '' );
}

/**
 * Comprueba si un puerto TCP local esta abierto.
 */
function blogcms_is_port_open( string $host, int $port, float $timeout = 0.3 ): bool {
	$fp = @fsockopen( $host, $port, $errno, $errstr, $timeout );
	if ( $fp ) {
		fclose( $fp );
		return true;
	}
	return false;
}

/**
 * Detecta automaticamente MySQL Local, MySQL de XAMPP y PostgreSQL.
 */
function blogcms_detect_db_engines(): array {
	$xampp_port = 3307;
	$my_ini     = 'C:/xampp/mysql/bin/my.ini';
	if ( is_readable( $my_ini ) ) {
		$ini_raw = file_get_contents( $my_ini );
		if ( preg_match( '/^\s*port\s*=\s*(\d+)/m', $ini_raw, $m ) ) {
			$xampp_port = (int) $m[1];
		}
	}

	$local_open = blogcms_is_port_open( '127.0.0.1', 3306 );
	$xampp_open = blogcms_is_port_open( '127.0.0.1', $xampp_port );
	$pgsql_open = blogcms_is_port_open( '127.0.0.1', 5432 );

	return [
		'mysql_local' => [
			'id'     => 'mysql_local',
			'name'   => 'MySQL Local (MySQL Server 8.x)',
			'engine' => 'mysql',
			'host'   => '127.0.0.1',
			'port'   => 3306,
			'user'   => 'root',
			'open'   => $local_open,
			'status' => $local_open ? 'ACTIVO (Puerto 3306)' : 'Detenido (Puerto 3306)',
		],
		'mysql_xampp' => [
			'id'     => 'mysql_xampp',
			'name'   => 'MySQL / MariaDB de XAMPP',
			'engine' => 'mysql',
			'host'   => '127.0.0.1',
			'port'   => $xampp_port,
			'user'   => 'root',
			'open'   => $xampp_open,
			'status' => $xampp_open ? "ACTIVO (Puerto $xampp_port)" : "Instalado en XAMPP (Puerto $xampp_port detenido)",
		],
		'pgsql'       => [
			'id'     => 'pgsql',
			'name'   => 'PostgreSQL (Local / Remoto)',
			'engine' => 'pgsql',
			'host'   => '127.0.0.1',
			'port'   => 5432,
			'user'   => 'postgres',
			'open'   => $pgsql_open,
			'status' => $pgsql_open ? 'ACTIVO (Puerto 5432)' : 'Disponible (Puerto 5432)',
		],
	];
}

/**
 * Evalua los requisitos tecnicos del servidor.
 */
function blogcms_check_requirements(): array {
	$checks = [
		[
			'label'    => 'Version de PHP >= 7.4 (Actual: ' . PHP_VERSION . ')',
			'ok'       => version_compare( PHP_VERSION, '7.4.0', '>=' ),
			'required' => true,
		],
		[
			'label'    => 'Extension MySQLi (Conexion a Base de Datos)',
			'ok'       => extension_loaded( 'mysqli' ),
			'required' => true,
		],
		[
			'label'    => 'Extension ZipArchive (Subida de Plugins y Plantillas .zip — Lamina 8)',
			'ok'       => class_exists( 'ZipArchive' ),
			'required' => true,
		],
		[
			'label'    => 'Extension GD (Procesamiento de portadas y miniaturas)',
			'ok'       => extension_loaded( 'gd' ),
			'required' => true,
		],
		[
			'label'    => 'Extensiones mbstring, curl y openssl',
			'ok'       => extension_loaded( 'mbstring' ) && extension_loaded( 'curl' ) && extension_loaded( 'openssl' ),
			'required' => true,
		],
		[
			'label'    => 'Permisos de escritura en el directorio raiz del CMS',
			'ok'       => is_writable( BLOGCMS_ROOT ),
			'required' => true,
		],
		[
			'label'    => 'Respaldo SQL inicial (database/blogcms_wp.sql)',
			'ok'       => is_readable( BLOGCMS_SQL_FILE ),
			'required' => false,
		],
	];

	$all_required_ok = true;
	foreach ( $checks as $c ) {
		if ( $c['required'] && ! $c['ok'] ) {
			$all_required_ok = false;
		}
	}

	return [ 'items' => $checks, 'ready' => $all_required_ok ];
}

/**
 * Endpoint AJAX para probar la conexion MySQL sin recargar.
 */
if ( isset( $_GET['action'] ) && $_GET['action'] === 'test_db' && ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' ) {
	header( 'Content-Type: application/json; charset=utf-8' );
	$engine = trim( $_POST['db_engine'] ?? 'mysql_local' );
	$host   = trim( $_POST['db_host'] ?? '127.0.0.1' );
	$port   = (int) ( $_POST['db_port'] ?? 3306 );
	$user   = trim( $_POST['db_user'] ?? 'root' );
	$pass   = (string) ( $_POST['db_pass'] ?? '' );
	$name   = trim( $_POST['db_name'] ?? 'blogcms_wp' );

	if ( $engine === 'pgsql' ) {
		if ( ! blogcms_is_port_open( $host, $port, 0.5 ) ) {
			echo json_encode( [ 'ok' => false, 'message' => "El puerto PostgreSQL $host:$port no responde." ] );
			exit;
		}
		if ( extension_loaded( 'pdo_pgsql' ) ) {
			try {
				$pdo = new PDO( "pgsql:host=$host;port=$port;dbname=postgres", $user, $pass, [ PDO::ATTR_TIMEOUT => 4 ] );
				echo json_encode( [ 'ok' => true, 'message' => "Conexion exitosa a PostgreSQL en $host:$port." ] );
			} catch ( Exception $e ) {
				echo json_encode( [ 'ok' => false, 'message' => 'Error PostgreSQL: ' . $e->getMessage() ] );
			}
		} else {
			echo json_encode( [ 'ok' => true, 'message' => "Puerto PostgreSQL $host:$port detectado activo." ] );
		}
		exit;
	}

	mysqli_report( MYSQLI_REPORT_OFF );
	$mysqli = @new mysqli( $host, $user, $pass, '', $port );
	if ( $mysqli->connect_errno ) {
		echo json_encode( [
			'ok'      => false,
			'message' => 'Error de conexion (' . $mysqli->connect_errno . '): ' . $mysqli->connect_error,
		] );
		exit;
	}

	$server_info = $mysqli->server_info;
	$db_exists   = false;
	$res         = $mysqli->query( "SHOW DATABASES LIKE '" . $mysqli->real_escape_string( $name ) . "'" );
	if ( $res && $res->num_rows > 0 ) {
		$db_exists = true;
	}
	$mysqli->close();

	echo json_encode( [
		'ok'      => true,
		'message' => "Conexion exitosa a MySQL $server_info ($host:$port). " . ( $db_exists ? "La BD '$name' ya existe." : "La BD '$name' se creara automaticamente." ),
	] );
	exit;
}

/**
 * Genera el contenido de wp-config.php con claves aleatorias.
 */
function blogcms_build_wp_config( string $db_name, string $db_user, string $db_pass, string $db_host_port, string $prefix = 'wp_' ): string {
	$keys = [
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
		'WP_SECRETS_KEY',
	];
	$salts_code = '';
	foreach ( $keys as $k ) {
		$salts_code .= sprintf( "define( '%s', '%s' );\n", $k, bin2hex( random_bytes( 32 ) ) );
	}

	$esc = static function ( string $val ): string {
		return addcslashes( $val, "\\'" );
	};

	return "<?php\n"
		. "/**\n"
		. " * Configuracion base de BlogCMS (WordPress)\n"
		. " * Generado automaticamente por instalador.php\n"
		. " */\n\n"
		. "define( 'DB_NAME', '" . $esc( $db_name ) . "' );\n"
		. "define( 'DB_USER', '" . $esc( $db_user ) . "' );\n"
		. "define( 'DB_PASSWORD', '" . $esc( $db_pass ) . "' );\n"
		. "define( 'DB_HOST', '" . $esc( $db_host_port ) . "' );\n"
		. "define( 'DB_CHARSET', 'utf8mb4' );\n"
		. "define( 'DB_COLLATE', '' );\n\n"
		. $salts_code . "\n"
		. "\$table_prefix = '" . preg_replace( '/[^a-zA-Z0-9_]/', '', $prefix ) . "';\n\n"
		. "define( 'WP_DEBUG', false );\n"
		. "define( 'FS_METHOD', 'direct' );\n\n"
		. "if ( ! defined( 'ABSPATH' ) ) {\n"
		. "\tdefine( 'ABSPATH', __DIR__ . '/' );\n"
		. "}\n\n"
		. "require_once ABSPATH . 'wp-settings.php';\n";
}

/**
 * Genera el archivo .htaccess ajustado al subdirectorio de la URL.
 */
function blogcms_write_htaccess( string $site_url ): bool {
	$parsed    = parse_url( $site_url );
	$base_path = isset( $parsed['path'] ) ? rtrim( $parsed['path'], '/' ) . '/' : '/';
	$rules     = "# BEGIN WordPress\n"
		. "<IfModule mod_rewrite.c>\n"
		. "RewriteEngine On\n"
		. "RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\n"
		. "RewriteBase " . $base_path . "\n"
		. "RewriteRule ^index\\.php$ - [L]\n"
		. "RewriteCond %{REQUEST_FILENAME} !-f\n"
		. "RewriteCond %{REQUEST_FILENAME} !-d\n"
		. "RewriteRule . " . $base_path . "index.php [L]\n"
		. "</IfModule>\n"
		. "# END WordPress\n";

	return @file_put_contents( BLOGCMS_ROOT . '/.htaccess', $rules ) !== false;
}

$step       = isset( $_GET['step'] ) ? max( 1, min( 4, (int) $_GET['step'] ) ) : 1;
$reqs       = blogcms_check_requirements();
$engines    = blogcms_detect_db_engines();
$base_url   = blogcms_detect_base_url();
$has_config = file_exists( BLOGCMS_CONFIG_FILE );
$error_msg  = '';
$install_log = [];

// Procesar la instalacion cuando se envia el formulario del Paso 2
if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' && isset( $_POST['run_install'] ) ) {
	$step        = 2;
	$db_host     = trim( $_POST['db_host'] ?? '127.0.0.1' );
	$db_port     = (int) ( $_POST['db_port'] ?? 3306 );
	$db_user     = trim( $_POST['db_user'] ?? 'root' );
	$db_pass     = (string) ( $_POST['db_pass'] ?? '' );
	$db_name     = preg_replace( '/[^a-zA-Z0-9_]/', '', trim( $_POST['db_name'] ?? 'blogcms_wp' ) );
	$db_mode     = $_POST['db_mode'] ?? 'import';
	$site_title  = trim( $_POST['site_title'] ?? 'Bloc-FISI' );
	$site_url    = rtrim( trim( $_POST['site_url'] ?? $base_url ), '/' );
	$theme_slug  = trim( $_POST['theme_slug'] ?? 'magazine' );
	$admin_user  = trim( $_POST['admin_user'] ?? 'BrayamAF' );
	$admin_pass  = trim( $_POST['admin_pass'] ?? '' );
	$admin_email = trim( $_POST['admin_email'] ?? 'brayamaristafrndz@gmail.com' );

	$allowed_themes = [ 'magazine', 'business', 'portfolio', 'minimal-blog' ];
	if ( ! in_array( $theme_slug, $allowed_themes, true ) ) {
		$theme_slug = 'magazine';
	}

	mysqli_report( MYSQLI_REPORT_OFF );
	$mysqli = @new mysqli( $db_host, $db_user, $db_pass, '', $db_port );
	if ( $mysqli->connect_errno ) {
		$error_msg = 'No se pudo conectar a MySQL (' . $mysqli->connect_errno . '): ' . $mysqli->connect_error;
	} elseif ( empty( $db_name ) ) {
		$error_msg = 'El nombre de la base de datos no es valido.';
	} else {
		$mysqli->set_charset( 'utf8mb4' );

		// 1. Crear BD si no existe
		$create_sql = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
		if ( ! $mysqli->query( $create_sql ) ) {
			$error_msg = 'Error al crear la base de datos: ' . $mysqli->error;
		} else {
			$mysqli->select_db( $db_name );
			$install_log[] = "Base de datos '$db_name' verificada en $db_host:$db_port.";

			// 2. Importar respaldo SQL si se selecciono
			if ( $db_mode === 'import' ) {
				if ( ! is_readable( BLOGCMS_SQL_FILE ) ) {
					$error_msg = 'No se encontro el archivo database/blogcms_wp.sql para importar.';
				} else {
					$sql_raw = file_get_contents( BLOGCMS_SQL_FILE );
					// Remover CREATE DATABASE y USE fijos para permitir cualquier nombre de BD y motor MySQL/MariaDB
					$sql_clean = preg_replace( '/^CREATE DATABASE .*?;\s*$/m', '', $sql_raw );
					$sql_clean = preg_replace( '/^USE `[^`]+`;\s*$/m', '', $sql_clean );

					if ( $mysqli->multi_query( $sql_clean ) ) {
						do {
							if ( $result = $mysqli->store_result() ) {
								$result->free();
							}
						} while ( $mysqli->more_results() && $mysqli->next_result() );

						if ( $mysqli->errno ) {
							$error_msg = 'Error durante la importacion SQL: ' . $mysqli->error;
						} else {
							$install_log[] = 'Respaldo database/blogcms_wp.sql importado (6 modulos, 6 plugins, 4 temas y contenido).';
						}
					} else {
						$error_msg = 'No se pudo iniciar la importacion SQL: ' . $mysqli->error;
					}
				}
			} else {
				$install_log[] = 'Se conservaron las tablas existentes en la base de datos.';
			}

			// 3. Actualizar opciones del sitio (URL, titulo, tema activo y admin)
			if ( empty( $error_msg ) ) {
				$stmt = $mysqli->prepare( "UPDATE wp_options SET option_value = ? WHERE option_name = ?" );
				if ( $stmt ) {
					$updates = [
						'siteurl'    => $site_url,
						'home'       => $site_url,
						'blogname'   => $site_title,
						'template'   => $theme_slug,
						'stylesheet' => $theme_slug,
					];
					foreach ( $updates as $opt_name => $opt_val ) {
						$stmt->bind_param( 'ss', $opt_val, $opt_name );
						$stmt->execute();
					}
					$stmt->close();
					$install_log[] = "Configuracion actualizada: URL ($site_url), titulo ('$site_title') y tema activo ('$theme_slug').";
				}

				if ( ! empty( $admin_user ) ) {
					$u_esc = $mysqli->real_escape_string( $admin_user );
					$e_esc = $mysqli->real_escape_string( $admin_email );
					$mysqli->query( "UPDATE wp_users SET user_login = '$u_esc', user_email = '$e_esc' WHERE ID = 1" );
					if ( ! empty( $admin_pass ) ) {
						// MD5 es aceptado por WordPress y actualizado automaticamente a phpass/bcrypt al primer login
						$p_hash = md5( $admin_pass );
						$mysqli->query( "UPDATE wp_users SET user_pass = '$p_hash' WHERE ID = 1" );
						$install_log[] = "Credenciales del administrador ($admin_user) actualizadas.";
					}
				}

				// 4. Escribir wp-config.php y .htaccess
				$host_port = ( $db_port === 3306 ) ? $db_host : ( $db_host . ':' . $db_port );
				$cfg_code  = blogcms_build_wp_config( $db_name, $db_user, $db_pass, $host_port );
				if ( @file_put_contents( BLOGCMS_CONFIG_FILE, $cfg_code ) === false ) {
					$error_msg = 'No se pudo escribir el archivo wp-config.php.';
				} else {
					$install_log[] = 'Archivo wp-config.php generado con claves criptograficas y FS_METHOD = direct.';
					if ( blogcms_write_htaccess( $site_url ) ) {
						$install_log[] = 'Archivo .htaccess generado para enlaces permanentes.';
					}
					$step = 4;
				}
			}
		}
		$mysqli->close();
	}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Instalador de BlogCMS &mdash; UNSM Ingenieria Web</title>
	<style>
		:root {
			--bg: #0f172a;
			--card: #ffffff;
			--text: #1e293b;
			--muted: #64748b;
			--border: #e2e8f0;
			--primary: #0b3b60;
			--accent: #0284c7;
			--ok: #059669;
			--err: #dc2626;
		}
		*, *::before, *::after { box-sizing: border-box; }
		body {
			margin: 0;
			font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
			background: linear-gradient(135deg, #072842 0%, #0f172a 100%);
			color: var(--text);
			min-height: 100vh;
			padding: 2.25rem 1rem;
		}
		.inst-wrap {
			max-width: 820px;
			margin: 0 auto;
		}
		.inst-header {
			color: #ffffff;
			text-align: center;
			margin-bottom: 1.75rem;
		}
		.inst-badge {
			display: inline-block;
			background: rgba(2, 132, 199, 0.25);
			border: 1px solid rgba(125, 211, 252, 0.4);
			color: #bae6fd;
			font-size: 0.78rem;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			padding: 0.25rem 0.85rem;
			border-radius: 999px;
			margin-bottom: 0.6rem;
		}
		.inst-header h1 {
			margin: 0 0 0.35rem;
			font-size: 2rem;
			letter-spacing: -0.02em;
		}
		.inst-header p {
			margin: 0;
			color: #94a3b8;
			font-size: 0.96rem;
		}
		.inst-steps {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			gap: 0.75rem;
			margin-bottom: 1.5rem;
		}
		.inst-step {
			background: rgba(255, 255, 255, 0.07);
			border: 1px solid rgba(255, 255, 255, 0.14);
			color: #cbd5e1;
			padding: 0.75rem 1rem;
			border-radius: 10px;
			font-size: 0.88rem;
			font-weight: 600;
			text-align: center;
		}
		.inst-step.active {
			background: var(--accent);
			border-color: #38bdf8;
			color: #ffffff;
		}
		.inst-step.done {
			background: rgba(5, 150, 105, 0.25);
			border-color: rgba(52, 211, 153, 0.5);
			color: #a7f3d0;
		}
		.inst-card {
			background: var(--card);
			border-radius: 14px;
			padding: 2rem;
			box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
		}
		.inst-card h2 {
			margin: 0 0 0.4rem;
			color: var(--primary);
			font-size: 1.4rem;
		}
		.inst-sub {
			margin: 0 0 1.5rem;
			color: var(--muted);
			font-size: 0.92rem;
		}
		.check-list {
			list-style: none;
			padding: 0;
			margin: 0 0 1.5rem;
		}
		.check-item {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 0.75rem 1rem;
			border: 1px solid var(--border);
			border-radius: 8px;
			margin-bottom: 0.55rem;
			font-size: 0.92rem;
		}
		.status-pill {
			padding: 0.2rem 0.65rem;
			border-radius: 999px;
			font-weight: 700;
			font-size: 0.78rem;
		}
		.status-ok { background: #d1fae5; color: #065f46; }
		.status-err { background: #fee2e2; color: #991b1b; }
		.form-grid {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 1rem;
			margin-bottom: 1.25rem;
		}
		.form-group {
			display: flex;
			flex-direction: column;
			gap: 0.35rem;
		}
		.form-group.full {
			grid-column: 1 / -1;
		}
		label {
			font-size: 0.86rem;
			font-weight: 700;
			color: #334155;
		}
		input[type="text"],
		input[type="password"],
		input[type="email"],
		input[type="number"],
		select {
			padding: 0.62rem 0.8rem;
			border: 1px solid #cbd5e1;
			border-radius: 7px;
			font-size: 0.94rem;
			font-family: inherit;
		}
		.port-presets {
			display: flex;
			gap: 0.4rem;
			margin-top: 0.25rem;
		}
		.port-btn {
			background: #f1f5f9;
			border: 1px solid #cbd5e1;
			border-radius: 5px;
			padding: 0.2rem 0.55rem;
			font-size: 0.76rem;
			cursor: pointer;
			color: #334155;
		}
		.port-btn:hover { background: #e2e8f0; }
		.section-divider {
			border: none;
			border-top: 1px solid var(--border);
			margin: 1.5rem 0;
		}
		.btn-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 1rem;
			flex-wrap: wrap;
			margin-top: 1.5rem;
		}
		.btn {
			display: inline-flex;
			align-items: center;
			gap: 0.4rem;
			background: var(--primary);
			color: #ffffff;
			border: none;
			padding: 0.72rem 1.4rem;
			border-radius: 8px;
			font-weight: 700;
			font-size: 0.94rem;
			cursor: pointer;
			text-decoration: none;
		}
		.btn:hover { background: var(--accent); }
		.btn-secondary {
			background: #e2e8f0;
			color: #1e293b;
		}
		.btn-secondary:hover { background: #cbd5e1; }
		.alert {
			padding: 0.85rem 1rem;
			border-radius: 8px;
			margin-bottom: 1.25rem;
			font-size: 0.9rem;
		}
		.alert-info { background: #e0f2fe; color: #075985; border: 1px solid #bae6fd; }
		.alert-err { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
		.alert-ok { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
		.cred-table {
			width: 100%;
			border-collapse: collapse;
			margin: 1rem 0;
			font-size: 0.88rem;
		}
		.cred-table th, .cred-table td {
			border: 1px solid var(--border);
			padding: 0.55rem 0.75rem;
			text-align: left;
		}
		.cred-table th { background: #f8fafc; }
		@media (max-width: 640px) {
			.form-grid, .inst-steps { grid-template-columns: 1fr; }
		}
	</style>
</head>
<body>
<div class="inst-wrap">
	<header class="inst-header">
		<span class="inst-badge">UNSM &middot; Ingenier&iacute;a Web &middot; Grupo 1</span>
		<h1>Instalador de BlogCMS (WordPress)</h1>
		<p>Asistente de configuraci&oacute;n, base de datos, m&oacute;dulos, plugins y temas</p>
	</header>

	<div class="inst-steps">
		<div class="inst-step <?php echo $step === 1 ? 'active' : 'done'; ?>">1. Diagn&oacute;stico del Sistema</div>
		<div class="inst-step <?php echo $step === 2 ? 'active' : ( $step > 2 ? 'done' : '' ); ?>">2. Base de Datos y Sitio</div>
		<div class="inst-step <?php echo $step === 4 ? 'active' : ''; ?>">3. Instalaci&oacute;n Completada</div>
	</div>

	<div class="inst-card">
		<?php if ( $step === 1 ) : ?>
			<h2>Paso 1: Verificaci&oacute;n de Requisitos del Servidor</h2>
			<p class="inst-sub">Comprobando extensiones PHP, permisos de archivos y paquetes del proyecto antes de instalar.</p>

			<?php if ( $has_config ) : ?>
				<div class="alert alert-info">
					<strong>Instalaci&oacute;n detectada:</strong> Ya existe un archivo <code>wp-config.php</code> activo. Puedes continuar para reconfigurar la conexi&oacute;n, cambiar el tema inicial o restaurar la base de datos desde el respaldo SQL, o ir directo al <a href="<?php echo htmlspecialchars( $base_url, ENT_QUOTES, 'UTF-8' ); ?>/">sitio web</a>.
				</div>
			<?php endif; ?>

			<ul class="check-list">
				<?php foreach ( $reqs['items'] as $item ) : ?>
					<li class="check-item">
						<span><?php echo htmlspecialchars( $item['label'], ENT_QUOTES, 'UTF-8' ); ?></span>
						<span class="status-pill <?php echo $item['ok'] ? 'status-ok' : 'status-err'; ?>">
							<?php echo $item['ok'] ? 'OK' : 'FALTA'; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="btn-row">
				<a class="btn btn-secondary" href="?step=1">Volver a comprobar</a>
				<?php if ( $reqs['ready'] ) : ?>
					<a class="btn" href="?step=2">Continuar al Paso 2 &rarr;</a>
				<?php else : ?>
					<span style="color:var(--err);font-weight:700;">Habilita las extensiones faltantes en php.ini para continuar.</span>
				<?php endif; ?>
			</div>

		<?php elseif ( $step === 2 ) : ?>
			<h2>Paso 2: Conexi&oacute;n MySQL y Configuraci&oacute;n del CMS</h2>
			<p class="inst-sub">Define los par&aacute;metros de tu servidor de base de datos y las opciones iniciales de BlogCMS.</p>

			<?php if ( ! empty( $error_msg ) ) : ?>
				<div class="alert alert-err"><?php echo htmlspecialchars( $error_msg, ENT_QUOTES, 'UTF-8' ); ?></div>
			<?php endif; ?>

			<div id="db-test-result" style="display:none;" class="alert"></div>

			<form method="post" action="?step=2" id="install-form">
				<input type="hidden" name="run_install" value="1">

				<div class="form-grid">
					<div class="form-group full">
						<label for="db_engine">Motor de Base de Datos Detectado Autom&aacute;ticamente</label>
						<select id="db_engine" name="db_engine" onchange="onSelectDetectedEngine(this.value)">
							<?php foreach ( $engines as $ekey => $einfo ) : ?>
								<option value="<?php echo htmlspecialchars( $ekey, ENT_QUOTES, 'UTF-8' ); ?>"
									data-host="<?php echo htmlspecialchars( $einfo['host'], ENT_QUOTES, 'UTF-8' ); ?>"
									data-port="<?php echo (int) $einfo['port']; ?>"
									data-user="<?php echo htmlspecialchars( $einfo['user'], ENT_QUOTES, 'UTF-8' ); ?>">
									<?php echo htmlspecialchars( $einfo['name'] . ' [' . $einfo['host'] . ':' . $einfo['port'] . '] — ' . $einfo['status'], ENT_QUOTES, 'UTF-8' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="form-group">
						<label for="db_host">Servidor (Host)</label>
						<input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars( $_POST['db_host'] ?? '127.0.0.1', ENT_QUOTES, 'UTF-8' ); ?>" required>
					</div>

					<div class="form-group">
						<label for="db_port">Puerto</label>
						<input type="number" id="db_port" name="db_port" value="<?php echo htmlspecialchars( $_POST['db_port'] ?? '3306', ENT_QUOTES, 'UTF-8' ); ?>" required>
						<div class="port-presets">
							<button type="button" class="port-btn" onclick="selectPreset('mysql_local')">3306 (MySQL Local)</button>
							<button type="button" class="port-btn" onclick="selectPreset('mysql_xampp')">3307 (XAMPP MySQL)</button>
							<button type="button" class="port-btn" onclick="selectPreset('pgsql')">5432 (PostgreSQL)</button>
						</div>
					</div>

					<div class="form-group">
						<label for="db_user">Usuario de Base de Datos</label>
						<input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars( $_POST['db_user'] ?? 'root', ENT_QUOTES, 'UTF-8' ); ?>" required>
					</div>

					<div class="form-group">
						<label for="db_pass">Contrase&ntilde;a de Base de Datos</label>
						<input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars( $_POST['db_pass'] ?? '', ENT_QUOTES, 'UTF-8' ); ?>" placeholder="Dejar vacio si root no tiene clave">
					</div>

					<div class="form-group">
						<label for="db_name">Nombre de la Base de Datos</label>
						<input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars( $_POST['db_name'] ?? 'blogcms_wp', ENT_QUOTES, 'UTF-8' ); ?>" required>
					</div>

					<div class="form-group">
						<label for="db_mode">Modo de Inicializaci&oacute;n SQL</label>
						<select id="db_mode" name="db_mode">
							<option value="import" selected>Importar database/blogcms_wp.sql (Recomendado)</option>
							<option value="keep">Conservar tablas actuales en la BD</option>
						</select>
					</div>
				</div>

				<button type="button" class="btn btn-secondary" onclick="testDbConnection()" style="font-size:0.85rem;padding:0.5rem 1rem;">
					Probar conexi&oacute;n MySQL
				</button>

				<hr class="section-divider">

				<div class="form-grid">
					<div class="form-group">
						<label for="site_title">T&iacute;tulo del Sitio</label>
						<input type="text" id="site_title" name="site_title" value="<?php echo htmlspecialchars( $_POST['site_title'] ?? 'Bloc-FISI', ENT_QUOTES, 'UTF-8' ); ?>" required>
					</div>

					<div class="form-group">
						<label for="site_url">URL del Sitio</label>
						<input type="text" id="site_url" name="site_url" value="<?php echo htmlspecialchars( $_POST['site_url'] ?? $base_url, ENT_QUOTES, 'UTF-8' ); ?>" required>
					</div>

					<div class="form-group full">
						<label for="theme_slug">Tema Inicial Activo (Desarrollados desde cero)</label>
						<select id="theme_slug" name="theme_slug">
							<option value="magazine">Magazine &mdash; 3 columnas con portadas grandes</option>
							<option value="business">Business &mdash; Corporativo con Hero Banner</option>
							<option value="portfolio">Portfolio &mdash; Grilla de proyectos con hover</option>
							<option value="minimal-blog">Minimal Blog &mdash; Clean y tipogr&aacute;fico</option>
						</select>
					</div>

					<div class="form-group">
						<label for="admin_user">Usuario Administrador</label>
						<input type="text" id="admin_user" name="admin_user" value="<?php echo htmlspecialchars( $_POST['admin_user'] ?? 'BrayamAF', ENT_QUOTES, 'UTF-8' ); ?>" required>
					</div>

					<div class="form-group">
						<label for="admin_pass">Nueva Contrase&ntilde;a Admin (Opcional)</label>
						<input type="password" id="admin_pass" name="admin_pass" placeholder="Dejar vacio para mantener la del respaldo SQL">
					</div>
				</div>

				<div class="btn-row">
					<a class="btn btn-secondary" href="?step=1">&larr; Volver al Paso 1</a>
					<button type="submit" class="btn">Instalar BlogCMS Ahora &rarr;</button>
				</div>
			</form>

			<script>
			function onSelectDetectedEngine(val) {
				var sel = document.getElementById('db_engine');
				var opt = sel.options[sel.selectedIndex];
				if (!opt) return;
				document.getElementById('db_host').value = opt.getAttribute('data-host') || '127.0.0.1';
				document.getElementById('db_port').value = opt.getAttribute('data-port') || '3306';
				document.getElementById('db_user').value = opt.getAttribute('data-user') || 'root';
			}
			function selectPreset(key) {
				var sel = document.getElementById('db_engine');
				sel.value = key;
				onSelectDetectedEngine(key);
			}
			function testDbConnection() {
				var box = document.getElementById('db-test-result');
				box.style.display = 'block';
				box.className = 'alert alert-info';
				box.textContent = 'Verificando conexion MySQL...';
				var form = document.getElementById('install-form');
				var data = new FormData(form);
				fetch('?action=test_db', { method: 'POST', body: data })
					.then(function(r) { return r.json(); })
					.then(function(res) {
						box.className = res.ok ? 'alert alert-ok' : 'alert alert-err';
						box.textContent = res.message;
					})
					.catch(function(e) {
						box.className = 'alert alert-err';
						box.textContent = 'Error al probar conexion: ' + e;
					});
			}
			</script>

		<?php elseif ( $step === 4 ) : ?>
			<h2>&iexcl;Instalaci&oacute;n de BlogCMS Completada!</h2>
			<p class="inst-sub">El gestor de contenidos, la base de datos, los 6 m&oacute;dulos, los 6 plugins y los 4 temas est&aacute;n listos.</p>

			<div class="alert alert-ok">
				<strong>Todo configurado correctamente:</strong>
				<ul style="margin:0.5rem 0 0 1.2rem;padding:0;">
					<?php foreach ( $install_log as $line ) : ?>
						<li><?php echo htmlspecialchars( $line, ENT_QUOTES, 'UTF-8' ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<h3 style="font-size:1rem;color:var(--primary);margin-top:1.5rem;">Cuentas configuradas por Rol (M&oacute;dulo Usuarios y Roles)</h3>
			<table class="cred-table">
				<thead>
					<tr>
						<th>Rol</th>
						<th>Usuario</th>
						<th>Contrase&ntilde;a</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong>Administrador</strong></td>
						<td><code><?php echo htmlspecialchars( $admin_user ?? 'BrayamAF', ENT_QUOTES, 'UTF-8' ); ?></code></td>
						<td><?php echo ! empty( $admin_pass ) ? '<em>(La que acabas de definir)</em>' : '<code>ArTpv&amp;e(N1)M0K4wLx</code>'; ?></td>
					</tr>
					<tr>
						<td><strong>Editor</strong></td>
						<td><code>editor_fisi</code></td>
						<td><code>EditorFisi2026!</code></td>
					</tr>
					<tr>
						<td><strong>Autor</strong></td>
						<td><code>autor_fisi</code></td>
						<td><code>AutorFisi2026!</code></td>
					</tr>
				</tbody>
			</table>

			<div class="btn-row">
				<a class="btn btn-secondary" href="<?php echo htmlspecialchars( $site_url ?? $base_url, ENT_QUOTES, 'UTF-8' ); ?>/wp-admin/">Ir al Panel de Administraci&oacute;n (/wp-admin)</a>
				<a class="btn" href="<?php echo htmlspecialchars( $site_url ?? $base_url, ENT_QUOTES, 'UTF-8' ); ?>/">Ver Sitio Web &rarr;</a>
			</div>
		<?php endif; ?>
	</div>
</div>
</body>
</html>
