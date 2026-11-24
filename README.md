# Cms_Web_2026 — BlogCMS Propio (Tipo: WordPress, Sin WordPress)

Proyecto **BlogCMS – Grupo 1 (Tipo: WordPress)** construido **100% desde cero en PHP 8.2+ (PDO MySQL/PostgreSQL)** para el curso **Ingeniería Web (UNSM)** — *Docente: Ing. Mg. Richard Injante Ore*.

> No utiliza código ni componentes de WordPress; implementa su propio núcleo modular (`Database`, `Auth`, `Security`, `PluginManager`, `ThemeManager`, router limpio y panel administrativo completo).

## Módulos Requeridos Implementados
- **Artículos y categorías:** gestión de entradas, categorías, etiquetas e imágenes de portada.
- **Páginas estáticas:** *Nosotros*, *Galería de Álbumes* (`[galeria]`) y *Contacto* (`[contacto]`).
- **Comentarios con moderación:** aprobación manual, rechazo y eliminación desde `/admin/comments`.
- **Usuarios y roles (`admin` / `editor` / `author`):** control de acceso basado en roles y propiedad en servidor (anti-IDOR).
- **Panel de administración (`/admin`):** escritorio, gestión de contenido, medios, ajustes y registro de auditoría.
- **Menú dinámico configurable:** ubicaciones `primary` y `footer` editables desde `/admin/menus`.

## Plugins Instalables con `plugin.json` y `ZipArchive` (Lámina 8)
Cada plugin reside en `/plugins/<nombre>/` con su manifiesto `plugin.json` y puede subirse en `.zip` desde `/admin/plugins` (los paquetes listos están en `packages/plugins/`):
1. `seo-optimizer` — Meta tags SEO, OpenGraph y `/sitemap.xml` automático.
2. `image-gallery` — Galería de imágenes organizada por álbumes (`[galeria]`).
3. `contact-form` — Formulario de contacto personalizable y bandeja de mensajes (`[contacto]`).
4. `social-share` — Botones de compartir en redes sociales al final de cada artículo.
5. `related-posts` — Posts relacionados por etiquetas compartidas.
6. `visit-counter` — Contador de visitas y panel de estadísticas.

## Temas Propios Desde Cero con `theme.json` (`themes/`)
1. **`minimal-blog`** — clean y tipográfico.
2. **`magazine`** — 3 columnas con portadas grandes y artículo destacado.
3. **`portfolio`** — grilla de proyectos con filtro y efecto `hover`.
4. **`business`** — corporativo con Hero Banner personalizable.

*(Los paquetes `.zip` de los 4 temas están en `packages/themes/` para probar la subida de plantillas desde `/admin/themes`).*

## Instalación en Windows y Kubuntu (Linux)

### Windows (`Instalador_BlogCMS.exe` o `instalador.php`)
1. Tener instalado **XAMPP** en `C:\xampp` y un motor de base de datos (**MySQL Local `3306`**, **MySQL de XAMPP `3307`** o **PostgreSQL `5432`**).
2. Ejecutar **`Instalador_BlogCMS.exe`**: detecta automáticamente los 3 motores, habilita `zip`, `gd`, `intl` y `pdo_pgsql` en `php.ini`, configura `http://blogcms.localhost` en Apache, importa [`database/schema_mysql.sql`](database/schema_mysql.sql) (o [`database/schema_pgsql.sql`](database/schema_pgsql.sql)) y genera `config/config.php`.

### Kubuntu / Linux (`instalador_linux.sh`)
```bash
sudo apt update && sudo apt install -y php php-mysql php-pgsql php-zip php-gd php-mbstring php-curl mariadb-server
chmod +x instalador_linux.sh
./instalador_linux.sh
```

### Credenciales por defecto
- **Administrador:** `admin` / `AdminFisi2026!`
- **Editor:** `editor_fisi` / `EditorFisi2026!`
- **Autor:** `autor_fisi` / `AutorFisi2026!`
