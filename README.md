# Cms_Web_2026 — BlogCMS (Tipo: WordPress)

Proyecto **BlogCMS – Grupo 1 (Tipo: WordPress)** para el curso **Ingeniería Web (UNSM)** — *Docente: Ing. Mg. Richard Injante Ore*.

## Módulos Requeridos Implementados
- **Artículos y categorías:** gestión de entradas, categorías y etiquetas.
- **Páginas estáticas:** *Nosotros*, *Galería de Álbumes* y *Contacto*.
- **Comentarios con moderación:** aprobación manual habilitada desde el panel.
- **Usuarios y roles (`admin` / `editor` / `autor`):** cuentas configuradas para pruebas por rol.
- **Panel de administración:** configurado en español (`es_ES`).
- **Menú dinámico configurable:** asignado a las ubicaciones `primary` y `footer` en los 4 temas.

## Plugins Gratuitos Instalados
1. **SEO optimizer:** Yoast SEO (`wordpress-seo`) — meta tags, OpenGraph y sitemap XML (`/sitemap_index.xml`).
2. **Galería de imágenes con álbumes:** NextGEN Gallery (`nextgen-gallery`).
3. **Formulario de contacto personalizable:** WPForms Lite (`wpforms-lite`).
4. **Botones de compartir en redes sociales:** AddToAny Share Buttons (`add-to-any`).
5. **Posts relacionados por etiquetas:** YARPP (`yet-another-related-posts-plugin`).
6. **Contador de visitas y estadísticas:** WP Statistics (`wp-statistics`).

## Temas Desarrollados Desde Cero (`wp-content/themes/`)
- **`minimal-blog`**: diseño limpio y tipográfico de una columna.
- **`magazine`**: diseño editorial de 3 columnas con portadas grandes.
- **`portfolio`**: grilla de proyectos con filtro por categoría y efecto `hover`.
- **`business`**: diseño corporativo con *Hero Banner* personalizable.

> Los paquetes `.zip` de los 4 temas se encuentran en `entregables-zip/temas/` para demostrar la subida e instalación automática mediante `ZipArchive` desde *Apariencia → Temas → Subir tema*.

## Puesta en Marcha Local
1. Clonar el repositorio y apuntar el servidor web (Apache/PHP 8.2+ con extensiones `mysqli`, `zip`, `gd` e `intl` activas) a la raíz del proyecto.
2. Importar el respaldo de base de datos [`database/blogcms_wp.sql`](database/blogcms_wp.sql) en MySQL/MariaDB.
3. Copiar `wp-config-sample.php` a `wp-config.php`, configurar `DB_NAME` (`blogcms_wp`), `DB_USER`, `DB_PASSWORD`, `DB_HOST` y añadir `define( 'FS_METHOD', 'direct' );`.
4. Si usas un dominio local distinto a `http://blogcms.localhost`, actualiza `siteurl` y `home` en la tabla `wp_options`.
