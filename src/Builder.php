<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2026 LandiCy contributors. See LICENSE and LICENSE-OUTPUT-EXCEPTION.md.

declare(strict_types=1);
namespace LandiCy;

use InvalidArgumentException;
use RuntimeException;

final class Builder {
    public const TYPES = ['hero', 'text', 'features', 'cta', 'footer'];

    public static function defaultBlocks(string $siteName): array {
        return [
            ['type' => 'hero', 'eyebrow' => 'Bienvenido', 'title' => $siteName,
             'subtitle' => 'Un sitio creado con LandiCy, software libre para la web.',
             'button_text' => 'Descubrir', 'button_url' => '#informacion'],
            ['type' => 'text', 'title' => 'Información',
             'body' => 'Edita este texto desde el panel de LandiCy y publícalo cuando estés listo.'],
            ['type' => 'features', 'title' => 'Lo que ofrecemos', 'items' => [
                ['title' => 'Ligero', 'text' => 'Solo HTML y CSS para la parte pública.'],
                ['title' => 'Libre', 'text' => 'Tú controlas tus datos y tus archivos.'],
                ['title' => 'Sencillo', 'text' => 'Edición por bloques predefinidos.'],
            ]],
            ['type' => 'footer', 'copyright' => $siteName],
        ];
    }

    public static function normalize(array $blocks): array {
        if (count($blocks) > 40) throw new InvalidArgumentException('Máximo 40 bloques.');
        $normalized = [];
        $fields = [
            'hero' => ['eyebrow', 'title', 'subtitle', 'button_text', 'button_url'],
            'text' => ['title', 'body'],
            'features' => ['title'],
            'cta' => ['title', 'body', 'button_text', 'button_url'],
            'footer' => ['copyright'],
        ];
        foreach ($blocks as $block) {
            if (!is_array($block) || !isset($block['type']) || !in_array($block['type'], self::TYPES, true)) {
                throw new InvalidArgumentException('Tipo de bloque no permitido.');
            }
            $type = $block['type'];
            $clean = ['type' => $type];
            foreach ($fields[$type] as $field) {
                $value = $block[$field] ?? '';
                if (!is_string($value)) throw new InvalidArgumentException('Campo de bloque inválido: ' . $field);
                if (strlen($value) > 12000) throw new InvalidArgumentException('Texto demasiado largo.');
                $clean[$field] = trim($value);
            }
            if ($type === 'features') {
                $items = $block['items'] ?? [];
                if (!is_array($items) || count($items) > 12) throw new InvalidArgumentException('Máximo 12 características.');
                $clean['items'] = [];
                foreach ($items as $item) {
                    if (!is_array($item)) throw new InvalidArgumentException('Característica inválida.');
                    $title = (string) ($item['title'] ?? '');
                    $text = (string) ($item['text'] ?? '');
                    if (strlen($title) > 300 || strlen($text) > 1200) throw new InvalidArgumentException('Característica demasiado larga.');
                    $clean['items'][] = ['title' => $title, 'text' => $text];
                }
            }
            $normalized[] = $clean;
        }
        return $normalized;
    }

