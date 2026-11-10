# LandiCy v0.6.3

Constructor ligero PHP/MySQL/HTML/CSS/JavaScript nativo. AGPL-3.0-or-later con la excepción de salida existente. Mantiene las páginas, permisos, historial, importación y publicación anteriores. No necesita WordPress, React ni un servicio Node.

El creador nativo y las copias HTML comparten cuadrícula de 12 columnas por contenedor, guías, arrastre desde ⠿ Mover, ocho tiradores, diseños de una a cuatro columnas, barra contextual y autoguardado. Las guías solo aparecen durante un movimiento o redimensionamiento; al seleccionar, escribir y soltar el lienzo queda limpio. El contenido permanece en el flujo responsive; las capas y coordenadas absolutas pertenecen solo a los controles del editor.

El editor HTML trabaja sobre una copia independiente: no modifica el sitio de origen. Los JS clásicos locales se ven exclusivamente en la vista previa aislada de origen opaco. Logos, CSS y fuentes locales se incorporan al documento de esa vista para no depender de cookies de terceros. La exportación usa el runtime propio.

Superadministrador crea sitios y aprueba publicaciones; secundarios editan únicamente los asignados. Los paquetes no incluyen configuración real, credenciales, sesiones ni contenido de usuarios. Ver INSTALL.md, ROLLBACK.md, TEST-RESULTS.md y landicy-private/docs/MANUAL-v060.md.

El arrastre agrupa eventos por frame y reutiliza medidas durante el gesto; el editor de copias evita serializaciones duplicadas. Los favicons bitmap locales se conservan al guardar/importar. Ver el informe de rendimiento para mediciones sintéticas y límites.

El superadministrador puede borrar proyectos sin publicación con una confirmación explícita y moverlos a una papelera recuperable. No se borran sus páginas, recursos, historial ni archivos públicos.
