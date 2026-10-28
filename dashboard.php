<?php
require 'config.php';
if (user() && user()['role'] === 'santri') { header('Location: my_balance.php'); exit; }
permission('dashboard');
$n = db()->query('SELECT COUNT(*) FROM students WHERE is_active=1')->fetchColumn();
$bal = db()->query('SELECT COALESCE(SUM(balance),0) FROM students')->fetchColumn();
$tx = db()->query('SELECT COUNT(*) FROM transactions WHERE DATE(created_at)=CURDATE()')->fetchColumn();
begin('Dashboard');
?>
<section class="dashboard-welcome">
    <div class="dashboard-welcome-copy">
        <span class="dashboard-kicker">PUSAT INFORMASI KEUANGAN</span>
        <h2>Kelola tabungan santri dengan lebih mudah.</h2>
        <p>Pantau data santri, saldo tabungan, dan aktivitas transaksi harian dari satu tempat.</p>
        <a class="dashboard-cta" href="students.php">Kelola data santri <span aria-hidden="true">→</span></a>
    </div>
    <div class="dashboard-art" aria-hidden="true">
        <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
        <div class="art-wallet"><span>✦</span><i></i><b></b><small>TABUNGAN SANTRI</small><strong>•••• 2026</strong></div>
        <div class="art-coin coin-one">Rp</div><div class="art-coin coin-two">+</div>
    </div>
</section>
<div class="section-heading"><div><span class="section-kicker">RINGKASAN</span><h2>Ikhtisar tabungan</h2></div><span class="live-status"><i></i> Data sistem</span></div>
<section class="cards dashboard-cards">
    <article class="metric-card metric-students"><div class="metric-top"><span class="metric-icon">♙</span><span class="metric-label">DATA TERDAFTAR</span></div><small>Santri aktif</small><strong><?=e(number_format((int)$n, 0, ',', '.'))?></strong><p>Santri dengan status aktif</p></article>
    <article class="metric-card metric-balance"><div class="metric-top"><span class="metric-icon">Rp</span><span class="metric-label">TOTAL KEUANGAN</span></div><small>Total saldo tabungan</small><strong><?=e(money((int)$bal))?></strong><p>Akumulasi saldo seluruh santri</p></article>
    <article class="metric-card metric-transactions"><div class="metric-top"><span class="metric-icon">↗</span><span class="metric-label">AKTIVITAS HARI INI</span></div><small>Transaksi hari ini</small><strong><?=e(number_format((int)$tx, 0, ',', '.'))?></strong><p>Transaksi tercatat hari ini</p></article>
</section>
<section class="dashboard-lower">
    <article class="panel getting-started">
        <div class="panel-heading-icon">✦</div><div><span class="section-kicker">MULAI DARI SINI</span><h2>Aktivitas pengelolaan</h2><p class="muted">Pilih langkah yang ingin dilakukan untuk mengelola tabungan.</p></div>
        <div class="quick-actions">
            <a href="students.php"><span class="quick-icon quick-green">♙</span><span><strong>Data santri</strong><small>Tambah dan kelola data santri</small></span><b>→</b></a>
            <a href="scan.php"><span class="quick-icon quick-blue">▦</span><span><strong>Top up / penarikan</strong><small>Proses transaksi menggunakan barcode</small></span><b>→</b></a>
            <a href="transactions.php"><span class="quick-icon quick-gold">▤</span><span><strong>Riwayat transaksi</strong><small>Lihat transaksi dan cetak kwitansi</small></span><b>→</b></a>
        </div>
    </article>
    <aside class="panel dashboard-note"><div class="note-emblem">✓</div><span class="section-kicker">PENGELOLAAN AMAN</span><h2>Catatan keuangan lebih tertata</h2><p>Pastikan setiap transaksi dicatat melalui sistem agar saldo dan riwayat santri tetap mudah ditelusuri.</p><div class="note-footer"><span>●</span> Gunakan akun sesuai hak akses</div></aside>
</section>
<?php endpage(); ?>
