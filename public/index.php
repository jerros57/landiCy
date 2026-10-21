<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2026 LandiCy contributors. See LICENSE and LICENSE-OUTPUT-EXCEPTION.md.

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use LandiCy\Builder;
use LandiCy\Scanner;
use LandiCy\Views;

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

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
        Views::start('Tus sitios', $user);
        echo '<p>Un panel, múltiples proyectos. La publicación produce HTML, CSS, JavaScript y JSON estáticos.</p>';
        if ($user['role'] === 'superadmin') echo '<p><a class="primary button" href="?action=new-site">+ Nuevo sitio</a></p>';
        echo '<div class="cards">';
        foreach ($sites as $site) {
            echo '<article class="card"><p class="overline">' . e($site['slug']) . '</p><h2>' . e($site['name']) . '</h2><p>Versión borrador ' . (int) $site['draft_version']
                . ' · publicada ' . (int) $site['published_version'] . '</p><a class="button" href="?action=site&id=' . (int) $site['id'] . '">Gestionar →</a></article>';
        }
        if (!$sites) echo '<p class="muted">Aún no tienes sitios asignados.</p>';
        echo '</div>'; Views::end(); exit;
    }

    if ($action === 'new-site') {
        if ($user['role'] !== 'superadmin') { http_response_code(403); exit('Solo superadministradores.'); }
        if ($post) {
            $name = trim((string) ($_POST['name'] ?? ''));
            $slug = trim((string) ($_POST['slug'] ?? ''));
            $domain = trim((string) ($_POST['domain'] ?? ''));
            $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : preg_match_all('/./us', $name);
            if ($nameLength === false || $nameLength < 2 || $nameLength > 120) throw new InvalidArgumentException('Nombre: entre 2 y 120 caracteres.');
            if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,58}[a-z0-9])?$/D', $slug)) throw new InvalidArgumentException('Slug inválido (solo minúsculas, números y guiones).');
            if (strlen($domain) > 190 || preg_match('/[\x00-\x20\\\/]/', $domain)) throw new InvalidArgumentException('Dominio descriptivo inválido.');
            $theme = json_encode(['primary' => Builder::color((string) ($_POST['primary'] ?? '')), 'accent' => Builder::color((string) ($_POST['accent'] ?? ''))], JSON_THROW_ON_ERROR);
            db()->beginTransaction();
            try {
                query('INSERT INTO sites(name,slug,domain_name,theme_json,created_by) VALUES(?,?,?,?,?)', [$name, $slug, $domain ?: null, $theme, $user['id']]);
                $siteId = (int) db()->lastInsertId();
                query('INSERT INTO site_users(site_id,user_id,role) VALUES(?,?,"admin")', [$siteId, $user['id']]);
                $blocks = json_encode(Builder::defaultBlocks($name), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                query('INSERT INTO pages(site_id,slug,title,draft_json,edited_by) VALUES(?,"index",?,?,?)', [$siteId, $name, $blocks, $user['id']]);
                $pageId = (int) db()->lastInsertId();
                query('INSERT INTO revisions(page_id,version,blocks_json,author_id) VALUES(?,1,?,?)', [$pageId, $blocks, $user['id']]);
                db()->commit();
            } catch (Throwable $exception) { db()->rollBack(); throw $exception; }
            message('Sitio creado. Edita su borrador y publícalo.');
            go('site', ['id' => $siteId]);
        }
        Views::start('Crear sitio', $user);
        echo '<div class="card narrow"><form method="post">' . csrf_field()
            . '<label>Nombre<input name="name" placeholder="Mi comunidad" required maxlength="120"></label>'
            . '<label>Slug (único)<input name="slug" placeholder="mi-comunidad" pattern="[a-z0-9-]+" required></label>'
            . '<label>Dominio (opcional)<input name="domain" placeholder="ejemplo.com"></label>'
            . '<div class="two"><label>Color principal<input type="color" name="primary" value="#225cf0"></label>'
            . '<label>Color contraste<input type="color" name="accent" value="#111827"></label></div>'
            . '<button class="primary">Crear proyecto</button></form></div>';
        Views::end(); exit;
    }

    if (in_array($action, ['site', 'edit', 'preview', 'publish', 'download', 'scan', 'source'], true)) {
        $siteId = (int) ($_GET['id'] ?? 0);
        $site = site_or_404($siteId, $user);
        $page = site_page($siteId);
    }

    if ($action === 'site') {
        $latest = query('SELECT * FROM site_scans WHERE site_id=? ORDER BY id DESC LIMIT 1', [$siteId])->fetch();
        Views::start($site['name'], $user);
        echo '<p><a href="?action=dashboard">← Mis sitios</a></p><div class="card">';
        echo '<p><strong>Proyecto:</strong> ' . e($site['slug']) . ' · <strong>Dominio orientativo:</strong> ' . e($site['domain_name'] ?: 'sin definir') . '</p>';
        echo '<p><strong>Borrador:</strong> v' . (int) $page['draft_version'] . ' · <strong>Publicado:</strong> v' . (int) $page['published_version'] . '</p>';
        echo '<div class="actions"><a class="primary button" href="?action=edit&id=' . $siteId . '">Editar bloques</a>';
        echo '<a class="button" target="_blank" rel="noopener" href="?action=preview&id=' . $siteId . '">Vista previa</a>';
        if (can_publish($site)) {
            echo '<form method="post" action="?action=publish&id=' . $siteId . '">' . csrf_field() . '<button class="success">Aprobar y compilar</button></form>';
        }
        if ($page['published_version'] > 0) echo '<a class="button" href="?action=download&id=' . $siteId . '">Descargar ZIP publicado</a>';
        echo '</div></div>';
        echo '<h2>Reconocer HTML, CSS y JavaScript existentes</h2><p>El escáner identifica archivos, dependencias e inserciones de código. No modifica los originales ni los convierte aún a bloques.</p>';
        if ($user['role'] === 'superadmin') echo '<p><a class="button" href="?action=scan&id=' . $siteId . '">Inventariar desde hosting o ZIP →</a></p>';
        if ($latest) {
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

    if ($action === 'edit') {
        if ($post) {
            $version = (int) ($_POST['version'] ?? 0);
            $blocks = Builder::normalize(json_data((string) ($_POST['blocks_json'] ?? '[]')));
            $json = json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            db()->beginTransaction();
            try {
                $stmt = query('UPDATE pages SET draft_json=?, draft_version=draft_version+1, edited_by=? WHERE id=? AND draft_version=?', [$json, $user['id'], $page['id'], $version]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('Otro editor actualizó este sitio. Recarga para evitar perder cambios.');
                $newVersion = $version + 1;
                query('INSERT INTO revisions(page_id,version,blocks_json,author_id) VALUES(?,?,?,?)', [$page['id'], $newVersion, $json, $user['id']]);
                db()->commit();
            } catch (Throwable $ex) { db()->rollBack(); throw $ex; }
            message('Borrador guardado (versión ' . $newVersion . '). No afecta la web pública.');
            go('site', ['id' => $siteId]);
        }
        Views::start('Editar: ' . $site['name'], $user);
        echo '<p><a href="?action=site&id=' . $siteId . '">← Proyecto</a> · Usa los controles para añadir, ordenar o borrar bloques.</p>';
        echo '<div class="layout-editor"><div class="card"><form method="post" id="editor-form">' . csrf_field()
            . '<input type="hidden" name="version" value="' . (int) $page['draft_version'] . '"><input type="hidden" id="blocks-json" name="blocks_json">'
            . '<div id="blocks" aria-live="polite"></div><div class="actions"><select id="new-type" aria-label="Tipo de bloque">'
            . '<option value="hero">Portada</option><option value="text">Texto</option><option value="features">Características</option><option value="cta">Llamado a la acción</option><option value="footer">Pie de página</option></select>'
            . '<button type="button" id="add-block" class="button">+ Añadir bloque</button></div>'
            . '<div class="actions"><button class="primary" type="submit">Guardar borrador</button><a class="button" target="_blank" rel="noopener" href="?action=preview&id=' . $siteId . '">Ver último borrador guardado ↗</a></div></form></div>'
            . '<aside class="hint"><h3>Edición segura</h3><p>Los campos son texto, no HTML ejecutable. Esto permite publicar archivos estáticos seguros y fáciles de mantener.</p>'
            . '<p>Para editar un sitio importado sin perder su estilo se necesitará un módulo posterior de conversión asistida.</p></aside></div>';
        echo '<script id="initial-blocks" type="application/json">' . str_replace('<', '\\u003C', $page['draft_json']) . '</script>';
        echo '<script src="assets/editor.js" defer></script>';
        Views::end(); exit;
    }

    if ($action === 'preview') {
        header_remove('X-Frame-Options');
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data: https:; script-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");
        echo Builder::html($site, $page, Builder::normalize(json_data($page['draft_json'])), true);
        exit;
    }

    if ($action === 'publish') {
        if (!$post || !can_publish($site)) { http_response_code(403); exit('No autorizado.'); }
        Builder::publish($site, $page, config()['storage']);
        query('UPDATE pages SET published_json=draft_json, published_version=draft_version WHERE id=? AND draft_version=?', [$page['id'], $page['draft_version']]);
        message('Archivos estáticos compilados. Descarga el ZIP para desplegar; el dominio no se modifica automáticamente.');
        go('site', ['id' => $siteId]);
    }

    if ($action === 'download') {
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

    if ($action === 'scan') {
        if ($user['role'] !== 'superadmin') { http_response_code(403); exit('Escaneo solo para superadministradores.'); }
        if ($post) {
            $type = (string) ($_POST['source_kind'] ?? 'directory');
            if ($type === 'directory') {
                $root = (string) (config()['scan_root'] ?? '');
                if ($root === '') throw new RuntimeException('Primero configura scan_root en config.php.');
                $reference = trim((string) ($_POST['relative'] ?? ''), '/');
                if (strlen($reference) > 240) throw new InvalidArgumentException('Ruta demasiado larga.');
                $files = Scanner::scanDirectory($root, $reference);
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
        echo '<div class="cards"><section class="card"><h2>Carpeta del hosting (lectura)</h2><p>Solo se leen archivos en la raíz que el servidor haya autorizado en <code>scan_root</code>.</p>';
        echo '<form method="post">' . csrf_field() . '<input type="hidden" name="source_kind" value="directory">'
            . '<label>Subcarpeta relativa a la raíz<input name="relative" placeholder="mi-sitio" autocomplete="off"></label>'
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
            $contents = Scanner::readDirectoryFile((string) (config()['scan_root'] ?? ''), $scan['source_ref'], $filename);
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
    Views::error('No se completó la operación', $isDb ? 'Error de base de datos. Revisa la instalación o consulta el registro del servidor.' : ($isPublic ? $ex->getMessage() : 'Error inesperado. Consulta el registro del servidor.'), $user ?? null);
}
