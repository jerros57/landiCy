# LandiCy 0.1.1 — instalar en cPanel / hosting compartido

## A. Antes de empezar

Necesitas un dominio/subdominio con HTTPS, PHP **8.3 o superior**, extensiones `pdo_mysql`, `json`, `session` y `zip`, MySQL/MariaDB, phpMyAdmin y acceso a **Terminal/SSH** para crear el primer usuario (este prototipo todavía no tiene instalador web). Algunos proveedores desactivan Terminal: solicita que te habiliten SSH/Terminal, o crea previamente el primer usuario mediante una vía administrativa local confiable; no subas un instalador público improvisado.

**Importante:** esta versión es un prototipo. No abras registros de usuarios externos ni importes datos sensibles sin auditoría y copias de seguridad.

## B. Directorios concretos para el ZIP especial cPanel

Supongamos que tu usuario cPanel es `miusuario`. Utiliza la distribución `LandiCy_v0.1.1_cPanel_AGPL.zip`. Descomprímela en el directorio **home de tu cuenta** (`/home/miusuario/`, no dentro de `public_html/`). Quedará así:

```
/home/miusuario/
├── landicy-private/                ← archivos privados, NO visibles en Internet
│   ├── config.example.php
│   ├── src/
│   ├── bin/create-admin.php
│   ├── database/schema.sql
│   └── storage/
└── public_html/
    └── landicy-admin/             ← ÚNICA carpeta pública del panel
        ├── index.php
        └── assets/
```

El `index.php` del paquete cPanel usa esta ruta de arranque:

```php
require dirname(__DIR__, 2) . '/landicy-private/src/bootstrap.php';
```

Funciona si `landicy-admin` está **directamente bajo `public_html`**. Si tu hosting utiliza una estructura diferente, adapta la ruta del bootstrap **antes** de publicar; nunca traslades `config.php` al área pública para resolver un error.

En cPanel > **Dominios**, crea o edita un subdominio, por ejemplo `admin.tudominio.com`. Desmarca «Compartir raíz de documentos» si aparece y asigna su raíz a `public_html/landicy-admin` (la interfaz puede mostrarla sin el prefijo `public_html`). El nombre de la carpeta debe corresponder al paquete. La posibilidad de personalizarla depende del proveedor.

## C. Configurar PHP y base de datos

1. En cPanel > **MultiPHP Manager / Selector de PHP** elige PHP 8.3 o superior para el subdominio; comprueba `pdo_mysql`, `zip` y `session`.
2. Activa HTTPS desde **SSL/TLS Status** o la sección de certificados que ofrezca tu proveedor.
3. En cPanel > **Bases de datos MySQL**, crea una base y un usuario, y concédele permisos para las tablas de LandiCy. Anota **nombres completos** (cPanel suele anteponer `miusuario_`).
4. En **phpMyAdmin**, selecciona esa base, abre «Importar» y carga el archivo privado `landicy-private/database/schema.sql`, o pega su contenido en «SQL».
5. En el administrador de archivos, dentro de `landicy-private`, copia `config.example.php` a `config.php` y adapta:

```php
'db' => [
    'dsn' => 'mysql:host=localhost;dbname=miusuario_landicy;charset=utf8mb4',
    'username' => 'miusuario_landicyusr',
    'password' => 'PON_AQUI_TU_CLAVE_REAL',
],
'storage' => __DIR__ . '/storage',
'scan_root' => '',
'source_url' => '',
```

No pegues contraseñas reales en un chat ni en capturas públicas. Los valores anteriores son ejemplos.

## D. Crear el primer superadministrador

Abre **cPanel > Terminal** o conéctate por SSH. Ejecuta (ajusta usuario/correo):

```bash
cd /home/miusuario/landicy-private
php bin/create-admin.php administrador@tudominio.com "Administrador"
```

Se solicita una contraseña de al menos 12 caracteres de forma interactiva. Si el ejecutable `php` usa otra versión que el sitio web, verifica `php -v` o consulta al hosting la ruta del PHP CLI correcto. El código rechaza intentos de registrar un segundo superadministrador con el script.

**Si tu hosting no dispone de terminal:** el MVP no tiene todavía un instalador web seguro. Necesitarás SSH/CLI habilitada por tu proveedor o aprovisionamiento asistido antes de iniciar sesión.

## E. Acceder, crear, publicar

Abre `https://admin.tudominio.com/?action=login`, entra con tus credenciales y:

1. «+ Nuevo sitio» → nombre, slug y colores → crear.
2. Edita bloques → «Guardar borrador» → «Vista previa».
3. «Aprobar y compilar» → «Descargar ZIP publicado».
4. Descomprime **solo ese ZIP publicado** en la carpeta raíz del sitio público final, como `public_html/mi-sitio/` o el document root de otro dominio.

El sitio final no necesita PHP ni MySQL para atender visitantes. El panel de LandiCy no debe publicarse en el mismo directorio que la web creada.

## F. Reconocer HTML, CSS y JS del hosting

Hay dos maneras:

- **ZIP:** dentro del proyecto → «Inventariar desde hosting o ZIP» → subir ZIP de HTML/CSS/JS propios o autorizados. Límite inicial de 15 MB.
- **Carpeta autorizada:** configura en `landicy-private/config.php` una ruta absoluta estrecha, por ejemplo `'scan_root' => '/home/miusuario/public_html/sitios-para-revisar'`. Dentro, crea una subcarpeta `miweb` y escribe `miweb` en el analizador. No uses `/` o `/home` como raíz.

El analizador solo **lee** y lista archivos, referencias y código. Todavía **no** reescribe CSS/JS originales ni los convierte automáticamente en bloques editables.

## G. Problemas comunes

- **Error 500:** comprueba PHP CLI/web versión 8.3+, ruta del bootstrap y el log de errores PHP. No actives la visualización pública de excepciones con datos confidenciales.
- **Falta config.php:** se copió en otra carpeta. Debe estar en `/home/miusuario/landicy-private/config.php`.
- **Error de base de datos:** revisa `mysql:host=localhost`, nombre con prefijo, usuario, permisos y que `schema.sql` se ejecutó.
- **Permiso denegado:** PHP debe poder escribir en `landicy-private/storage/`. Aplica los permisos mínimos que indique tu hosting; nunca `777`.
- **Subdominio no carga:** confirma DNS, HTTPS y su document root **exacto**, sin compartir raíz con el dominio principal.
- **PHP no se ejecuta:** hosting estático o extensión de PHP desactivada; el **panel** requiere PHP.

## H. Seguridad y AGPLv3

No hagas públicos `config.php`, la base de datos, `src`, `bin`, `storage` ni los ZIP importados. Haz copias de seguridad. No instales sobre un sitio productivo existente.

Si modificas LandiCy y lo ofreces a usuarios por la red, configura `source_url` con una URL pública que entregue el código fuente correspondiente a esa **versión modificada**, sin secretos del servidor. La publicación debe cumplir GNU AGPLv3 §13. El enlace debe conducir a la fuente real, no a una versión distinta o incompleta.

Las webs independientes exportadas disponen de permiso adicional para los fragmentos de plantillas propios de LandiCy. Consulta `LICENSE-OUTPUT-EXCEPTION.md`.
