<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
require __DIR__.'/../landicy-private/src/bootstrap.php';
use LandiCy\Importer;
$n=0;function ok062(bool $v,string $label):void{global $n;if(!$v)throw new RuntimeException('FAIL '.$label);$n++;echo "PASS $label\n";}
function links062(string $html):array{$d=new DOMDocument();@$d->loadHTML(Importer::html($html));$out=[];foreach($d->getElementsByTagName('link') as $node)$out[]=$node->getAttribute('href');return $out;}
ok062(links062('<link rel="icon" href="img/icon.png">')===['img/icon.png'],'Local PNG favicon retained');
ok062(links062('<link rel="shortcut icon" href="img/favicon.ico">')===['img/favicon.ico'],'Local ICO favicon retained');
ok062(links062('<link rel="apple-touch-icon" href="img/apple.webp">')===['img/apple.webp'],'Local bitmap touch icon retained');
ok062(links062('<link rel="icon" href="img/icon.svg">')===[''],'SVG favicon still refused');
ok062(links062('<link rel="icon" href="https://external.invalid/icon.png">')===[''],'Remote icon still refused');
ok062(links062('<link rel="preload" href="img/icon.png">')===[''],'Unrelated bitmap link not enabled');
ok062(links062('<link rel="stylesheet" href="css/styles.css">')===['css/styles.css'],'Local stylesheet remains compatible');
ok062(links062('<link rel="icon" href="javascript:alert(1)">')===[''],'Executable icon URL refused');
echo "$n v0.6.2 checks PASS\n";
