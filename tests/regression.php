<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
function e($value): string {return htmlspecialchars((string)$value, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
spl_autoload_register(function($class){if(str_starts_with($class,'LandiCy\\'))require __DIR__.'/../landicy-private/src/'.substr($class,8).'.php';});

use LandiCy\Builder; use LandiCy\DraftCode;
$count=0;
function check($value,$message){global $count;if(!$value)throw new RuntimeException($message);$count++;echo "PASS $message\n";}
foreach(['https://youtu.be/abcdefghijk','https://www.youtube.com/watch?v=abcdefghijk','https://youtube.com/shorts/abcdefghijk'] as $url)check(Builder::embedUrl($url)==='https://www.youtube-nocookie.com/embed/abcdefghijk','YouTube '.$url);
foreach(['javascript:alert(1)','https://www.youtube.com/feed/yot','https://www.youtube.com.evil.test/embed/abcdefghijk'] as $url)check(Builder::embedUrl($url)==='','Embed rechazado');
$theme=Builder::normalizeTheme(['header_brand'=>'Marca común','footer_text'=>'Pie común','favicon'=>'img/'.str_repeat('a',24).'.png']);
$site=['id'=>1,'name'=>'Prueba','theme_json'=>json_encode($theme)];
$nav=[['id'=>1,'slug'=>'index','title'=>'Inicio'],['id'=>2,'slug'=>'segunda','title'=>'Segunda']];
foreach(['index','segunda'] as $slug){$html=Builder::html($site,['slug'=>$slug,'title'=>$slug],Builder::normalize([['type'=>'cta','button_kind'=>'page','button_page'=>'segunda','button_text'=>'Ir']]),false,$nav);check(str_contains($html,'Marca común')&&str_contains($html,'Pie común'),'Globales '.$slug);check(str_contains($html,'href="segunda.html"'),'Enlace interno '.$slug);check(str_contains($html,'rel="icon" href="img/'),'Favicon '.$slug);}
check(!DraftCode::allowed('../config.php',$nav),'Traversal borrador');
check(!str_contains(DraftCode::cleanHtml('<script>alert(1)</script><p>Seguro</p>'),'script'),'HTML seguro');
try{DraftCode::normalize('css/custom.css','a{background:url(https://evil.test)}');check(false,'CSS peligroso');}catch(InvalidArgumentException $e){check(true,'CSS peligroso rechazado');}
echo "$count comprobaciones completadas.\n";
