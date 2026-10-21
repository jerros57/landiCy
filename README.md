# LandiCy · Crea páginas, comprende tu hosting y publica sin depender de un CMS

**Software libre · GNU AGPLv3-or-later · v0.1.1, prototipo comunitario**

LandiCy es una propuesta de constructor web **multisitio** sin dependencias de npm ni contenedores. Utiliza PHP y MySQL **solo en el panel administrativo** y exporta sitios públicos basados en HTML, CSS, JavaScript y JSON estáticos. Este repositorio es una primera implementación funcional de la [especificación original](docs/especificacion-original.txt), no un producto final auditado.

## Qué incluye esta primera versión

- Inicio de sesión seguro básico, contraseñas con hash y CSRF.
- Superadministrador inicial y soporte de base de datos para permisos por proyecto.
- Creación de sitios con paleta de dos colores y 5 bloques editables: portada, texto, características, CTA y pie de página.
- Guardado de borradores y revisiones; el superadministrador aprueba la compilación.
- Vista previa privada.
- Publicación estática en `storage/exports/<slug>/`, con `index.html`, `css/site.css`, `js/site.js`, `data/content.json` y `assets/`.
- Descarga ZIP del sitio, listo para subir a cualquier hosting estático.
- **Reconocimiento de código HTML/CSS/JS** mediante carpeta local autorizada o ZIP, con listado de extensiones, enlaces a hojas de estilos y scripts, contenido interno CSS/JS, tamaños, huellas SHA-256 e inspector solo lectura.

**No hace todavía:** importar automáticamente un sitio existente y convertirlo en bloques editables; editar CSS/JS del hosting en vivo; sincronizar archivos por FTP/SFTP; publicar directamente en un dominio; gestionar usuarios desde el navegador; arrastrar bloques con el ratón; editor WYSIWYG; página multipágina. Consulta [Hoja de ruta](docs/HOJA_DE_RUTA.md).

## Requisitos

- PHP **8.3 o superior** (recomendado 8.4/8.5 con últimos parches).
- Extensiones PHP: `pdo_mysql`, `json`, `session`; `zip` para analizar y descargar ZIP.
- MySQL 8 o versión compatible de MariaDB con JSON, credenciales de escritura y tablas InnoDB.
- Hosting con HTTPS. Para instalar sin consola necesitas crear el administrador de forma segura en un entorno que sí tenga PHP CLI, o desarrollar el instalador previsto en v1.0.
- Permisos de lectura sobre los sitios a inspeccionar, y lectura/escritura sobre `storage/` privado.

## Instalación

**En cPanel habitual:** consulta primero [Guía de cPanel sin exponer archivos privados](docs/INSTALACION_CPANEL.md). La versión de despliegue `LandiCy_v0.1.1_cPanel_AGPL.zip` incluye dos carpetas con las rutas ya ajustadas.


1. Descomprime LandiCy en una carpeta del servidor, **fuera del directorio público**. El subdominio del panel (por ejemplo, `admin.tudominio.com`) debe apuntar a `LandiCy-MVP/public/`, **no** al directorio raíz del proyecto.
2. Crea una base de datos en cPanel o tu panel de hosting y ejecuta [database/schema.sql](database/schema.sql) en esa base (mediante phpMyAdmin, por ejemplo). No requiere `CREATE DATABASE`.
3. Copia `config.example.php` a `config.php` y ajusta `db.dsn`, `db.username`, `db.password` y `storage`. Evita incluir `config.php` en Git.
4. Desde una terminal del hosting, ejecuta:

   ```bash
   php bin/create-admin.php admin@ejemplo.org "Administrador"
   ```

   Se solicitará una contraseña de al menos 12 caracteres sin pasarla como argumento de consola.
5. Abre `https://admin.tudominio.com/?action=login`, inicia sesión y crea un sitio.
6. Abre el editor, modifica sus bloques y guarda un borrador. Revisa la vista previa, pulsa **Aprobar y compilar** y después **Descargar ZIP publicado**.
7. Descomprime **solo los archivos del ZIP exportado** en la carpeta pública del sitio, por ejemplo `public_html/` de ese dominio. No subas el código PHP del panel al sitio público. El alojamiento público **no necesita PHP**.

> En hosting compartido, cPanel puede restringir el `document root` a `public_html`. En ese caso **no copies el proyecto entero a la web**: utiliza el ZIP especial cPanel preparado con `landicy-private/` en tu directorio de usuario y `public_html/landicy-admin/` como raíz web. Tampoco basta con copiar `public/` sin adaptar la ruta del bootstrap. Detalles en [INSTALACION_CPANEL.md](docs/INSTALACION_CPANEL.md).

## Ejemplo: identificar HTML/CSS/JS de una web propia

### Modo A: en el mismo hosting

En `config.php`, configura una raíz **restrictiva**, ejemplo:

