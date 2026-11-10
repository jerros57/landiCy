# Arquitectura y decisiones v0.5.0

Se conserva el controlador PHP, PDO con consultas preparadas, MySQL, almacenamiento privado y JavaScript nativo del editor. Builder normaliza y renderiza; Layout valida estilos y agrupa filas/columnas; Access y site_or_404 verifican rol/asignación; Importer prepara copias sanitizadas; Scanner inventaría sin ejecutar; SourceRoots resuelve exclusivamente raíces configuradas; Pages mantiene catálogo reversible e historial del diseño.

No hay cambios del esquema SQL. Los IDs, slugs, borradores, publicaciones y revisiones existentes permanecen en sus tablas. Los metadatos pages opcionales de theme_json indican estado, orden y excepción. Ausencia de metadatos equivale a página activa. No se ofrece borrado físico de páginas.

Los bloques nuevos reciben ID estable al normalizarse por primera vez; los posteriores guardados lo conservan y rechazan duplicados. Los clientes reciben una lista de tipos cerrada; el servidor valida campos, tamaños, colores, tipografías y enlaces. El modelo de diseño generado es compartido por editor/exportador. Los fragmentos avanzados son adiciones separadas: no sustituyen el HTML generado, y usan bloqueo y hash para impedir sobrescritura concurrente.

Cabecera/pie usan control optimista por hash de tema y snapshots privados previos. Las revisiones de página son las filas existentes de revisions. Restaurar crea una versión nueva, sin eliminar las anteriores. Los nuevos estados y versiones son aditivos e idempotentes al leer datos antiguos.

Embeds: DOMDocument/DOMParser extraen solo src del iframe, sin ejecución o petición de red desde el servidor. Se exige HTTPS, host exacto, sin credenciales ni puerto. YouTube se convierte a youtube-nocookie; se conserva una lista cerrada de parámetros numéricos. El lienzo generado tiene CSP sin scripts, mientras el iframe remoto aislado permite solo las capacidades del proveedor. Los fragmentos importados no ejecutan scripts.

## Estudio de WordPress/Gutenberg

Se adoptan conceptos: atributos serializables y registro de tipos, separación de edición/renderizado, selección y herramientas contextuales, validación central y revisiones restaurables. Referencias oficiales: [interfaz del bloque](https://developer.wordpress.org/block-editor/getting-started/fundamentals/block-in-the-editor/), [metadatos](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/) y [validación/sanitización](https://developer.wordpress.org/apis/security/sanitizing/).

Implementación propia: no se incorpora código de Gutenberg, WordPress, React ni dependencias de su editor. Se revisaron la [licencia de WordPress](https://wordpress.org/about/license/) y la [licencia del repositorio Gutenberg](https://github.com/WordPress/gutenberg/blob/trunk/LICENSE.md). No se presupone una licencia única para todos sus componentes ni se añade código externo sin comprobar licencia, archivos y atribuciones concretos. LandiCy continúa AGPL-3.0-or-later con su política y excepción existentes para el contenido generado.

Las sesiones de v0.5.0 se guardan exclusivamente en storage/sessions de LandiCy (0700). No se copian sesiones del hosting compartido. Tras actualizar, las cuentas deben iniciar sesión de nuevo una vez; sus proyectos y borradores permanecen intactos. No se cambia la configuración general de PHP ni del hosting.