    private static function url(string $url): string {
        $url = trim($url);
        if ($url === '') return '#';
        if (preg_match('~^(https?://|mailto:|tel:)~i', $url)) return $url;
        if (str_starts_with($url, '#') && preg_match('/^#[a-zA-Z0-9_-]*$/', $url)) return $url;
        if (str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '\\') && !preg_match('/[\x00-\x1F]/', $url)) return $url;
        return '#';
    }

    public static function css(array $theme): string {
        $primary = self::color($theme['primary'] ?? '#225cf0');
        $accent = self::color($theme['accent'] ?? '#111827');
        return <<<CSS
:root{--primary:$primary;--accent:$accent;--text:#162239;--muted:#586478;--border:#e6eaf2;--surface:#fff;--bg:#f6f8fc}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--surface);color:var(--text);font:16px/1.65 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
a{color:var(--primary)}a:focus-visible,button:focus-visible{outline:3px solid var(--primary);outline-offset:3px}
.wrap{width:min(1120px,calc(100% - 40px));margin-inline:auto}.site-head{border-bottom:1px solid var(--border);padding:18px 0}
.brand{color:var(--accent);font-weight:800;letter-spacing:-.02em;font-size:1.1rem;text-decoration:none}.hero{padding:110px 0;background:linear-gradient(135deg,#f3f6ff,#ffffff)}
.hero h1{font-size:clamp(2.3rem,6vw,4.8rem);line-height:1.1;max-width:900px;letter-spacing:-.05em;margin:14px 0 22px}
.eyebrow{text-transform:uppercase;letter-spacing:.15em;font-size:.73rem;font-weight:800;color:var(--primary)}.lead{font-size:1.2rem;max-width:760px;color:var(--muted)}
.section{padding:76px 0}.section.alt{background:var(--bg)}h2{font-size:clamp(1.8rem,3.4vw,2.7rem);line-height:1.2;letter-spacing:-.035em;margin:0 0 25px}
.prose{max-width:770px;white-space:pre-line}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.feature{border:1px solid var(--border);border-radius:18px;padding:26px;background:#fff}
.feature h3{margin:0 0 8px}.feature p{margin:0;color:var(--muted)}.btn{display:inline-block;color:#fff;background:var(--primary);padding:12px 23px;border-radius:11px;text-decoration:none;font-weight:700;margin-top:12px}
.btn:hover{filter:brightness(.88)}.cta{background:var(--accent);color:#fff}.cta .prose{opacity:.88}.cta .btn{background:#fff;color:var(--accent)}
.site-foot{padding:35px 0;border-top:1px solid var(--border);color:var(--muted);font-size:.93rem}@media(max-width:700px){.hero{padding:75px 0}.section{padding:52px 0}.grid{grid-template-columns:1fr}}
CSS;
    }

    public static function color(string $value): string {
        return preg_match('/^#[0-9a-fA-F]{6}$/D', $value) ? $value : '#225cf0';
    }

    public static function html(array $site, array $page, array $blocks, bool $inlineCss = false): string {
        $theme = json_decode($site['theme_json'], true) ?: [];
        $body = '';
        foreach ($blocks as $block) {
            $type = $block['type'];
            $title = \e($block['title'] ?? '');
            if ($type === 'hero') {
                $eyebrow = \e($block['eyebrow'] ?? '');
                $sub = \e($block['subtitle'] ?? '');
                $btn = self::button($block);
                $body .= "<section class=\"hero\" id=\"inicio\"><div class=\"wrap\"><div class=\"eyebrow\">$eyebrow</div><h1>$title</h1><p class=\"lead\">$sub</p>$btn</div></section>";
            } elseif ($type === 'text') {
                $text = \e($block['body'] ?? '');
                $body .= "<section class=\"section\" id=\"informacion\"><div class=\"wrap\"><h2>$title</h2><div class=\"prose\">$text</div></div></section>";
            } elseif ($type === 'features') {
                $cards = '';
                foreach (($block['items'] ?? []) as $item) {
                    $cards .= '<article class="feature"><h3>' . \e($item['title']) . '</h3><p>' . \e($item['text']) . '</p></article>';
                }
                $body .= "<section class=\"section alt\"><div class=\"wrap\"><h2>$title</h2><div class=\"grid\">$cards</div></div></section>";
            } elseif ($type === 'cta') {
                $txt = \e($block['body'] ?? '');
                $body .= "<section class=\"section cta\"><div class=\"wrap\"><h2>$title</h2><div class=\"prose\">$txt</div>" . self::button($block) . '</div></section>';
            } elseif ($type === 'footer') {
                $copyright = \e($block['copyright'] ?? $site['name']);
                $body .= '<footer class="site-foot"><div class="wrap">© <span data-year>' . date('Y') . '</span> ' . $copyright . '</div></footer>';
            }
        }
        $css = $inlineCss ? '<style>' . self::css($theme) . '</style>' : '<link rel="stylesheet" href="css/site.css">';
        $script = $inlineCss ? '' : '<script src="js/site.js" defer></script>';
        $lang = 'es';
        $siteName = \e($site['name']);
        $description = \e($page['title'] ?? $site['name']);
        return <<<HTML
<!doctype html>
<html lang="$lang">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="$description"><meta name="generator" content="LandiCy">
<title>$description — $siteName</title>
$css
</head>
<body><a class="skip" href="#contenido" style="position:absolute;left:-10000px">Ir al contenido</a><header class="site-head"><div class="wrap"><a class="brand" href="./">$siteName</a></div></header>
<main id="contenido">$body</main>
$script
</body></html>
HTML;
    }

    private static function button(array $block): string {
        $text = trim($block['button_text'] ?? '');
        if ($text === '') return '';
        return '<a class="btn" href="' . \e(self::url((string) ($block['button_url'] ?? '#'))) . '">' . \e($text) . '</a>';
    }

    public static function publish(array $site, array $page, string $storage): string {
        $slug = $site['slug']; // validado al crear
        $base = rtrim($storage, '/') . '/exports';
        if (!is_dir($base) && !mkdir($base, 0700, true) && !is_dir($base)) throw new RuntimeException('No se pudo crear exports');
        $temp = $base . '/.' . $slug . '-' . bin2hex(random_bytes(8));
        foreach ([$temp, "$temp/css", "$temp/js", "$temp/data", "$temp/assets"] as $folder) {
            if (!mkdir($folder, 0700) && !is_dir($folder)) throw new RuntimeException('Error al crear carpeta temporal.');
        }
        try {
            $blocks = self::normalize(\json_data($page['draft_json']));
            $theme = json_decode($site['theme_json'], true) ?: [];
            self::write($temp . '/index.html', self::html($site, $page, $blocks));
            self::write($temp . '/css/site.css', self::css($theme));
            self::write($temp . '/assets/LEEME.txt', 'Coloca aquí tus imágenes y fuentes optimizadas.' . "\n");
            self::write($temp . '/js/site.js', "document.querySelectorAll('[data-year]').forEach(el => {el.textContent = String(new Date().getFullYear())});\n");
            self::write($temp . '/data/content.json', json_encode([
                'site' => ['slug' => $slug, 'name' => $site['name']],
                'theme' => $theme,
                'blocks' => $blocks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $target = $base . '/' . $slug;
            $backup = $base . '/.' . $slug . '-backup-' . bin2hex(random_bytes(6));
            if (is_dir($target) && !rename($target, $backup)) throw new RuntimeException('No se pudo apartar la versión anterior.');
            if (!rename($temp, $target)) {
                if (is_dir($backup)) rename($backup, $target);
                throw new RuntimeException('No se pudo publicar.');
            }
            if (is_dir($backup)) self::remove($backup);
            return $target;
        } catch (\Throwable $error) {
            if (is_dir($temp)) self::remove($temp);
            throw $error;
        }
    }

    private static function write(string $path, string $data): void {
        if (file_put_contents($path, $data, LOCK_EX) === false) throw new RuntimeException('Error al escribir ' . basename($path));
    }

    private static function remove(string $folder): void {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        rmdir($folder);
    }
}
