<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
require __DIR__.'/../landicy-private/src/bootstrap.php';
use LandiCy\Importer;use LandiCy\ImportedCanvas;use LandiCy\ScriptPreview;use LandiCy\EditorChrome;use LandiCy\Access;
$n=0;function ok052(bool $v,string $label):void{global $n;if(!$v)throw new RuntimeException('FAIL '.$label);$n++;echo "PASS $label\n";}
$base=sys_get_temp_dir().'/landicy-052-'.bin2hex(random_bytes(6));mkdir($base,0700);mkdir($base.'/source',0700);mkdir($base.'/source/js',0700);mkdir($base.'/source/components',0700);
try{
 $source='<html><head><title>Snapshot</title></head><body><div id="header-container"></div><h1>Snapshot</h1><i class="fa-solid fa-eye"></i><script src="js/z.js"></script><script src="js/a.js"></script><script src="https://external.invalid/evil.js"></script></body></html>';
 file_put_contents($base.'/source/index.html',$source);file_put_contents($base.'/source/js/z.js','window.order=[1];');file_put_contents($base.'/source/js/a.js','window.order.push(2);');file_put_contents($base.'/source/components/header.html','<header>Snapshot header</header>');
 $m=Importer::prepareDirectory($base.'/storage',1,$base.'/source','');Importer::attach($base.'/storage',$m['token'],1,1);$m=Importer::manifest($base.'/storage',1);
 ok052($m['scripts']['index.html']===['js/z.js','js/a.js'],'Original local script order retained');
 ok052(ScriptPreview::scripts($m,'index.html')===['js/z.js','js/a.js'],'Only attached classic scripts selected');
 $old=['files'=>['js/main.js','js/theme.js','index.html']];ok052(ScriptPreview::scripts($old,'index.html')===['js/theme.js','js/main.js'],'Older snapshots keep main entry last');
 $html=ScriptPreview::html($base.'/storage',1,'index.html','synthetic-test-nonce');ok052(str_contains($html,'Snapshot header'),'Preview retains shared component composition');ok052(str_contains($html,'window.order=[1]')&&str_contains($html,'window.order.push(2)'),'Local copied JavaScript included');ok052(!str_contains($html,'external.invalid'),'No external script reference');
 preg_match_all('/<script\b([^>]*)>/i',$html,$tags);ok052(count($tags[0])===3&&count(array_filter($tags[1],fn($a)=>str_contains($a,'nonce="synthetic-test-nonce"')))===3,'All preview script tags nonced');
 ok052(str_contains($html,'Solo recursos de la copia privada'),'Fetch limited to resource map');ok052(str_contains($html,'Object.defineProperty(window,key'),'Storage is ephemeral inside sandbox');
 $editor=Importer::preview(ImportedCanvas::composeFile(Importer::file($base.'/storage',1,'index.html'),'index.html'),'index.html','?action=import-file&id=1&file=');ok052(!str_contains($editor,'<script'),'Editor document remains script free');
 $decoded=ImportedCanvas::decode('<p data-lcy-selected="1">Text</p><style data-lcy-static="1">.editor{}</style>','index.html',1,$m);ok052(!str_contains($decoded,'data-lcy-static')&&!str_contains($decoded,'data-lcy-selected'),'Transient editor styles stripped');
 $tabs=EditorChrome::tabs();foreach(['Insertar','Editar','Páginas','Temas','Cabecera y pie'] as $label)ok052(str_contains($tabs,$label),'Shared tab '.$label);
 ok052(str_contains(EditorChrome::iconCss(),'.fa-eye::before')&&str_contains(EditorChrome::iconCss(),'.fa-moon::before'),'Local icon fallbacks available');
 ok052(Access::allows('import-live','admin',true)&&!Access::allows('import-live','admin',false),'Interactive preview respects assignment');ok052(!Access::allows('import-live','unknown'),'Interactive preview denies unknown roles');
 ok052(hash_file('sha256',$base.'/source/index.html')===hash('sha256',$source),'Original source untouched');
 echo "$n v0.5.2 checks PASS\n";
}finally{Importer::remove($base);}
