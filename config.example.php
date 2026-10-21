<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2026 LandiCy contributors. See LICENSE and LICENSE-OUTPUT-EXCEPTION.md.

/** Copia a config.php y adapta. Mantén este archivo FUERA del document root. */
return [
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=landicy;charset=utf8mb4',
        'username' => 'landicy',
        'password' => 'CAMBIAR',
    ],
    // Carpeta de salida privada (descargar un ZIP y subirlo al hosting público).
    'storage' => __DIR__ . '/storage',
    // OPCIONAL: raíz exclusiva en la que el superadministrador puede INVENTARIAR
    // sitios HTML/CSS/JS existentes. Nunca apuntar a todo el servidor.
    'scan_root' => '',
    // Para instalaciones modificadas: URL pública de la fuente realmente desplegada.
    // Debe poder descargarse sin credenciales y no contener config.php ni contraseñas.
    'source_url' => '',
];
