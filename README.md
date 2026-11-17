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

## Puesta en Marcha Local (Con Instalador Ejecutable o Web)

### Qué necesita la otra persona en su PC
1. **Windows 10/11** (ya incluye `.NET Framework 4.x`, no requiere instalar nada extra para el `.exe`).
2. **XAMPP** instalado en `C:\xampp` (para Apache y PHP).
3. **Un motor de Base de Datos** (el instalador los detecta automáticamente):
   - **MySQL / MariaDB de XAMPP**, o
   - **MySQL Server local** (`3306`), o
   - **PostgreSQL** (`5432`).

### Opción A: Instalación en 1 clic con el Ejecutable (`Instalador_BlogCMS.exe`)
1. Clonar o descargar el repositorio en cualquier carpeta:
   ```bash
   git clone https://github.com/MrBrayam/Cms_Web_2026.git
   ```
2. Ejecutar **[`Instalador_BlogCMS.exe`](Instalador_BlogCMS.exe)** con doble clic.
3. En la ventana del instalador:
   - Elegir el **motor de base de datos** detectado (MySQL Local, MySQL de XAMPP o PostgreSQL) e ingresar la contraseña (en XAMPP por defecto va vacía).
   - Elegir el **tema inicial** (`magazine`, `business`, `portfolio` o `minimal-blog`) y hacer clic en **"Instalar BlogCMS Ahora"**.
   - El ejecutable habilita automáticamente `zip`, `gd` e `intl` en `C:\xampp\php\php.ini`, registra el VirtualHost `http://blogcms.localhost` en Apache apuntando a la carpeta clonada, inicia Apache, crea la base de datos `blogcms_wp`, importa [`database/blogcms_wp.sql`](database/blogcms_wp.sql) y genera `wp-config.php` + `.htaccess`.
4. Hacer clic en **"Abrir Web"** o **"Admin"** desde el propio ejecutable.

### Opción B: Instalación en Linux / Kubuntu (`instalador_linux.sh`)
1. Si aún no tienes PHP/MySQL o XAMPP (`/opt/lampp`) en Kubuntu:
   ```bash
   sudo apt update && sudo apt install -y php php-mysql php-zip php-gd php-intl php-curl php-mbstring php-xml mariadb-server
   ```
2. Clonar el repositorio y ejecutar el instalador para Linux/Kubuntu (usa ventana gráfica `kdialog` de KDE Plasma o terminal interactiva, y detecta automáticamente MySQL/MariaDB local `3306`, XAMPP `/opt/lampp` y PostgreSQL `5432`):
   ```bash
   git clone https://github.com/MrBrayam/Cms_Web_2026.git
   cd Cms_Web_2026
   chmod +x instalador_linux.sh
   ./instalador_linux.sh
   ```

### Opción C: Asistente Web Multiplataforma (`instalador.php`)
- Funciona igual en **Windows y Kubuntu/Linux**: abre `http://blogcms.localhost/instalador.php` (o `http://localhost:8080/instalador.php`) y sigue los 3 pasos visuales.
