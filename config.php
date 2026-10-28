<?php

declare(strict_types=1);
session_start();
const DB_HOST = '127.0.0.1';
const DB_NAME = 'tabungan_santri';
const DB_USER = 'root';
const DB_PASS = '';
function db(): PDO
{
    static $p = null;
    if ($p instanceof PDO) return $p;
    $p = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    return $p;
}
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function money(int $n): string
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}
function csrf(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function check_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Token formulir tidak valid. Muat ulang halaman.');
    }
}
function user(): ?array
{
    return $_SESSION['user'] ?? null;
}
function login_required(): array
{
    if (!user()) {
        header('Location: index.php');
        exit;
    }
    return user();
}
function allowed(string $key): bool
{
    if (!user()) return false;
    if (user()['role'] === 'admin') return true;
    $s = db()->prepare('SELECT allowed FROM menu_permissions WHERE role=? AND menu_key=?');
    $s->execute([user()['role'], $key]);
    return (bool)$s->fetchColumn();
}
function permission(string $key): void
{
    login_required();
    if (!allowed($key)) {
        http_response_code(403);
        exit('Akses menu ditolak oleh administrator.');
    }
}
function flash(string $m, string $t = 'ok'): void
{
    $_SESSION['flash'] = [$m, $t];
}

function ensure_password_display_column(): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $columns = db()->query("SHOW COLUMNS FROM users LIKE 'password_display'")->fetchAll();
        if (!$columns) db()->exec('ALTER TABLE users ADD COLUMN password_display VARCHAR(255) NULL AFTER password_hash');
    } catch (Throwable $e) {
        // The schema can also be updated by importing the updated database.sql.
    }
}
function generate_account_password(int $length = 14): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $password = '';
    for ($i = 0, $max = strlen($alphabet) - 1; $i < $length; $i++) $password .= $alphabet[random_int(0, $max)];
    return $password;
}


function ensure_identity_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    db()->exec("CREATE TABLE IF NOT EXISTS site_identity (id TINYINT UNSIGNED NOT NULL PRIMARY KEY, pesantren_name VARCHAR(180) NOT NULL DEFAULT 'Nama Pesantren', logo_path VARCHAR(255) NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("INSERT IGNORE INTO site_identity (id, pesantren_name, logo_path) VALUES (1, 'Nama Pesantren', NULL)");
}
function identity(): array
{
    try {
        ensure_identity_table();
        $row = db()->query('SELECT pesantren_name, logo_path FROM site_identity WHERE id=1')->fetch();
        return $row ?: ['pesantren_name' => 'Nama Pesantren', 'logo_path' => null];
    } catch (Throwable $e) {
        return ['pesantren_name' => 'Nama Pesantren', 'logo_path' => null];
    }
}

function begin(string $title): void
{
    $u = user();
    $site = identity();
    $siteName = trim((string)($site['pesantren_name'] ?? '')) ?: 'Nama Pesantren';
    $logo = !empty($site['logo_path']) ? '<img class="brand-logo" src="' . e($site['logo_path']) . '" alt="Logo ' . e($siteName) . '">' : '';
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title><link rel="stylesheet" href="assets/style.css"><script src="https://unpkg.com/html5-qrcode"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script></head><body><header><a href="dashboard.php" class="brand">' . $logo . '<span class="brand-text"><strong>' . e($siteName) . '</strong><small>Tabungan Santri</small></span></a>' . ($u ? '<span class="user-chip">' . e($u['username']) . ' · ' . e($u['role']) . '</span><button class="menu-toggle" type="button" aria-label="Buka menu navigasi" aria-expanded="false" aria-controls="app-sidebar"><span></span><span></span><span></span></button>' : '') . '</header><div class="sidebar-backdrop" data-sidebar-close></div><div class="layout">';
    if ($u) {
        echo '<nav id="app-sidebar" aria-label="Navigasi utama">';
        foreach (['dashboard' => 'Dashboard', 'students' => 'Data Santri', 'scan' => 'Top up / Penarikan', 'transactions' => 'Riwayat & Kwitansi', 'my_balance' => (user()['role'] === 'admin' ? 'Rekap Saldo Santri' : 'Saldo Saya'), 'users' => 'Pengguna', 'permissions' => 'Hak Akses', 'identity' => 'Identitas Pesantren'] as $k => $v) {
            if (user()['role'] === 'santri' && $k === 'dashboard') continue;
            if (allowed($k)) echo '<a class="' . (basename($_SERVER['PHP_SELF'], '.php') === $k ? 'active' : '') . '" href="' . $k . '.php">' . e($v) . '</a>';
        }
        echo '<div class="sidebar-account"><div class="sidebar-user-avatar">' . e(strtoupper(substr((string)$u['username'], 0, 1))) . '</div><div class="sidebar-user-meta"><strong>' . e($u['username']) . '</strong><small>' . e($u['role'] === 'admin' ? 'Administrator' : 'Santri') . '</small></div><a class="sidebar-logout" href="logout.php" aria-label="Keluar dari akun"><span aria-hidden="true">↪</span><span>Keluar</span></a></div></nav>';
    }
    echo '<main><h1>' . e($title) . '</h1>';
    if ($f) echo '<p class="flash ' . e($f[1]) . '">' . e($f[0]) . '</p>';
}
function endpage(): void
{
    echo '</main></div><script src="assets/app.js"></script></body></html>';
}
