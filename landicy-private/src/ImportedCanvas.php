<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace LandiCy;
use InvalidArgumentException;
use RuntimeException;
/** Edits only an attached private snapshot. Never receives an original source path. */
final class ImportedCanvas {
    public const PARTS=['components/header.html','components/footer.html'];
    private static function document(string $html): \DOMDocument {
        $doc=new \DOMDocument();$prior=libxml_use_internal_errors(true);
        try{$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}finally{libxml_clear_errors();libxml_use_internal_errors($prior);}return $doc;
    }
    private static function inner(\DOMNode $node):string{$s='';foreach($node->childNodes as $child)$s.=$node->ownerDocument->saveHTML($child);return $s;}
    public static function compose(string $html,array $parts):string {
        $doc=self::document($html);$x=new \DOMXPath($doc);
        foreach(['header','footer'] as $part){$file='components/'.$part.'.html';if(!isset($parts[$file]))continue;
            foreach(iterator_to_array($x->query('//*[@id="'.$part.'-container" or @data-lcy-component="'.$file.'"]')) as $node){
                while($node->firstChild)$node->removeChild($node->firstChild);$fragment=self::document(Importer::html($parts[$file]));$body=$fragment->getElementsByTagName('body')->item(0);
                foreach($body?iterator_to_array($body->childNodes):[] as $child)$node->appendChild($doc->importNode($child,true));$node->setAttribute('data-lcy-component',$file);
            }
        }foreach(iterator_to_array($doc->childNodes) as $n)if($n->nodeType===XML_PI_NODE)$doc->removeChild($n);return (string)$doc->saveHTML();
    }
    public static function composeFile(string $file,string $path):string {
        $base=substr($file,0,-strlen($path));$parts=[];
        foreach(self::PARTS as $part){$candidate=$base.$part;if($part!==$path&&!is_link($candidate)&&is_file($candidate)&&filesize($candidate)<=1048576)$parts[$part]=(string)file_get_contents($candidate);}
        return self::compose((string)file_get_contents($file),$parts);
    }
    public static function expandCss(string $path,array $files,array $seen=[],?string $entry=null):string {
        if(count($seen)>8||isset($seen[$path]))throw new InvalidArgumentException('Importaciones CSS cíclicas o demasiado profundas.');$seen[$path]=true;$entry??=$path;$css=$files[$path]??'';$imports=[];
        $css=preg_replace_callback('~@import\s+(?:url\(\s*(["\']?)([^)"\']+)\1\s*\)|(["\'])([^"\']+)\3)\s*([^;]*);~i',static function($m)use($path,$files,$seen,$entry,&$imports){
            $target=Importer::resolve($path,trim($m[2]?:($m[4]??'')));$content='/* Importación externa o ausente omitida */';
            if($target!==null&&isset($files[$target])&&strtolower(pathinfo($target,PATHINFO_EXTENSION))==='css'){$content=self::expandCss($target,$files,$seen,$entry);$media=trim($m[5]??'');if($media!=='')$content='@media '.$media.'{'.$content.'}';}
            $token='/*__LCY_IMPORT_'.count($imports).'__*/';$imports[$token]=$content;return $token;
        },$css)??'';
        $css=preg_replace_callback('~url\(\s*(["\']?)(.*?)\1\s*\)~is',static function($m)use($path,$entry){$target=Importer::resolve($path,$m[2]);return $target!==null?'url("'.self::relative($entry,$target).'")':$m[0];},$css)??'';
        $css=strtr($css,$imports);if(strlen($css)>1048576)throw new InvalidArgumentException('CSS combinado supera 1 MB.');return $css;
    }
    public static function relative(string $from,string $to):string {$a=explode('/',dirname($from));if($a===['.'])$a=[];$b=explode('/',$to);while($a&&$b&&$a[0]===$b[0]){array_shift($a);array_shift($b);}return str_repeat('../',count($a)).implode('/',$b);}
    private static function unproxy(string $url,string $path,int $siteId,array $manifest):string {
        $url=html_entity_decode($url,ENT_QUOTES|ENT_HTML5,'UTF-8');$p=parse_url($url);if(!$p||isset($p['host']))return $url;parse_str($p['query']??'',$q);
        if(($q['action']??'')!=='import-file')return $url;
        $target=$q['file']??null;if((int)($q['id']??0)!==$siteId||!is_string($target)||!in_array($target,$manifest['files']??[],true))throw new InvalidArgumentException('Recurso ajeno al borrador importado.');
        return self::relative($path,$target).(isset($p['fragment'])?'#'.$p['fragment']:'');
    }
    public static function decode(string $html,string $path,int $siteId,array $manifest):string {
        if(strlen($html)>1048576)throw new InvalidArgumentException('HTML supera 1 MB.');$doc=self::document($html);$x=new \DOMXPath($doc);
        foreach(iterator_to_array($x->query('//*[@data-lcy-editor or @data-lcy-static]')) as $n)$n->parentNode?->removeChild($n);
        foreach($x->query('//*') as $node){foreach(iterator_to_array($node->attributes) as $attr){
            if(in_array(strtolower($attr->name),['contenteditable','draggable','data-lcy-selected'],true)){$node->removeAttribute($attr->name);continue;}
            if(in_array(strtolower($attr->name),['src','href','poster','data-lcy-light-src','data-lcy-dark-src'],true))$node->setAttribute($attr->name,self::unproxy($attr->value,$path,$siteId,$manifest));
            if(strtolower($attr->name)==='style')$node->setAttribute('style',preg_replace_callback('~url\(\s*(["\']?)(.*?)\1\s*\)~is',static fn($m)=>'url("'.self::unproxy($m[2],$path,$siteId,$manifest).'")',$attr->value)??'');
        }}return (string)$doc->saveHTML();
    }
    public static function exportPage(string $storage,int $siteId,string $path,array $pages):string {
        $manifest=Importer::manifest($storage,$siteId);$file=Importer::file($storage,$siteId,$path);$doc=self::document(Importer::html(self::composeFile($file,$path)));$x=new \DOMXPath($doc);
        $rewrite=static function(string $url)use($path,$manifest,$pages):string {
            if(str_starts_with($url,'#')||preg_match('~^(https?://|mailto:|tel:)~i',$url))return $url;
            [$value,$fragment]=array_pad(explode('#',$url,2),2,'');$target=Importer::resolve($path,$value);if($target===null)return '#';
            $href='imported/'.implode('/',array_map('rawurlencode',explode('/',$target)));
            foreach($manifest['pages']??[] as $entry)if($entry['path']===$target){foreach($pages as $page)if($page['slug']===$entry['slug']){$href=$page['slug'].'.html';break;}break;}
            return $href.($fragment!==''?'#'.rawurlencode($fragment):'');
        };
        foreach($x->query('//*') as $node)foreach(iterator_to_array($node->attributes) as $attr){if(in_array(strtolower($attr->name),['src','href','poster','data-lcy-light-src','data-lcy-dark-src'],true))$node->setAttribute($attr->name,$rewrite($attr->value));if(strtolower($attr->name)==='style')$node->setAttribute('style',preg_replace_callback('~url\(\s*(["\']?)(.*?)\1\s*\)~is',static fn($m)=>'url("'.$rewrite($m[2]).'")',$attr->value)??'');}
        foreach($x->query('//style') as $style)$style->textContent=preg_replace_callback('~url\(\s*(["\']?)(.*?)\1\s*\)~is',static fn($m)=>'url("'.$rewrite($m[2]).'")',$style->textContent)??'';
        foreach(iterator_to_array($doc->childNodes) as $n)if($n->nodeType===XML_PI_NODE)$doc->removeChild($n);$script=$doc->createElement('script');$script->setAttribute('src','js/site.js');$script->setAttribute('defer','');$doc->getElementsByTagName('body')->item(0)?->appendChild($script);$style=$doc->createElement('style');$style->textContent=Builder::canvasCss().EditorChrome::iconCss().'@media(max-width:650px){.lcy-layout-grid{grid-template-columns:1fr!important}}';$doc->getElementsByTagName('head')->item(0)?->appendChild($style);return (string)$doc->saveHTML();
    }

