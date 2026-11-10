<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2026 LandiCy contributors. See LICENSE and LICENSE-OUTPUT-EXCEPTION.md.

declare(strict_types=1);

require dirname(__DIR__, 2) . '/landicy-private/src/bootstrap.php';

use LandiCy\Builder;
use LandiCy\DraftTrash;
use LandiCy\Pages;
use LandiCy\Access;
use LandiCy\Importer;
use LandiCy\ImportedCanvas;
use LandiCy\DraftCode;
use LandiCy\Media;
use LandiCy\Scanner;
use LandiCy\SourceRoots;
use LandiCy\Views;

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data: https:; frame-src 'self' https://www.youtube-nocookie.com https://www.google.com https://docs.google.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

// Public AGPL source delivery: one fixed verified archive, never arbitrary hosting files.
if (($_GET['action'] ?? '') === 'source-download') {
    $method=(string)($_SERVER['REQUEST_METHOD']??'GET');
    if (!in_array($method,['GET','HEAD'],true)) {http_response_code(405);header('Allow: GET, HEAD');exit('Método no permitido.');}
    $directory=dirname(__DIR__,2).'/landicy-private/source-packages';
    $archive=$directory.'/LandiCy_codigo_fuente_v0.6.3_AGPL.zip';
    if (is_link($directory)||is_link($archive)||!is_file($archive)||!is_readable($archive)) {http_response_code(404);exit('Fuente no disponible.');}
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="LandiCy_codigo_fuente_v0.6.3_AGPL.zip"');
    header('Content-Length: '.filesize($archive));
    if ($method==='GET') readfile($archive);
    exit;
}

start_session();
$action = (string) ($_GET['action'] ?? 'dashboard');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post) check_csrf();

