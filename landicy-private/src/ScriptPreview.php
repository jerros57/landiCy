<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace LandiCy;
/** Original scripts run only in an opaque-origin sandbox, never in the editor. */
final class ScriptPreview {
    private static function embedded(string $html,string $path,string $prefix,array $assets,array $css):string {
        $html=Importer::preview($html,$path,$prefix);
        $assetName=static function(string $url):string{parse_str((string)parse_url(html_entity_decode($url,ENT_QUOTES|ENT_HTML5,'UTF-8'),PHP_URL_QUERY),$q);return is_string($q['file']??null)?$q['file']:'';};
        $urls=static function(string $value)use($assets,$assetName):string{return preg_replace_callback('~url\(\s*(["\']?)(.*?)\1\s*\)~is',static fn($m)=>str_starts_with($m[2],'data:')?$m[0]:(isset($assets[$assetName($m[2])])?'url("'.$assets[$assetName($m[2])].'")':'none'),$value)??'';};
        $doc=new \DOMDocument();$prior=libxml_use_internal_errors(true);try{$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}finally{libxml_clear_errors();libxml_use_internal_errors($prior);}
        $x=new \DOMXPath($doc);foreach(iterator_to_array($x->query('//link')) as $link){$name=$assetName($link->getAttribute('href'));if(isset($css[$name])){$style=$doc->createElement('style');$style->textContent=$urls($css[$name]);$link->parentNode->replaceChild($style,$link);}else $link->parentNode->removeChild($link);}
        foreach($x->query('//*') as $node){foreach(['src','poster','data-lcy-light-src','data-lcy-dark-src'] as $attr)if($node->hasAttribute($attr)){$name=$assetName($node->getAttribute($attr));if(isset($assets[$name])){$node->setAttribute($attr,$assets[$name]);if($attr==='src')$node->setAttribute('data-lcy-preview-asset',$name);}}if($node->hasAttribute('style'))$node->setAttribute('style',$urls($node->getAttribute('style')));}
        foreach($x->query('//style') as $style)$style->textContent=$urls($style->textContent);
        foreach(iterator_to_array($doc->childNodes) as $n)if($n->nodeType===XML_PI_NODE)$doc->removeChild($n);return (string)$doc->saveHTML();
    }
    public static function scripts(array $manifest,string $path):array {
        $scripts=$manifest['scripts'][$path]??array_values(array_filter($manifest['files']??[],static fn($p)=>str_ends_with($p,'.js')));
        if(!isset($manifest['scripts'][$path]))usort($scripts,static fn($a,$b)=>(basename($a)==='main.js'?1:0)<=>(basename($b)==='main.js'?1:0));
        return array_values(array_filter($scripts,static fn($p)=>is_string($p)&&in_array($p,$manifest['files']??[],true)&&str_ends_with($p,'.js')));
    }
    public static function html(string $storage,int $id,string $path,string $nonce):string {
        $manifest=Importer::manifest($storage,$id);$file=Importer::file($storage,$id,$path);
        if(!in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['html','htm'],true))throw new \InvalidArgumentException('Selecciona una página HTML.');
        $prefix='?action=import-file&id='.$id.'&file=';$assets=[];$css=[];
        $types=['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','ico'=>'image/x-icon','woff'=>'font/woff','woff2'=>'font/woff2'];
        foreach($manifest['files'] as $asset){$ext=strtolower(pathinfo($asset,PATHINFO_EXTENSION));if(isset($types[$ext]))$assets[$asset]='data:'.$types[$ext].';base64,'.base64_encode((string)file_get_contents(Importer::file($storage,$id,$asset)));if($ext==='css')$css[$asset]=Importer::preview((string)file_get_contents(Importer::file($storage,$id,$asset)),$asset,$prefix);}
        $html=self::embedded(ImportedCanvas::composeFile($file,$path),$path,$prefix,$assets,$css);
        $resources=[];foreach($manifest['files'] as $asset)if(preg_match('/\.html?$/iD',$asset))$resources[$asset]=self::embedded((string)file_get_contents(Importer::file($storage,$id,$asset)),in_array($asset,ImportedCanvas::PARTS,true)?$path:$asset,$prefix,$assets,$css);foreach($css as $asset=>$value)$resources[$asset]=$value;
        $model=['path'=>$path,'prefix'=>$prefix,'files'=>$manifest['files'],'resources'=>$resources,'assets'=>$assets];
        $safe=JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR;
        $runtime=(string)file_get_contents(dirname(__DIR__,2).'/public_html/landicy-admin/assets/sandbox-preview.js');
        $script='<script nonce="'.$nonce.'">window.LANDICY_SNAPSHOT='.json_encode($model,$safe).';'.$runtime.'</script>';
        foreach(self::scripts($manifest,$path) as $asset){$code=(string)file_get_contents(Importer::file($storage,$id,$asset));if(strlen($code)>1048576)continue;$script.='<script nonce="'.$nonce.'">'.str_ireplace('</script','<\\/script',$code)."\n</script>";}
        $html=str_ireplace('</head>','<style>'.EditorChrome::iconCss().Builder::canvasCss().'</style></head>',$html);
        return str_ireplace('</body>',$script.'</body>',$html);
    }
    public static function serve(string $storage,int $id,string $path):never {
        $nonce=bin2hex(random_bytes(24));header_remove('X-Frame-Options');
        header('Content-Type: text/html; charset=utf-8');header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: sandbox allow-scripts; default-src 'none'; script-src 'nonce-$nonce'; style-src 'unsafe-inline'; img-src data:; font-src data:; connect-src 'none'; base-uri 'none'; form-action 'none'; frame-src 'none'; frame-ancestors 'self'");
        echo self::html($storage,$id,$path,$nonce);exit;
    }
}
