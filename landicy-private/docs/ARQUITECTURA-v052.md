# Arquitectura v0.5.2

EditorChrome centraliza las pestañas y el shell importado, reutiliza los estilos del constructor y mantiene los modelos nativo/HTML sin conversiones destructivas. El importado añade estructura, selección contextual, columnas receptoras y controles de vista/recuperación.

ScriptPreview compone solo archivos de la copia adjunta y selecciona JS clásicos por el orden del manifiesto (fallback antiguo: main.js al final). Los originales no se ejecutan en el lienzo de edición ni en el administrador. La ruta import-live requiere autenticación y asignación del sitio. El iframe y CSP sandbox permiten scripts sin allow-same-origin. Nonce por respuesta, connect-src none, form-action none, frame-src none; sin popups/top-navigation. El runtime sustituye fetch por un mapa en memoria de HTML/CSS adjuntos, usa almacenamiento efímero y reescribe imágenes hacia el proxy autorizado. El acceso al DOM del padre queda bloqueado por origen opaco.

Los recursos gráficos/CSS locales se sirven por el controlador con sesión y alcance del sitio. No se permite ejecutar módulos, dependencias CDN, formularios/backend ni efectuar peticiones fetch reales. La vista aislada es para revisión de la última copia guardada, no ofrece una equivalencia de todos los plugins de WordPress. Scripts originales continúan inertes en exportaciones; runtime propio de tema/menú/año y logos por atributos data-lcy-light-src/data-lcy-dark-src.

EditorChrome.iconCss aporta símbolos propios de sistema para clases de iconos frecuentes; no copia fuentes o código de Font Awesome. Los pares de logos existentes se conservan; no se genera una identidad visual nueva ni se escribe sobre el origen. Sin cambios de esquema/configuración ni dependencias pesadas.


La vista interactiva incorpora imágenes, fuentes locales y CSS dentro del documento autenticado. No depende de cookies para sus subrecursos: el navegador restringe esas cookies dentro de un sandbox de origen opaco. CSP de imágenes/fuentes limitado a data: y estilos inline; fetch de componentes solo desde memoria. La prueba Edge exige cookie SameSite=Lax y confirma que no hay solicitudes de recursos sin autenticar. Esta codificación añade tamaño en la vista previa, cargada solo a petición.