```php
'scan_root' => '/home/miusuario/sitios-autorizados',
```

Asegúrate de que existe la subcarpeta `/home/miusuario/sitios-autorizados/cliente1/`, y que contiene archivos como `index.html`, `css/style.css`, `js/app.js`. En LandiCy, abre el proyecto → **Inventariar desde hosting o ZIP** → escribe `cliente1` → **Analizar carpeta**.

LandiCy enumera archivos permitidos y detecta, por ejemplo, que `index.html` hace referencia a `css/style.css` y `js/app.js`. El inspector enseña texto **sin ejecutar** el JavaScript encontrado. No toca los archivos originales.

### Modo B: otro hosting o alguien sin acceso a la carpeta

Crea un ZIP de los archivos del sitio (de tu propiedad o con permiso), abre la sección de inventario y súbelo. Se limita a **15 MB de ZIP**, 400 archivos HTML/CSS/JS y 12 MB totales de texto analizado. El ZIP se guarda en una carpeta privada para habilitar lectura posterior. **Nunca** se descomprime código sobre la web pública.

El repositorio incluye [demo/ejemplo-hosting](demo/ejemplo-hosting) para verificar el escaneo.

### Qué significa «identificar»

| Capacidad | Estado v0.1 |
|---|---|
| Detectar archivos HTML, CSS, JS y MJS | Sí |
| Inspeccionar código sin ejecutarlo | Sí |
| Identificar algunas referencias `link`/`script` en HTML | Sí, aproximado |
| Identificar estilos y scripts embebidos | Sí, por conteo aproximado |
| Distinguir qué archivo carga cada sitio real con JS dinámico | No |
| Convertir cualquier web existente a bloques que se puedan editar sin pérdidas | No |
| Modificar el hosting existente y publicar de vuelta automáticamente | No |

## Arquitectura

```text
LandiCy-MVP/
├── public/             # ÚNICO directorio expuesto por HTTP (panel PHP)
│   ├── index.php
│   └── assets/         # CSS/JS del editor del panel
├── src/                # Motor, escáner, autenticación y vistas
├── database/schema.sql
├── bin/create-admin.php
├── config.example.php  # Copiar a config.php (secreto, no público)
├── storage/            # ZIP de importación y publicaciones (privado)
├── tests/smoke.php
├── demo/
└── docs/
```

No son necesarias librerías de terceros para la capa pública. Los archivos generados funcionan de forma independiente del panel de LandiCy.

## Pruebas rápidas

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/smoke.php
```

Las pruebas sin MySQL verifican rutas seguras, reconocimiento de dependencias, escape de HTML y escaneo de la demo. Para abrir el panel necesitas MySQL real y `pdo_mysql`; se requerirán pruebas de integración, de navegador y auditoría antes de una beta pública.

## Seguridad y límites

Consulta [docs/SEGURIDAD.md](docs/SEGURIDAD.md) antes de desplegar el panel. La versión 0.1 no debe ofrecerse al público como un constructor abierto multiusuario sin endurecimiento adicional. Nunca importes ni inspecciones archivos de terceros sin autorización. Una web pública estática evita PHP/MySQL por cada visita, pero **no garantiza** ausencia total de vulnerabilidades ni un tiempo universal de carga inferior a 0,4 segundos.

## Licencia y comunidad

**GNU AGPLv3-or-later** para LandiCy. Texto completo en [LICENSE](LICENSE) y [COPYING](COPYING). Los fragmentos de plantillas originales incluidos en sitios exportados tienen un [permiso adicional](LICENSE-OUTPUT-EXCEPTION.md) para no imponer la licencia del editor sobre el contenido del usuario. Consulta [política de licencia](LICENSE-POLICY.md).

AGPLv3 obliga en determinados casos a facilitar el código de las **versiones modificadas** utilizadas por usuarios remotos (sección 13), pero no obliga a nadie a desarrollar nuevas funciones ni enviar sus mejoras al repositorio oficial. La copia anterior v0.1.0 se entregó bajo MIT y esos permisos ya otorgados no pueden retirarse retroactivamente.

Para desplegar una versión modificada de LandiCy, configura `source_url` con el enlace a la fuente correspondiente realmente desplegada. **No** publiques nunca `config.php`, `storage/` ni credenciales.

[Guía para contribuir](CONTRIBUTING.md) · [Hoja de ruta](docs/HOJA_DE_RUTA.md) · [Seguridad](docs/SEGURIDAD.md)

---

**English overview (AGPLv3):** LandiCy is a free/libre PHP/MySQL multi-site web builder that exports framework-free HTML, CSS, JS and headless JSON. This early prototype also inventories existing web source files from an approved hosting folder or an uploaded ZIP, without executing them or modifying production. Visual conversion of arbitrary websites is **not** implemented yet. Contributions and translations are welcome.