try {
    if ($action === 'login') {
        if (current_user()) go();
        if ($post) {
            // Límite sencillo por sesión; complementar con limitación por IP en producción.
            $attempts = (int) ($_SESSION['login_attempts'] ?? 0);
            $first = (int) ($_SESSION['login_first'] ?? time());
            if (time() - $first > 900) { $attempts = 0; $first = time(); }
            if ($attempts >= 8) throw new RuntimeException('Demasiados intentos. Vuelve a intentarlo pasados 15 minutos.');
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $user = query('SELECT * FROM users WHERE email=?', [$email])->fetch();
            if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['login_attempts'] = 0;
                go();
            }
            $_SESSION['login_first'] = $first;
            $_SESSION['login_attempts'] = $attempts + 1;
            message('Correo o contraseña incorrectos.');
            go('login');
        }
        Views::start('Acceder al panel', null);
        echo '<div class="card narrow"><form method="post">' . csrf_field()
            . '<label>Correo electrónico<input name="email" type="email" required autocomplete="username"></label>'
            . '<label>Contraseña<input name="password" type="password" required autocomplete="current-password"></label>'
            . '<button class="primary">Entrar</button></form></div>';
        Views::end(); exit;
    }

    $user = require_user();
    if (!Access::allows($action,(string)$user['role'])) {http_response_code(403);exit('No autorizado.');}
    if ($action === 'import-preview') {
        $token=(string)($_GET['token']??'');$path=(string)($_GET['file']??'index.html');
        $file=Importer::file(config()['storage'],0,$path,$token,(int)$user['id']);
        Importer::serve($file,$path,'?action=import-preview&token='.rawurlencode($token).'&file=');
    }
    if ($action === 'import-review') {
        $token=(string)($_GET['token']??'');
        $manifest=Importer::pending(config()['storage'],$token,(int)$user['id']);
        $details=$_SESSION['import-details'][$token]??null;
        if(!is_array($details))throw new InvalidArgumentException('Prepara de nuevo la importación.');
        if($post){
            $action='new-site';
            $_POST=$details+['origin'=>'new','import_token'=>$token];
        }else{
            Views::start('Revisar importación',$user);
            echo '<p class="notice">Copia aislada: no se modificará el origen. HTML complejo permanece en modo importado; scripts y formularios desactivados. No hay importación remota por URL.</p><h2>'.count($manifest['files']).' archivos · '.count($manifest['pages']).' páginas</h2><ul>';
            foreach($manifest['warnings'] as $warning)echo '<li>'.e($warning).'</li>';
            echo '</ul><div class="table-scroll"><table><tr><th>Archivo detectado</th></tr>';
            foreach($manifest['files'] as $path)echo '<tr><td>'.e($path).'</td></tr>';
            echo '</table></div><h2>Vista previa segura</h2><iframe class="import-review-frame" sandbox="allow-same-origin" title="Vista previa importación" src="?action=import-preview&token='.e($token).'&file='.rawurlencode($manifest['pages'][0]['path']).'"></iframe><form method="post">'.csrf_field().'<button class="primary">Confirmar y crear un sitio independiente</button> <a href="?action=new-site">Cancelar</a></form>';
            Views::end();exit;
        }
    }

    if ($action === 'logout') {
        if (!$post) { http_response_code(405); exit('Método no permitido'); }
        $_SESSION = [];
        session_regenerate_id(true);
        session_destroy();
        go('login');
    }

    if ($action === 'dashboard') {
        if ($user['role'] === 'superadmin') {
            $sites = query('SELECT s.*,p.draft_version,p.published_version FROM sites s JOIN pages p ON p.site_id=s.id AND p.slug="index" ORDER BY s.id DESC')->fetchAll();
        } else {
            $sites = query('SELECT s.*,su.role AS my_role,p.draft_version,p.published_version FROM sites s JOIN site_users su ON su.site_id=s.id JOIN pages p ON p.site_id=s.id AND p.slug="index" WHERE su.user_id=? ORDER BY s.id DESC', [$user['id']])->fetchAll();
        }
        $showTrash=$user['role']==='superadmin'&&($_GET['trash']??'')==='1';
        $sites=array_values(array_filter($sites,static fn($entry)=>DraftTrash::contains(config()['storage'],(int)$entry['id'])===$showTrash));
        Views::start($showTrash?'Papelera de borradores':'Tus sitios', $user);
        echo '<p>Un panel, múltiples proyectos. La publicación produce HTML, CSS, JavaScript y JSON estáticos. · <a href="https://github.com/jerros57/landiCy" target="_blank" rel="noopener noreferrer">LandiCy en GitHub ↗</a></p>';
        if ($user['role'] === 'superadmin') echo '<p class="actions"><a class="primary button" href="?action=new-site">+ Nuevo sitio</a> <a class="button" href="?action=users">Gestionar usuarios</a></p>';
        if($user['role']==='superadmin')echo '<p><a class="button" href="?action=dashboard">Sitios activos</a> <a class="button" href="?action=dashboard&amp;trash=1">Papelera de borradores</a></p>';
        echo '<div class="cards">';
        foreach ($sites as $site) {
            echo '<article class="card"><p class="overline">' . e($site['slug']) . '</p><h2>' . e($site['name']) . '</h2><p>Versión borrador ' . (int) $site['draft_version']
                . ' · publicada ' . (int) $site['published_version'] . '</p>';
            if($showTrash)echo '<a class="button" href="?action=draft-restore&amp;id='.(int)$site['id'].'">Restaurar borrador</a>';else{echo '<a class="button" href="?action=site&amp;id='.(int)$site['id'].'">Gestionar →</a>';if($user['role']==='superadmin')echo ' <a class="button" href="?action=draft-trash&amp;id='.(int)$site['id'].'">Borrar borrador…</a>';}echo '</article>';
        }
        if (!$sites) echo '<p class="muted">'.($showTrash?'No hay borradores en la papelera.':'Aún no tienes sitios asignados.').'</p>';
        echo '</div>'; Views::end(); exit;
    }

    // Exclusivo de superadministradores: altas de usuarios y asignación de sitios.
    if ($action === 'users') {
        if ($user['role'] !== 'superadmin') { http_response_code(403); exit('Solo superadministradores.'); }
        if ($post) {
            $operation = (string)($_POST['operation'] ?? '');
            if ($operation === 'create') {
                $name = trim((string)($_POST['name'] ?? ''));
                $email = strtolower(trim((string)($_POST['email'] ?? '')));
                $password = (string)($_POST['password'] ?? '');
                $role = (string)($_POST['role'] ?? 'editor');
                if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) throw new InvalidArgumentException('Nombre o correo inválidos.');
                if (!in_array($role, ['superadmin','admin','editor'], true)) throw new InvalidArgumentException('Rol inválido.');
                if (strlen($password) < 12 || strlen($password) > 200) throw new InvalidArgumentException('Contraseña: mínimo 12 caracteres.');
                if (query('SELECT id FROM users WHERE email=?', [$email])->fetch()) throw new InvalidArgumentException('Ya existe un usuario con ese correo.');
                $hash = password_hash($password, PASSWORD_DEFAULT);
                query('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)', [$name,$email,$hash,$role]);
                message('Usuario creado. Asigna sus sitios en la sección inferior.');
            } elseif ($operation === 'assign') {
                $memberId = (int)($_POST['member_id'] ?? 0);
                $assignedSite = (int)($_POST['assigned_site'] ?? 0);
                $member = query('SELECT id,role FROM users WHERE id=?', [$memberId])->fetch();
                if (!$member || $member['role'] === 'superadmin') throw new InvalidArgumentException('Usuario inválido: los superadministradores ya pueden acceder a todos los sitios.');
                if (!query('SELECT id FROM sites WHERE id=?', [$assignedSite])->fetch()) throw new InvalidArgumentException('El sitio no existe.');
                $siteRole = $member['role'] === 'admin' ? 'admin' : 'editor';
                query('INSERT INTO site_users(site_id,user_id,role) VALUES(?,?,?) ON DUPLICATE KEY UPDATE role=VALUES(role)', [$assignedSite, $memberId, $siteRole]);
                message('Acceso asignado al sitio.');
            } elseif ($operation === 'revoke') {
                $memberId = (int)($_POST['member_id'] ?? 0);
                $assignedSite = (int)($_POST['assigned_site'] ?? 0);
                if ($memberId === (int)$user['id']) throw new InvalidArgumentException('No puedes revocar tu propio acceso desde aquí.');
                query('DELETE FROM site_users WHERE site_id=? AND user_id=?', [$assignedSite, $memberId]);
                message('Acceso retirado.');
            } else throw new InvalidArgumentException('Acción desconocida.');
            go('users');
        }
        $members = query('SELECT id,name,email,role FROM users ORDER BY id ASC')->fetchAll();
        $sites = query('SELECT id,name FROM sites ORDER BY name')->fetchAll();
        $assignments = query('SELECT su.site_id,su.user_id,s.name AS site_name,u.name AS user_name FROM site_users su JOIN sites s ON su.site_id=s.id JOIN users u ON su.user_id=u.id WHERE u.role<>"superadmin" ORDER BY s.name,u.name')->fetchAll();
        Views::start('Usuarios y permisos', $user);
        echo '<p><a href="?action=dashboard">← Panel principal</a></p><p>Solo el superadministrador crea sitios, usuarios y aprueba la publicación. Los demás preparan borradores de sus sitios asignados.</p>';
        echo '<div class="cards"><section class="card"><h2>Crear usuario</h2><form method="post">' . csrf_field() . '<input type="hidden" name="operation" value="create">'
            . '<label>Nombre<input name="name" maxlength="120" required></label><label>Correo<input type="email" name="email" maxlength="190" required></label>'
            . '<label>Contraseña inicial (12 caracteres o más)<input name="password" type="password" minlength="12" autocomplete="new-password" required></label>'
            . '<label>Rol<select name="role"><option value="editor">Editor de contenido</option><option value="admin">Administrador de sitio</option><option value="superadmin">Superadministrador (acceso total)</option></select></label>'
            . '<p class="hint">Al crear otro superadministrador, esa persona podrá administrar todos los proyectos y crear más cuentas.</p><button class="primary">Crear usuario</button></form></section>';
        echo '<section class="card"><h2>Asignar un sitio</h2><form method="post">' . csrf_field() . '<input type="hidden" name="operation" value="assign"><label>Usuario<select name="member_id" required>';
        foreach ($members as $m) if ($m['role'] !== 'superadmin') echo '<option value="' . (int)$m['id'] . '">' . e($m['name']) . ' (' . e($m['role']) . ')</option>';
        echo '</select></label><label>Sitio<select name="assigned_site" required>';
        foreach ($sites as $entry) echo '<option value="' . (int)$entry['id'] . '">' . e($entry['name']) . '</option>';
        echo '</select></label><button class="primary"' . (!$sites ? ' disabled' : '') . '>Asignar acceso</button></form></section></div>';
        echo '<h2>Usuarios registrados</h2><div class="page-list">';
        foreach ($members as $m) echo '<div class="page-entry"><strong>' . e($m['name']) . '</strong><small>' . e($m['email']) . ' · ' . e($m['role']) . '</small></div>';
        echo '</div><h2>Accesos por sitio</h2><div class="page-list">';
        foreach ($assignments as $a) echo '<div class="page-entry"><strong>' . e($a['user_name']) . '</strong><small>' . e($a['site_name']) . '</small><form method="post" onsubmit="return confirm(\'¿Retirar acceso a este sitio?\')">' . csrf_field()
            . '<input type="hidden" name="operation" value="revoke"><input type="hidden" name="member_id" value="' . (int)$a['user_id'] . '"><input type="hidden" name="assigned_site" value="' . (int)$a['site_id'] . '"><button>Retirar acceso</button></form></div>';
        echo '</div>'; Views::end(); exit;
    }

    if ($action === 'new-site') {
        if ($user['role'] !== 'superadmin') { http_response_code(403); exit('Solo superadministradores.'); }
        if ($post) {
            if(($_POST['origin']??'new')!=='new') {
                $origin=(string)$_POST['origin'];
                if($origin==='directory'){[$sourceRoot,$relative]=SourceRoots::resolve(config(),(string)($_POST['source_root']??'default'),(string)($_POST['relative']??''));$manifest=Importer::prepareDirectory(config()['storage'],(int)$user['id'],$sourceRoot,$relative);}
                elseif($origin==='zip') {
                    $upload=$_FILES['archive']??[];
                    if(($upload['error']??-1)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($upload['tmp_name']??'')))throw new InvalidArgumentException('Selecciona un ZIP válido.');
                    $manifest=Importer::prepareZip(config()['storage'],(int)$user['id'],$upload['tmp_name']);
                }else throw new InvalidArgumentException('Origen inválido.');
                $_SESSION['import-details'][$manifest['token']]=['name'=>(string)($_POST['name']??''),'slug'=>(string)($_POST['slug']??''),'domain'=>(string)($_POST['domain']??''),'primary'=>(string)($_POST['primary']??''),'accent'=>(string)($_POST['accent']??'')];
                go('import-review',['token'=>$manifest['token']]);
            }
            $name = trim((string) ($_POST['name'] ?? ''));
            $slug = trim((string) ($_POST['slug'] ?? ''));
            $domain = trim((string) ($_POST['domain'] ?? ''));
            $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : preg_match_all('/./us', $name);
            if ($nameLength === false || $nameLength < 2 || $nameLength > 120) throw new InvalidArgumentException('Nombre: entre 2 y 120 caracteres.');
            if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,58}[a-z0-9])?$/D', $slug)) throw new InvalidArgumentException('Slug inválido (solo minúsculas, números y guiones).');
            if (strlen($domain) > 190 || ($domain !== '' && !filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) throw new InvalidArgumentException('Dominio descriptivo inválido: usa solo el nombre de host, sin protocolo ni ruta.');
            $theme = json_encode(Builder::normalizeTheme($_POST), JSON_THROW_ON_ERROR);
            $importToken=(string)($_POST['import_token']??'');
            $importManifest=$importToken!==''?Importer::pending(config()['storage'],$importToken,(int)$user['id']):null;
            $attached=false;
            db()->beginTransaction();
            try {
                query('INSERT INTO sites(name,slug,domain_name,theme_json,created_by) VALUES(?,?,?,?,?)', [$name, $slug, $domain ?: null, $theme, $user['id']]);
                $siteId = (int) db()->lastInsertId();
                query('INSERT INTO site_users(site_id,user_id,role) VALUES(?,?,"admin")', [$siteId, $user['id']]);
                $blocks = json_encode($importManifest?Builder::normalize([['type'=>'imported','path'=>$importManifest['pages'][0]['path']]]):Builder::defaultBlocks($name), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                query('INSERT INTO pages(site_id,slug,title,draft_json,edited_by) VALUES(?,"index",?,?,?)', [$siteId, $name, $blocks, $user['id']]);
                $pageId = (int) db()->lastInsertId();
                query('INSERT INTO revisions(page_id,version,blocks_json,author_id) VALUES(?,1,?,?)', [$pageId, $blocks, $user['id']]);
                if($importManifest){
                    foreach(array_slice($importManifest['pages'],1) as $entry){
                        $data=json_encode(Builder::normalize([['type'=>'imported','path'=>$entry['path']]]),JSON_THROW_ON_ERROR);
                        query('INSERT INTO pages(site_id,slug,title,draft_json,edited_by) VALUES(?,?,?,?,?)',[$siteId,$entry['slug'],$entry['title'],$data,$user['id']]);
                        $newPage=(int)db()->lastInsertId();query('INSERT INTO revisions(page_id,version,blocks_json,author_id) VALUES(?,1,?,?)',[$newPage,$data,$user['id']]);
                    }
                    Importer::attach(config()['storage'],$importToken,(int)$user['id'],$siteId);$attached=true;
                }
                db()->commit();
                if($importToken!=='')unset($_SESSION['import-details'][$importToken]);
            } catch (Throwable $exception) { db()->rollBack(); if($attached)Importer::detach(config()['storage'],$importToken,$siteId); throw $exception; }
            message('Sitio creado. Edita su borrador y publícalo.');
            go('site', ['id' => $siteId]);
        }
        Views::start('Crear sitio', $user);
        echo '<div class="card narrow"><form method="post" enctype="multipart/form-data">' . csrf_field()
            . SourceRoots::options(config(),(string)($_GET['source_root']??'default')).'<label>Origen del proyecto<select name="origin"><option value="new">Sitio nuevo</option><option value="directory"'.(($_GET['origin']??'')==='directory'?' selected':'').'>Importar sitio existente</option><option value="zip">Subir sitio (ZIP)</option></select></label><label>Subcarpeta autorizada (para importar del hosting)<input name="relative" maxlength="240" value="'.e((string)($_GET['relative']??'')).'"></label><label>ZIP (solo para subir sitio, máximo 15 MB)<input type="file" name="archive" accept=".zip,application/zip"></label><p>La importación prepara una copia privada y muestra un informe antes de crear el sitio. scan_root debe estar configurado explícitamente.</p>'
            . '<label>Nombre<input name="name" placeholder="Mi comunidad" required maxlength="120" value="'.e((string)($_GET['name']??'')).'"></label>'
            . '<label>Slug (único)<input name="slug" placeholder="mi-comunidad" pattern="[a-z0-9-]+" required value="'.e((string)($_GET['slug']??'')).'"></label>'
            . '<label>Dominio (opcional)<input name="domain" placeholder="ejemplo.com"></label>'
            . '<div class="two"><label>Color principal<input type="color" name="primary" value="#225cf0"></label>'
            . '<label>Color contraste<input type="color" name="accent" value="#111827"></label></div>'
            . '<button class="primary">Crear proyecto</button></form></div>';
        Views::end(); exit;
    }

    if (in_array($action, ['draft-trash', 'draft-restore', 'import-live', 'import-visual', 'pages', 'site', 'edit', 'preview', 'publish', 'download', 'scan', 'source', 'code', 'new-page', 'delete-page', 'media-library', 'media', 'import-file', 'import-code'], true)) {
        $siteId = (int) ($_GET['id'] ?? 0);
        $site = site_or_404($siteId, $user);
        $page = site_page($siteId);
        if(DraftTrash::contains(config()['storage'],$siteId)&&!in_array($action,['draft-trash','draft-restore'],true)){http_response_code(410);Views::error('Borrador en la papelera','Restaura el proyecto desde la papelera para volver a editarlo.',$user);exit;}
    }

    
    if(in_array($action,['draft-trash','draft-restore'],true)){
        $restore=$action==='draft-restore';if($post){$draftLock=DraftTrash::projectLock(config()['storage'],$siteId);$site=site_or_404($siteId,$user);}$trashed=DraftTrash::contains(config()['storage'],$siteId);
        $draftPages=query('SELECT id,draft_version,published_version FROM pages WHERE site_id=? ORDER BY id',[$siteId])->fetchAll();
        if($restore!==$trashed)throw new RuntimeException($restore?'El borrador ya está activo.':'El borrador ya está en la papelera.');
        if(!$restore&&DraftTrash::published($draftPages))throw new RuntimeException('Este proyecto tiene una versión publicada. No se puede borrar desde la papelera de borradores.');
        $token=DraftTrash::token($site,$draftPages,$trashed);
        if($post){if(($_POST['confirm_draft']??'')!=='1')throw new InvalidArgumentException('Confirma expresamente la operación o pulsa Cancelar.');if(!is_string($_POST['draft_token']??null)||!hash_equals($token,$_POST['draft_token']))throw new RuntimeException('El proyecto cambió. Recarga y revisa antes de confirmar.');DraftTrash::change(config()['storage'],$siteId,(int)$user['id'],$restore);message($restore?'Borrador restaurado.':'Borrador movido a la papelera. Puedes restaurarlo desde el panel.');go('dashboard',$restore?[]:['trash'=>1]);}
        Views::start($restore?'Restaurar borrador':'Confirmar borrado de borrador',$user);
        echo '<section class="card narrow"><h1>'.($restore?'¿Restaurar este borrador?':'¿Seguro que quieres borrar este borrador?').'</h1><h2>'.e($site['name']).'</h2><p>'.($restore?'El proyecto volverá a aparecer en tus sitios activos, con sus páginas y recursos.':'Se moverá el proyecto completo a la papelera y dejará de estar disponible para editar. Sus páginas, recursos e historial se conservan para poder restaurarlo.').'</p><p>Esta acción no elimina archivos de la web pública ni modifica el sitio original del que se importó.</p><form method="post">'.csrf_field().'<input type="hidden" name="draft_token" value="'.e($token).'"><label style="display:flex;align-items:flex-start;gap:12px"><input type="checkbox" name="confirm_draft" value="1" required style="width:18px;height:18px;flex:0 0 18px;margin:3px 0"> '.($restore?'Confirmo que quiero restaurar este borrador.':'Sí, quiero mover este borrador a la papelera.').'</label><div class="actions"><a class="button" href="?action=dashboard'.($restore?'&amp;trash=1':'').'">Cancelar</a><button class="primary" style="'.($restore?'':'background:#a61f35;border-color:#a61f35').'">'.($restore?'Restaurar borrador':'Sí, borrar borrador').'</button></div></form></section>';Views::end();exit;
    }

    if ($action === 'import-file') {
        $path=(string)($_GET['file']??'');
        Importer::serve(Importer::file(config()['storage'],$siteId,$path),$path,'?action=import-file&id='.$siteId.'&file=');
    }
    if ($action === 'import-live') { \LandiCy\ScriptPreview::serve(config()['storage'],$siteId,(string)($_GET['file']??'index.html')); }
    if ($action === 'import-visual') {
        $manifest=Importer::manifest(config()['storage'],$siteId);
        if(!$manifest)throw new InvalidArgumentException('Primero crea una copia del sitio desde Crear sitio → Importar sitio existente.');
        $path=(string)($_GET['file']??($manifest['pages'][0]['path']??''));
        $file=Importer::file(config()['storage'],$siteId,$path);
        if(!in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['html','htm'],true))throw new InvalidArgumentException('Selecciona una página HTML.');
        if($post){
            if(($_POST['operation']??'')==='upload'){if(!isset($_FILES['image']))throw new InvalidArgumentException('Selecciona una imagen.');$media=Media::upload(config()['storage'],$siteId,$_FILES['image']);ImportedCanvas::attachMedia(config()['storage'],$siteId,$media);message('Imagen añadida a la copia. Selecciónala en el inspector.');go('import-visual',['id'=>$siteId,'file'=>$path]);}
            $versions=json_decode((string)($_POST['file_versions']??'{}'),true,16,JSON_THROW_ON_ERROR);
            if(!is_array($versions))throw new InvalidArgumentException('Versión inválida.');
            ImportedCanvas::save(config()['storage'],$siteId,$path,(string)($_POST['contents']??''),$versions);
            if(\LandiCy\CanvasUI::requested())\LandiCy\CanvasUI::reply(['ok'=>true,'file_versions'=>ImportedCanvas::versions(config()['storage'],$siteId,$path)]);
            message('Borrador importado guardado. Cabecera y pie compartidos actualizados solo en la copia.');go('import-visual',['id'=>$siteId,'file'=>$path]);
        }
        Views::start('Diseñar copia: '.$site['name'],$user,true);
        $versions=ImportedCanvas::versions(config()['storage'],$siteId,$path);
        \LandiCy\EditorChrome::imported($site,$manifest,$path,$versions);
        $images=array_values(array_filter($manifest['files'],static fn($p)=>preg_match('/\.(png|jpe?g|gif|webp)$/iD',$p)));
        echo '<details class="imported-upload" id="imported-upload"><summary>Añadir imagen a la copia</summary><form method="post" enctype="multipart/form-data">'.csrf_field().'<input type="hidden" name="operation" value="upload"><label>JPEG, PNG, WebP o GIF, máximo 5 MB<input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required></label><button>Añadir imagen</button></form></details>';
        echo '<script id="imported-model" type="application/json">'.json_encode(['site'=>$siteId,'path'=>$path,'images'=>$images,'pages'=>$manifest['pages']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR).'</script><script src="assets/imported-editor.js?v=063" defer></script>';
        Views::end();exit;
    }

    if ($action === 'import-code') {
        $manifest=Importer::manifest(config()['storage'],$siteId);
        $path=(string)($_GET['file']??($manifest['pages'][0]['path']??''));
        $file=Importer::file(config()['storage'],$siteId,$path);
        $isHtml=in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['html','htm'],true);
        if($post){
            if(!$isHtml)throw new InvalidArgumentException('Este archivo es de solo lectura.');
            if(($_POST['operation']??'')==='convert'){
                $entry=null;foreach($manifest['pages'] as $candidate)if($candidate['path']===$path)$entry=$candidate;
                if(!$entry)throw new InvalidArgumentException('Página no encontrada.');
                $target=query('SELECT * FROM pages WHERE site_id=? AND slug=?',[$siteId,$entry['slug']])->fetch();
                if(!$target)throw new InvalidArgumentException('Página no encontrada.');
                if((int)($_POST['version']??0)!==(int)$target['draft_version'])throw new RuntimeException('Otro editor guardó cambios. Recarga.');
                $text=trim(html_entity_decode(strip_tags(Importer::html((string)file_get_contents($file))),ENT_QUOTES|ENT_HTML5,'UTF-8'));
                $blocks=Builder::normalize(json_data($target['draft_json']));
                $blocks[]=['type'=>'text_box','body'=>mb_substr($text,0,12000),'align'=>'left','size'=>'normal'];
                $blocks=Builder::normalize($blocks);$json=json_encode($blocks,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
                db()->beginTransaction();try{
                    $stmt=query('UPDATE pages SET draft_json=?,draft_version=draft_version+1,edited_by=? WHERE id=? AND draft_version=?',[$json,$user['id'],$target['id'],$target['draft_version']]);
                    if($stmt->rowCount()!==1)throw new RuntimeException('Cambios concurrentes. Recarga.');
                    query('INSERT INTO revisions(page_id,version,blocks_json,author_id) VALUES(?,?,?,?)',[$target['id'],(int)$target['draft_version']+1,$json,$user['id']]);db()->commit();
                }catch(Throwable $e){db()->rollBack();throw $e;}
                go('edit',['id'=>$siteId,'page'=>$target['id']]);
            }
            $fileVersions=json_decode((string)($_POST['file_versions']??'{}'),true,16,JSON_THROW_ON_ERROR);if(!is_array($fileVersions))throw new InvalidArgumentException('Versión inválida.');ImportedCanvas::save(config()['storage'],$siteId,$path,(string)($_POST['contents']??''),$fileVersions,false);
            message('HTML importado guardado y versionado en este borrador. Scripts eliminados.');go('import-code',['id'=>$siteId,'file'=>$path]);
        }
        Views::start('Archivos del sitio importado',$user);
        echo '<p><a href="?action=site&id='.$siteId.'">← Proyecto</a></p><p class="notice">Solo la copia del proyecto. HTML editable con sanitización y respaldo privado. JS disponible en la vista previa aislada; no se ejecuta dentro del editor. La conversión extrae texto como un bloque nuevo y conserva el HTML importado.</p><div class="code-browser"><aside class="code-tree">';
        foreach($manifest['files']??[] as $name)echo '<a href="?action=import-code&id='.$siteId.'&file='.rawurlencode($name).'">'.e($name).'</a><br>';
        echo '</aside><section class="card"><h2>'.e($path).'</h2>';
        if($isHtml){
            echo '<form method="post">'.csrf_field().'<input type="hidden" name="file_versions" value="'.e(json_encode(ImportedCanvas::versions(config()['storage'],$siteId,$path),JSON_THROW_ON_ERROR)).'"><textarea name="contents" rows="24" maxlength="1048576" spellcheck="false">'.e(file_get_contents($file)).'</textarea><button class="primary">Guardar HTML del borrador</button></form>';
            $entry=null;foreach($manifest['pages'] as $candidate)if($candidate['path']===$path)$entry=$candidate;
            if($entry){$version=query('SELECT draft_version FROM pages WHERE site_id=? AND slug=?',[$siteId,$entry['slug']])->fetchColumn();echo '<form method="post">'.csrf_field().'<input type="hidden" name="operation" value="convert"><input type="hidden" name="version" value="'.(int)$version.'"><button>Extraer texto a un bloque nativo</button></form>';}
        }elseif(in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['css','js','mjs'],true))echo '<pre class="codeview">'.e(file_get_contents($file)).'</pre>';
        else echo '<p>Recurso binario conservado; no editable como texto.</p>';
        echo '</section></div>';Views::end();exit;
    }

    if (in_array($action, ['edit','preview'], true) && isset($_GET['page'])) {
        $selectedPage = query('SELECT * FROM pages WHERE site_id=? AND id=?', [$siteId, (int)$_GET['page']])->fetch();
        if (!$selectedPage) { http_response_code(404); exit('Página inexistente.'); }
        $page = $selectedPage;
    }

    if (in_array($action, ['edit', 'preview', 'site', 'publish', 'code'], true)) {
        $sitePages = query("SELECT id, slug, title, draft_version, published_version FROM pages WHERE site_id=? ORDER BY (slug = 'index') DESC, id ASC", [$siteId])->fetchAll();
    }

    if ($action === 'pages') {
        $all=query('SELECT * FROM pages WHERE site_id=? ORDER BY id',[$siteId])->fetchAll();
        if($post){
            db()->beginTransaction();
            try{
                $locked=(string)query('SELECT theme_json FROM sites WHERE id=? FOR UPDATE',[$siteId])->fetchColumn();
                if(!hash_equals(hash('sha256',$locked),(string)($_POST['theme_hash']??'')))throw new RuntimeException('El sitio cambió. Recarga antes de modificar páginas.');
                $theme=json_decode($locked,true,32,JSON_THROW_ON_ERROR);$meta=Pages::metadata($theme['pages']??[]);
                $target=query('SELECT * FROM pages WHERE id=? AND site_id=?',[(int)($_POST['page_id']??0),$siteId])->fetch();
                if(!$target)throw new InvalidArgumentException('Página ajena o inexistente.');
                $operation=(string)($_POST['operation']??'');$key=(string)$target['id'];$entry=$meta[$key]??['status'=>'active','order'=>(int)$target['id'],'layout'=>null];
                if($operation==='rename'){
                    $title=trim((string)($_POST['title']??''));if(mb_strlen($title)<2||mb_strlen($title)>180)throw new InvalidArgumentException('Título entre 2 y 180 caracteres.');
                    query('UPDATE pages SET title=?,edited_by=? WHERE id=? AND site_id=?',[$title,$user['id'],$target['id'],$siteId]);
                }elseif($operation==='duplicate'){
                    if(count($all)>=100)throw new InvalidArgumentException('Máximo 100 páginas.');
                    $slug=substr($target['slug'],0,70).'-copia-'.bin2hex(random_bytes(4));
                    $blocks=Builder::normalize(json_data($target['draft_json']));foreach($blocks as &$block)$block['id']='b-'.bin2hex(random_bytes(12));unset($block);$json=json_encode($blocks,JSON_THROW_ON_ERROR);
                    query('INSERT INTO pages(site_id,slug,title,draft_json,edited_by) VALUES(?,?,?,?,?)',[$siteId,$slug,mb_substr($target['title'].' (copia)',0,180),$json,$user['id']]);$newId=(int)db()->lastInsertId();
                    query('INSERT INTO revisions(page_id,version,blocks_json,author_id) VALUES(?,1,?,?)',[$newId,$json,$user['id']]);
                }elseif($operation==='order'){$entry['order']=max(-10000,min(10000,(int)($_POST['order']??0)));}
                elseif(in_array($operation,['active','hidden','archived','trash'],true)){
                    if($target['slug']==='index'&&$operation!=='active')throw new InvalidArgumentException('La página principal debe permanecer activa.');$entry['status']=$operation;
                }elseif($operation==='layout'){
                    $entry['layout']=!empty($_POST['custom_layout'])?Builder::normalizeLayout(array_replace($theme,['header_brand'=>(string)($_POST['header_brand']??''),'footer_text'=>(string)($_POST['footer_text']??'')]),$site['name']):null;
                }elseif($operation==='restore-revision'){
                    $revision=query('SELECT blocks_json FROM revisions WHERE page_id=? AND version=?',[$target['id'],(int)($_POST['revision']??0)])->fetchColumn();
                    if($revision===false)throw new InvalidArgumentException('Revisión inexistente.');
                    $v=(int)($_POST['version']??0);$json=json_encode(Builder::normalize(json_data($revision)),JSON_THROW_ON_ERROR);
                    $updated=query('UPDATE pages SET draft_json=?,draft_version=draft_version+1,edited_by=? WHERE id=? AND site_id=? AND draft_version=?',[$json,$user['id'],$target['id'],$siteId,$v]);
                    if($updated->rowCount()!==1)throw new RuntimeException('La página cambió. Recarga antes de restaurar.');
                    query('INSERT INTO revisions(page_id,version,blocks_json,author_id) VALUES(?,?,?,?)',[$target['id'],$v+1,$json,$user['id']]);
                }elseif($operation==='restore-layout'){
                    $historical=Pages::historical(config()['storage'],$siteId,(string)($_POST['layout_revision']??''));$theme['layout']=$historical['layout']??[];
                }else throw new InvalidArgumentException('Operación desconocida.');
                $meta[$key]=$entry;$theme['pages']=$meta;
                Pages::saveHistory(config()['storage'],$siteId,$locked);
                query('UPDATE sites SET theme_json=? WHERE id=?',[json_encode($theme,JSON_THROW_ON_ERROR),$siteId]);db()->commit();
            }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
            message('Páginas actualizadas. Se conservaron los registros y revisiones.');go('pages',['id'=>$siteId]);
        }
        Views::start('Páginas, papelera e historial',$user);
        echo '<p><a href="?action=site&id='.$siteId.'">← Proyecto</a> · <a href="?action=new-page&id='.$siteId.'">Crear página</a></p><p>Ocultar excluye del menú. Archivar y enviar a papelera excluyen de la próxima compilación, sin borrar contenido. Restaurar conserva el slug.</p>';
        $theme=json_decode($site['theme_json'],true)?:[];$token='<input type="hidden" name="theme_hash" value="'.hash('sha256',$site['theme_json']).'">';
        foreach(Pages::listing($all,$theme,true) as $p){
            $base=csrf_field().$token.'<input type="hidden" name="page_id" value="'.(int)$p['id'].'">';
            echo '<section class="card"><h2>'.e($p['title']).' · /'.e($p['slug']).'</h2><p>Estado: '.e($p['state']['status']).' · v'.(int)$p['draft_version'].' · <a href="?action=edit&id='.$siteId.'&page='.(int)$p['id'].'">Editar</a> · <a target="_blank" rel="noopener" href="?action=preview&id='.$siteId.'&page='.(int)$p['id'].'">Vista previa</a></p>';
            echo '<form method="post">'.$base.'<label>Título<input name="title" value="'.e($p['title']).'" maxlength="180"></label><button name="operation" value="rename">Renombrar</button> <button name="operation" value="duplicate">Duplicar</button><label>Orden<input type="number" name="order" min="-10000" max="10000" value="'.(int)$p['state']['order'].'"></label><button name="operation" value="order">Ordenar</button><div class="actions">';
            foreach(['active'=>'Restaurar / mostrar','hidden'=>'Ocultar del menú','archived'=>'Archivar','trash'=>'Papelera'] as $op=>$label)echo '<button name="operation" value="'.$op.'">'.$label.'</button>';echo '</div></form>';
            $revs=query('SELECT version,created_at FROM revisions WHERE page_id=? ORDER BY version DESC',[$p['id']])->fetchAll();
            echo '<form method="post">'.$base.'<input type="hidden" name="version" value="'.(int)$p['draft_version'].'"><label>Revisiones<select name="revision">';foreach($revs as $r)echo '<option value="'.(int)$r['version'].'">v'.(int)$r['version'].' · '.e($r['created_at']).'</option>';echo '</select></label><button name="operation" value="restore-revision">Restaurar como nueva revisión</button></form>';
            $layout=$p['state']['layout']??Builder::normalizeLayout($theme,$site['name']);
            echo '<details><summary>Excepción de cabecera y pie por página</summary><form method="post">'.$base.'<label><input type="checkbox" name="custom_layout" value="1"'.($p['state']['layout']!==null?' checked':'').'> Usar copia local del diseño global</label><label>Nombre de cabecera<input name="header_brand" value="'.e($layout['header_brand']).'"></label><label>Copyright / pie<input name="footer_text" value="'.e($layout['footer_text']).'"></label><button name="operation" value="layout">Guardar excepción</button></form></details></section>';
        }
        $history=Pages::history(config()['storage'],$siteId);
        if($history){echo '<section class="card"><h2>Historial global de cabecera y pie</h2><form method="post">'.csrf_field().$token.'<input type="hidden" name="page_id" value="'.(int)$all[0]['id'].'"><select name="layout_revision">';foreach(array_slice($history,0,100) as $r)echo '<option>'.e($r).'</option>';echo '</select><button name="operation" value="restore-layout">Restaurar cabecera y pie</button></form></section>';}
        Views::end();exit;
    }

    if ($action === 'site') {
        $latest = query('SELECT * FROM site_scans WHERE site_id=? ORDER BY id DESC LIMIT 1', [$siteId])->fetch();
        Views::start($site['name'], $user);
        echo '<p><a href="?action=dashboard">← Mis sitios</a></p><div class="card">';
        echo '<p><strong>Proyecto:</strong> ' . e($site['slug']) . ' · <strong>Dominio orientativo:</strong> ' . e($site['domain_name'] ?: 'sin definir') . '</p>';
        if($user['role']==='superadmin')echo '<p><a class="button" href="?action=draft-trash&amp;id='.$siteId.'">Borrar borrador…</a></p>';
        echo '<p><strong>Borrador:</strong> v' . (int) $page['draft_version'] . ' · <strong>Publicado:</strong> v' . (int) $page['published_version'] . '</p>';
        echo '<div class="actions"><a class="primary button" href="?action=edit&id=' . $siteId . '">Editar bloques</a>';
        echo '<a class="button" href="?action=code&id=' . $siteId . '">Analizar código</a>';
        echo '<a class="button" target="_blank" rel="noopener" href="?action=preview&id=' . $siteId . '">Vista previa</a>';
        if (can_publish($site)) {
            echo '<form method="post" action="?action=publish&id=' . $siteId . '">' . csrf_field() . '<button class="success">Aprobar y compilar</button></form>';
        }
        if (can_publish($site) && $page['published_version'] > 0) echo '<a class="button" href="?action=download&id=' . $siteId . '">Descargar ZIP publicado</a>';
        echo '</div></div>';
        echo '<p><a class="button" href="?action=media-library&id=' . $siteId . '">Biblioteca de imágenes →</a></p>';
        echo '<p><a class="button" href="?action=pages&id='.$siteId.'">Gestionar páginas, papelera e historial</a></p>';
        echo '<h2>Páginas del sitio</h2><p>Organiza tu sitio en varias páginas. La navegación se genera al compilar.</p>';
        echo '<div class="page-list">';
        foreach ($sitePages as $entry) {
            echo '<a class="page-entry" href="?action=edit&id=' . $siteId . '&page=' . (int)$entry['id'] . '"><strong>' . e($entry['title']) . '</strong><small>/' . e($entry['slug']) . ' · borrador v' . (int)$entry['draft_version'] . '</small><span>Editar →</span></a>';
        }
        echo '</div><p><a class="button" href="?action=new-page&id=' . $siteId . '">+ Añadir página</a></p>';
        if(Importer::manifest(config()['storage'],$siteId))echo '<p><a class="button primary" href="?action=import-visual&id='.$siteId.'">Editar visualmente la copia importada</a> <a class="button" href="?action=import-code&id='.$siteId.'">Editar HTML importado / convertir texto a bloque</a></p>';
        echo '<h2>Reconocer HTML, CSS y JavaScript existentes</h2><p>El escáner identifica archivos, dependencias e inserciones de código. No modifica los originales ni los convierte aún a bloques.</p>';
        if ($user['role'] === 'superadmin') echo '<p><a class="button" href="?action=scan&id=' . $siteId . '">Inventariar desde hosting o ZIP →</a></p>';
        if ($latest) {
            if($latest['source_kind']==='directory'){$ref=json_decode($latest['source_ref'],true);$root=is_array($ref)?($ref['root']??'default'):'default';$relative=is_array($ref)?($ref['relative']??''):$latest['source_ref'];echo '<p><a class="button primary" href="?'.e(http_build_query(['action'=>'new-site','origin'=>'directory','source_root'=>$root,'relative'=>$relative,'name'=>$site['name'].' — copia','slug'=>substr($site['slug'],0,40).'-copia-'.date('His')])).'">Crear borrador editable independiente desde este inventario</a></p>';}
            $files = json_data($latest['files_json']);
            echo '<h3>Último inventario: ' . count($files) . ' archivos <span class="muted">(' . e($latest['source_kind']) . ')</span></h3>';
            echo '<div class="table-scroll"><table><thead><tr><th>Archivo</th><th>Tipo</th><th>Tamaño</th><th>Referencias</th><th>Acción</th></tr></thead><tbody>';
            foreach ($files as $file) {
                $summary = array_map(fn($ref) => $ref['type'] . ': ' . $ref['target'], $file['references']);
                $summary[] = 'CSS interno ' . $file['inline_css'] . ' · JS interno ' . $file['inline_js'];
                echo '<tr><td><code>' . e($file['path']) . '</code></td><td>' . e(strtoupper($file['kind'])) . '</td><td>' . number_format($file['bytes']) . ' B</td><td><small>' . e(implode(' | ', $summary)) . '</small></td>';
                echo '<td><a href="?action=source&id=' . $siteId . '&scan_id=' . (int) $latest['id'] . '&file=' . rawurlencode($file['path']) . '">Ver código</a></td></tr>';
            }
            echo '</tbody></table></div>';
        }
        Views::end(); exit;
    }

    if ($action === 'new-page') {
        if ($post) {
            $name = trim((string)($_POST['title'] ?? ''));
            $slug = trim((string)($_POST['slug'] ?? ''));
            $nameLen = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
            if ($nameLen < 2 || $nameLen > 180) throw new InvalidArgumentException('Título: entre 2 y 180 caracteres.');
            if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,97}[a-z0-9])?$/D', $slug) || $slug === 'index') throw new InvalidArgumentException('Ruta inválida o reservada. Usa minúsculas, números y guiones.');
            $count = (int)query('SELECT COUNT(*) FROM pages WHERE site_id=?', [$siteId])->fetchColumn();
            if ($count >= 40) throw new RuntimeException('Máximo 40 páginas por sitio.');
            if (query('SELECT id FROM pages WHERE site_id=? AND slug=?', [$siteId,$slug])->fetch()) throw new InvalidArgumentException('Ya existe una página con esa ruta.');
            $blocks = json_encode([['type'=>'hero','eyebrow'=>'','title'=>$name,'subtitle'=>'','button_text'=>'','button_url'=>'','image_url'=>''], ['type'=>'text','title'=>'Acerca de','body'=>'Escribe aquí el contenido de esta página.']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            db()->beginTransaction();
            try {
                query('INSERT INTO pages(site_id,slug,title,draft_json,edited_by) VALUES(?,?,?,?,?)', [$siteId,$slug,$name,$blocks,$user['id']]);
                $newId = (int)db()->lastInsertId();
                query('INSERT INTO revisions(page_id,version,blocks_json,author_id) VALUES(?,?,?,?)', [$newId,1,$blocks,$user['id']]);
                db()->commit();
            } catch (Throwable $e) { db()->rollBack(); throw $e; }
            message('Página creada. Ahora puedes diseñarla.');
            go('edit', ['id'=>$siteId,'page'=>$newId]);
        }
        Views::start('Nueva página de ' . $site['name'], $user);
        echo '<p><a href="?action=site&id=' . $siteId . '">← Volver al sitio</a></p><div class="card narrow"><form method="post">' . csrf_field()
            . '<label>Nombre de la página<input name="title" maxlength="180" required placeholder="Servicios"></label>'
            . '<label>Ruta (slug)<input name="slug" maxlength="100" pattern="[a-z0-9-]+" required placeholder="servicios"></label>'
            . '<p class="hint">Se publicará como servicios.html. Podrás enlazarla desde el menú.</p><button class="primary">Crear página</button></form></div>';
        Views::end(); exit;
    }

    if ($action === 'media') {
        if ($post) { http_response_code(405); exit('Método no permitido'); }
        $item = Media::read(config()['storage'], $siteId, (string)($_GET['file'] ?? ''));
        header('Content-Type: ' . $item['mime']);
        header('Content-Disposition: inline');
        header('Content-Length: ' . $item['size']);
        header('Cache-Control: private, max-age=3600');
        readfile($item['path']);
        exit;
    }

    if ($action === 'media-library') {
        if ($post) {
            if (!isset($_FILES['image'])) throw new InvalidArgumentException('Selecciona una imagen.');
            $path = Media::upload(config()['storage'], $siteId, $_FILES['image']);
            message('Imagen subida correctamente: ' . $path);
            go('media-library', ['id'=>$siteId]);
        }
        $images = Media::list(config()['storage'], $siteId);
        Views::start('Imágenes de ' . $site['name'], $user);
        echo '<p><a href="?action=site&id=' . $siteId . '">← Proyecto</a> · <a href="?action=edit&id=' . $siteId . '">Ir al editor</a></p>';
        echo '<div class="card"><h2>Subir una imagen</h2><p>JPEG, PNG, WebP o GIF, máximo 5 MB, 100 imágenes por sitio. Las imágenes se guardan fuera de la web pública y se incluyen en <code>img/</code> al publicar.</p>';
        echo '<form method="post" enctype="multipart/form-data">' . csrf_field() . '<label>Seleccionar imagen<input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required></label><button class="primary">Subir imagen</button></form></div>';
        echo '<h2>Imágenes de este sitio</h2><div class="media-grid">';
        foreach ($images as $image) {
            $preview = '?action=media&id=' . $siteId . '&file=' . rawurlencode($image['name']);
            echo '<div class="card media-card"><img src="' . e($preview) . '" alt="Imagen subida" loading="lazy"><small>' . e($image['name']) . '</small><label>Ruta para el editor<input readonly value="' . e($image['path']) . '" onclick="this.select()"></label></div>';
        }
        if (!$images) echo '<p>Todavía no has subido imágenes.</p>';
        echo '</div>'; Views::end(); exit;
    }

    if(in_array($action,['edit','preview'],true)){
        $catalog=Pages::metadata((json_decode($site['theme_json'],true)?:[])['pages']??[]);
        if(in_array($catalog[(string)$page['id']]['status']??'active',['archived','trash'],true))throw new InvalidArgumentException('Página archivada o en papelera. Restáurala desde Gestionar páginas.');
    }
    if ($action === 'edit') {
        if ($post) {
            $version = (int) ($_POST['version'] ?? 0);
            $blocks = Builder::normalize(json_data((string) ($_POST['blocks_json'] ?? '[]')));
            foreach($blocks as $block)if($block['type']==='imported')Importer::file(config()['storage'],$siteId,$block['path']);
            $json = json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if(!hash_equals(\LandiCy\CanvasUI::codeHash(config()['storage'],$siteId),(string)($_POST['code_hash']??'')))throw new RuntimeException('El código del sitio cambió. Recarga antes de guardar los cambios visuales.');
            // Conserva el diseño global mientras se guardan los bloques de esta página.
            $storedTheme=json_decode($site['theme_json'],true)?:[];
            $theme = Builder::normalizeTheme(array_replace($storedTheme,$_POST,['pages'=>$storedTheme['pages']??[]]));
            $themeJson = json_encode($theme, JSON_THROW_ON_ERROR);
            db()->beginTransaction();
            try {
                $locked=query('SELECT theme_json FROM sites WHERE id=? FOR UPDATE',[$siteId])->fetchColumn();
                if(!hash_equals(hash('sha256',(string)$locked),(string)($_POST['theme_hash']??'')))throw new RuntimeException('La cabecera o el tema cambió en otra página. Recarga antes de guardar.');
                $stmt = query('UPDATE pages SET draft_json=?, draft_version=draft_version+1, edited_by=? WHERE id=? AND draft_version=?', [$json, $user['id'], $page['id'], $version]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('Otro editor actualizó este sitio. Recarga para evitar perder cambios.');
                if($locked!==$themeJson)Pages::saveHistory(config()['storage'],$siteId,(string)$locked);
                query('UPDATE sites SET theme_json=? WHERE id=?', [$themeJson, $siteId]);
                $newVersion = $version + 1;
                query('INSERT INTO revisions(page_id,version,blocks_json,author_id) VALUES(?,?,?,?)', [$page['id'], $newVersion, $json, $user['id']]);
                db()->commit();
            } catch (Throwable $ex) { db()->rollBack(); throw $ex; }
            if(\LandiCy\CanvasUI::requested())\LandiCy\CanvasUI::reply(['ok'=>true,'version'=>$newVersion,'theme_hash'=>hash('sha256',$themeJson),'code_hash'=>\LandiCy\CanvasUI::codeHash(config()['storage'],$siteId)]);
            message('Borrador guardado (versión ' . $newVersion . '). No afecta la web pública.');
            go('edit', ['id' => $siteId, 'page' => (int)$page['id']]);
        }
        foreach(json_data($page['draft_json']) as $existingBlock)if(($existingBlock['type']??'')==='imported'&&count(json_data($page['draft_json']))===1)go('import-visual',['id'=>$siteId,'file'=>$existingBlock['path']]);
        Views::start('Diseñar: ' . $page['title'], $user, true);
        $theme = Builder::normalizeTheme(json_decode($site['theme_json'], true) ?: []);
        $layout = Builder::normalizeLayout($theme, (string)$site['name']);
        $media = Media::list(config()['storage'], $siteId);
        $fontNames = ['system' => 'Sistema', 'arial' => 'Arial', 'verdana' => 'Verdana', 'trebuchet' => 'Trebuchet MS', 'georgia' => 'Georgia', 'times' => 'Times New Roman', 'mono' => 'Monoespaciada'];
        $fontSelect = static function(string $id, string $label, string $current) use ($fontNames): string {
            $html = '<label>' . e($label) . '<select id="' . e($id) . '" name="' . e($id) . '">';
            foreach ($fontNames as $key => $name) $html .= '<option value="' . e($key) . '"' . ($current === $key ? ' selected' : '') . '>' . e($name) . '</option>';
            return $html . '</select></label>';
        };
        $pageTitle = e($page['title']);
        echo '<form method="post" id="editor-form" class="visual-editor">' . csrf_field()
            . '<input type="hidden" name="code_hash" value="'.\LandiCy\CanvasUI::codeHash(config()['storage'],$siteId).'"><input type="hidden" name="theme_hash" value="'.hash('sha256',$site['theme_json']).'"><input type="hidden" name="version" value="' . (int)$page['draft_version'] . '"><input type="hidden" id="blocks-json" name="blocks_json">'
            . '<div class="editor-toolbar"><div class="editor-identity"><a href="?action=site&id=' . $siteId . '" class="editor-back" aria-label="Volver al proyecto">←</a><div><strong>' . e($site['name']) . '</strong><small>' . $pageTitle . ' · v' . (int)$page['draft_version'] . '</small></div></div>'
            . '<div class="editor-toolbar-mid"><span class="editor-tip">Haz clic en una sección para editarla. Doble clic en un texto para escribir.</span><span id="editor-state" role="status" aria-live="polite">' . e(Views::editorStatus()) . '</span></div>'
            . '<a class="button" href="?action=preview&id=' . $siteId . '&page=' . (int)$page['id'] . '" target="_blank" rel="noopener noreferrer" title="Ver el último borrador guardado">Vista previa</a><button class="primary editor-save" type="submit">Guardar borrador</button></div>'
            . \LandiCy\CanvasUI::controls()
            . '<div class="visual-workspace"><div class="visual-stage">'
            . '<div class="stage-top"><div class="stage-info"><strong>Lienzo del sitio</strong><small>Vista en vivo · ' . $pageTitle . '</small></div>'
            . '<div class="actions" role="group" aria-label="Tamaño de vista previa">'
            . '<button type="button" id="view-desktop" class="view-mode active" aria-pressed="true" title="Escritorio">▣ <span>Escritorio</span></button>'
            . '<button type="button" id="view-tablet" class="view-mode" aria-pressed="false" title="Tableta">▤ <span>Tableta</span></button>'
            . '<button type="button" id="view-mobile" class="view-mode" aria-pressed="false" title="Móvil">▯ <span>Móvil</span></button></div></div>'
            . '<div id="frame-holder" class="frame-holder"><iframe id="live-frame" title="Lienzo editable de la página" referrerpolicy="no-referrer" srcdoc="' . e('<meta http-equiv="Content-Security-Policy" content="default-src &apos;none&apos;; script-src &apos;none&apos;; style-src &apos;unsafe-inline&apos;; img-src &apos;self&apos; data: https:; frame-src &apos;self&apos; https://www.youtube-nocookie.com https://www.google.com https://docs.google.com; base-uri &apos;none&apos;; form-action &apos;none&apos;">'.Builder::html($site, $page, Builder::normalize(json_data($page['draft_json'])), true, $sitePages, DraftCode::read(config()['storage'], $siteId))) . '"></iframe></div>'
            . '<p class="editor-help">Los enlaces del menú y los botones internos pueden llevarte a otras páginas del editor. Guarda antes de cambiar de página. Puedes arrastrar secciones desde su controlador en el lienzo.</p>'
            . '</div><aside class="visual-controls" aria-label="Herramientas de edición">'
            . \LandiCy\EditorChrome::tabs()
            . '<div class="editor-sidebar-scroll"><section class="tab-pane active" data-pane="insert"><h2>Insertar contenido</h2><p class="pane-intro">Añade una sección al final y selecciónala para personalizarla.</p>'
            . '<div id="insert-grid" class="insert-grid"></div><div class="sections-shortcut"><h3>Estructura de la página</h3><div id="section-list"></div></div></section>'
            . '<section class="tab-pane" data-pane="edit"><h2>Editar sección</h2><div id="blocks"></div></section>'
            . '<section class="tab-pane" data-pane="pages"><h2>Páginas del sitio</h2><p class="pane-intro">Cada página tiene su propio borrador. Todas se publican juntas.</p><div class="editor-pages">';
        foreach ($sitePages as $entry) {
            $isCurrent = (int)$entry['id'] === (int)$page['id'];
            echo '<a class="editor-page-link' . ($isCurrent ? ' current' : '') . '" href="?action=edit&id=' . $siteId . '&page=' . (int)$entry['id'] . '"' . ($isCurrent ? ' aria-current="page"' : '') . '><span>▤</span><span><strong>' . e($entry['title']) . '</strong><small>/' . e($entry['slug']) . '</small></span></a>';
        }
        echo '</div><p><a class="button new-page-link" href="?action=new-page&id=' . $siteId . '">+ Crear página</a></p></section>'
            . '<section class="tab-pane" data-pane="themes"><h2>Apariencia</h2><p class="pane-intro">Los cambios se ven de inmediato y se aplican a todo el sitio al guardar.</p><h3>Paletas rápidas</h3>'
            . '<div class="theme-presets"><button type="button" class="palette" data-primary="#225cf0" data-accent="#111827" data-background="#ffffff" title="Azul clásico"><i style="background:#225cf0"></i><i style="background:#111827"></i><i style="background:#ffffff"></i></button>'
            . '<button type="button" class="palette" data-primary="#136f63" data-accent="#164b45" data-background="#fafcfb" title="Verde"><i style="background:#136f63"></i><i style="background:#164b45"></i><i style="background:#fafcfb"></i></button>'
            . '<button type="button" class="palette" data-primary="#a03f6a" data-accent="#4b2241" data-background="#fffafd" title="Rosa"><i style="background:#a03f6a"></i><i style="background:#4b2241"></i><i style="background:#fffafd"></i></button>'
            . '<button type="button" class="palette" data-primary="#b45d19" data-accent="#27222a" data-background="#fffaf3" title="Cálido"><i style="background:#b45d19"></i><i style="background:#27222a"></i><i style="background:#fffaf3"></i></button></div>'
            . '<div class="theme-fields"><div class="color-fields"><label>Principal<input type="color" id="theme-primary" name="primary" value="' . e($theme['primary']) . '"></label>'
            . '<label>Contraste<input type="color" id="theme-accent" name="accent" value="' . e($theme['accent']) . '"></label>'
            . '<label>Modo<select id="color_scheme" name="color_scheme"><option value="light"'.($theme['color_scheme']==='light'?' selected':'').'>Claro</option><option value="dark"'.($theme['color_scheme']==='dark'?' selected':'').'>Oscuro</option><option value="auto"'.($theme['color_scheme']==='auto'?' selected':'').'>Sistema</option></select></label><label>Fondo<input type="color" id="theme-background" name="background" value="' . e($theme['background']) . '"></label></div>'
            . $fontSelect('font_body', 'Fuente del contenido', $theme['font_body'])
            . '<label>Texto<input type="color" id="text_color" name="text_color" value="'.e($theme['text_color']).'"></label><label>Texto secundario<input type="color" id="muted_color" name="muted_color" value="'.e($theme['muted_color']).'"></label><label>Fondo alternativo<input type="color" id="background_alt" name="background_alt" value="'.e($theme['background_alt']).'"></label><label>Bordes<input type="color" id="border_color" name="border_color" value="'.e($theme['border_color']).'"></label>'
            . $fontSelect('font_heading', 'Fuente de títulos', $theme['font_heading'])
            . '<label>Tamaño de texto: <output id="base-size-label">' . (int)$theme['base_size'] . '</output> px<input id="base_size" name="base_size" type="range" min="14" max="20" step="1" value="' . (int)$theme['base_size'] . '"></label>'
            . '<label>Escala de títulos: <output id="title-scale-label">' . (int)$theme['title_scale'] . '</output> %<input id="title_scale" name="title_scale" type="range" min="80" max="125" step="5" value="' . (int)$theme['title_scale'] . '"></label></div></section>'
            . '<section class="tab-pane" data-pane="layout"><h2>Cabecera y pie globales</h2><p class="pane-intro">Estos elementos aparecen automáticamente en todas las páginas. Haz clic en la cabecera o pie del lienzo para venir aquí.</p>'
            . '<h3>Cabecera</h3><label>Nombre visible<input name="header_brand" id="header_brand" maxlength="120" value="' . e($layout['header_brand']) . '"></label>'
            . '<label>Icono de pestaña (independiente del logo)<input name="favicon" id="favicon" value="' . e($layout['favicon']) . '" placeholder="img/archivo.png o HTTPS"></label>'
            . '<label>Logotipo<input name="header_logo" id="header_logo" value="' . e($layout['header_logo']) . '" placeholder="img/archivo.png o HTTPS"></label>'
            . '<div class="color-fields"><label>Fondo<input type="color" name="header_bg" id="header_bg" value="' . e($layout['header_bg']) . '"></label><label>Texto<input type="color" name="header_color" id="header_color" value="' . e($layout['header_color']) . '"></label></div>'
            . '<label>Posición<select name="header_style" id="header_style"><option value="normal"' . ($layout['header_style'] === 'normal' ? ' selected' : '') . '>Normal</option><option value="sticky"' . ($layout['header_style'] === 'sticky' ? ' selected' : '') . '>Fijada arriba al desplazarse</option></select></label>'
            . '<p class="field-hint">El menú se crea automáticamente desde las páginas del sitio.</p><h3>Pie de página</h3>'
            . '<label>Texto<input name="footer_text" id="footer_text" maxlength="400" value="' . e($layout['footer_text']) . '"></label>'
            . '<label>Logotipo del pie<input name="footer_logo" id="footer_logo" value="' . e($layout['footer_logo']) . '" placeholder="img/archivo.png o HTTPS"></label>'
            . '<label>Texto del enlace opcional<input name="footer_link_text" id="footer_link_text" maxlength="100" value="' . e($layout['footer_link_text']) . '"></label>'
            . '<label>Destino del enlace<input name="footer_link_url" id="footer_link_url" value="' . e($layout['footer_link_url'] === '#' ? '' : $layout['footer_link_url']) . '" placeholder="https://ejemplo.com"></label>'
            . '<div class="color-fields"><label>Fondo<input type="color" name="footer_bg" id="footer_bg" value="' . e($layout['footer_bg']) . '"></label><label>Texto<input type="color" name="footer_color" id="footer_color" value="' . e($layout['footer_color']) . '"></label></div>'
                        . '<label>Imagen de fondo de cabecera<input id="header_image" name="header_image" value="'.e($layout['header_image']).'" placeholder="img/archivo.png o HTTPS"></label>'
            . '<label>Altura mínima de cabecera<input id="header_height" name="header_height" type="range" min="40" max="480" value="'.(int)$layout['header_height'].'"></label>'
            . '<label>Espaciado global<input id="layout_spacing" name="layout_spacing" type="range" min="0" max="80" value="'.(int)$layout['layout_spacing'].'"></label>'
            . '<label>Alineación de cabecera<select id="header_align" name="header_align">';
        foreach(['left'=>'Izquierda','center'=>'Centro','right'=>'Derecha'] as $key=>$label)echo '<option value="'.$key.'"'.($layout['header_align']===$key?' selected':'').'>'.$label.'</option>';
        echo '</select></label><label>Columnas del pie<select id="footer_columns" name="footer_columns">';
        foreach([1,2,3,4] as $count)echo '<option value="'.$count.'"'.($layout['footer_columns']===$count?' selected':'').'>'.$count.'</option>';
        echo '</select></label>';
        foreach(['header_mode'=>'Cabecera','footer_mode'=>'Pie'] as $key=>$label)echo '<label>'.e($label).'<select id="'.$key.'" name="'.$key.'"><option value="visual"'.($layout[$key]==='visual'?' selected':'').'>Diseño visual</option><option value="template"'.($layout[$key]==='template'?' selected':'').'>Plantilla HTML de código</option></select></label>';echo '<p><a href="?action=code&id='.$siteId.'&file=html%2Fheader-template.html">Editar plantilla de cabecera</a> · <a href="?action=code&id='.$siteId.'&file=html%2Ffooter-template.html">Editar plantilla de pie</a></p><p>Variables: {{brand}}, {{menu}}, {{logo}}, {{copyright}}, {{year}}. Guarda y recarga para aplicar el cambio de modo; las plantillas tienen historial y control de conflictos.</p>';
        foreach(['header_button_text'=>'Texto del botón de cabecera','header_button_url'=>'URL del botón de cabecera','footer_padding'=>'Padding del pie'] as $key=>$label)echo '<label>'.e($label).'<input id="'.e($key).'" name="'.e($key).'" value="'.e((string)$layout[$key]).'"></label>';
        foreach(['header_visibility'=>'Visibilidad de cabecera','footer_visibility'=>'Visibilidad del pie','footer_align'=>'Alineación del pie'] as $key=>$label){echo '<label>'.e($label).'<select id="'.e($key).'" name="'.e($key).'">';foreach(($key==='footer_align'?['left'=>'Izquierda','center'=>'Centro','right'=>'Derecha']:['all'=>'Siempre','desktop'=>'Solo escritorio','mobile'=>'Solo móvil','none'=>'Oculto']) as $value=>$text)echo '<option value="'.$value.'"'.($layout[$key]===$value?' selected':'').'>'.$text.'</option>';echo '</select></label>';}
        echo '<label>Enlaces sociales (JSON: label, kind=link, url HTTPS)<textarea id="social_links" name="social_links" maxlength="12000">'.e(json_encode($layout['social_links'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)).'</textarea></label>';
        foreach(['header_font_size'=>'Texto de cabecera (px)','footer_font_size'=>'Texto de pie (px)','footer_min_height'=>'Altura mínima del pie','footer_image'=>'Fondo del pie (imagen HTTPS o biblioteca)'] as $key=>$label)echo '<label>'.e($label).'<input id="'.e($key).'" name="'.e($key).'" value="'.e((string)$layout[$key]).'"></label>';
        echo $fontSelect('header_font','Fuente de cabecera',$layout['header_font']).$fontSelect('footer_font','Fuente del pie',$layout['footer_font']);
        echo '<h3>Menú compartido</h3><p>Vacío: navegación automática por páginas. Añade y ordena páginas o enlaces externos.</p><input type="hidden" id="menu-json" name="menu" value="'.e(json_encode($layout['menu'],JSON_THROW_ON_ERROR)).'"><div id="menu-controls"></div>'

            . '<p><a class="button" href="?action=media-library&id=' . $siteId . '">Subir imágenes →</a></p></section>'
            . '</div></aside></div></form>';
        echo '<script id="initial-blocks" type="application/json">' . str_replace('<', '\u003C', $page['draft_json']) . '</script>';
        echo '<script id="site-title" type="application/json">' . json_encode((string)$site['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) . '</script>';
        echo '<script id="site-media" type="application/json">' . json_encode($media, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) . '</script>';
        echo '<script id="site-id" type="application/json">' . (int)$siteId . '</script>';
        echo '<script src="assets/editor.js?v=063" defer></script>';
        Views::end(); exit;
    }

    if($action==='preview'){$previewBlocks=json_data($page['draft_json']);if(count($previewBlocks)===1&&($previewBlocks[0]['type']??'')==='imported'){header_remove('X-Frame-Options');$importPath=$previewBlocks[0]['path'];Importer::serve(Importer::file(config()['storage'],$siteId,$importPath),$importPath,'?action=import-file&id='.$siteId.'&file=');}}
    if ($action === 'preview') {
        header_remove('X-Frame-Options');
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data: https:; script-src 'none'; frame-src 'self' https://www.youtube-nocookie.com https://www.google.com https://docs.google.com; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");
        echo Builder::html($site, $page, Builder::normalize(json_data($page['draft_json'])), true, $sitePages, DraftCode::read(config()['storage'], $siteId));
        exit;
    }

    if ($action === 'publish') {
        if (!$post || $user['role'] !== 'superadmin' || !can_publish($site)) { http_response_code(403); exit('No autorizado.'); }
        $publishLock=DraftTrash::projectLock(config()['storage'],$siteId);if(DraftTrash::contains(config()['storage'],$siteId))throw new RuntimeException('El borrador está en la papelera.');
        $allPages = query("SELECT * FROM pages WHERE site_id=? ORDER BY (slug = 'index') DESC, id ASC", [$siteId])->fetchAll();
        $allPages=Pages::listing($allPages,json_decode($site['theme_json'],true)?:[]);
        Builder::publishPages($site, $allPages, config()['storage']);
        db()->beginTransaction();
        try {
            foreach ($allPages as $entry) query('UPDATE pages SET published_json=draft_json, published_version=draft_version WHERE id=? AND draft_version=?', [$entry['id'], $entry['draft_version']]);
            db()->commit();
        } catch (Throwable $e) { db()->rollBack(); throw $e; }
        message('Sitio compilado (' . count($allPages) . ' páginas). Descarga el ZIP para desplegar; tu dominio no se modifica automáticamente.');
        go('site', ['id' => $siteId]);
    }

    if ($action === 'download') {
        if ($user['role'] !== 'superadmin') { http_response_code(403); exit('Solo superadministradores.'); }
        if ($page['published_version'] < 1) throw new RuntimeException('Publica primero.');
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('Necesitas la extensión PHP zip para descargar.');
        $folder = rtrim(config()['storage'], '/') . '/exports/' . $site['slug'];
        if (!is_dir($folder)) throw new RuntimeException('No existe el paquete exportado.');
        $temporary = tempnam(sys_get_temp_dir(), 'landicy_');
        if ($temporary === false) throw new RuntimeException('No se pudo crear el ZIP temporal.');
        $zip = new ZipArchive();
        try {
            if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('No se pudo abrir ZIP de descarga.');
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $item) {
                if ($item->isFile()) $zip->addFile($item->getPathname(), substr($item->getPathname(), strlen($folder) + 1));
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $site['slug'] . '-publicado.zip"');
            header('Content-Length: ' . filesize($temporary));
            readfile($temporary);
        } finally { if (is_file($temporary)) unlink($temporary); }
        exit;
    }

    if ($action === 'code') {
        // Solo archivos generados por este sitio y fragmentos privados del borrador.
        $available=[];
        foreach ($sitePages as $p) {
            $slug=(string)$p['slug'];
            $available['html/'.$slug.'.html']=['editable'=>true,'label'=>'Fragmento de '.$p['title']];
            $available[$slug.'.html']=['editable'=>false,'label'=>'HTML generado: '.$p['title']];
        }
        $available=['html/header-template.html'=>['editable'=>true,'label'=>'Reemplazo completo de cabecera (activar en Cabecera y pie)'],'html/footer-template.html'=>['editable'=>true,'label'=>'Reemplazo completo de pie (activar en Cabecera y pie)'],'html/header.html'=>['editable'=>true,'label'=>'Contenido adicional de cabecera'],
            'html/footer.html'=>['editable'=>true,'label'=>'Contenido adicional de pie'],
            ...$available,
            'css/site.css'=>['editable'=>false,'label'=>'CSS generado por el tema'],
            'css/custom.css'=>['editable'=>true,'label'=>'CSS personalizado'],
            'js/site.js'=>['editable'=>false,'label'=>'JavaScript base'],
            'js/custom.js'=>['editable'=>true,'label'=>'JavaScript personalizado']];
        $file=(string)($_GET['file']??'html/header.html');
        if (!isset($available[$file])) throw new InvalidArgumentException('El archivo no pertenece al borrador del sitio.');
        if ($post) {
            $lockDir=rtrim(config()['storage'],'/').'/code-locks';if(!is_dir($lockDir)&&!mkdir($lockDir,0700,true)&&!is_dir($lockDir))throw new RuntimeException('No se pudo bloquear el código.');
            $lock=fopen($lockDir.'/'.$siteId.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Código ocupado.');
            $currentCode=DraftCode::read(config()['storage'],$siteId);
            if(!hash_equals(hash('sha256',json_encode($currentCode,JSON_THROW_ON_ERROR)),(string)($_POST['code_hash']??'')))throw new RuntimeException('Otro editor modificó el código. Recarga antes de guardar o restaurar.');
            if(($_POST['operation']??'')==='restore'){DraftCode::restore(config()['storage'],$siteId,(string)($_POST['revision']??''),$sitePages);message('Versión del código restaurada en el borrador.');go('code',['id'=>$siteId,'file'=>$file]);}
            if (!$available[$file]['editable']) { http_response_code(403); exit('Archivo generado de solo lectura.'); }
            DraftCode::write(config()['storage'], $siteId, $file, (string)($_POST['contents']??''), $sitePages);
            message('Código del borrador guardado. No se ha publicado ni modificado el sitio existente.');
            go('code', ['id'=>$siteId,'file'=>$file]);
        }
        $draft=DraftCode::read(config()['storage'],$siteId);
        $source='';
        if ($available[$file]['editable']) $source=(string)($draft[$file]??'');
        elseif ($file==='css/site.css') $source=Builder::css(json_decode($site['theme_json'],true)?:[]);
        elseif ($file==='js/site.js') $source=Builder::runtimeJs();
        else {
            foreach ($sitePages as $p) if (($p['slug'].'.html')===$file) {
                $full=query('SELECT * FROM pages WHERE id=? AND site_id=?',[(int)$p['id'],$siteId])->fetch();
                if ($full) $source=Builder::html($site,$full,Builder::normalize(json_data($full['draft_json'])),false,$sitePages,$draft);
                break;
            }
        }
        Views::start('Analizar código: '. $site['name'], $user);
        echo '<p><a href="?action=site&id='.$siteId.'">← Volver al proyecto</a></p>';
        echo '<p class="notice">Solo el borrador de este sitio. Los archivos HTML y CSS generados son de lectura; los fragmentos editables se guardan aparte y solo se incorporan al ZIP cuando el superadministrador publica. El JavaScript personalizado NO se ejecuta en el editor.</p>';
        $codeHash='<input type="hidden" name="code_hash" value="'.hash('sha256',json_encode($draft,JSON_THROW_ON_ERROR)).'">';
        $history=DraftCode::history(config()['storage'],$siteId);
        if($history){echo '<form method="post">'.csrf_field().$codeHash.'<input type="hidden" name="operation" value="restore"><label>Restaurar versión del código<select name="revision">';foreach(array_slice($history,0,60) as $revision)echo '<option value="'.e($revision).'">'.e($revision).'</option>';echo '</select></label><button>Restaurar código del borrador</button></form>';}
        if(Importer::manifest(config()['storage'],$siteId))echo '<p><a class="button" href="?action=import-code&id='.$siteId.'">HTML/CSS/JS/imagenes del sitio importado</a></p>';
        echo '<div class="code-browser"><aside class="code-tree"><h2>Archivos del borrador</h2>';
        foreach (['html','css','js'] as $folder) {
            echo '<h3>📁 '.e($folder).'/</h3>';
            foreach ($available as $name=>$meta) if (str_starts_with($name,$folder.'/') || ($folder==='html'&&!str_contains($name,'/'))) {
                echo '<a class="code-tree-link'.($file===$name?' current':'').'" href="?action=code&id='.$siteId.'&file='.rawurlencode($name).'">'.e($name).' '.($meta['editable']?'✎':'🔒').'</a>';
            }
        }
        echo '</aside><section class="card code-editor"><h2>'.e($file).'</h2><p>'.e($available[$file]['label']).'</p>';
        if ($available[$file]['editable']) {
            echo '<form method="post">'.csrf_field().$codeHash.'<label for="draft-source">Contenido del archivo</label><textarea id="draft-source" name="contents" spellcheck="false" maxlength="100000" rows="24">'.e($source).'</textarea><p class="hint">HTML permitido: texto, imágenes, enlaces y elementos habituales, sin scripts ni atributos peligrosos. CSS sin importaciones remotas. JS: revisar antes de publicar.</p><button class="primary">Guardar en borrador</button></form>';
        } else echo '<pre class="codeview"><code>'.e($source).'</code></pre>';
        echo '</section></div><p><a class="button" href="?action=scan&id='.$siteId.'">Identificar web existente (solo lectura) →</a></p>';
        Views::end();exit;
    }

    if ($action === 'scan') {
        if ($user['role'] !== 'superadmin') { http_response_code(403); exit('Escaneo solo para superadministradores.'); }
        if ($post) {
            $type = (string) ($_POST['source_kind'] ?? 'directory');
            if ($type === 'directory') {
                [$root,$reference]=SourceRoots::resolve(config(),(string)($_POST['source_root']??'default'),(string)($_POST['relative']??''));
                if ($root === '' || !is_dir($root)) throw new InvalidArgumentException('Configura scan_root explícitamente antes de inventariar una carpeta.'); // Por defecto, solo public_html, nunca landicy-private.
                // Canonical relative path resolved against an exact authorized root.
                if (strlen($reference) > 240) throw new InvalidArgumentException('Ruta demasiado larga.');
                $files = Scanner::scanDirectory($root, $reference);
                $reference=json_encode(['root'=>(string)($_POST['source_root']??'default'),'relative'=>$reference],JSON_THROW_ON_ERROR);
            } elseif ($type === 'zip') {
                if (!isset($_FILES['archive']) || $_FILES['archive']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir el archivo ZIP.');
                if ($_FILES['archive']['size'] > 15728640) throw new InvalidArgumentException('ZIP excede 15 MB.');
                $tmp = $_FILES['archive']['tmp_name'];
                $files = Scanner::scanZip($tmp);
                $directory = rtrim(config()['storage'], '/') . '/imports/' . $siteId;
                if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('No se pudo crear directorio de importación.');
                $reference = bin2hex(random_bytes(16)) . '.zip';
                if (!move_uploaded_file($tmp, $directory . '/' . $reference)) throw new RuntimeException('No se pudo guardar ZIP privado.');
            } else throw new InvalidArgumentException('Origen de escaneo inválido.');
            query('INSERT INTO site_scans(site_id,source_kind,source_ref,files_json) VALUES(?,?,?,?)', [
                $siteId, $type, $reference,
                json_encode($files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
            message('Inventario realizado: ' . count($files) . ' archivos HTML/CSS/JS. Sin modificaciones sobre la web original.');
            go('site', ['id' => $siteId]);
        }
        Views::start('Inventariar código existente', $user);
        echo '<p><a href="?action=site&id=' . $siteId . '">← Proyecto</a></p>';
        echo '<div class="cards"><section class="card"><h2>Carpeta del hosting (lectura)</h2><p>Solo lectura: requiere una raíz permitida explícita en <code>scan_root</code> de config.php. No se ejecuta ni modifica código.</p>';
        echo '<form method="post">' . csrf_field() . '<input type="hidden" name="source_kind" value="directory">'
            . SourceRoots::options(config()).'<label>Subcarpeta relativa a la raíz elegida (vacía para la raíz autorizada)<input name="relative" placeholder="mi-sitio (opcional)" autocomplete="off"></label>'
            . '<button class="primary">Analizar carpeta</button></form></section>';
        echo '<section class="card"><h2>Archivo ZIP de un sitio</h2><p>Sube un ZIP de archivos web propios. Se examina sin ejecutar ni extraer código PHP.</p>';
        echo '<form method="post" enctype="multipart/form-data">' . csrf_field() . '<input type="hidden" name="source_kind" value="zip">'
            . '<label>ZIP (máximo 15 MB)<input type="file" name="archive" accept=".zip,application/zip" required></label>'
            . '<button class="primary">Analizar ZIP</button></form></section></div>';
        Views::end(); exit;
    }

    if ($action === 'source') {
        $scanId = (int) ($_GET['scan_id'] ?? 0);
        $filename = (string) ($_GET['file'] ?? '');
        $scan = query('SELECT * FROM site_scans WHERE id=? AND site_id=?', [$scanId, $siteId])->fetch();
        if (!$scan) { http_response_code(404); exit('Inventario no encontrado.'); }
        $files = json_data($scan['files_json']);
        if (!in_array($filename, array_column($files, 'path'), true)) throw new InvalidArgumentException('El archivo no pertenece al inventario.');
        if ($scan['source_kind'] === 'zip') {
            $archive = rtrim(config()['storage'], '/') . '/imports/' . $siteId . '/' . $scan['source_ref'];
            $contents = Scanner::readZipFile($archive, $filename);
        } else {
            $reference=json_decode($scan['source_ref'],true);
            [$allowedRoot,$sourceRelative]=SourceRoots::resolve(config(),is_array($reference)?(string)($reference['root']??'default'):'default',is_array($reference)?(string)($reference['relative']??''):$scan['source_ref']);
            if ($allowedRoot === '' || !is_dir($allowedRoot)) throw new RuntimeException('Configura scan_root explícitamente.');
            $contents = Scanner::readDirectoryFile($allowedRoot, $sourceRelative, $filename);
        }
        Views::start('Código: ' . $filename, $user);
        echo '<p><a href="?action=site&id=' . $siteId . '">← Inventario</a></p>';
        echo '<p class="notice">Vista de solo lectura: el código no se ejecuta.</p><pre class="codeview"><code>' . e($contents) . '</code></pre>';
        Views::end(); exit;
    }

    http_response_code(404);
    Views::error('No encontrado', 'Esta sección todavía no existe.', $user);
} catch (Throwable $ex) {
    $isDb = $ex instanceof PDOException;
    $isPublic = $ex instanceof InvalidArgumentException || $ex instanceof RuntimeException;
    http_response_code($isDb ? 500 : ($isPublic ? 400 : 500));
    if(\LandiCy\CanvasUI::requested())\LandiCy\CanvasUI::reply(['ok'=>false,'message'=>$isPublic&&!$isDb?$ex->getMessage():'No se pudo guardar el borrador.'], $isDb?500:400);
    Views::error('No se completó la operación', $isDb ? 'Error de base de datos. Revisa la instalación o consulta el registro del servidor.' : ($isPublic ? $ex->getMessage() : 'Error inesperado. Consulta el registro del servidor.'), $user ?? null);
}
