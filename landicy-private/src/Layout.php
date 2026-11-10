<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace LandiCy;
final class Layout {
    /** Flat backwards-compatible blocks, grouped by stable row key and column. */
    public static function normalize(array $block): array {
        $row=(string)($block['row']??'');
        if(!preg_match('/^[a-z0-9-]{1,40}$/D',$row))$row='';
        $widths=(string)($block['widths']??'1 1 1');if(!preg_match('/^(?:[1-9]|1[0-2])(?: (?:[1-9]|1[0-2])){0,3}$/D',$widths))$widths='1 1 1';
        $rich=[];foreach(is_array($block['rich']??null)?$block['rich']:[] as $key=>$value)if(in_array($key,['body','title','subtitle','left','right','button_text','caption'],true)&&is_string($value))$rich[$key]=RichText::clean($value);
        return ['min_height'=>max(0,min(1600,(int)($block['min_height']??0))),'rich'=>$rich,'canvas'=>!empty($block['canvas']),'grid_span'=>max(1,min(12,(float)($block['grid_span']??12))),'grid_start'=>max(1,min(12,(float)($block['grid_start']??1))),'max_width'=>max(80,min(2400,(int)($block['max_width']??2400))),'space_before'=>max(0,min(120,(int)($block['space_before']??0))),
            'font_size'=>max(0,min(160,(int)($block['font_size']??0))),'line_height'=>max(1,min(3,(float)($block['line_height']??1.6))),'letter_spacing'=>max(-2,min(12,(float)($block['letter_spacing']??0))),
            'heading_level'=>max(1,min(4,(int)($block['heading_level']??2))),'image_ratio'=>in_array($block['image_ratio']??'auto',['auto','1/1','4/3','16/9','3/4'],true)?($block['image_ratio']??'auto'):'auto','image_position'=>in_array($block['image_position']??'center',['center','top','bottom','left','right'],true)?($block['image_position']??'center'):'center','link_target'=>($block['link_target']??'')==='_blank'?'_blank':'_self',
            'full_width'=>!empty($block['full_width']),'background_image'=>Builder::imageUrl((string)($block['background_image']??'')),
            'widths'=>$widths,'gap'=>max(0,min(80,(int)($block['gap']??24))),'border_width'=>max(0,min(12,(int)($block['border_width']??0))),'radius'=>max(0,min(80,(int)($block['radius']??0))),'border_color'=>preg_match('/^#[a-f0-9]{6}$/iD',(string)($block['border_color']??''))?$block['border_color']:'#dce2eb','block_align'=>in_array($block['block_align']??'',['left','center','right'],true)?$block['block_align']:'left','row'=>$row,'columns'=>max(1,min(4,(int)($block['columns']??1))),'column'=>max(1,min(4,(int)($block['column']??1))),
            'spacing'=>max(0,min(120,(int)($block['spacing']??0))),
            'background'=>preg_match('/^#[a-f0-9]{6}$/iD',(string)($block['background']??''))?$block['background']:'',
            'bold'=>!empty($block['bold']),'italic'=>!empty($block['italic']),'underline'=>!empty($block['underline']),
            'text_color'=>preg_match('/^#[a-f0-9]{6}$/iD',(string)($block['text_color']??''))?$block['text_color']:'',
            'font'=>array_key_exists((string)($block['font']??''),Builder::fonts())?$block['font']:'system'];
    }
    public static function style(array $block): string {
        $b=self::normalize($block);$style='padding-block:'.$b['spacing'].'px;border:'.$b['border_width'].'px solid '.$b['border_color'].';border-radius:'.$b['radius'].'px;text-align:'.$b['block_align'].';';
        if($b['min_height'])$style.='min-height:'.$b['min_height'].'px;';
        if($b['background']!=='')$style.='background:'.$b['background'].';';
        if($b['text_color']!=='')$style.='color:'.$b['text_color'].';';
        $style.='font-weight:'.($b['bold']?'700':'400').';font-style:'.($b['italic']?'italic':'normal').';text-decoration:'.($b['underline']?'underline':'none').';';
        $style.='font-family:'.Builder::fonts()[$b['font']].';';
        if($b['font_size'])$style.='font-size:'.$b['font_size'].'px;--lcy-font-size:'.$b['font_size'].'px;';$style.='line-height:'.$b['line_height'].';letter-spacing:'.$b['letter_spacing'].'px;';
        if($b['canvas']){$span=min($b['grid_span'],13-$b['grid_start']);$style.='box-sizing:border-box;width:'.round($span/12*100,4).'%;max-width:'.$b['max_width'].'px;margin-left:'.round(($b['grid_start']-1)/12*100,4).'%;margin-right:0;margin-top:'.$b['space_before'].'px;';}
        if($b['background_image']!=='')$style.='background-image:url("'.str_replace('"','%22',$b['background_image']).'");background-size:cover;background-position:center;';
        return $style;
    }
    public static function render(array $blocks,array $parts): string {
        $rows=[];$order=[];
        foreach($blocks as $idx=>$block){$b=self::normalize($block);$key=$b['row']!==''?'row-'.$b['row']:'single-'.$idx;if(!isset($rows[$key])){$rows[$key]=['columns'=>$b['row']!==''?$b['columns']:1,'widths'=>$b['widths'],'gap'=>$b['gap'],'cells'=>[]];$order[]=$key;}$col=min($rows[$key]['columns'],$b['column']);$rows[$key]['cells'][$col][]='<div class="content-block'.($b['canvas']?' lcy-flow':'').($b['full_width']?' lcy-full':'').'" style="'.\e(self::style($block)).'">'.($parts[$idx]??'').'</div>';}
        $html='';foreach($order as $key){$row=$rows[$key];if(str_starts_with($key,'single-')){$html.=implode('',$row['cells'][1]??[]);continue;}$tracks=array_slice(array_pad(explode(' ',$row['widths']),4,'1'),0,$row['columns']);$grid=implode(' ',array_map(static fn($n)=>'minmax(0,'.$n.'fr)',$tracks));$html.='<div class="content-row columns-'.$row['columns'].'" style="--row-tracks:'.$grid.';gap:'.$row['gap'].'px">';for($c=1;$c<=$row['columns'];$c++)$html.='<div class="content-cell">'.implode('',$row['cells'][$c]??[]).'</div>';$html.='</div>';}
        return $html;
    }
}
