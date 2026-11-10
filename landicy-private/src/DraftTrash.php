<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace LandiCy;
use InvalidArgumentException;
use RuntimeException;
/** Reversible project-level removal: page/content rows and files are never deleted. */
final class DraftTrash {
    private static function folder(string $storage,bool $create=false):string {
        $root=realpath($storage);if(!$root||is_link($storage))throw new RuntimeException('Almacenamiento inseguro.');$dir=$root.'/draft-trash';
        if(is_link($dir))throw new RuntimeException('Papelera insegura.');if($create&&!is_dir($dir)&&!@mkdir($dir,0700)&&!is_dir($dir))throw new RuntimeException('No se pudo preparar la papelera.');return $dir;
    }
    private static function file(string $storage,int $site):string {if($site<1)throw new InvalidArgumentException('Proyecto inválido.');$p=self::folder($storage).'/'.$site.'.json';if(is_link($p))throw new RuntimeException('Registro inseguro.');return $p;}
    public static function projectLock(string $storage,int $site):mixed {self::file($storage,$site);$path=self::folder($storage,true).'/.site-'.$site.'.lock';if(is_link($path))throw new RuntimeException('Bloqueo inseguro.');$lock=fopen($path,'c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Proyecto ocupado.');return $lock;}
    public static function contains(string $storage,int $site):bool {return is_file(self::file($storage,$site));}
    public static function token(array $site,array $pages,bool $trashed):string {return hash('sha256',json_encode([$site['id'],$site['name'],$site['theme_json'],$pages,$trashed],JSON_THROW_ON_ERROR));}
    public static function published(array $pages):bool {foreach($pages as $p)if((int)$p['published_version']>0)return true;return false;}
    public static function change(string $storage,int $site,int $actor,bool $restore):void {
        $dir=self::folder($storage,true);$lockPath=$dir.'/.lock';if(is_link($lockPath))throw new RuntimeException('Bloqueo inseguro.');$lock=fopen($lockPath,'c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Papelera ocupada.');
        try{$file=self::file($storage,$site);if($restore){if(!is_file($file))throw new RuntimeException('El borrador ya está activo.');if(!unlink($file))throw new RuntimeException('No se pudo restaurar.');}
        else{if(is_file($file))throw new RuntimeException('El borrador ya está en la papelera.');$temp=tempnam($dir,'.trash-');if($temp===false)throw new RuntimeException('No se pudo crear el registro.');try{$bytes=json_encode(['site'=>$site,'actor'=>$actor,'at'=>gmdate('c')],JSON_THROW_ON_ERROR);if(file_put_contents($temp,$bytes,LOCK_EX)!==strlen($bytes))throw new RuntimeException('Registro incompleto.');chmod($temp,0600);if(!rename($temp,$file))throw new RuntimeException('No se pudo mover a la papelera.');}finally{if(is_file($temp))unlink($temp);}}}
        finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
