<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Test fixture ONLY. Generates synthetic SQLite data outside the public directory.
declare(strict_types=1);
$root=realpath(__DIR__.'/..');
if(getenv('LANDICY_ISOLATED_TEST')!=='1')throw new RuntimeException('Solo entorno aislado.');
if(is_file($root.'/landicy-private/config.php'))throw new RuntimeException('No sobrescribe configuración.');
require $root.'/landicy-private/src/bootstrap.php';
$fixture=$root.'/test-fixtures';mkdir($fixture,0700);mkdir($fixture.'/sessions',0700);mkdir($fixture.'/storage',0700);mkdir($fixture.'/source',0700);mkdir($fixture.'/storage/sessions',0700);
$pdo=new PDO('sqlite:'.$fixture.'/fixture.sqlite');
$pdo->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT,email TEXT,password_hash TEXT,role TEXT);CREATE TABLE sites(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,slug TEXT UNIQUE,domain_name TEXT,theme_json TEXT,created_by INTEGER);CREATE TABLE site_users(site_id INTEGER,user_id INTEGER,role TEXT,UNIQUE(site_id,user_id));CREATE TABLE pages(id INTEGER PRIMARY KEY AUTOINCREMENT,site_id INTEGER,slug TEXT,title TEXT,draft_json TEXT,published_json TEXT,draft_version INTEGER DEFAULT 1,published_version INTEGER DEFAULT 0,edited_by INTEGER);CREATE TABLE revisions(id INTEGER PRIMARY KEY,page_id INTEGER,version INTEGER,blocks_json TEXT,author_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP);CREATE TABLE site_scans(id INTEGER PRIMARY KEY,site_id INTEGER,source_kind TEXT,source_ref TEXT,files_json TEXT);');
foreach([1=>'superadmin',2=>'admin',3=>'admin'] as $id=>$role){$stmt=$pdo->prepare('INSERT INTO users VALUES(?,?,?,?,?)');$stmt->execute([$id,'Fixture '.$id,'fixture'.$id.'@example.invalid',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),$role]);}
$theme=LandiCy\Builder::normalizeTheme(['header_brand'=>'Fixture global','footer_text'=>'Fixture pie']);$pdo->prepare('INSERT INTO sites VALUES(1,?,?,?,?,?)')->execute(['Fixture','fixture','',json_encode($theme),1]);$pdo->exec('INSERT INTO site_users VALUES(1,2,"admin")');
$blocks=LandiCy\Builder::normalize([['type'=>'text_box','body'=>'Texto editable','row'=>'fixture-row','columns'=>2,'column'=>1],['type'=>'image','src'=>'','row'=>'fixture-row','columns'=>2,'column'=>2]]);
foreach(['index','segunda'] as $slug)$pdo->prepare('INSERT INTO pages(site_id,slug,title,draft_json) VALUES(1,?,?,?)')->execute([$slug,$slug==='index'?'Inicio':'Segunda',json_encode($blocks)]);
file_put_contents($fixture.'/source/index.html','<title>Importado</title><link rel="stylesheet" href="site.css"><a href="segunda.html">Segunda</a><p>Importación aislada</p><script>window.bad=true</script>');file_put_contents($fixture.'/source/site.css','body{color:green}');file_put_contents($fixture.'/source/segunda.html','<title>Otra</title><p>Segunda importada</p>');
$config=['db'=>['dsn'=>'sqlite:'.$fixture.'/fixture.sqlite','username'=>'','password'=>''],'storage'=>$fixture.'/storage','scan_root'=>$fixture.'/source','source_url'=>''];file_put_contents($root.'/landicy-private/config.php',"<?php return ".var_export($config,true).";");chmod($root.'/landicy-private/config.php',0600);
// SQLite test adapter: only strips the MySQL row-lock suffix in the private copy.
$bootstrap=$root.'/landicy-private/src/bootstrap.php';$source=file_get_contents($bootstrap);$source=str_replace('$stmt = db()->prepare($sql);',"\$stmt = db()->prepare(preg_replace('/ FOR UPDATE$/', '', \$sql));",$source);file_put_contents($bootstrap,$source);
foreach([1=>'super',2=>'assigned',3=>'other'] as $id=>$label){session_save_path($fixture.'/storage/sessions');session_id('landicy-test-'.$label);session_start();$_SESSION=['user_id'=>$id,'csrf'=>bin2hex(random_bytes(24))];session_write_close();}
echo "Isolated SQLite fixture ready. Production credentials/data never used.\n";
