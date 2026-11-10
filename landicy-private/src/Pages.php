<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace LandiCy;
use InvalidArgumentException;
use RuntimeException;
/** Additive page metadata inside the existing site theme; never deletes page rows. */
final class Pages {
    public static function metadata(mixed $input): array {
        if(!is_array($input))return [];$out=[];
        foreach(array_slice($input,0,200,true) as $id=>$value){
            if(!ctype_digit((string)$id)||!is_array($value))continue;
            $status=(string)($value['status']??'active');if(!in_array($status,['active','hidden','archived','trash'],true))$status='active';
            $out[(string)$id]=['status'=>$status,'order'=>max(-10000,min(10000,(int)($value['order']??$id))),'layout'=>is_array($value['layout']??null)?Builder::normalizeLayout(['layout'=>$value['layout']]):null];
        }return $out;
    }
    public static function listing(array $pages,array $theme,bool $includeInactive=false,bool $navigation=false): array {
        $meta=self::metadata($theme['pages']??[]);$result=[];
        foreach($pages as $p){$p['state']=$meta[(string)$p['id']]??['status'=>'active','order'=>(int)$p['id'],'layout'=>null];
            if(!$includeInactive&&in_array($p['state']['status'],['archived','trash'],true))continue;
            if($navigation&&$p['state']['status']==='hidden')continue;$result[]=$p;}
        usort($result,static fn($a,$b)=>[$a['slug']==='index'?0:1,$a['state']['order'],(int)$a['id']]<=>[$b['slug']==='index'?0:1,$b['state']['order'],(int)$b['id']]);return $result;
    }
    public static function saveHistory(string $storage,int $siteId,string $theme): void {
        if($siteId<1)throw new InvalidArgumentException('Sitio inválido.');
        $dir=rtrim($storage,'/').'/layout-history/'.$siteId;
        if(is_link($dir))throw new RuntimeException('Historial inseguro.');
        if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('No se pudo versionar el diseño.');
        $file=$dir.'/'.gmdate('YmdHis').'-'.bin2hex(random_bytes(6)).'.json';
        if(file_put_contents($file,$theme,LOCK_EX)===false)throw new RuntimeException('No se pudo conservar el diseño.');chmod($file,0600);
    }
    public static function history(string $storage,int $siteId): array {
        $out=[];foreach(glob(rtrim($storage,'/').'/layout-history/'.$siteId.'/*.json')?:[] as $file)if(!is_link($file)&&preg_match('/^\d{14}-[a-f0-9]{12}\.json$/D',basename($file)))$out[]=basename($file);rsort($out);return $out;
    }
    public static function historical(string $storage,int $siteId,string $token): array {
        if(!in_array($token,self::history($storage,$siteId),true))throw new InvalidArgumentException('Revisión de diseño ajena o inválida.');
        return json_decode((string)file_get_contents(rtrim($storage,'/').'/layout-history/'.$siteId.'/'.$token),true,32,JSON_THROW_ON_ERROR);
    }
}
