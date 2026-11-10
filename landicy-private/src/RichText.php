<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace LandiCy;
final class RichText {
    public static function clean(string $html):string {
        if(strlen($html)>24000)throw new \InvalidArgumentException('Texto con formato demasiado largo.');
        if($html==='')return '';$doc=new \DOMDocument();$prior=libxml_use_internal_errors(true);
        try{$doc->loadHTML('<?xml encoding="UTF-8"><div id="lcy-rich-root">'.$html.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}finally{libxml_clear_errors();libxml_use_internal_errors($prior);}
        $root=$doc->getElementById('lcy-rich-root');if(!$root)return '';
        $walk=static function(\DOMNode $node)use(&$walk):string{$out='';foreach($node->childNodes as $child){if($child instanceof \DOMText){$out.=htmlspecialchars($child->textContent,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');continue;}if(!$child instanceof \DOMElement)continue;$tag=strtolower($child->tagName);if(in_array($tag,['script','style','iframe','object','embed','svg','math','template','form','img','input'],true))continue;$inner=$walk($child);if(!in_array($tag,['strong','b','em','i','u','br','span','a','ul','ol','li'],true)){$out.=$inner;continue;}$attrs='';if($tag==='a'){$href=Builder::url($child->getAttribute('href'));if($href==='#'&&preg_match('/^[a-z0-9-]+\.html$/D',$child->getAttribute('href')))$href=$child->getAttribute('href');$attrs=' href="'.\e($href).'"';if($child->getAttribute('target')==='_blank')$attrs.=' target="_blank" rel="noopener noreferrer"';}$out.='<'.$tag.$attrs.'>'.($tag==='br'?'':$inner.'</'.$tag.'>');}return $out;};
        return $walk($root);
    }
    public static function render(array $block,string $field):string {return isset($block['rich'][$field])?self::clean((string)$block['rich'][$field]):\e((string)($block[$field]??''));}
}
