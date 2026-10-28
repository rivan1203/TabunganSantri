<?php
require 'config.php';
permission('transactions');
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$type = (string)($_GET['type'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = '';
if (!in_array($type, ['topup', 'withdrawal'], true)) $type = '';
$where = [];
$args = [];
if ($from !== '') {
    $where[] = 't.created_at >= ?';
    $args[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 't.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $args[] = $to;
}
if ($type !== '') {
    $where[] = 't.type = ?';
    $args[] = $type;
}
if (user()['role'] === 'santri') {
    $where[] = 't.student_id = ?';
    $args[] = (int)user()['student_id'];
}
$sql = 'SELECT t.*,s.name,s.nis,s.class_name,u.username FROM transactions t JOIN students s ON s.id=t.student_id JOIN users u ON u.id=t.user_id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY t.id DESC';
$s = db()->prepare($sql);
$s->execute($args);
$rows = $s->fetchAll();
$query = http_build_query(array_filter(['from' => $from, 'to' => $to, 'type' => $type], static fn($v) => $v !== ''));
begin('Riwayat & Kwitansi');
?>
<section class="panel">
    <h2>Laporan transaksi</h2>
    <form method="get" class="search">
        <label>
            Dari tanggal
            <input type="date" name="from" value="<?= e($from) ?>">
        </label>

        <label>
            Sampai tanggal
            <input type="date" name="to" value="<?= e($to) ?>">
        </label>

        <label>
            Jenis transaksi
            <select name="type">
                <option value="">Semua jenis</option>
                <option value="topup" <?= $type === 'topup' ? 'selected' : '' ?>>Top up / Setoran</option>
                <option value="withdrawal" <?= $type === 'withdrawal' ? 'selected' : '' ?>>Penarikan</option>
            </select>
        </label>

        <div class="filter-actions">
            <button type="submit">Terapkan filter</button>

            <a href="transactions.php"
                class="reset-filter"
                title="Reset filter"
                aria-label="Reset filter">
                <svg xmlns="http://www.w3.org/2000/svg"
                    width="20" height="20"
                    viewBox="0 0 24 24"
                    fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M3 12a9 9 0 1 0 2.6-6.4L3 8" />
                    <path d="M3 3v5h5" />
                </svg>
            </a>
        </div>
    </form>
    <div class="button-row report-actions"><a class="report-button" href="export_transactions.php?format=pdf<?= $query !== '' ? '&' . e($query) : '' ?>" target="_blank" rel="noopener">⬇ Laporan PDF</a><a class="report-button" href="export_transactions.php?format=excel<?= $query !== '' ? '&' . e($query) : '' ?>">⬇ Excel (CSV)</a></div>
    <p class="muted">Jumlah data: <?= number_format(count($rows), 0, ',', '.') ?> transaksi. Laporan mengikuti filter tanggal dan jenis transaksi di atas.</p>
    <div class="tablewrap transaction-tablewrap">
        <table class="transaction-table">
            <thead>
                <tr>
                    <th>Kwitansi</th>
                    <th>Santri</th>
                    <th>Jenis</th>
                    <th>Nominal</th>
                    <th>Saldo akhir</th>
                    <th>Petugas</th>
                    <th>Waktu</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?><tr class="empty-row">
                        <td colspan="8">Belum ada transaksi sesuai filter.</td>
                    </tr><?php endif;
                        foreach ($rows as $r): ?><tr>
                        <td data-label="Kwitansi"><span class="transaction-receipt-no"><?= e($r['receipt_no']) ?></span></td>
                        <td data-label="Santri"><span class="transaction-student"><?= e($r['name']) ?></span><small class="transaction-nis">NIS: <?= e($r['nis']) ?></small></td>
                        <td data-label="Jenis"><span class="transaction-type <?= e($r['type'] === 'topup' ? 'type-topup' : 'type-withdrawal') ?>"><?= e($r['type'] === 'topup' ? 'Top up / Setoran' : 'Penarikan') ?></span></td>
                        <td data-label="Nominal"><strong class="transaction-amount <?= e($r['type'] === 'topup' ? 'amount-in' : 'amount-out') ?>"><?= e(money((int)$r['amount'])) ?></strong></td>
                        <td data-label="Saldo akhir"><?= e(money((int)$r['balance_after'])) ?></td>
                        <td data-label="Petugas"><?= e($r['username']) ?></td>
                        <td data-label="Waktu"><span class="transaction-datetime"><?= e($r['created_at']) ?></span></td>
                        <td data-label="Aksi"><a class="transaction-print-link" href="receipt.php?id=<?= (int)$r['id'] ?>">Cetak kwitansi <span aria-hidden="true">↗</span></a></td>
                    </tr><?php endforeach; ?></tbody>
        </table>
    </div>
</section>
<?php endpage(); ?>