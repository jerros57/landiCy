# Arquitectura v0.5.1

Se conserva el controlador PHP, PDO/MySQL y JavaScript nativo. No cambia el esquema SQL. ImportedCanvas opera exclusivamente sobre snapshots adjuntos privados; nunca recibe el destino original como ruta de escritura. Importer inventaría/copia tipos permitidos, aplana @import local con límites, compone fragmentos conocidos, sanitiza y exporta. El origen solo se lee. La expansión distingue scroll-behavior de la propiedad activa behavior.

La edición visual manipula el DOM dentro de un iframe con scripts y conexiones deshabilitados. El JavaScript del panel propio selecciona texto, elementos y contenedores. Al serializar retira atributos/estilos de edición. El servidor revalida HTML/CSS, convierte proxies de recursos a rutas relativas de la copia y verifica archivos y componentes por hash bajo bloqueo de sitio. Las cabeceras/pies se guardan en sus fragmentos compartidos, no en herUX u otra fuente.

El HTML exportado de una página puramente importada es una página completa con recursos relativos, sin otro header/footer envolvente ni iframe exterior. Su runtime legible propio cubre tema/año/menú reconocido; no copia ni ejecuta el JavaScript del sitio original. Las páginas mixtas conservan el modelo anterior de bloques.

Builder mantiene el renderizador nativo y sus temas serializables. TemplateParts sanitiza reemplazos completos de cabecera/pie con variables cerradas. DraftCode mantiene historial y control optimista; los snippets adicionales anteriores no se sobrescriben. El logo/fav del administrador es un SVG geométrico propio. El mensaje de guardado se consume en el estado del editor.

## Estudio de WordPress/Gutenberg

Se estudiaron [estilos globales](https://developer.wordpress.org/block-editor/explanations/architecture/styles/), [theme.json](https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/), [transformaciones](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-transforms/) y el [registro del bloque template-part](https://github.com/WordPress/gutenberg/blob/trunk/packages/block-library/src/template-part/index.js). Se adoptó el concepto de piezas compartidas y la separación entre datos, vista, estilos y transformaciones, con una implementación propia basada en archivos/JSON/PHP y el modelo existente.

No se incorpora código WordPress, Gutenberg, React, fuentes o paquetes de sus dependencias. Se conservan AGPLv3 y la excepción de salida existentes. Las licencias de contenido, imágenes y estilos importados continúan siendo de sus titulares y no forman parte del paquete de código fuente de LandiCy.
