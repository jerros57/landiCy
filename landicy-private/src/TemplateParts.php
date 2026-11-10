<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace LandiCy;
final class TemplateParts {
    public static function clean(string $html):string {
        $clean=Importer::html($html);$d=new \DOMDocument();$old=libxml_use_internal_errors(true);try{$d->loadHTML('<?xml encoding="UTF-8">'.$clean,LIBXML_NONET);}finally{libxml_clear_errors();libxml_use_internal_errors($old);}
        $body=$d->getElementsByTagName('body')->item(0);$out='';foreach($body?->childNodes??[] as $n)$out.=$d->saveHTML($n);return $out;
    }
    public static function render(string $template,array $tokens):string {
        $template=self::clean($template);foreach($tokens as $key=>$value)$template=str_replace('{{'.$key.'}}',$value,$template);return $template;
    }
}