    public static function attachMedia(string $storage,int $siteId,string $mediaPath):string {
        if(!preg_match('~^img/([a-f0-9]{24}\.(?:png|jpg|webp|gif))$~D',$mediaPath,$m))throw new InvalidArgumentException('Imagen inválida.');
        $source=Media::folder($storage,$siteId).'/'.$m[1];if(is_link($source)||!is_file($source))throw new InvalidArgumentException('Imagen ajena o ausente.');
        $root=rtrim($storage,'/').'/imported/'.$siteId;$lock=fopen($root.'/.canvas.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Borrador ocupado.');
        try{
            $manifest=Importer::manifest($storage,$siteId);$path='landicy-media/'.$m[1];if(in_array($path,$manifest['files'],true))return $path;
            if(count($manifest['files'])>=Importer::MAX_FILES)throw new InvalidArgumentException('Límite de recursos alcanzado.');$bytes=filesize($source);foreach($manifest['files'] as $name)$bytes+=filesize(Importer::file($storage,$siteId,$name));if($bytes>Importer::MAX_BYTES)throw new InvalidArgumentException('Recursos superan 30 MB.');
            $dir=$root.'/files/landicy-media';if(is_link($dir))throw new RuntimeException('Directorio inseguro.');if(!is_dir($dir))mkdir($dir,0700);if(!copy($source,$root.'/files/'.$path))throw new RuntimeException('No se pudo adjuntar la imagen.');chmod($root.'/files/'.$path,0600);
            $manifest['files'][]=$path;$temp=tempnam($root,'.manifest-');if($temp===false)throw new RuntimeException('No se pudo actualizar manifiesto.');try{file_put_contents($temp,json_encode($manifest,JSON_THROW_ON_ERROR),LOCK_EX);chmod($temp,0600);if(!rename($temp,$root.'/manifest.json'))throw new RuntimeException('Error al actualizar recursos.');}finally{if(is_file($temp))unlink($temp);}return $path;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    public static function versions(string $storage,int $siteId,string $path):array {
        $manifest=Importer::manifest($storage,$siteId);$out=[$path=>hash_file('sha256',Importer::file($storage,$siteId,$path))];
        foreach(self::PARTS as $part)if(in_array($part,$manifest['files']??[],true))$out[$part]=hash_file('sha256',Importer::file($storage,$siteId,$part));return $out;
    }
    public static function save(string $storage,int $siteId,string $path,string $html,array $versions,bool $extractParts=true):void {
        $manifest=Importer::manifest($storage,$siteId);$file=Importer::file($storage,$siteId,$path);
        if(!in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['html','htm'],true))throw new InvalidArgumentException('Solo HTML editable.');
        $dir=rtrim($storage,'/').'/imported/'.$siteId;$lock=fopen($dir.'/.canvas.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Borrador ocupado.');
        try{
            foreach(self::versions($storage,$siteId,$path) as $name=>$hash)if(!isset($versions[$name])||!is_string($versions[$name])||!hash_equals($hash,$versions[$name]))throw new RuntimeException('La página o su cabecera/pie cambió. Recarga para evitar sobrescribir cambios.');
            $html=self::decode($html,$path,$siteId,$manifest);$doc=self::document($html);$x=new \DOMXPath($doc);$writes=[];
            foreach($x->query('//*[@data-lcy-component]') as $node){if(!$extractParts)continue;$part=$node->getAttribute('data-lcy-component');if(!in_array($part,self::PARTS,true)||!in_array($part,$manifest['files'],true))throw new InvalidArgumentException('Componente ajeno.');$writes[$part]=self::inner($node);while($node->firstChild)$node->removeChild($node->firstChild);}
            $writes[$path]=(string)$doc->saveHTML();$previous=[];
            foreach($writes as $name=>$value){$previous[$name]=(string)file_get_contents(Importer::file($storage,$siteId,$name));$writes[$name]=Importer::html($value);}
            try{foreach($writes as $name=>$value)Importer::writeHtml($storage,$siteId,$name,$value);}catch(\Throwable $e){foreach($previous as $name=>$value)Importer::writeHtml($storage,$siteId,$name,$value);throw $e;}
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
