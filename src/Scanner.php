<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2026 LandiCy contributors. See LICENSE and LICENSE-OUTPUT-EXCEPTION.md.

declare(strict_types=1);
namespace LandiCy;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/** Inventario de solo lectura. Nunca ejecuta scripts ni copia código a la web. */
final class Scanner {
    public const ALLOWED = ['html', 'htm', 'css', 'js', 'mjs'];
    public const MAX_FILES = 400;
    public const MAX_FILE_BYTES = 1048576;
    public const MAX_TOTAL_BYTES = 12582912;

    public static function isSafePath(string $name): bool {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) return false;
        foreach (explode('/', $name) as $part) {
            if ($part === '' || $part === '.' || $part === '..') return false;
        }
        return true;
    }

    public static function allowed(string $filename): bool {
        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::ALLOWED, true);
    }

    public static function inspect(string $name, string $contents): array {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $report = [
            'path' => $name,
            'kind' => $ext === 'htm' ? 'html' : $ext,
            'bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'references' => [],
            'inline_css' => 0,
            'inline_js' => 0,
            'sections' => 0,
        ];
        if (in_array($ext, ['html', 'htm'], true)) {
            if (preg_match_all('~<link\b[^>]*>~is', $contents, $tags)) {
                foreach ($tags[0] as $tag) {
                    $attrs = self::attributes($tag);
                    $rel = preg_split('/\s+/', strtolower($attrs['rel'] ?? ''));
                    if (in_array('stylesheet', $rel, true) && isset($attrs['href'])) {
                        $report['references'][] = ['type' => 'css', 'target' => $attrs['href']];
                    }
                }
            }
            if (preg_match_all('~<script\b[^>]*>~is', $contents, $tags)) {
                foreach ($tags[0] as $tag) {
                    $attrs = self::attributes($tag);
                    if (isset($attrs['src'])) {
                        $report['references'][] = ['type' => 'js', 'target' => $attrs['src']];
                    } else {
                        $report['inline_js']++;
                    }
                }
            }
            $report['inline_css'] = preg_match_all('~<style\b[^>]*>~i', $contents);
            $report['sections'] = preg_match_all('~<(section|header|footer|main|nav)\b~i', $contents);
        }
        return $report;
    }

    /** Extrae atributos para reconocer orden y comillas variables sin ejecutar HTML. */
    private static function attributes(string $tag): array {
        $attributes = [];
        preg_match_all('~([a-z_:][a-z0-9_:.-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i', $tag, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $value = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : ($match[4] ?? ''));
            $attributes[strtolower($match[1])] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $attributes;
    }

    /** relative a la RAÍZ ASIGNADA, nunca una ruta arbitraria del sistema. */
    public static function scanDirectory(string $configuredRoot, string $relative): array {
        $root = realpath($configuredRoot);
        if ($root === false || !is_dir($root)) throw new RuntimeException('La raíz de escaneo no existe; configura scan_root.');
        $relative = trim($relative, '/');
        if ($relative !== '' && !self::isSafePath($relative)) throw new InvalidArgumentException('Ruta relativa inválida.');
        $target = realpath($root . '/' . $relative);
        if ($target === false || !is_dir($target) || !self::within($root, $target)) throw new InvalidArgumentException('La carpeta no pertenece a la raíz permitida.');
        $files = [];
        $total = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $entry) {
            if ($it->getDepth() > 8) throw new InvalidArgumentException('Profundidad máxima de carpetas: 8.');
            if ($entry->isLink()) { if ($entry->isDir()) $it->next(); continue; }
            if (!$entry->isFile()) continue;
            $absolute = $entry->getRealPath();
            if ($absolute === false || !self::within($target, $absolute)) continue;
            $name = str_replace('\\', '/', substr($absolute, strlen($target) + 1));
            if (!self::isSafePath($name) || !self::allowed($name)) continue;
            if (count($files) >= self::MAX_FILES) throw new InvalidArgumentException('Se superó el límite de archivos.');
            $size = $entry->getSize();
            if ($size > self::MAX_FILE_BYTES) throw new InvalidArgumentException('Archivo demasiado grande: ' . $name);
            $total += $size;
            if ($total > self::MAX_TOTAL_BYTES) throw new InvalidArgumentException('Se superó el tamaño total permitido.');
            $content = file_get_contents($absolute);
            if ($content === false) continue;
            $files[] = self::inspect($name, $content);
        }
        usort($files, fn($a, $b) => strcmp($a['path'], $b['path']));
        return $files;
    }

    private static function within(string $root, string $candidate): bool {
        return $candidate === $root || str_starts_with($candidate, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    public static function readDirectoryFile(string $root, string $relative, string $filename): string {
        if (!self::isSafePath($filename) || !self::allowed($filename)) throw new InvalidArgumentException('Archivo no permitido.');
        $base = realpath(rtrim($root, '/') . '/' . trim($relative, '/'));
        $rootReal = realpath($root);
        if (!$base || !$rootReal || !self::within($rootReal, $base)) throw new InvalidArgumentException('Ruta inválida.');
        $file = realpath($base . '/' . $filename);
        if (!$file || !self::within($base, $file) || !is_file($file) || filesize($file) > self::MAX_FILE_BYTES) throw new InvalidArgumentException('Archivo inaccesible.');
        $content = file_get_contents($file);
        if ($content === false) throw new RuntimeException('No se pudo leer.');
        return $content;
    }

    /** Inspección sin extraer; archivos PHP, binarios y rutas peligrosas nunca se ejecutan. */
    public static function scanZip(string $filename): array {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('Activa la extensión PHP zip.');
        $zip = new ZipArchive();
        if ($zip->open($filename, ZipArchive::RDONLY) !== true) throw new InvalidArgumentException('ZIP inválido.');
        try {
            if ($zip->numFiles > 1000) throw new InvalidArgumentException('ZIP con demasiadas entradas.');
            $files = [];
            $total = 0;
            $seen = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!$stat) continue;
                $path = $stat['name'];
                if (str_ends_with($path, '/')) continue;
                if (!self::isSafePath($path)) throw new InvalidArgumentException('ZIP contiene ruta insegura.');
                if (!self::allowed($path)) continue;
                if (isset($seen[strtolower($path)])) throw new InvalidArgumentException('ZIP contiene rutas duplicadas.');
                $seen[strtolower($path)] = true;
                if (count($files) >= self::MAX_FILES) throw new InvalidArgumentException('Demasiados archivos compatibles.');
                $size = (int) $stat['size'];
                if ($size > self::MAX_FILE_BYTES) throw new InvalidArgumentException('Archivo ZIP demasiado grande: ' . $path);
                $total += $size;
                if ($total > self::MAX_TOTAL_BYTES) throw new InvalidArgumentException('ZIP demasiado grande descomprimido.');
                $contents = $zip->getFromIndex($i, self::MAX_FILE_BYTES + 1);
                if ($contents === false || strlen($contents) !== $size) throw new InvalidArgumentException('No se pudo validar el contenido ZIP.');
                $files[] = self::inspect($path, $contents);
            }
            usort($files, fn($a, $b) => strcmp($a['path'], $b['path']));
            return $files;
        } finally { $zip->close(); }
    }

    public static function readZipFile(string $archive, string $name): string {
        if (!self::isSafePath($name) || !self::allowed($name)) throw new InvalidArgumentException('Archivo no permitido.');
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) throw new InvalidArgumentException('ZIP inaccesible.');
        try {
            $stat = $zip->statName($name);
            if (!$stat || $stat['size'] > self::MAX_FILE_BYTES) throw new InvalidArgumentException('Archivo ZIP inaccesible.');
            $result = $zip->getFromName($name, self::MAX_FILE_BYTES + 1);
            if ($result === false) throw new RuntimeException('No se pudo leer del ZIP.');
            return $result;
        } finally { $zip->close(); }
    }
}
