# Arquitectura v0.6.0

Inspección inicial: núcleo PHP privado con PDO MySQL y autoload, controlador dedicado, Builder/Layout para modelo JSON plano por fila/columna, ImportedCanvas sobre copias HTML, Media/Importer/Scanner, plantillas compartidas, DraftCode e historial; editor nativo e importado separados pero con pestañas comunes. Se conserva esta estructura.

CanvasUI genera controles compartidos y tokens de código. canvas-tools.js implementa la capa de guías mediante gradientes CSS, selección, ocho tiradores, toolbar, Pointer Events/capture, fantasma y adaptadores. Los gestos no mutan el modelo hasta terminar; se guarda una sola entrada de historial. Se limitan candidatos de alineación a 60 y se evita crear miles de líneas DOM. Los controles son editor-only y se retiran al serializar.

El adaptador nativo conserva el modelo plano compatible: row/columns/column/widths, más medidas de flujo (grid_span/start, max_width, min_height, space_before), tipografía, recorte y rich allowlist. Layout valida rangos; RichText usa DOM allowlist y rechaza atributos activos. Los valores antiguos no activan canvas automáticamente. Builder añade reglas móviles al documento exportado; no exporta guías ni controles administrativos.

El adaptador importado opera sobre el DOM saneado de la copia; mueve nodos en flujo y CSS grid. La serialización elimina capas, seleccionados y contenteditable/draggable. Las partes compartidas, recursos relativos, hashes de archivo e historial existentes se conservan. No se promete conversión completa de HTML externo.

Autosave reutiliza POST, CSRF, autorización y transacciones/bloqueos existentes; devuelve JSON con versiones/hashes renovados. Guarda tras inactividad y serializa solicitudes. Ante conflicto o respuesta incierta conserva recuperación local y se pausa. La recuperación de una versión vieja mantiene sus tokens viejos y desactiva autoguardado para impedir sobrescrituras silenciosas. No se incluye sincronización colaborativa ni fusión automática.

La vista JS sigue siendo un origen opaco sandbox allow-scripts sin allow-same-origin; CSP bloquea conexiones, formularios y marcos. Los recursos locales se incrustan y fetch solo resuelve recursos de la copia en memoria. La navegación solicita por postMessage una página de la misma copia; el padre verifica ventana, origen null y lista exacta de páginas. La exportación conserva runtime propio y JS importado inerte.

## Referencias y licencia

Se estudiaron controles de selección/inspector, RichText/InnerBlocks y atributos del editor en la documentación oficial de Gutenberg: https://developer.wordpress.org/block-editor/ y https://github.com/WordPress/gutenberg . También edición y distribución de contenido de Google Sites: https://support.google.com/sites/answer/90569?hl=es y https://support.google.com/sites/answer/6372878?hl=es .

Implementación propia PHP/JavaScript nativo; no se copió código de Gutenberg ni se añadieron paquetes WordPress o dependencias de ejecución Node/React. Se conserva AGPL-3.0-or-later y la política/excepción de salida existente. El SVG aportado por el usuario se usó como referencia de cuadrícula y selección; no sustituye el logo ni se incorpora como librería.
