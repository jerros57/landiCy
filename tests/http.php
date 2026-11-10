<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
if(getenv('LANDICY_ISOLATED_TEST')!=='1')throw new RuntimeException('Solo pruebas privadas.');
$base='http://127.0.0.1:18942/';$count=0;
function checkHttp(bool $v,string $label):void{global $count;if(!$v)throw new RuntimeException('FAIL '.$label);$count++;echo "PASS $label\n";}
function request(string $query,string $role='super',?array $form=null):array {
 global $base;$ch=curl_init($base.'?'.$query);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15,CURLOPT_COOKIE=>'PHPSESSID=landicy-test-'.$role]);
 if($form!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>(array_filter($form,fn($value)=>$value instanceof CURLFile)?$form:http_build_query($form))]);
 $raw=curl_exec($ch);if($raw===false)throw new RuntimeException(curl_error($ch));$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$size=curl_getinfo($ch,CURLINFO_HEADER_SIZE);$headers=substr($raw,0,$size);return ['status'=>$status,'headers'=>$headers,'body'=>substr($raw,$size)];
}
function field(string $body,string $name):string{if(!preg_match('/name="'.preg_quote($name,'/').'"[^>]*value="([^"]*)"/',$body,$m))throw new RuntimeException('Campo ausente '.$name);return html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8');}
function location(array $response):string {preg_match('/^Location:\s*([^\r\n]+)/mi',$response['headers'],$m);return ltrim(trim($m[1]??''),'?');}
$edit=request('action=edit&id=1');checkHttp($edit['status']===200,'editor super GET');
$fixture=__DIR__.'/browser-fixtures';if(!is_dir($fixture))mkdir($fixture,0700);
foreach([1,2] as $page){$body=request('action=edit&id=1&page='.$page)['body'];$body=preg_replace('/(name="csrf" value=")[^"]*/','$1synthetic-csrf-not-authentication',$body);file_put_contents($fixture.'/editor-'.$page.'.html',$body);}
foreach(['assigned','other'] as $role){
 foreach(['users','new-site','publish','download','scan','source','import-review','import-preview'] as $action)checkHttp(request('action='.$action.'&id=1',$role)['status']===403,$role.' GET '.$action.' 403');
 checkHttp(request('action=edit&id=1',$role)['status']===($role==='assigned'?200:403),$role.' acceso sitio');
}
$admin=request('action=edit&id=1','assigned');$token=field($admin['body'],'csrf');
foreach(['publish','new-site','users'] as $action)checkHttp(request('action='.$action.'&id=1','assigned',['csrf'=>$token])['status']===403,'admin POST '.$action.' 403');
checkHttp(request('action=edit&id=1','super',[])['status']===419,'POST sin CSRF 419');
$doc=new DOMDocument();@$doc->loadHTML($admin['body']);$xpath=new DOMXPath($doc);$post=[];foreach($xpath->query('//form[@id="editor-form"]//input[@name]') as $node)$post[$node->getAttribute('name')]=$node->getAttribute('value');foreach($xpath->query('//form[@id="editor-form"]//select[@name]') as $node){$selected=null;foreach($node->getElementsByTagName('option') as $option){if($selected===null||$option->hasAttribute('selected'))$selected=$option->getAttribute('value');}$post[$node->getAttribute('name')]=$selected??'';}
$post['blocks_json']='[{"type":"text_box","body":"Guardado por admin asignado","row":"fila","columns":2,"column":1},{"type":"image","src":"","row":"fila","columns":2,"column":2}]';$post['header_brand']='Global modificado';$post['footer_text']='Pie modificado';
checkHttp(request('action=edit&id=1','assigned',$post)['status']===303,'admin asignado guarda borrador');
checkHttp(request('action=edit&id=1','assigned',$post)['status']===400,'guardado obsoleto rechazado');
$second=request('action=preview&id=1&page=2');checkHttp(str_contains($second['body'],'Global modificado')&&str_contains($second['body'],'Pie modificado'),'globales visibles segunda página');
$csrf=field($edit['body'],'csrf');checkHttp(request('action=publish&id=1','super',['csrf'=>$csrf])['status']===303,'super compila sin desplegar');$zip=request('action=download&id=1');checkHttp($zip['status']===200&&str_starts_with($zip['body'],'PK'),'descarga ZIP controlador');
$new=request('action=new-site');$csrf=field($new['body'],'csrf');$report=request('action=new-site','super',['csrf'=>$csrf,'origin'=>'directory','relative'=>'','name'=>'Importado sintético','slug'=>'importado-http-'.bin2hex(random_bytes(3)),'domain'=>'','primary'=>'#225cf0','accent'=>'#111827']);checkHttp($report['status']===303&&str_contains(location($report),'import-review'),'prepara importación hosting');
$review=request(location($report));checkHttp($review['status']===200&&str_contains($review['body'],'Confirmar y crear'),'informe previo');preg_match('/token=([a-f0-9]{32})/',location($report),$m);$importToken=$m[1];
$preview=request('action=import-preview&token='.$importToken.'&file=index.html');checkHttp($preview['status']===200&&str_contains(html_entity_decode($preview['body']),'Importación aislada'),'preview importación');checkHttp(!str_contains($preview['body'],'<script'),'preview sin scripts');
$confirmed=request(location($report),'super',['csrf'=>field($review['body'],'csrf')]);checkHttp($confirmed['status']===303&&str_contains(location($confirmed),'action=site'),'confirmar crea sitio');preg_match('/id=(\d+)/',location($confirmed),$m);$siteId=(int)$m[1];checkHttp($siteId>1,'nuevo ID independiente');
checkHttp(request('action=import-file&id='.$siteId.'&file=index.html','assigned')['status']===403,'admin no accede importación ajena');
checkHttp(request('action=import-file&id='.$siteId.'&file=../config.php')['status']===400,'archivo importado traversal rechazado');
$imported=request('action=import-file&id='.$siteId.'&file=index.html');checkHttp($imported['status']===200&&str_contains($imported['body'],'file=site.css'),'recursos importados resueltos');
$view=request('action=preview&id='.$siteId);checkHttp($view['status']===200&&str_contains(html_entity_decode($view['body']),'Importación aislada'),'proyecto importado renderiza');
$code=request('action=import-code&id='.$siteId.'&file=index.html');checkHttp($code['status']===200&&str_contains($code['body'],'Extraer texto'),'HTML importado editable');$save=request('action=import-code&id='.$siteId.'&file=index.html','super',['csrf'=>field($code['body'],'csrf'),'file_versions'=>field($code['body'],'file_versions'),'contents'=>'<p>Edición aislada</p><script>evil()</script>']);checkHttp($save['status']===303,'guardar HTML importado');$after=request('action=import-file&id='.$siteId.'&file=index.html');checkHttp(str_contains(html_entity_decode($after['body']),'Edición aislada')&&!str_contains($after['body'],'script'),'HTML guardado sanitizado');
$version=field($code['body'],'version');$convert=request('action=import-code&id='.$siteId.'&file=index.html','super',['csrf'=>field($code['body'],'csrf'),'operation'=>'convert','version'=>$version]);checkHttp($convert['status']===303&&str_contains(location($convert),'action=edit'),'convertir texto a bloque');
checkHttp(request('action=publish&id='.$siteId,'super',['csrf'=>$csrf])['status']===303,'compilar importación');
$fixtureRoot=realpath(__DIR__.'/../test-fixtures');if(!$fixtureRoot)throw new RuntimeException('Fixture requerida.');
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');file_put_contents($fixtureRoot.'/upload.png',$png);
$upload=request('action=media-library&id=1','assigned',['csrf'=>$token,'image'=>new CURLFile($fixtureRoot.'/upload.png','image/png','upload.png')]);checkHttp($upload['status']===303,'subida HTTP imagen');
$library=request('action=media-library&id=1','assigned');preg_match('~img/([a-f0-9]{24}\.png)~',$library['body'],$imageMatch);checkHttp(isset($imageMatch[1]),'biblioteca muestra imagen');
$media=request('action=media&id=1&file='.$imageMatch[1],'assigned');checkHttp($media['status']===200&&$media['body']===$png,'imagen privada íntegra HTTP');
$sourceZip=$fixtureRoot.'/upload.zip';$archive=new ZipArchive();$archive->open($sourceZip,ZipArchive::CREATE|ZipArchive::OVERWRITE);$archive->addFromString('index.html','<p>ZIP via HTTP</p>');$archive->addFromString('style.css','body{color:navy}');$archive->close();
$zipStage=request('action=new-site','super',['csrf'=>$csrf,'origin'=>'zip','name'=>'ZIP sintético','slug'=>'zip-http-'.bin2hex(random_bytes(3)),'domain'=>'','archive'=>new CURLFile($sourceZip,'application/zip','site.zip')]);checkHttp($zipStage['status']===303&&str_contains(location($zipStage),'import-review'),'subida ZIP HTTP prepara');
$zipReview=request(location($zipStage));$zipConfirm=request(location($zipStage),'super',['csrf'=>field($zipReview['body'],'csrf')]);checkHttp($zipConfirm['status']===303&&str_contains(location($zipConfirm),'action=site'),'subida ZIP HTTP crea proyecto');

echo "$count comprobaciones HTTP PASS\n";
