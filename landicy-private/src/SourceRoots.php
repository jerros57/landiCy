<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace LandiCy;
use InvalidArgumentException;
/** Exact, administrator-configured roots. Never accepts a new root from a request. */
final class SourceRoots {
    public static function all(array $config): array {
        $roots=['default'=>(string)($config['scan_root']??'')];
        foreach(($config['scan_roots']??[]) as $key=>$root)if(is_string($key)&&preg_match('/^[a-z0-9-]{1,30}$/D',$key)&&is_string($root))$roots[$key]=$root;
        $extra=dirname(__DIR__).'/scan-roots.php';
        if(is_file($extra)&&!is_link($extra)){ $verified=require $extra; if(is_array($verified))foreach($verified as $key=>$root)if(is_string($key)&&preg_match('/^[a-z0-9-]{1,30}$/D',$key)&&is_string($root))$roots[$key]=$root; }
        return array_filter($roots,static fn($root)=>$root!==''&&is_dir($root)&&!is_link($root));
    }
    public static function resolve(array $config,string $key,string $relative): array {
        $roots=self::all($config);if(!isset($roots[$key]))throw new InvalidArgumentException('Raíz no autorizada. Selecciona una raíz configurada.');
        $root=realpath($roots[$key]);if($root===false)throw new InvalidArgumentException('Raíz no disponible.');
        $relative=trim($relative);if(strlen($relative)>240||str_contains($relative,"\0")||str_contains($relative,'\\'))throw new InvalidArgumentException('Ruta inválida.');
        if(str_starts_with($relative,'/')){
            $real=realpath($relative);if($real===false||($real!==$root&&!str_starts_with($real,$root.'/')))throw new InvalidArgumentException('Carpeta fuera de la raíz seleccionada.');
            $relative=ltrim(substr($real,strlen($root)),'/');
        }
        if($relative!==''&&!Scanner::isSafePath($relative))throw new InvalidArgumentException('Usa una subcarpeta de la raíz seleccionada, sin .. ni URL.');
        $current=$root;foreach($relative===''?[]:explode('/',$relative) as $part){$current.='/'.$part;if(is_link($current))throw new InvalidArgumentException('No se admiten enlaces simbólicos en la ruta.');}
        $target=realpath($current);if($target===false||!is_dir($target)||($target!==$root&&!str_starts_with($target,$root.'/')))throw new InvalidArgumentException('Carpeta fuera de la raíz permitida o inexistente.');
        return [$root,$relative];
    }
    public static function options(array $config,string $selected='default'): string {
        $html='<label>Raíz autorizada<select name="source_root">';foreach(self::all($config) as $key=>$root)$html.='<option value="'.\e($key).'"'.($selected===$key?' selected':'').'>'.\e($key).'</option>';return $html.'</select></label>';
    }
}
