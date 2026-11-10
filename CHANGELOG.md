# v0.6.3 — 2026-10-29

- Borrar borrador desde panel y gestión del proyecto, con pantalla nominativa y confirmación obligatoria en servidor.
- Papelera recuperable de proyectos sin publicación; restauración también confirmada.
- Permiso exclusivo superadministrador, CSRF, token de estado y bloqueo compartido con publicación.
- Proyectos en papelera bloqueados para edición, recursos y publicación; datos y archivos conservados.
- Sin migración SQL ni eliminación de contenido real durante las pruebas.

## v0.6.2 — 2026-10-29

- Agrupación de eventos de puntero por frame; cache de alineación limitado e invalidado al desplazar/redimensionar la vista. Un gesto conserva un solo commit.
- Proporciones de columnas calculadas desde las medidas iniciales, sin acumulación de redondeo por cada evento.
- Una serialización HTML por cambio en el editor importado, reutilizada para recuperación local.
- Menú exportado cierra al seguir un enlace, pulsar Escape o pulsar fuera; Escape devuelve el foco.
- Saneador conserva favicons bitmap locales reconocidos sin permitir SVG ni iconos remotos.
- Sin migraciones, credenciales ni cambios generales del hosting. La propuesta de contenido herUX se entrega aparte, fuera de los paquetes de software.

## v0.6.1 — 2026-10-23

- Guías, cuadrícula, contorno de sección y destinos de columna visibles únicamente al arrastrar o redimensionar.
- Selección y escritura sin cuadrícula permanente; control «Guías al mover» conserva la opción de desactivarlas.
- Escape cancela un gesto y oculta las guías sin alterar el bloque.
- Mismo comportamiento en creador nativo y copias HTML; sin cambios de datos o esquema.

## v0.6.0 — 2026-10-22

- Cuadrícula por contenedor de 12 columnas y guías horizontales, ajuste, zoom y capas exclusivas de edición.
- Arrastre con fantasma entre columnas/secciones y redimensionamiento responsive de bloques/imágenes; divisores proporcionales.
- Toolbar de texto enriquecido saneado, nuevos diseños de una a cuatro columnas, tarjetas/galería independientes y fondos.
- Cabecera/pie con tipografía, alturas, fondos y enlaces sociales visuales; respeto de plantillas y excepciones de página.
- Autoguardado con versiones/hashes, recuperación local con conflicto seguro, teclado e historial por gesto.
- Navegación interna de vista JS aislada restringida a páginas de la copia; compatibilidad anterior y permisos conservados.
- Sin migraciones SQL ni cambios de hosting; documentación/manual y pruebas sintéticas ampliadas.

## v0.5.2 — 2026-10-22

- Pestañas compartidas entre creador y editor importado; selección contextual, estructura, imágenes, columnas receptoras y Ctrl/Cmd+S.
- Vista JS clásica aislada de origen opaco con recursos de la copia y almacenamiento efímero; conserva orden de scripts en nuevas importaciones.
- Iconos locales, variantes de logo claro/oscuro y exportación de variantes con runtime propio.
- Sin migración SQL ni cambios del hosting; fuente y paquetes revisados.

## v0.5.1 — 2026-10-22

- Editor visual para copias HTML, movimiento de texto en flujo, estilos, imágenes y recuperación.
- Conversión estática de CSS modular y cabecera/pie, sin ejecutar scripts de origen; componentes excluidos de la lista de páginas.
- Corrección de falso positivo CSS scroll-behavior; recursos relativos preservados al guardar y exportar.
- Enlace directo inventario → borrador independiente y apertura del editor correspondiente.
- Temas nativos claro/oscuro/sistema, runtime ligero de exportación y plantillas HTML completas de cabecera/pie.
- Rombo negro propio como logo/favicon; estado de guardado compacto en la barra.
- Conflictos de edición HTML/cabecera/pie protegidos por hash y bloqueo. Sin migraciones SQL.

## v0.5.0 — 2026-10-22

- Embeds reales responsivos en lienzo, preview y exportación; normalización segura de YouTube e iframe pegado.
- Catálogo reversible de páginas, duplicación, título y orden, ocultación, archivo, papelera y restauración; historial de página y diseño global.
- Raíces de lectura explícitas y canonizadas, separadas del destino de importación. Exclusión de fuentes privadas y administrativas.
- IDs de bloques, títulos/botones/formulario por correo, columnas proporcionales, bordes, alineación y teclado.
- Controles globales ampliados, botón de cabecera, enlaces sociales, visibilidad responsive y excepciones de página.
- Recuperación de borrador de pestaña y rechazo de cambios de código concurrentes. Esquema SQL intacto.

# v0.4.2

Se conserva la arquitectura y funcionalidad anterior. Se recuperaron los componentes faltantes del parche local (sin secretos), se implementaron importación privada por carpeta/ZIP, modo HTML editable, filas flexibles, formato por bloque, controles globales ampliados, menú ordenable, favicon y enlaces de imágenes. Se añadieron control central de permisos, recuperación de código avanzado y protección contra guardados globales obsoletos.

Corregidos: inicialización temprana del historial, selección que impedía doble clic, selector de destinos de botones no expuesto, raíz implícita de inventario, copia de symlinks de medios y divergencia del compilador heredado de una página.

No hay cambio de esquema ni despliegue automático. MANIFEST.json identifica archivos y hashes.

Las sesiones de v0.5.0 se guardan exclusivamente en storage/sessions de LandiCy (0700). No se copian sesiones del hosting compartido. Tras actualizar, las cuentas deben iniciar sesión de nuevo una vez; sus proyectos y borradores permanecen intactos. No se cambia la configuración general de PHP ni del hosting.
