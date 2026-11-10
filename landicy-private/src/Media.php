<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2026 LandiCy contributors.

declare(strict_types=1);
namespace LandiCy;

use InvalidArgumentException;
use RuntimeException;

/** Biblioteca de imágenes por sitio, fuera del directorio público. */
final class Media {
    private const MIMES = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];

    public static function validName(string $file): bool {
        return (bool)preg_match('/^[a-f0-9]{24}\.(?:jpg|png|webp|gif)$/D', $file);
    }

    public static function folder(string $storage, int $siteId): string {
        if ($siteId < 1) throw new InvalidArgumentException('Identificador de sitio inválido.');
        return rtrim($storage, '/') . '/media/' . $siteId;
    }

    public static function list(string $storage, int $siteId): array {
        $folder = self::folder($storage, $siteId);
        if (!is_dir($folder)) return [];
        $files = [];
        foreach (new \DirectoryIterator($folder) as $entry) {
            if (!$entry->isLink() && $entry->isFile() && self::validName($entry->getFilename())) {
                $files[] = ['name'=>$entry->getFilename(), 'size'=>$entry->getSize(), 'time'=>$entry->getMTime(), 'path'=>'img/' . $entry->getFilename()];
            }
        }
        usort($files, static fn(array $a,array $b): int => $b['time'] <=> $a['time']);
        return $files;
    }

    /** Hasta 5 MB, formatos de mapa de bits identificados por contenido, nunca SVG/PHP. */
    public static function upload(string $storage, int $siteId, array $upload): string {
        if (($upload['error'] ?? null) !== UPLOAD_ERR_OK || !isset($upload['tmp_name'])) throw new InvalidArgumentException('La carga de imagen falló.');
        if (($upload['size'] ?? 0) < 1 || $upload['size'] > 5 * 1024 * 1024) throw new InvalidArgumentException('Tamaño de imagen: máximo 5 MB.');
        $tmp = (string)$upload['tmp_name'];
        if (!is_uploaded_file($tmp)) throw new InvalidArgumentException('Archivo de carga inválido.');
        $info = @getimagesize($tmp);
        if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] > 8000 || $info[1] > 8000) throw new InvalidArgumentException('Imagen inválida o dimensiones mayores que 8000 px.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset(self::MIMES[$mime]) || $mime !== ($info['mime'] ?? '')) throw new InvalidArgumentException('Solo se permiten JPEG, PNG, WebP o GIF.');
        $folder = self::folder($storage, $siteId);
        if (count(self::list($storage, $siteId)) >= 100) throw new InvalidArgumentException('Máximo 100 imágenes por sitio.');
        if (!is_dir($folder) && !mkdir($folder, 0700, true) && !is_dir($folder)) throw new RuntimeException('No se pudo crear la carpeta privada de imágenes.');
        $name = bin2hex(random_bytes(12)) . '.' . self::MIMES[$mime];
        if (!move_uploaded_file($tmp, $folder . '/' . $name)) throw new RuntimeException('No se pudo guardar la imagen.');
        chmod($folder . '/' . $name, 0600);
        return 'img/' . $name;
    }

    public static function read(string $storage, int $siteId, string $name): array {
        if (!self::validName($name)) throw new InvalidArgumentException('Nombre de imagen no permitido.');
        $path = self::folder($storage, $siteId) . '/' . $name;
        if (is_link($path) || !is_file($path)) throw new InvalidArgumentException('Imagen inexistente.');
        $mime = match (strrchr($name, '.')) {
            '.jpg' => 'image/jpeg', '.png' => 'image/png', '.webp' => 'image/webp', '.gif' => 'image/gif',
            default => throw new InvalidArgumentException('Formato inválido.'),
        };
        return ['path'=>$path,'mime'=>$mime,'size'=>filesize($path)];
    }
}
