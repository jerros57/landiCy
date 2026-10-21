<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2026 LandiCy contributors. See LICENSE and LICENSE-OUTPUT-EXCEPTION.md.

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI.'); }
require dirname(__DIR__) . '/src/bootstrap.php';

if ($argc !== 3) {
    fwrite(STDERR, "Uso: php bin/create-admin.php correo@ejemplo.com 'Nombre visible'\nLa contraseña se pedirá de forma interactiva.\n");
    exit(1);
}
$email = strtolower(trim($argv[1]));
$name = trim($argv[2]);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || strlen($name) > 120) {
    fwrite(STDERR, "Correo o nombre inválido.\n"); exit(1);
}
fwrite(STDOUT, "Nueva contraseña (mínimo 12 caracteres): ");
if (strncasecmp(PHP_OS, 'WIN', 3) !== 0 && function_exists('shell_exec')) @shell_exec('stty -echo');
$password = rtrim((string) fgets(STDIN), "\r\n");
if (strncasecmp(PHP_OS, 'WIN', 3) !== 0 && function_exists('shell_exec')) @shell_exec('stty echo');
fwrite(STDOUT, "\n");
if (strlen($password) < 12) { fwrite(STDERR, "Contraseña demasiado corta.\n"); exit(1); }
$exists = query('SELECT id FROM users WHERE role="superadmin" LIMIT 1')->fetch();
if ($exists) { fwrite(STDERR, "Ya hay un superadministrador. Crea otros usuarios desde una herramienta administrativa segura.\n"); exit(1); }
query('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,"superadmin")', [$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Superadministrador creado correctamente.\n");
