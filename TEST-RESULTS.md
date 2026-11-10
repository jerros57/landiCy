# Pruebas v0.6.3

Nuevas suites v063.php y http063.php verifican marcadores, conservación de contenido, restauración, permisos, CSRF, confirmación obligatoria, versiones obsoletas, GET sin efectos, bloqueo de edición/publicación y proyectos publicados. HTTP se ejecuta exclusivamente en una instancia privada con tablas sintéticas registradas. El informe final detalla resultados efectivos y limpieza.

La confirmación no depende de un confirm JavaScript: es una pantalla con nombre del proyecto, alcance, Cancelar y checkbox obligatorio; el servidor valida también esa decisión. Navegador verifica cancelar y confirmar, y las rutas PHP verifican las mutaciones en datos sintéticos. No se borran borradores reales al probar o instalar.
