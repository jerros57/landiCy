<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// LandiCy: snippets de código exclusivos del borrador de un sitio.
declare(strict_types=1);
namespace LandiCy;
use InvalidArgumentException;
use RuntimeException;
final class DraftCode {
    public static function defaults(): array { return ['html/header-template.html'=>'','html/footer-template.html'=>'','html/header.html'=>'','html/footer.html'=>'','css/custom.css'=>'','js/custom.js'=>'']; }
    public static function path(string $storage, int $siteId): string {
        if ($siteId < 1) throw new InvalidArgumentException('Sitio inválido.');
        return rtrim($storage, '/') . '/draft-code/' . $siteId . '.json';
    }
    public static function read(string $storage, int $siteId): array {
        $file=self::path($storage,$siteId);
        if (!is_file($file)) return self::defaults();
        $data=json_decode((string)file_get_contents($file),true);
        return is_array($data) ? array_merge(self::defaults(),$data) : self::defaults();
    }
    public static function allowed(string $file, array $pages): bool {
        if (array_key_exists($file,self::defaults())) return true;
        if (!preg_match('~^html/(index|[a-z0-9](?:[a-z0-9-]{0,97}[a-z0-9])?)\.html$~D',$file,$m)) return false;
        return in_array($m[1],array_column($pages,'slug'),true);
    }
    /** Sanitizador conservador sin extensiones PHP adicionales.
     *  Los fragmentos admiten texto y una lista cerrada de etiquetas. No admite
     *  atributos de eventos, JS ni estilos embebidos; cualquier otro tag se descarta.
     */
    public static function cleanHtml(string $input): string {
        if (strlen($input)>30000) throw new InvalidArgumentException('El fragmento HTML supera 30 KB.');
        if ($input==='') return '';
        $input=preg_replace('~<(script|style|iframe|svg|object|template|form)\b[^>]*>.*?</\1\s*>~is','',$input)??'';
        preg_match_all('~<[^>]*>~s',$input,$found,PREG_OFFSET_CAPTURE);
        $result='';$cursor=0;
        foreach ($found[0] as [$tag,$offset]) {
            $result.=htmlspecialchars(substr($input,$cursor,$offset-$cursor),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $cursor=$offset+strlen($tag);
            if (preg_match('~^</?(div|section|p|span|strong|em|b|i|u|br|h1|h2|h3|h4|ul|ol|li|blockquote|figure|figcaption|small|hr)\s*/?>$~iD',$tag,$m)) {
                $result.='<'.(str_starts_with($tag,'</')?'/':'').strtolower($m[1]).'>';
                continue;
            }
            if (preg_match('~^<(div|section|p|span|h1|h2|h3|h4)\s+(class|id)\s*=\s*(["\'])([A-Za-z0-9_ -]{1,90})\3\s*>$~iD',$tag,$m)) {
                $result.='<'.strtolower($m[1]).' '.strtolower($m[2]).'="'.htmlspecialchars($m[4],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'">';continue;
            }
            if (preg_match('~^<a\s+href\s*=\s*(["\'])(.*?)\1\s*>$~isD',$tag,$m) && self::safeHref($m[2])) {
                $result.='<a href="'.htmlspecialchars($m[2],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'">'; continue;
            }
            if (preg_match('~^</a\s*>$~iD',$tag)) { $result.='</a>'; continue; }
            if (preg_match('~^<img\s+src\s*=\s*(["\'])(.*?)\1(?:\s+alt\s*=\s*(["\'])(.*?)\3)?\s*/?>$~isD',$tag,$m)
                && Builder::imageUrl($m[2])!=='') {
                $alt=htmlspecialchars($m[4]??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
                $result.='<img src="'.htmlspecialchars($m[2],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" alt="'.$alt.'">';
            }
        }
        $result.=htmlspecialchars(substr($input,$cursor),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        return $result;
    }
    private static function safeHref(string $url): bool {
        return preg_match('~^(https://[^\s<>"\']+|mailto:[^\s<>"\']+|tel:[+0-9-]+|#[A-Za-z0-9_-]+|(?:index|[a-z0-9-]+)\.html)$~Di',$url)===1;
    }
    public static function normalize(string $file,string $contents): string {
        if (strlen($contents)>100000) throw new InvalidArgumentException('Máximo 100 KB por archivo.');
        if(in_array($file,['html/header-template.html','html/footer-template.html'],true))return TemplateParts::clean($contents);
        if (str_starts_with($file,'html/')) return self::cleanHtml($contents);
        if ($file==='css/custom.css') {
            // Evitar importaciones remotas, URLs activas y cierres de <style> en la vista previa.
            if (preg_match('~@import|url\s*\(|expression\s*\(|</?style|behavior\s*:|@charset~i',$contents)) throw new InvalidArgumentException('CSS no permitido: evita @import, url(), expression y etiquetas style.');
            return $contents;
        }
        if ($file==='js/custom.js') {
            // Solo se coloca en la exportación; nunca se ejecuta dentro del editor.
            if (str_contains($contents,'<?')) throw new InvalidArgumentException('No se permite código de servidor.');
            return $contents;
        }
        throw new InvalidArgumentException('Archivo de borrador no editable.');
    }
    public static function write(string $storage,int $siteId,string $file,string $contents,array $pages): void {
        if (!self::allowed($file,$pages)) throw new InvalidArgumentException('Archivo fuera del sitio en borrador.');
        $all=self::read($storage,$siteId);
        $normalized=self::normalize($file,$contents);
        $previous=$all; $all[$file]=$normalized;
        $path=self::path($storage,$siteId);$dir=dirname($path);
        if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('No se pudo crear el almacenamiento.');
        // Copia recuperable antes de sustituir el borrador; nunca se publica.
        if (is_file($path)) {
            $history=$dir.'/history/'.$siteId;
            if (!is_dir($history) && !mkdir($history,0700,true) && !is_dir($history)) throw new RuntimeException('No se pudo versionar el código.');
            $backup=$history.'/'.gmdate('YmdHis').'-'.bin2hex(random_bytes(6)).'.json';
            if (file_put_contents($backup,json_encode($previous,JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Error al versionar.');
            chmod($backup,0600);
        }
        $tmp=tempnam($dir,'.draft-'); if ($tmp===false) throw new RuntimeException('No se pudo guardar borrador.');
        try { if(file_put_contents($tmp,json_encode($all,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Error al guardar.'); chmod($tmp,0600); if(!rename($tmp,$path)) throw new RuntimeException('No se pudo guardar el código.'); }
        finally { if (is_file($tmp)) unlink($tmp); }
    }
    public static function history(string $storage,int $siteId): array {
        $dir=dirname(self::path($storage,$siteId)).'/history/'.$siteId;
        $versions=[];foreach(glob($dir.'/*.json')?:[] as $file){$name=basename($file);if(preg_match('/^[0-9]{14}-[a-f0-9]{12}\.json$/D',$name)&&!is_link($file))$versions[]=$name;}rsort($versions);return $versions;
    }
    public static function restore(string $storage,int $siteId,string $version,array $pages): void {
        if(!in_array($version,self::history($storage,$siteId),true))throw new InvalidArgumentException('Versión ajena o inválida.');
        $path=dirname(self::path($storage,$siteId)).'/history/'.$siteId.'/'.$version;
        $data=json_decode((string)file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
        if(!is_array($data))throw new InvalidArgumentException('Versión inválida.');
        $all=array_merge(self::read($storage,$siteId),$data);
        foreach($all as $file=>$_){if(!self::allowed($file,$pages))continue;$contents=(string)($data[$file]??'');if(str_starts_with($file,'html/'))$contents=html_entity_decode($contents,ENT_QUOTES|ENT_HTML5,'UTF-8');self::write($storage,$siteId,$file,$contents,$pages);}
    }

}
