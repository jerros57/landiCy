<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2026 LandiCy contributors. See LICENSE and LICENSE-OUTPUT-EXCEPTION.md.

declare(strict_types=1);
namespace LandiCy;

final class Views {
    private static ?string $editorMessage=null;
    public static function editorStatus():string { $text=self::$editorMessage??'Sin cambios';if(preg_match('/Borrador guardado.*?versión (\d+)/u',$text,$m))return 'Guardado · v'.$m[1];return mb_substr($text,0,120); }
    public static function start(string $title, ?array $user, bool $editor = false): void {
        $flash = \message();if($editor)self::$editorMessage=$flash;
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<link rel="icon" type="image/svg+xml" href="assets/landicy-mark.svg"><title>' . \e($title) . ' • LandiCy</title><link rel="stylesheet" href="assets/admin.css?v=063"></head><body' . ($editor ? ' class="editor-route"' : '') . '>';
        if($editor)echo '<script src="assets/canvas-tools.js?v=063" defer></script>';
        echo '<header class="top"><div class="container top-inner"><a class="logo" href="?action=dashboard"><img class="landicy-mark" src="assets/landicy-mark.svg" width="24" height="24" alt="">LandiCy <span>v0.6.3</span></a><a class="github-top-link" href="https://github.com/jerros57/landiCy" target="_blank" rel="noopener noreferrer">GitHub ↗</a>';
        if ($user) {
            echo '<div class="top-actions"><span>' . \e($user['name']) . '</span><form method="post" action="?action=logout">' . \csrf_field() . '<button class="linklike">Salir</button></form></div>';
        }
        echo '</div></header><main class="container' . ($editor ? ' editor-root' : '') . '">';
        if ($flash && !$editor) echo '<p class="notice">' . \e($flash) . '</p>';
        if (!$editor) echo '<div class="page-head"><p class="overline">Software libre · Primer prototipo</p><h1>' . \e($title) . '</h1></div>';
    }
    public static function end(): void {
        $source = (string) ((\config()['source_url'] ?? '') ?: 'https://github.com/jerros57/landiCy');
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
