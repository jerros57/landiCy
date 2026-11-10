# Actualización v0.6.2 → v0.6.3

Verificar rutas/dominio y base exclusiva LandiCy, symlinks y deriva de hashes. Crear respaldo privado recuperable y verificar rollback. Ejecutar sintaxis PHP/JS, pruebas aisladas y navegador antes de instalar. Aplicar solo los cuatro update_files de MANIFEST.json: DraftTrash.php y Access.php primero, Views.php después e index.php al final. Conservar configuración, almacenamiento y base; no hay migración SQL.

La papelera usa marcadores privados storage/draft-trash por ID numérico; no elimina filas ni contenido. El usuario confirma cada operación con CSRF, checkbox obligatorio y token actual. Solo el superadministrador puede borrar/restaurar proyectos, y solo si ninguna página tiene versión publicada. Un proyecto en papelera no se puede editar ni publicar.

Guardar fuente fija en landicy-private/source-packages/LandiCy_codigo_fuente_v0.6.3_AGPL.zip, servida por source-download. No cambiar reglas del hosting ni copiar sobre todo public_html. No tocar herUX/UX Day. Para instalación nueva, ver landicy-private/docs/INSTALACION_CPANEL.md.
