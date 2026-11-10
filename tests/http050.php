<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
require __DIR__.'/http.php';
function pagePost(int $page,string $operation,array $extra=[]):array{$get=request('action=pages&id=1','assigned');return request('action=pages&id=1','assigned',array_merge(['csrf'=>field($get['body'],'csrf'),'theme_hash'=>field($get['body'],'theme_hash'),'page_id'=>$page,'operation'=>$operation],$extra));}
checkHttp(request('action=pages&id=1','other')['status']===403,'gestión de páginas deniega proyecto ajeno');
checkHttp(pagePost(2,'rename',['title'=>'Contactos recuperados'])['status']===303,'renombrar sin cambiar slug');
checkHttp(str_contains(request('action=preview&id=1&page=2')['body'],'Contactos recuperados'),'vista previa conserva página');
checkHttp(pagePost(2,'trash')['status']===303,'papelera reversible');checkHttp(request('action=preview&id=1&page=2')['status']===400,'papelera no visible');
checkHttp(pagePost(2,'active')['status']===303&&request('action=preview&id=1&page=2')['status']===200,'restaurar ID original');
checkHttp(pagePost(1,'trash')['status']===400,'principal no va a papelera');
checkHttp(pagePost(2,'duplicate')['status']===303,'duplicar página conserva original');
$managed=request('action=pages&id=1');checkHttp(str_contains($managed['body'],'(copia)')&&str_contains($managed['body'],'/segunda'),'duplicada slug único y original permanece');
checkHttp(pagePost(2,'layout',['custom_layout'=>'1','header_brand'=>'Excepción contacto','footer_text'=>'Pie contacto'])['status']===303,'guardar excepción');checkHttp(str_contains(request('action=preview&id=1&page=2')['body'],'Excepción contacto'),'excepción aplicada');
checkHttp(pagePost(2,'restore-revision',['revision'=>9999,'version'=>1])['status']===400,'rechaza revisión inexistente sin sobrescribir');
$editor=request('action=edit&id=1');$dom=new DOMDocument();@$dom->loadHTML($editor['body']);$xp=new DOMXPath($dom);$form=[];foreach($xp->query('//form[@id="editor-form"]//input[@name]') as $n)$form[$n->getAttribute('name')]=$n->getAttribute('value');$form['blocks_json']=json_encode([['type'=>'embed','title'=>'YouTube solicitado','url'=>'https://www.youtube-nocookie.com/embed/qIhkkTHTQCU?si=abc&controls=0&start=20']]);
checkHttp(request('action=edit&id=1','super',$form)['status']===303,'guardar vídeo solicitado');$preview=request('action=preview&id=1');checkHttp(str_contains($preview['body'],'<iframe title="YouTube solicitado"')&&str_contains($preview['headers'],'https://www.youtube-nocookie.com'),'preview iframe y CSP');
$code=request('action=code&id=1');$hash=field($code['body'],'code_hash');$csrf=field($code['body'],'csrf');checkHttp(request('action=code&id=1','super',['csrf'=>$csrf,'code_hash'=>$hash,'contents'=>'<p>Código seguro</p>'])['status']===303,'código con control de concurrencia');checkHttp(request('action=code&id=1','super',['csrf'=>$csrf,'code_hash'=>$hash,'contents'=>'Obsoleto'])['status']===400,'código obsoleto rechazado');
$scan=request('action=scan&id=1');$csrf=field($scan['body'],'csrf');checkHttp(request('action=scan&id=1','super',['csrf'=>$csrf,'source_kind'=>'directory','source_root'=>'default','relative'=>''])['status']===303,'análisis raíz autorizada');checkHttp(request('action=scan&id=1','super',['csrf'=>$csrf,'source_kind'=>'directory','source_root'=>'other','relative'=>''])['status']===400,'análisis raíz arbitraria rechazado');

checkHttp(pagePost(2,'hidden')['status']===303,'ocultar mantiene página');
checkHttp(request('action=preview&id=1&page=2')['status']===200,'página oculta permite enlace directo');
checkHttp(pagePost(2,'active')['status']===303,'restaurar menú de página oculta');
$manage=request('action=pages&id=1');$csrf=field($manage['body'],'csrf');
$stale=['csrf'=>$csrf,'theme_hash'=>field($manage['body'],'theme_hash'),'page_id'=>2,'operation'=>'rename','title'=>'Nombre obsoleto'];
checkHttp(pagePost(2,'order',['order'=>-10])['status']===303,'ordenar sin cambiar slug');
checkHttp(request('action=pages&id=1','super',$stale)['status']===400,'catálogo obsoleto rechazado');
$current=request('action=edit&id=1');$v=field($current['body'],'version');
checkHttp(pagePost(1,'restore-revision',['revision'=>2,'version'=>$v])['status']===303,'restaurar revisión válida como nueva versión');
checkHttp(request('action=edit&id=1')['status']===200,'página conserva edición tras restauración');
echo "$count comprobaciones HTTP totales PASS\n";

