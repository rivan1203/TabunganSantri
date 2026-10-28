<?php
require 'config.php'; $siteIdentity = identity(); $siteName = trim((string)($siteIdentity['pesantren_name'] ?? '')) ?: 'Nama Pesantren'; $siteLogo = !empty($siteIdentity['logo_path']) ? '<img class="login-custom-logo" src="' . e($siteIdentity['logo_path']) . '" alt="Logo ' . e($siteName) . '">' : '<span class="login-brand-mark">✦</span>'; if(user()){header('Location: dashboard.php');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$s=db()->prepare('SELECT * FROM users WHERE username=? AND is_active=1');$s->execute([trim($_POST['username']??'')]);$u=$s->fetch();if($u&&password_verify($_POST['password']??'',$u['password_hash'])){session_regenerate_id(true);$_SESSION['user']=['id'=>$u['id'],'username'=>$u['username'],'role'=>$u['role'],'student_id'=>$u['student_id']];header('Location: dashboard.php');exit;}$error='Login gagal. Periksa username dan kata sandi.';}
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#062f28"><title>Masuk · <?=e($siteName)?></title><link rel="stylesheet" href="assets/style.css"></head>
<body class="login login-premium">
<main class="login-shell">
  <section class="login-showcase" aria-label="Tentang Tabungan Santri">
    <div class="login-brand"><?=$siteLogo?><span><?=e($siteName)?><small>SMART PESANTREN FINANCE</small></span></div>
    <div class="showcase-copy"><span class="eyebrow"><i></i> SISTEM KEUANGAN PESANTREN</span><h1>Kelola tabungan.<br><em>Bangun masa depan.</em></h1><p>Pengelolaan saldo santri yang lebih tertata, transparan, dan mudah diakses dalam satu sistem.</p>
      <div class="showcase-stats"><div><span class="stat-icon">↗</span><span><strong>Lebih teratur</strong><small>Catatan transaksi terpusat</small></span></div><div><span class="stat-icon">◇</span><span><strong>Lebih aman</strong><small>Akses sesuai hak pengguna</small></span></div></div>
    </div>
    <div class="showcase-footer"><span>✦</span> Mendampingi proses belajar dan kemandirian santri <span class="footer-dot"></span> <?=e(mb_strtoupper($siteName))?></div>
    <div class="decor-orbit orbit-one"></div><div class="decor-orbit orbit-two"></div><div class="decor-glow"></div>
  </section>
  <section class="login-form-side"><form method="post" class="loginbox">
    <div class="form-kicker">SELAMAT DATANG KEMBALI</div><h2>Masuk ke akun Anda</h2><p class="form-intro">Silakan masukkan detail akun untuk melanjutkan.</p>
    <?php if($error)echo '<p class="flash error">'.e($error).'</p>'; ?>
    <input type="hidden" name="csrf" value="<?=e(csrf())?>">
    <label for="username">Username<span class="field-wrap"><span class="field-icon">◎</span><input id="username" name="username" placeholder="Masukkan username" autocomplete="username" required autofocus></span></label>
    <label for="password">Kata sandi<span class="field-wrap"><span class="field-icon">⌑</span><input id="password" name="password" type="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required></span></label>
    <button type="submit"><span>Masuk ke dashboard</span><span class="button-arrow">→</span></button>
    <div class="login-secure"><span>◈</span> Koneksi akun terlindungi oleh sesi login aman</div>
  </form><footer class="login-copyright">© <?=date('Y')?> <?=e($siteName)?> <span>•</span> Sistem administrasi pesantren</footer></section>
</main></body></html>
