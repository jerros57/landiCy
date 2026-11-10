# Notas de seguridad y límites actuales

1. Publica **solo la carpeta `public/`** como raíz del subdominio del panel. `config.php`, base de datos, `src/` y `storage/` no deben ser accesibles por HTTP. En hostings que no permitan modificar `document_root`, crea una estructura equivalente fuera de `public_html` y expón solo `public` mediante configuración apropiada. No basta con ocultar archivos mediante CSS ni asumir que `.htaccess` protege Nginx.
2. Obliga HTTPS y actualiza PHP. El software se ha concebido para PHP 8.3+; se recomienda 8.4+.
3. El `scan_root` es una ruta exclusiva y limitada a los sitios que administras. No la configures como `/`, `/home` o todo `public_html` de varias cuentas. El análisis de carpetas es exclusivo del superadministrador.
4. No se ejecuta PHP desde ZIP ni de archivos escaneados. El sistema solo lee extensiones `html`, `htm`, `css`, `js` y `mjs`. Los ZIP se almacenan en `storage/` privado.
5. El «inspector» no significa que un HTML externo pueda traducirse fielmente al editor visual. Se realiza inventario pasivo. La importación/edición de sitios existentes está planificada, no implementada.
6. Todas las acciones que modifican datos pasan por sesión/CSRF, contraseña hash PDO y consultas preparadas. Hay límite de intentos por sesión, pero **faltan** limitación por IP, MFA, auditoría integral y recuperación de cuenta.
7. Los sitios generados son estáticos; aun así, no son inmunes a XSS si un operador los modifica después o aloja scripts de terceros. Se escapan contenidos y se restringen enlaces, pero se requiere auditoría previa a uso con datos sensibles.
8. El publicado se genera en `storage/exports/` y se descarga como ZIP: **no** modifica automáticamente el hosting o dominio de producción. El nombre de dominio en la base es descriptivo.
9. Actualiza archivos en producción y haz backups tanto de MySQL como de archivos generados y `storage/imports`.
10. La versión v0.1 es un prototipo inicial: antes de abrirlo a usuarios desconocidos, añade análisis de amenazas, permisos, pruebas de integración y revisión independiente.
