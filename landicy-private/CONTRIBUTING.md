# Contribuir a LandiCy / Contributing

¡Bienvenidos! LandiCy aspira a ser una herramienta libre, ligera y útil en hostings compartidos.

1. Describe el fallo o mejora con pasos reproducibles y capturas sin credenciales.
2. Abre un cambio pequeño y enfocado, sin añadir dependencias JavaScript/PHP pesadas al sitio exportado.
3. Ejecuta `php tests/smoke.php` y `php -l` en los archivos PHP que modifiques.
4. No subas archivos ZIP de clientes, contraseñas ni copias de `config.php`.
5. Mantén la compatibilidad con PHP 8.3+, accesibilidad, textos localizables y consultas parametrizadas.
6. Para seguridad crítica, evita publicar detalles de explotación antes de coordinar una solución con el mantenedor.

**English:** Open a focused issue/PR, keep public output static and dependency-free, run smoke tests, never commit secrets, and make security and accessibility core requirements. The current prototype is not production-hardened.

## Licencia de aportaciones

Las aportaciones nuevas al programa deben ser compatibles con `AGPL-3.0-or-later`. No copies código de proyectos con derechos incompatibles. Si contribuyes plantillas o fragmentos utilizados en el HTML/CSS/JS exportado, indica expresamente si también autorizas el permiso adicional de `LICENSE-OUTPUT-EXCEPTION.md`. Las personas autoras conservan sus derechos sobre sus aportaciones. Las solicitudes de mejora y pull requests son bienvenidos, nunca obligatorios por el mero uso.
