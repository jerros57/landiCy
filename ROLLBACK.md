# Reversión v0.6.3 → v0.6.2

Usar rollback.php --verify-only del respaldo privado previo y revisar antes de --apply. Restaura solo código/configuración, rechaza deriva y no restaura SQL ni borra borradores. El informe de entrega identifica la ruta exacta. La clase nueva puede quedar inerte.

Importante: v0.6.2 no interpreta marcadores de papelera. Al volver a esa versión los proyectos conservados se vuelven a listar como activos; no se pierde su contenido. Restaurar borradores desde la interfaz de v0.6.3 es distinto de revertir el código. No borrar manualmente marcadores de proyectos ajenos.
