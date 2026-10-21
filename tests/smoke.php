<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2026 LandiCy contributors. See LICENSE and LICENSE-OUTPUT-EXCEPTION.md.

/** Pruebas básicas sin base de datos: php tests/smoke.php */
require dirname(__DIR__) . '/src/bootstrap.php';
use LandiCy\Builder;
use LandiCy\Scanner;

function check(bool $value, string $name): void {
    if (!$value) throw new RuntimeException('Falló: ' . $name);
    echo "OK: $name\n";
}
check(!Scanner::isSafePath('../config.php'), 'bloquea traversal');
check(!Scanner::isSafePath('css\\secret'), 'bloquea separador Windows');
check(Scanner::isSafePath('css/site.css'), 'permite ruta web');
check(!Scanner::allowed('archivo.php'), 'no lee PHP');
check(Scanner::allowed('app.js'), 'detecta JS');
$sample = '<link rel="stylesheet" href="assets/site.css"><script src="a.js"></script><style>body{}</style><section>Hola</section>';
$report = Scanner::inspect('index.html', $sample);
check(count($report['references']) === 2, 'identifica referencias CSS y JS');
check($report['inline_css'] === 1, 'identifica CSS inline');
check($report['sections'] === 1, 'identifica secciones');
$alternate = Scanner::inspect('other.html', '<link href="theme.css" rel="preload stylesheet"><script src=app.js></script>');
check(count($alternate['references']) === 2, 'reconoce orden alterno y atributos sin comillas');
$blocks = Builder::normalize([['type' => 'hero', 'title' => '<img src=x onerror=alert(1)>']]);
$site = ['name' => 'Prueba', 'slug' => 'prueba', 'theme_json' => '{}'];
$html = Builder::html($site, ['title'=>'Inicio'], $blocks, true);
check(!str_contains($html, '<img src=x'), 'escapa HTML potencialmente peligroso');
check(str_contains($html, '&lt;img'), 'muestra texto seguro');
$files = Scanner::scanDirectory(__DIR__ . '/../demo/ejemplo-hosting', '');
check(count($files) === 3, 'escaneo de tres archivos demo');
echo "Pruebas básicas satisfactorias.\n";
