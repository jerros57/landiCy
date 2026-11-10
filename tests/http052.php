<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
require __DIR__.'/http051.php';
$zip=$fixtureRoot.'/runtime.zip';$z=new ZipArchive();$z->open($zip,ZipArchive::CREATE|ZipArchive::OVERWRITE);
$runtimeHtml='<html><head><title>Runtime snapshot</title><link rel="stylesheet" href="style.css"></head><body><div id="header-container"></div><main><h1>JS snapshot</h1><i class="fa-solid fa-eye"></i><img class="hero-logo-img" src="img/logo.png" alt="Logo"></main><script src="js/main.js"></script></body></html>';
$runtimeJs=<<<'JS'
document.addEventListener('DOMContentLoaded',async()=>{
document.querySelector('h1').dataset.jsReady='yes';
localStorage.setItem('demo','yes');document.body.dataset.memory=localStorage.getItem('demo');
const resource=await fetch(`${location.origin}/components/header.html`);document.getElementById('header-container').innerHTML=await resource.text();
document.getElementById('theme-toggle').onclick=()=>{document.body.dataset.theme='dark';document.querySelector('.hero-logo-img').src='img/logo-white.png';};
try{parent.document.body.dataset.escaped='yes';}catch{document.body.dataset.parentBlocked='yes';}
try{await fetch('https://external.invalid/private');}catch{document.body.dataset.fetchBlocked='yes';}
});
JS;
foreach(['index.html'=>$runtimeHtml,'components/header.html'=>'<header><button id="theme-toggle">Cambiar tema</button></header>','style.css'=>'body{background:white;color:#172033}body[data-theme=dark]{background:#10131c;color:white}main{padding:24px}img{width:100px}','img/logo.png'=>$png,'img/logo-white.png'=>$png,'js/main.js'=>$runtimeJs] as $name=>$data)$z->addFromString($name,$data);$z->close();
$created=request('action=new-site','super',['csrf'=>$csrf,'origin'=>'zip','name'=>'Runtime snapshot','slug'=>'runtime-'.bin2hex(random_bytes(3)),'domain'=>'','archive'=>new CURLFile($zip,'application/zip','runtime.zip')]);checkHttp($created['status']===303,'runtime ZIP prepared');$review=request(location($created));$confirmed=request(location($created),'super',['csrf'=>field($review['body'],'csrf')]);parse_str(location($confirmed),$target);$runtimeId=(int)$target['id'];checkHttp($runtimeId>1,'runtime copy attached');
$edit=request('action=import-visual&id='.$runtimeId);checkHttp($edit['status']===200&&str_contains($edit['body'],'data-tab="insert"')&&str_contains($edit['body'],'data-tab="layout"'),'same editor navigation as native creator');checkHttp(str_contains($edit['body'],'sandbox="allow-scripts"')&&!str_contains($edit['body'],'sandbox="allow-scripts allow-same-origin"'),'interactive iframe has opaque origin');
checkHttp(request('action=import-live&id='.$runtimeId,'assigned')['status']===403,'interactive preview rejects foreign admin');checkHttp(request('action=import-live&id='.$runtimeId.'&file=../config.php')['status']===400,'interactive preview rejects traversal');checkHttp(request('action=import-live&id='.$runtimeId.'&file=js/main.js')['status']===400,'interactive preview rejects script as document');
$live=request('action=import-live&id='.$runtimeId.'&file=index.html');checkHttp($live['status']===200&&str_contains($live['body'],'jsReady'),'original JS available in interactive preview');checkHttp(str_contains($live['headers'],'sandbox allow-scripts;')&&!str_contains($live['headers'],'allow-same-origin'),'HTTP CSP enforces opaque sandbox');checkHttp(str_contains($live['headers'],"connect-src 'none'")&&str_contains($live['headers'],"form-action 'none'"),'network APIs and forms disabled');
$static=request('action=import-file&id='.$runtimeId.'&file=index.html');checkHttp(!str_contains($static['body'],'<script')&&str_contains($static['body'],'.fa-eye::before'),'editing remains static with visible local icons');checkHttp(request('action=import-code&id='.$runtimeId.'&file=js/main.js')['status']===200,'copied JavaScript source readable');
foreach(['runtime-preview.html'=>$live['body'],'runtime-editor.html'=>$edit['body'],'runtime-page.html'=>$static['body'],'runtime-style.css'=>'body{background:white;color:#172033}body[data-theme=dark]{background:#10131c;color:white}main{padding:24px}img{width:100px}'] as $name=>$data){$data=preg_replace('/(name="csrf" value=")[^"]*/','$1synthetic-csrf-not-authentication',$data);file_put_contents($fixture.'/'.$name,$data);}file_put_contents($fixture.'/runtime-csp.txt',trim(preg_replace('/^Content-Security-Policy:\s*/i','',explode("\r\n",explode('Content-Security-Policy: ',$live['headers'],2)[1])[0])));
echo "$count HTTP checks including v0.5.2 PASS\n";
