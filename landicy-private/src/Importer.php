<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace LandiCy;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/** Imports are private snapshots. No URL fetching and no writes to the source. */
final class Importer {
    public const MAX_FILES = 400;
    public const MAX_BYTES = 31457280;
    public const TYPES = ['html','htm','css','js','mjs','png','jpg','jpeg','webp','gif','ico','woff','woff2'];

    public static function safePath(string $path): bool {
        if (strlen($path)>240 || !Scanner::isSafePath($path) || preg_match('/[\x00-\x20\x7f?#%:]/',$path)) return false;
        foreach (explode('/',$path) as $part) {
            if ($part[0]==='.' || preg_match('/^(?:config|credentials|secrets?|passwords?|private|storage|sessions?|vendor|node_modules|landicy-private|landicy-admin)(?:[._-]|$)/i',$part)) return false;
        }
        return in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),self::TYPES,true);
    }
    private static function directory(string $storage,string $token): string {
        if (!preg_match('/^[a-f0-9]{32}$/D',$token)) throw new InvalidArgumentException('Importación inválida.');
        return rtrim($storage,'/').'/import-staging/'.$token;
    }
    public static function prepareDirectory(string $storage,int $owner,string $root,string $relative): array {
        if ($root==='' || !is_dir($root) || is_link($root)) throw new InvalidArgumentException('Configura una raíz scan_root explícita y autorizada.');
        $root=(string)realpath($root);
        if ($relative!=='' && !Scanner::isSafePath($relative)) throw new InvalidArgumentException('Subcarpeta inválida.');
        $candidate=$root;
        foreach ($relative===''?[]:explode('/',$relative) as $part) {
            $candidate.='/'.$part;
            if (is_link($candidate)) throw new InvalidArgumentException('No se permiten enlaces simbólicos.');
        }
        $base=realpath($candidate);
        if (!$base || !is_dir($base) || ($base!==$root && !str_starts_with($base,$root.DIRECTORY_SEPARATOR))) throw new InvalidArgumentException('Carpeta fuera de la raíz permitida.');
        $files=[];$visited=0;
        $walk=function(string $dir,string $prefix,int $depth) use (&$walk,&$files,&$visited):void {
            if ($depth>8) throw new InvalidArgumentException('Demasiados niveles de carpetas.');
            foreach (new \DirectoryIterator($dir) as $entry) {
                if ($entry->isDot()) continue;
                if (++$visited>2000) throw new InvalidArgumentException('Carpeta demasiado extensa.');
                $name=$prefix.$entry->getFilename();
                if ($entry->isLink()) continue;
                if ($entry->isDir()) {
                    if (preg_match('/^(?:[.]|private$|storage$|sessions?$|vendor$|node_modules$|landicy-private$|landicy-admin$)/i',$entry->getFilename())) continue;
                    $walk($entry->getPathname(),$name.'/', $depth+1);continue;
                }
                if (!self::safePath($name) || !$entry->isFile()) continue;
                if ($entry->getSize()>5242880) throw new InvalidArgumentException('Archivo supera 5 MB.');
                $data=file_get_contents($entry->getPathname());
                if ($data===false) throw new RuntimeException('No se pudo leer recurso.');
                $files[$name]=$data;
                self::limits($files);
            }
        };
        $walk($base,'',0);
        return self::stage($storage,$owner,$files);
    }
    public static function prepareZip(string $storage,int $owner,string $file): array {
        if (!is_file($file) || filesize($file)>15728640) throw new InvalidArgumentException('ZIP máximo 15 MB.');
        $z=new ZipArchive();
        if ($z->open($file,ZipArchive::RDONLY)!==true) throw new InvalidArgumentException('ZIP inválido.');
        $files=[];$seen=[];
        try {
            if ($z->numFiles>1000) throw new InvalidArgumentException('Más de 1000 entradas ZIP.');
            for ($i=0;$i<$z->numFiles;$i++) {
                $stat=$z->statIndex($i);
                if (!$stat) throw new InvalidArgumentException('Entrada ZIP ilegible.');
                $name=$stat['name'];
                $isDir=str_ends_with($name,'/');
                if (!Scanner::isSafePath($isDir?rtrim($name,'/'):$name)) throw new InvalidArgumentException('ZIP Slip: ruta insegura.');
                $ops=0;$attributes=0;
                if ($z->getExternalAttributesIndex($i,$ops,$attributes) && (($attributes>>16)&0170000)===0120000) throw new InvalidArgumentException('ZIP con enlace simbólico.');
                $key=strtolower($name);
                if (isset($seen[$key])) throw new InvalidArgumentException('ZIP con rutas duplicadas.');
                $seen[$key]=true;
                // Count every entry, even disallowed ones, before decompression.
                if ($stat['size']>5242880 || ($stat['size']>1048576 && $stat['size']/max(1,$stat['comp_size'])>100)) throw new InvalidArgumentException('ZIP con tamaño o compresión excesivos.');
                if ($isDir || !self::safePath($name)) continue;
                $data=$z->getFromIndex($i,5242881);
                if ($data===false || strlen($data)!==$stat['size']) throw new InvalidArgumentException('Contenido ZIP inválido.');
                $files[$name]=$data;self::limits($files);
            }
        } finally { $z->close(); }
        // A common single directory wrapper is removed, keeping all relative paths.
        if (!isset($files['index.html']) && !isset($files['index.htm']) && $files) {
            $prefix=explode('/',array_key_first($files))[0].'/';
            if (count(array_filter(array_keys($files),fn($p)=>!str_starts_with($p,$prefix)))===0 && (isset($files[$prefix.'index.html'])||isset($files[$prefix.'index.htm']))) {
                $files=array_combine(array_map(fn($p)=>substr($p,strlen($prefix)),array_keys($files)),array_values($files));
            }
        }
        return self::stage($storage,$owner,$files);
    }
    private static function limits(array $files): void {
        if (count($files)>self::MAX_FILES || array_sum(array_map('strlen',$files))>self::MAX_BYTES) throw new InvalidArgumentException('Importación supera 400 archivos o 30 MB.');
    }
    public static function css(string $css): string {
        if (strlen($css)>1048576) throw new InvalidArgumentException('CSS supera 1 MB.');
        // Block escaped/encoded CSS tokens and remote fetches, keeping ordinary relative assets.
        if (str_contains($css,'\\') || preg_match('/expression\s*\(|(?:^|[;{])\s*(?:-ms-)?behavior\s*:|[<]/i',$css)) return '/* CSS complejo omitido por seguridad */';
        $css=preg_replace('~/\*.*?\*/~s','',$css)??'';
        $css=preg_replace('~@import\s+[^;]+;?~i','',$css)??'';
        return preg_replace_callback('~url\(\s*(["\']?)(.*?)\1\s*\)~is',static function($m){
            $url=trim($m[2]);
            return self::relativeUrl($url)?'url("'.str_replace('"','',$url).'")':'none';
        },$css)??'';
    }
    private static function relativeUrl(string $url): bool {
        return $url!=='' && !preg_match('~^[a-z][a-z0-9+.-]*:|^/|[\\\\\x00-\x20<>"\'%]~i',$url) && !str_contains($url,'?');
    }
    /** DOM sanitizer preserves layout markup, disables scripts and executable navigation. */
    public static function html(string $html): string {
        if (strlen($html)>1048576) throw new InvalidArgumentException('HTML supera 1 MB.');
        $doc=new \DOMDocument();$previous=libxml_use_internal_errors(true);
        try {$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);} finally {libxml_clear_errors();libxml_use_internal_errors($previous);}
        $xpath=new \DOMXPath($doc);
        foreach ($xpath->query('//script|//iframe|//object|//embed|//base|//form|//input|//textarea|//select|//svg|//math|//template|//meta[@http-equiv]') as $node) $node->parentNode?->removeChild($node);
        foreach ($xpath->query('//*') as $node) {
            foreach (iterator_to_array($node->attributes) as $attr) {
                $name=strtolower($attr->name);$value=trim($attr->value);
                if (str_starts_with($name,'on') || in_array($name,['srcdoc','action','formaction','srcset','ping','xmlns','is'],true)) {$node->removeAttribute($attr->name);continue;}
                if($name==='target'){if($node->nodeName!=='a'||$value!=='_blank')$node->removeAttribute('target');else $node->setAttribute('rel','noopener noreferrer');continue;}
                if ($name==='style') {$node->setAttribute('style',self::css($value));continue;}
                if (in_array($name,['src','href','poster','background','data','xlink:href'],true)) {
                    $external=$name==='href' && $node->nodeName==='a' && preg_match('~^(?:https?://|mailto:|tel:)[^\x00-\x20<>]+$~iD',$value);
                    if (!self::relativeUrl($value) && !$external && !preg_match('/^#[a-z0-9_-]*$/iD',$value)) $node->removeAttribute($attr->name);
                    if ($node->nodeName==='link' && $name==='href') {$ext=strtolower(pathinfo($value,PATHINFO_EXTENSION));$rels=preg_split('/\s+/',strtolower($node->getAttribute('rel')));$icon=in_array('icon',$rels,true)||in_array('apple-touch-icon',$rels,true);if($ext!=='css'&&!($icon&&in_array($ext,['png','jpg','jpeg','webp','gif','ico'],true)))$node->removeAttribute('href');}
                }
            }
        }
        foreach ($xpath->query('//style') as $style) $style->textContent=self::css($style->textContent);
        foreach (iterator_to_array($doc->childNodes) as $node) if ($node->nodeType===XML_PI_NODE) $doc->removeChild($node);
        return (string)$doc->saveHTML();
    }
    private static function stage(string $storage,int $owner,array $files): array {
        self::limits($files);
        $index=isset($files['index.html'])?'index.html':(isset($files['index.htm'])?'index.htm':'');
        if ($index==='') throw new InvalidArgumentException('Se requiere index.html o index.htm en la raíz del sitio.');
        $pages=[];$clean=[];$seen=[];$scripts=[];$warnings=['Scripts desactivados en edición; vista previa JS aislada disponible. Formularios, CSS externo, SVG y recursos no admitidos se omiten. No se garantiza fidelidad exacta.'];
        $modules=$files;foreach($files as $asset=>$contents)if(strtolower(pathinfo($asset,PATHINFO_EXTENSION))==='css')$files[$asset]=ImportedCanvas::expandCss($asset,$modules);
        $paths=array_keys($files);usort($paths,fn($a,$b)=>($a===$index?-1:($b===$index?1:strcmp($a,$b))));
        foreach ($paths as $path) {
            if (!self::safePath($path)) continue;
            if (isset($seen[strtolower($path)])) throw new InvalidArgumentException('Rutas duplicadas.');
            $seen[strtolower($path)]=true;$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));$data=$files[$path];
            if (in_array($ext,['html','htm','css','js','mjs'],true) && (strlen($data)>1048576 || preg_match('//u',$data)!==1)) throw new InvalidArgumentException('Texto demasiado grande o no UTF-8.');
            if (in_array($ext,['png','jpg','jpeg','gif','webp','ico'],true)) {
                $info=@getimagesizefromstring($data);
                if (!$info || $info[0]>8000 || $info[1]>8000 || !in_array($info['mime'],['image/png','image/jpeg','image/gif','image/webp','image/x-icon','image/vnd.microsoft.icon'],true)) throw new InvalidArgumentException('Imagen inválida.');
            }
            if (in_array($ext,['html','htm'],true)) {
                $title=pathinfo($path,PATHINFO_FILENAME);
                if (preg_match('~<title[^>]*>(.*?)</title>~is',$data,$m)) $title=trim(html_entity_decode(strip_tags($m[1]),ENT_QUOTES|ENT_HTML5,'UTF-8'));
                $slug=$path===$index?'index':trim(preg_replace('/[^a-z0-9-]+/','-',strtolower(preg_replace('/\.[^.]+$/','',$path)??$path))??'','-');
                $slug=substr($slug?:'pagina',0,80);
                if (in_array($slug,array_column($pages,'slug'),true)) $slug.='-'.substr(hash('sha256',$path),0,8);
                if(!str_starts_with($path,'components/'))$pages[]=['slug'=>$slug,'title'=>mb_substr($title?:$slug,0,180),'path'=>$path];
                preg_match_all('~<script\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>~i',$data,$scriptTags);
                $scripts[$path]=[];foreach($scriptTags[1] as $src){$resolved=self::resolve($path,$src);if($resolved!==null&&isset($files[$resolved])&&str_ends_with($resolved,'.js'))$scripts[$path][]=$resolved;}
                $data=self::constrain(self::html($data),$path);
                preg_match_all('~(?:href|src)=["\']([^"\']+)["\']~i',$data,$links);
                foreach ($links[1] as $link) {
                    if (!self::relativeUrl($link) || str_starts_with($link,'#')) continue;
                    $target=self::resolve($path,explode('#',$link,2)[0]);
                    if ($target===null || !array_key_exists($target,$files)) $warnings[]='Recurso/vínculo ausente: '.$path.' → '.$link;
                }
            } elseif ($ext==='css') $data=self::constrain(self::css($data),$path);
            $clean[$path]=$data;
        }
        if (count($pages)>40) throw new InvalidArgumentException('Máximo 40 páginas HTML.');
        $token=bin2hex(random_bytes(16));$dir=self::directory($storage,$token);
        if (!mkdir($dir.'/files',0700,true)) throw new RuntimeException('No se pudo preparar importación.');
        try {
            foreach ($clean as $path=>$data) {
                $dest=$dir.'/files/'.$path;
                if (!is_dir(dirname($dest))) mkdir(dirname($dest),0700,true);
                if (file_put_contents($dest,$data,LOCK_EX)===false) throw new RuntimeException('No se pudo guardar recurso importado.');
                chmod($dest,0600);
            }
            $manifest=['token'=>$token,'owner'=>$owner,'created'=>time(),'pages'=>$pages,'scripts'=>$scripts,'files'=>array_keys($clean),'warnings'=>array_values(array_unique($warnings))];
            file_put_contents($dir.'/manifest.json',json_encode($manifest,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),LOCK_EX);
            return $manifest;
        } catch (\Throwable $e) {self::remove($dir);throw $e;}
    }
    public static function constrain(string $contents,string $path): string {
        $valid=static function(string $url) use($path): bool {
            $url=html_entity_decode($url,ENT_QUOTES|ENT_HTML5,'UTF-8');
            if(str_starts_with($url,'#') || preg_match('~^(https?://|mailto:|tel:)~i',$url))return true;
            return self::resolve($path,explode('#',$url,2)[0])!==null;
        };
        if(in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['html','htm'],true))$contents=preg_replace_callback('~\b(href|src|poster)=(["\'])(.*?)\2~is',fn($m)=>$valid($m[3])?$m[0]:$m[1].'="#"',$contents)??'';
        return preg_replace_callback('~url\(\s*(["\']?)(.*?)\1\s*\)~is',fn($m)=>$valid($m[2])?$m[0]:'none',$contents)??'';
    }
    public static function resolve(string $from,string $relative): ?string {
        if (!self::relativeUrl($relative)) return null;
        $parts=explode('/',dirname($from)==='.'?$relative:dirname($from).'/'.$relative);$stack=[];
        foreach ($parts as $part) {if ($part==='.'||$part==='')continue;if($part==='..'){if(!$stack)return null;array_pop($stack);}else $stack[]=$part;}
        $path=implode('/',$stack);return self::safePath($path)?$path:null;
    }
    public static function pending(string $storage,string $token,int $owner): array {
        $file=self::directory($storage,$token).'/manifest.json';
        $data=is_file($file)?json_decode((string)file_get_contents($file),true):null;
        if (!is_array($data) || $data['owner']!==$owner || $data['created']<time()-3600) throw new InvalidArgumentException('Importación caducada o ajena. Prepara otra.');
        return $data;
    }
    public static function attach(string $storage,string $token,int $owner,int $siteId): void {
        self::pending($storage,$token,$owner);
        if ($siteId<1) throw new InvalidArgumentException('Sitio inválido.');
        $parent=rtrim($storage,'/').'/imported';if(!is_dir($parent))mkdir($parent,0700,true);
        $target=$parent.'/'.$siteId;
        if (file_exists($target) || !rename(self::directory($storage,$token),$target)) throw new RuntimeException('No se pudo asignar importación.');
    }
    public static function detach(string $storage,string $token,int $siteId): void {
        $target=self::directory($storage,$token);
        if($siteId>0 && !file_exists($target))rename(rtrim($storage,'/').'/imported/'.$siteId,$target);
    }
    public static function manifest(string $storage,int $siteId): array {
        if ($siteId<1) throw new InvalidArgumentException('Sitio inválido.');
        $file=rtrim($storage,'/').'/imported/'.$siteId.'/manifest.json';
        return is_file($file)?(json_decode((string)file_get_contents($file),true)?:[]):[];
    }
    public static function file(string $storage,int $siteId,string $path,?string $token=null,int $owner=0): string {
        $manifest=$token===null?self::manifest($storage,$siteId):self::pending($storage,$token,$owner);
        if (!self::safePath($path) || !in_array($path,$manifest['files']??[],true)) throw new InvalidArgumentException('Archivo fuera de la importación.');
        $base=$token===null?rtrim($storage,'/').'/imported/'.$siteId:self::directory($storage,$token);
        $file=$base.'/files/'.$path;
        if (is_link($file) || !is_file($file)) throw new InvalidArgumentException('Archivo inaccesible.');
        return $file;
    }
    public static function preview(string $contents,string $path,string $prefix): string {
        $rewrite=static function(string $url) use($path,$prefix):string {
            if (str_starts_with($url,'#') || preg_match('~^(https?://|mailto:|tel:)~i',$url)) return $url;
            [$relative,$fragment]=array_pad(explode('#',$url,2),2,'');
            $resolved=self::resolve($path,$relative);
            return $resolved===null?'#':$prefix.rawurlencode($resolved).($fragment!==''?'#'.rawurlencode($fragment):'');
        };
        if (in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['html','htm'],true)) {
            $contents=self::html($contents);
            $contents=preg_replace_callback('~\b(href|src|poster)=(["\'])(.*?)\2~is',fn($m)=>$m[1].'="'.htmlspecialchars($rewrite(html_entity_decode($m[3],ENT_QUOTES|ENT_HTML5,'UTF-8')),ENT_QUOTES,'UTF-8').'"',$contents)??'';
        }
        return preg_replace_callback('~url\(\s*(["\']?)(.*?)\1\s*\)~is',fn($m)=>'url("'.$rewrite($m[2]).'")',$contents)??'';
    }
    public static function export(string $storage,int $siteId,string $target): void {
        $manifest=self::manifest($storage,$siteId);
        foreach ($manifest['files']??[] as $path) {
            $source=self::file($storage,$siteId,$path);$dest=$target.'/imported/'.$path;
            if (!is_dir(dirname($dest))) mkdir(dirname($dest),0700,true);
            $contents=(string)file_get_contents($source);$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
            if (in_array($ext,['html','htm'],true)) $contents=self::constrain(self::html(ImportedCanvas::composeFile($source,$path)),$path);
            if ($ext==='css') $contents=self::constrain(self::css($contents),$path);
            // JS is retained as inert source, never referenced from sanitized HTML.
            if (file_put_contents($dest,$contents,LOCK_EX)===false) throw new RuntimeException('Error exportando importación.');
        }
    }
    public static function writeHtml(string $storage,int $siteId,string $path,string $contents): void {
        if (!in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['html','htm'],true)) throw new InvalidArgumentException('Solo HTML importado editable.');
        $file=self::file($storage,$siteId,$path);$contents=self::constrain(self::html($contents),$path);
        $history=rtrim($storage,'/').'/imported/'.$siteId.'/history';if(!is_dir($history))mkdir($history,0700,true);
        if(!copy($file,$history.'/'.bin2hex(random_bytes(12)).'.html'))throw new RuntimeException('No se pudo versionar HTML.');
        $temp=tempnam(dirname($file),'.html-');if($temp===false)throw new RuntimeException('No se pudo guardar HTML.');
        try{file_put_contents($temp,$contents,LOCK_EX);chmod($temp,0600);if(!rename($temp,$file))throw new RuntimeException('No se pudo guardar HTML.');}finally{if(is_file($temp))unlink($temp);}
    }
    public static function serve(string $file,string $path,string $prefix): never {
        $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
        $mime=['html'=>'text/html','htm'=>'text/html','css'=>'text/css','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','ico'=>'image/x-icon','woff'=>'font/woff','woff2'=>'font/woff2'][$ext]??'text/plain';
        header('Content-Type: '.$mime.(str_starts_with($mime,'text/')?'; charset=utf-8':''));
        header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
        header("Content-Security-Policy: sandbox allow-same-origin; default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self'; font-src 'self'; script-src 'none'; connect-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");
        $contents=in_array($ext,['html','htm'],true)?ImportedCanvas::composeFile($file,$path):(string)file_get_contents($file);
        if(in_array($ext,['html','htm','css'],true))$contents=self::preview($contents,$path,$prefix);
        if(in_array($ext,['html','htm'],true))$contents=str_replace('</head>','<style data-lcy-static="1">[data-aos],.reveal,.fade-in,.animate-on-scroll{opacity:1!important;transform:none!important}html{scroll-behavior:auto}button{cursor:pointer}#theme-toggle:empty:before{content:"◐"}#nav-toggle:empty:before{content:"☰"}@media(max-width:650px){.lcy-layout-grid{grid-template-columns:1fr!important}}</style></head>',$contents);
        if(in_array($ext,['html','htm'],true))$contents=str_replace('</head>','<style data-lcy-static="1">'.EditorChrome::iconCss().Builder::canvasCss().'</style></head>',$contents);
        echo $contents;exit;
    }
    public static function remove(string $dir): void {
        if(!is_dir($dir)||is_link($dir))return;
        foreach(new \DirectoryIterator($dir) as $entry){if($entry->isDot())continue;if($entry->isDir()&&!$entry->isLink())self::remove($entry->getPathname());else unlink($entry->getPathname());}rmdir($dir);
    }
}
