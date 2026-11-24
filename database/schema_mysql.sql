-- BlogCMS Propio (Tipo: WordPress) — Esquema MySQL / MariaDB
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS visit_logs;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS gallery_images;
DROP TABLE IF EXISTS gallery_albums;
DROP TABLE IF EXISTS media;
DROP TABLE IF EXISTS themes;
DROP TABLE IF EXISTS plugins;
DROP TABLE IF EXISTS menus;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS pages;
DROP TABLE IF EXISTS post_tags;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS options;

CREATE TABLE options (
    option_name VARCHAR(120) PRIMARY KEY,
    option_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'author',
    failed_attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    excerpt TEXT,
    content LONGTEXT NOT NULL,
    featured_image VARCHAR(255) DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'publish',
    author_id INT NOT NULL,
    category_id INT NULL,
    meta_title VARCHAR(220) DEFAULT '',
    meta_description VARCHAR(320) DEFAULT '',
    views INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_posts_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_posts_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    CONSTRAINT fk_pt_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_pt_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    content LONGTEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'publish',
    author_id INT NOT NULL,
    meta_description VARCHAR(320) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pages_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    author_name VARCHAR(100) NOT NULL,
    author_email VARCHAR(120) NOT NULL,
    content TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    url VARCHAR(255) NOT NULL,
    target_type VARCHAR(30) NOT NULL DEFAULT 'custom',
    target_id INT NULL,
    location VARCHAR(30) NOT NULL DEFAULT 'primary',
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plugins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(140) NOT NULL,
    version VARCHAR(30) NOT NULL DEFAULT '1.0.0',
    description TEXT,
    author VARCHAR(100) DEFAULT 'BlogCMS',
    entry_file VARCHAR(120) NOT NULL DEFAULT 'plugin.php',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    settings_json TEXT,
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE themes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    version VARCHAR(30) NOT NULL DEFAULT '1.0.0',
    description TEXT,
    author VARCHAR(100) DEFAULT 'BlogCMS',
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(80) NOT NULL,
    file_size INT NOT NULL DEFAULT 0,
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gallery_albums (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL UNIQUE,
    description TEXT,
    cover_image VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gallery_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    album_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    caption VARCHAR(255) DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_gal_album FOREIGN KEY (album_id) REFERENCES gallery_albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(140) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    extra_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE visit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NULL,
    page_path VARCHAR(255) NOT NULL,
    ip_hash VARCHAR(64) NOT NULL,
    user_agent VARCHAR(255) DEFAULT '',
    visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(80) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos iniciales
INSERT INTO options (option_name, option_value) VALUES
('site_title', 'Bloc-FISI'),
('site_tagline', 'Gestor de Contenido Propio — UNSM Ingenieria Web'),
('site_url', 'http://blogcms.localhost'),
('active_theme', 'magazine'),
('comment_moderation', '1'),
('posts_per_page', '9'),
('hero_title', 'Soluciones Digitales y Gestion de Contenido Propio'),
('hero_subtitle', 'BlogCMS construido desde cero con arquitectura de hooks, plugins .zip con plugin.json, temas intercambiables y seguridad OWASP.');

INSERT INTO users (id, username, email, password_hash, display_name, role) VALUES
(1, 'admin', 'admin@blogcms.localhost', '$2y$10$WJYwjpGe5kOjURFGQmQeZuEMiKgaXdMLHVd6lkT2TtiudzFK3qguC', 'Administrador FISI', 'admin'),
(2, 'editor_fisi', 'editor@blogcms.localhost', '$2y$10$znQIiPo2g6qY4L0NKmJY0uvdo.ZgLfIlxsXxhTIq5pbdGM1CdsOti', 'Carlos Mendoza (Editor)', 'editor'),
(3, 'autor_fisi', 'autor@blogcms.localhost', '$2y$10$x/ycQ8tzCkv00muI69F7MeZWvplmS3sas0t/x34u8p5OgA2sU8/o6', 'Lucia Paredes (Autora)', 'author');

INSERT INTO categories (id, name, slug, description) VALUES
(1, 'Ingenieria Web', 'ingenieria-web', 'Fundamentos, protocolos y buenas practicas de desarrollo web.'),
(2, 'Desarrollo Frontend', 'desarrollo-frontend', 'Maquetacion responsiva, CSS Grid y experiencia de usuario.'),
(3, 'Arquitectura CMS', 'arquitectura-cms', 'Diseno de nucleo modular, hooks, plugins y plantillas.'),
(4, 'Bases de Datos', 'bases-de-datos', 'Modelado relacional y consultas parametrizadas en MySQL y PostgreSQL.'),
(5, 'Proyectos UNSM', 'proyectos-unsm', 'Casos practicos y portafolio academico de la FISI.');

INSERT INTO tags (id, name, slug) VALUES
(1, 'php', 'php'),
(2, 'cms', 'cms'),
(3, 'seo', 'seo'),
(4, 'seguridad', 'seguridad'),
(5, 'mysql', 'mysql'),
(6, 'postgresql', 'postgresql'),
(7, 'plugins', 'plugins'),
(8, 'temas', 'temas');

INSERT INTO posts (id, title, slug, excerpt, content, featured_image, status, author_id, category_id, meta_title, meta_description, views) VALUES
(1, 'Arquitectura Modular de BlogCMS Desde Cero', 'arquitectura-modular-de-blogcms', 'Como disenar un gestor de contenido propio inspirado en WordPress con nucleo desacoplado, router limpio y capa PDO.', '<p>Un gestor de contenido propio separa claramente el enrutamiento HTTP, la capa de datos PDO, el sistema de autenticacion RBAC y los gestores de plugins y temas.</p><h2>Sistema de Hooks</h2><p>Mediante acciones y filtros (<code>addAction</code>, <code>applyFilters</code>), cada plugin extiende el CMS sin modificar el codigo del nucleo.</p>', '/uploads/covers/cover-1.png', 'publish', 1, 3, 'Arquitectura Modular de BlogCMS', 'Conoce la arquitectura PHP modular de BlogCMS con hooks, PDO dual y temas.', 48),
(2, 'Subida Automatica de Plugins .zip con ZipArchive y plugin.json', 'subida-automatica-de-plugins-zip', 'Implementacion exacta del flujo de instalacion de plugins mediante validacion PHP, extraccion ZipArchive y registro en BD.', '<p>Cuando el administrador sube un archivo <code>.zip</code> desde el panel, PHP valida el tamano, formato y evita ataques zip-slip antes de extraerlo a <code>/plugins/nombre/</code>.</p><p>Luego <strong>PluginManager</strong> lee <code>plugin.json</code>, registra los metadatos en la base de datos y lo carga en cada peticion HTTP.</p>', '/uploads/covers/cover-2.png', 'publish', 2, 3, 'Instalacion de Plugins .zip con ZipArchive', 'Flujo completo de subida e instalacion automatica de plugins en BlogCMS.', 39),
(3, 'Motor de Temas Intercambiables con theme.json', 'motor-de-temas-intercambiables', 'Como funcionan los cuatro temas desarrollados desde cero (Minimal Blog, Magazine, Portfolio y Business) sobre un mismo CMS.', '<p>Cada tema reside en su carpeta dentro de <code>/themes/</code> con su manifiesto <code>theme.json</code>, hoja de estilos propia y plantillas independientes.</p><p>Al cambiar de tema en <em>Apariencia &rarr; Temas</em>, todo el sitio adopta el nuevo diseno manteniendo intactos articulos, menus y plugins.</p>', '/uploads/covers/cover-3.png', 'publish', 3, 2, 'Motor de Temas en BlogCMS', 'Cuatro temas propios intercambiables desde el panel de administracion.', 31),
(4, 'Acceso Dual a MySQL y PostgreSQL con PDO Preparado', 'acceso-dual-mysql-postgresql-pdo', 'Uso de sentencias preparadas en PHP PDO para soportar tanto MySQL/MariaDB como PostgreSQL previniendo inyeccion SQL.', '<p>Las consultas concatenadas representan un riesgo critico (OWASP A03). Al utilizar marcadores preparados en <strong>PDO</strong>, el motor distingue claramente entre instrucciones SQL y datos del usuario.</p><p>BlogCMS detecta automaticamente MySQL Local (3306), MySQL de XAMPP (3307) y PostgreSQL (5432).</p>', '/uploads/covers/cover-4.png', 'publish', 1, 4, 'PDO Dual MySQL y PostgreSQL', 'Consultas parametrizadas y soporte dual MySQL/PostgreSQL en BlogCMS.', 27),
(5, 'Mitigacion OWASP Top 10: CSRF, XSS, BCrypt y Cabeceras HTTP', 'mitigacion-owasp-top-10-php', 'Defensa en profundidad en aplicaciones web PHP: tokens CSRF, codificacion de salida, cookies HttpOnly y Content-Security-Policy.', '<p>La seguridad se construye por capas: contrasenas con <code>PASSWORD_BCRYPT</code>, cookies de sesion <code>HttpOnly</code> y <code>SameSite=Lax</code>, validacion de propiedad en el servidor (anti-IDOR) y seis cabeceras HTTP de proteccion.</p>', '/uploads/covers/cover-5.png', 'publish', 2, 1, 'Seguridad OWASP Top 10 en PHP', 'Practicas de seguridad aplicadas de la Unidad IX en BlogCMS.', 42),
(6, 'Control de Acceso Basado en Roles: Admin, Editor y Autor', 'control-de-acceso-roles-admin-editor-autor', 'Matriz de permisos reales en servidor para separar administracion total, moderacion editorial y autoria individual.', '<p>En BlogCMS el <strong>Autor</strong> solo crea y edita sus propios articulos, el <strong>Editor</strong> administra articulos, categorias, paginas y comentarios globales, y el <strong>Administrador</strong> controla usuarios, menus, plugins y temas.</p>', '/uploads/covers/cover-6.png', 'publish', 1, 1, 'Roles Admin Editor Autor', 'Gestion de usuarios y permisos RBAC en BlogCMS.', 22),
(7, 'Diseno Editorial Magazine en 3 Columnas con Portadas Grandes', 'diseno-magazine-3-columnas', 'Maquetacion moderna con CSS Grid, articulo destacado principal y tarjetas responsivas de portadas 16:10.', '<p>El tema <strong>Magazine</strong> destaca la publicacion mas reciente en formato horizontal y organiza las siguientes entregas en una grilla limpia de tres columnas adaptable a dispositivos moviles.</p>', '/uploads/covers/cover-7.png', 'publish', 3, 2, 'Tema Magazine 3 Columnas', 'Arquitectura CSS Grid del tema Magazine de BlogCMS.', 19),
(8, 'Portafolio Interactivo UNSM: Grilla de Proyectos con Hover', 'portafolio-grilla-proyectos-hover', 'Presentacion visual de proyectos academicos con filtros por categoria y transiciones CSS sobre tarjetas.', '<p>El tema <strong>Portfolio</strong> transforma las entradas en una galeria interactiva donde al pasar el cursor aparece una capa informativa con categoria, resumen y acceso directo al detalle.</p>', '/uploads/covers/cover-8.png', 'publish', 2, 5, 'Tema Portfolio con Hover', 'Grilla interactiva de proyectos academicos en BlogCMS.', 25),
(9, 'SEO Tecnico, Sitemap XML y Posts Relacionados por Etiquetas', 'seo-sitemap-posts-relacionados', 'Integracion de meta etiquetas OpenGraph, generacion automatica de sitemap.xml y recomendacion por tags compartidos.', '<p>Gracias a los plugins modulares de BlogCMS, cada articulo recibe meta tags dinamicos, botones de compartir, conteo de visitas y sugerencias automaticas basadas en etiquetas en comun.</p>', '/uploads/covers/cover-9.png', 'publish', 3, 5, 'SEO y Posts Relacionados', 'Funcionamiento conjunto de los plugins SEO, Related Posts y Visit Counter.', 34);

INSERT INTO post_tags (post_id, tag_id) VALUES
(1,1),(1,2),(1,7),(1,8),
(2,1),(2,2),(2,7),(2,4),
(3,2),(3,8),(3,1),
(4,1),(4,5),(4,6),(4,4),
(5,1),(5,4),(5,2),
(6,4),(6,2),(6,1),
(7,8),(7,2),
(8,8),(8,3),
(9,3),(9,7),(9,2);

INSERT INTO pages (id, title, slug, content, status, author_id, meta_description) VALUES
(1, 'Nosotros', 'nosotros', '<p><strong>Bloc-FISI</strong> es un gestor de contenido propio desarrollado desde cero para el curso de Ingenieria Web (UNSM). Incluye los seis modulos base, arquitectura de plugins mediante <code>plugin.json</code> y <code>ZipArchive</code>, y cuatro temas intercambiables.</p>', 'publish', 1, 'Informacion institucional del proyecto BlogCMS - UNSM.'),
(2, 'Galeria de Albumes', 'galeria', '<p>Explora nuestra coleccion fotografica organizada por albumes mediante el plugin propio <strong>Galeria de Imagenes con Albumes</strong>:</p>[galeria]', 'publish', 1, 'Galeria fotografica organizada por albumes en BlogCMS.'),
(3, 'Contacto', 'contacto', '<p>Envianos tus consultas mediante nuestro formulario personalizable administrado por el plugin <strong>Formulario de Contacto</strong>:</p>[contacto]', 'publish', 1, 'Formulario de contacto personalizable de BlogCMS.');

INSERT INTO comments (id, post_id, author_name, author_email, content, status) VALUES
(1, 1, 'Andrea Torres', 'andrea@unsm.edu.pe', 'Excelente explicacion sobre la separacion entre nucleo, plugins y temas propios.', 'approved'),
(2, 1, 'Visitante en Cola', 'pendiente@unsm.edu.pe', 'Este comentario esta pendiente de aprobacion manual desde el panel de moderacion.', 'pending'),
(3, 2, 'Mateo Silva', 'mateo@unsm.edu.pe', 'Muy claro el flujo con ZipArchive y plugin.json siguiendo la lamina 8.', 'approved'),
(4, 5, 'Revisor Seguridad', 'seguridad@unsm.edu.pe', 'Comentario de prueba pendiente para validar la moderacion antes de publicar.', 'pending');

INSERT INTO menus (id, label, url, target_type, target_id, location, sort_order) VALUES
(1, 'Inicio', '/', 'custom', NULL, 'primary', 1),
(2, 'Nosotros', '/pagina/nosotros', 'page', 1, 'primary', 2),
(3, 'Galeria', '/pagina/galeria', 'page', 2, 'primary', 3),
(4, 'Contacto', '/pagina/contacto', 'page', 3, 'primary', 4),
(5, 'Ingenieria Web', '/categoria/ingenieria-web', 'category', 1, 'primary', 5),
(6, 'Arquitectura CMS', '/categoria/arquitectura-cms', 'category', 3, 'primary', 6),
(7, 'Inicio', '/', 'custom', NULL, 'footer', 1),
(8, 'Nosotros', '/pagina/nosotros', 'page', 1, 'footer', 2),
(9, 'Contacto', '/pagina/contacto', 'page', 3, 'footer', 3);

INSERT INTO plugins (slug, name, version, description, author, entry_file, is_active, settings_json) VALUES
('seo-optimizer', 'SEO Optimizer', '1.0.0', 'Genera meta tags SEO, OpenGraph y sitemap XML automatico en /sitemap.xml.', 'Grupo 1 UNSM', 'plugin.php', 1, '{"default_robots":"index, follow","include_pages":true}'),
('image-gallery', 'Galeria de Imagenes con Albumes', '1.0.0', 'Gestiona albumes y galerias fotograficas mediante el shortcode [galeria].', 'Grupo 1 UNSM', 'plugin.php', 1, '{"columns":3}'),
('contact-form', 'Formulario de Contacto Personalizable', '1.0.0', 'Formulario configurable con campos personalizables y bandeja de mensajes via [contacto].', 'Grupo 1 UNSM', 'plugin.php', 1, '{"fields":["name","email","subject","message"],"success_msg":"Gracias por tu mensaje. Te responderemos pronto."}'),
('social-share', 'Botones de Compartir en Redes Sociales', '1.0.0', 'Agrega botones de compartir (WhatsApp, X, Facebook, LinkedIn y copiar enlace) al final de cada articulo.', 'Grupo 1 UNSM', 'plugin.php', 1, '{"networks":["whatsapp","facebook","x","linkedin","copy"]}'),
('related-posts', 'Posts Relacionados por Etiquetas', '1.0.0', 'Muestra articulos relacionados automaticamente segun etiquetas compartidas.', 'Grupo 1 UNSM', 'plugin.php', 1, '{"limit":3}'),
('visit-counter', 'Contador de Visitas y Estadisticas', '1.0.0', 'Registra visitas unicas por sesion y muestra estadisticas detalladas en el panel.', 'Grupo 1 UNSM', 'plugin.php', 1, '{"show_badge":true}');

INSERT INTO themes (slug, name, version, description, author, is_active) VALUES
('minimal-blog', 'Minimal Blog', '1.0.0', 'Clean y tipografico: columna editorial centrada con lectura confortable.', 'Grupo 1 UNSM', 0),
('magazine', 'Magazine', '1.0.0', '3 columnas con portadas grandes, articulo destacado y barra lateral.', 'Grupo 1 UNSM', 1),
('portfolio', 'Portfolio', '1.0.0', 'Grilla de proyectos con filtro por categoria y efecto hover interactivo.', 'Grupo 1 UNSM', 0),
('business', 'Business', '1.0.0', 'Corporativo con Hero Banner a ancho completo, pilares de servicio y blog.', 'Grupo 1 UNSM', 0);

INSERT INTO gallery_albums (id, title, slug, description, cover_image) VALUES
(1, 'Campus UNSM', 'campus-unsm', 'Fotografias de la Facultad de Ingenieria de Sistemas e Informatica.', '/uploads/gallery/campus-1.png'),
(2, 'Proyectos Web FISI', 'proyectos-web-fisi', 'Capturas y arquitectura del proyecto BlogCMS.', '/uploads/gallery/proj-1.png');

INSERT INTO gallery_images (id, album_id, title, image_path, caption, sort_order) VALUES
(1, 1, 'Facultad de Ingenieria de Sistemas', '/uploads/gallery/campus-1.png', 'Sede academica UNSM Tarapoto', 1),
(2, 1, 'Laboratorio de Ingenieria Web', '/uploads/gallery/campus-2.png', 'Practicas de desarrollo web y seguridad', 2),
(3, 2, 'PluginManager con ZipArchive', '/uploads/gallery/proj-1.png', 'Instalacion modular mediante plugin.json', 1),
(4, 2, 'Temas Propios y Galeria', '/uploads/gallery/proj-2.png', 'Cuatro temas intercambiables desde el panel', 2);

INSERT INTO visit_logs (post_id, page_path, ip_hash, user_agent) VALUES
(1, '/post/arquitectura-modular-de-blogcms', 'seed1', 'Mozilla/5.0'),
(2, '/post/subida-automatica-de-plugins-zip', 'seed2', 'Mozilla/5.0'),
(5, '/post/mitigacion-owasp-top-10-php', 'seed3', 'Mozilla/5.0');

SET FOREIGN_KEY_CHECKS = 1;
