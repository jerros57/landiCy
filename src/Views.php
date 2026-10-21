<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2026 LandiCy contributors. See LICENSE and LICENSE-OUTPUT-EXCEPTION.md.

declare(strict_types=1);
namespace LandiCy;

final class Views {
    public static function start(string $title, ?array $user): void {
        $flash = \message();
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . \e($title) . ' • LandiCy</title><link rel="stylesheet" href="assets/admin.css"></head><body>';
        echo '<header class="top"><div class="container top-inner"><a class="logo" href="?action=dashboard">◈ LandiCy <span>community</span></a>';
        if ($user) {
            echo '<div class="top-actions"><span>' . \e($user['name']) . '</span><form method="post" action="?action=logout">' . \csrf_field() . '<button class="linklike">Salir</button></form></div>';
        }
        echo '</div></header><main class="container">';
        if ($flash) echo '<p class="notice">' . \e($flash) . '</p>';
        echo '<div class="page-head"><p class="overline">Software libre · Primer prototipo</p><h1>' . \e($title) . '</h1></div>';
    }
    public static function end(): void {
        $source = (string) (\config()['source_url'] ?? '');
        $link = (preg_match('~^https?://~i', $source) && filter_var($source, FILTER_VALIDATE_URL))
            ? ' · <a href="' . \e($source) . '" rel="noopener noreferrer" target="_blank">Obtener código fuente</a>'
            : '';
        echo '</main><footer class="container foot">LandiCy · Software libre GNU AGPLv3 · <a href="https://www.gnu.org/licenses/agpl-3.0.html" rel="noopener noreferrer" target="_blank">Licencia</a>' . $link . '</footer></body></html>';
    }
    public static function error(string $title, string $error, ?array $user = null): void {
        self::start($title, $user);
        echo '<div class="error">' . \e($error) . '</div><p><a href="?action=dashboard">Volver</a></p>';
        self::end();
    }
}
