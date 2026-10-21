# LandiCy: hoja de ruta comunitaria

## v0.1 (esta entrega): prototipo funcional
- Panel con inicio de sesión, administrador inicial por CLI y multisitio.
- Temas con colores; constructor por 5 tipos de bloque y edición por formulario.
- Guardado de borrador, historial de versiones en la base de datos y vista previa.
- Aprobación y exportación de sitio estático: `index.html`, `css/site.css`, `js/site.js`, `data/content.json`.
- Descarga ZIP para publicar manualmente en cualquier hosting estático.
- Inventario de HTML, CSS y JS desde una subcarpeta permitida del hosting o ZIP.
- Detección aproximada de referencias CSS/JS, scripts/estilos embebidos, checksums y tamaños.
- Inspector de código **de solo lectura** (con autorización, sin ejecutar contenido).

## v0.2: primera beta de importación
- Importar estructura de un sitio existente conservando el HTML original.
- Edición de código integrado con validación, sandbox y copias de seguridad.
- Mapeo asistido de secciones conocidas (`header`, `main`, `section`, `footer`) a bloques.
- Fallback explícito «bloque HTML personalizado», sin prometer conversión total.
- Detector de enlaces internos rotos y de dependencias que faltan.
- Integración de activos (imágenes, fuentes) bajo límites seguros.

## v0.3: colaboración y publicación gobernada
- Gestión completa de usuarios y permisos por sitio.
- Flujo formal borrador → solicitud de revisión → aprobación → publicación.
- Comparación visual y textual de revisiones, reversión a versiones anteriores.
- Publicación directa opcional en destinos configurados y permitidos por el administrador.
- Publicación atómica con respaldo, auditoría y restauración.

## v0.4: experiencia de edición profesional
- Editor visual in situ, inspector de componentes, responsive, drag and drop.
- Secciones reutilizables, librería de diseños, assets multimedia y páginas múltiples.
- Soporte i18n, accesibilidad y SEO técnico.

## v1.0: plataforma de software libre comunitaria
- Pruebas automatizadas en PHP/MySQL reales y auditoría de seguridad.
- Instalador web seguro en hostings sin SSH/CLI.
- Documentación multilingüe, contribuciones y ecosistema de plugins aislados.
- Exportaciones reproducibles, importaciones con diff y compatibilidad estable.

**No prometer** cargas inferiores a 0,4 s ni inmunidad total a ataques: dependen de hardware, red, caché, activos y despliegue seguro.
