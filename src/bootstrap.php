<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2026 LandiCy contributors. See LICENSE and LICENSE-OUTPUT-EXCEPTION.md.

declare(strict_types=1);

spl_autoload_register(static function (string $name): void {
    if (str_starts_with($name, 'LandiCy\\')) {
        $path = __DIR__ . '/' . substr($name, strlen('LandiCy\\')) . '.php';
        if (is_file($path)) require_once $path;
    }
});

function config(): array {
    static $cfg;
    if ($cfg === null) {
        $path = dirname(__DIR__) . '/config.php';
        if (!is_file($path)) throw new RuntimeException('Falta config.php; consulta README.md.');
        $cfg = require $path;
    }
    return $cfg;
}

function db(): PDO {
    static $pdo;
    if ($pdo === null) {
        $c = config()['db'];
        $pdo = new PDO($c['dsn'], $c['username'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function e(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (PHP_SAPI !== 'cli') {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax', 'path' => '/',
        ]);
    }
    session_start();
}

function csrf_token(): string {
    start_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void {
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419); exit('Token CSRF inválido. Recarga la página.');
    }
}

function go(string $action = 'dashboard', array $args = []): never {
    header('Location: ?' . http_build_query(['action' => $action] + $args), true, 303);
    exit;
}

function current_user(): ?array {
    start_session();
    if (empty($_SESSION['user_id'])) return null;
    $user = query('SELECT id,name,email,role FROM users WHERE id=?', [(int) $_SESSION['user_id']])->fetch();
    return $user ?: null;
}

function require_user(): array {
    $user = current_user();
    if (!$user) go('login');
    return $user;
}

function site_or_404(int $id, array $user): array {
    $site = query('SELECT * FROM sites WHERE id=?', [$id])->fetch();
    if (!$site) { http_response_code(404); exit('Sitio inexistente'); }
    if ($user['role'] !== 'superadmin') {
        $access = query('SELECT role FROM site_users WHERE site_id=? AND user_id=?', [$id, $user['id']])->fetch();
        if (!$access) { http_response_code(403); exit('No tienes acceso a este sitio'); }
        $site['my_role'] = $access['role'];
    } else {
        $site['my_role'] = 'admin';
    }
    return $site;
}

function can_publish(array $site): bool {
    return ($site['my_role'] ?? '') === 'admin';
}

function site_page(int $siteId): array {
    $page = query('SELECT * FROM pages WHERE site_id=? AND slug="index"', [$siteId])->fetch();
    if (!$page) { http_response_code(404); exit('Página principal inexistente'); }
    return $page;
}

function message(?string $value = null): ?string {
    start_session();
    if ($value !== null) { $_SESSION['flash'] = $value; return null; }
    $result = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $result;
}

function json_data(string $json): array {
    $result = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($result) || !array_is_list($result)) throw new InvalidArgumentException('Los bloques deben ser una lista JSON.');
    return $result;
}
