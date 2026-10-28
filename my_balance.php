<?php
require 'config.php';
permission('my_balance');

$currentUser = user();

if ($currentUser['role'] === 'admin') {
    $search = trim((string) ($_GET['q'] ?? ''));

    // Detail transaksi per santri hanya dapat dibuka oleh admin.
    $detailId = (int) ($_GET['detail'] ?? 0);
    if ($detailId > 0) {
        $detailStmt = db()->prepare('SELECT id, nis, name, class_name, balance FROM students WHERE id = ?');
        $detailStmt->execute([$detailId]);
        $detailStudent = $detailStmt->fetch();
        if (!$detailStudent) {
            http_response_code(404);
            exit('Data santri tidak ditemukan.');
        }
        $txStmt = db()->prepare('SELECT t.id, t.receipt_no, t.created_at, t.type, t.amount, t.balance_before, t.balance_after, t.note, u.username AS operator_name FROM transactions t LEFT JOIN users u ON u.id = t.user_id WHERE t.student_id = ? ORDER BY t.created_at DESC, t.id DESC');
        $txStmt->execute([$detailId]);
        $detailTransactions = $txStmt->fetchAll();
        $totalTopup = 0;
        $totalWithdrawal = 0;
        foreach ($detailTransactions as $tx) {
            if ($tx['type'] === 'topup') $totalTopup += (int)$tx['amount'];
            else $totalWithdrawal += (int)$tx['amount'];
        }
        begin('Detail Saldo Santri');
?>
        <section class="balance-intro">
            <div><span class="eyebrow">DETAIL KEUANGAN SANTRI</span>
                <h2><?= e($detailStudent['name']) ?></h2>
                <p>NIS <?= e($detailStudent['nis']) ?> · Kelas <?= e($detailStudent['class_name']) ?></p>
            </div>
            <div class="balance-intro-icon" aria-hidden="true">↗</div>
        </section>
        <section class="balance-summary">
            <article class="balance-summary-card summary-total"><span class="summary-icon">Rp</span>
                <div>
                    <p>Saldo terkini</p><strong><?= e(money((int)$detailStudent['balance'])) ?></strong><small>Saldo saat ini di database</small>
                </div>
            </article>
            <article class="balance-summary-card"><span class="summary-icon students-icon">＋</span>
                <div>
                    <p>Total setoran</p><strong><?= e(money($totalTopup)) ?></strong><small><?= e((string)count(array_filter($detailTransactions, static fn($tx) => $tx['type'] === 'topup'))) ?> transaksi setoran</small>
                </div>
            </article>
            <article class="balance-summary-card"><span class="summary-icon filtered-icon">−</span>
                <div>
                    <p>Total penarikan</p><strong><?= e(money($totalWithdrawal)) ?></strong><small><?= e((string)count(array_filter($detailTransactions, static fn($tx) => $tx['type'] !== 'topup'))) ?> transaksi penarikan</small>
                </div>
            </article>
        </section>
        <section class="panel balance-list-panel">
            <div class="balance-list-heading">
                <div>
                    <h2>Riwayat transaksi</h2>
                    <p class="muted">Seluruh transaksi yang tercatat untuk santri ini.</p>
                </div><a class="button-secondary" href="my_balance.php<?= $search !== '' ? '?q=' . rawurlencode($search) : '' ?>">← Kembali ke rekap</a>
            </div>
            <div class="tablewrap">
                <table class="balance-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>No. kwitansi</th>
                            <th>Jenis</th>
                            <th>Nominal</th>
                            <th>Saldo sebelum</th>
                            <th>Saldo akhir</th>
                            <th>Petugas</th>
                            <th>Catatan</th>
                            <!-- <th>Kwitansi</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$detailTransactions): ?><tr>
                                <td colspan="9" class="empty-state">Belum ada transaksi untuk santri ini.</td>
                            </tr><?php else: foreach ($detailTransactions as $tx): ?><tr>
                                    <td><?= e(date('d/m/Y H:i', strtotime($tx['created_at']))) ?></td>
                                    <td><?= e($tx['receipt_no'] ?? '-') ?></td>
                                    <td><span class="class-pill"><?= e($tx['type'] === 'topup' ? 'Setoran' : 'Penarikan') ?></span></td>
                                    <td><strong><?= e(money((int)$tx['amount'])) ?></strong></td>
                                    <td><?= e(money((int)$tx['balance_before'])) ?></td>
                                    <td><?= e(money((int)$tx['balance_after'])) ?></td>
                                    <td><?= e($tx['operator_name'] ?? '-') ?></td>
                                    <td><?= e($tx['note'] ?? '-') ?></td>
                                    <!-- <td><a href="receipt.php?id=<?= (int)$tx['id'] ?>" target="_blank" rel="noopener">Lihat</a></td> -->
                                </tr><?php endforeach;
                                endif; ?>
                    </tbody>
                </table>
            </div>
        </section><?php endpage();
                    exit;
                }

                $sql = 'SELECT id, nis, name, class_name, balance FROM students';
                $params = [];

                if ($search !== '') {
                    $sql .= ' WHERE name LIKE ? OR nis LIKE ? OR class_name LIKE ?';
                    $like = '%' . $search . '%';
                    $params = [$like, $like, $like];
                }

                $sql .= ' ORDER BY class_name ASC, name ASC';
                $statement = db()->prepare($sql);
                $statement->execute($params);
                $students = $statement->fetchAll();

                $summary = db()->query(
                    'SELECT COUNT(*) AS student_count, COALESCE(SUM(balance), 0) AS total_balance FROM students'
                )->fetch();

                $filteredBalance = array_sum(array_map(
                    static fn(array $student): int => (int) $student['balance'],
                    $students
                ));

                begin('Rekap Saldo Santri');
                    ?>
    <section class="balance-intro">
        <div>
            <span class="eyebrow">RINGKASAN KEUANGAN</span>
            <h2>Rekap saldo santri</h2>
            <p>Pantau saldo terkini seluruh santri dalam satu halaman.</p>
        </div>
        <div class="balance-intro-icon" aria-hidden="true">↗</div>
    </section>

    <section class="balance-summary">
        <article class="balance-summary-card summary-total">
            <span class="summary-icon">Rp</span>
            <div>
                <p>Total saldo seluruh santri</p>
                <strong><?= e(money((int) $summary['total_balance'])) ?></strong>
                <small>Akumulasi saldo yang tercatat</small>
            </div>
        </article>
        <article class="balance-summary-card">
            <span class="summary-icon students-icon">♙</span>
            <div>
                <p>Jumlah santri terdaftar</p>
                <strong><?= e(number_format((int) $summary['student_count'], 0, ',', '.')) ?></strong>
                <small>Seluruh data santri</small>
            </div>
        </article>
        <article class="balance-summary-card">
            <span class="summary-icon filtered-icon">◎</span>
            <div>
                <p>Saldo hasil pencarian</p>
                <strong><?= e(money($filteredBalance)) ?></strong>
                <small><?= e(number_format(count($students), 0, ',', '.')) ?> santri pada daftar ini</small>
            </div>
        </article>
    </section>

    <section class="panel balance-list-panel">
        <div class="balance-list-heading">
            <div>
                <h2>Saldo terkini per santri</h2>
                <p class="muted">Saldo ditampilkan berdasarkan data terbaru di database.</p>
            </div>
            <span class="record-count"><?= e(number_format(count($students), 0, ',', '.')) ?> data</span>
        </div>

        <div class="balance-export-row"><a class="button-secondary" href="export_balance.php<?= $search !== '' ? '?q=' . rawurlencode($search) : '' ?>" target="_blank" rel="noopener">↓ Ekspor PDF</a></div>
        <form class="search balance-search" method="get">
            <input name="q" placeholder="Cari nama, NIS, atau kelas..." value="<?= e($search) ?>">
            <button type="submit">Cari santri</button>
            <?php if ($search !== ''): ?>
                <a class="clear-search" href="my_balance.php">Reset</a>
            <?php endif; ?>
        </form>

        <div class="tablewrap">
            <table class="balance-table">
                <thead>
                    <tr>
                        <th>Santri</th>
                        <th>NIS</th>
                        <th>Kelas</th>
                        <th class="amount-cell">Saldo terkini</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$students): ?>
                        <tr>
                            <td colspan="5" class="empty-state">Data santri tidak ditemukan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <div class="student-name-cell">
                                        <span class="student-avatar"><?= e(mb_strtoupper(mb_substr($student['name'], 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                                        <strong><?= e($student['name']) ?></strong>
                                    </div>
                                </td>
                                <td><?= e($student['nis']) ?></td>
                                <td><span class="class-pill"><?= e($student['class_name']) ?></span></td>
                                <td class="amount-cell"><strong class="balance-amount"><?= e(money((int) $student['balance'])) ?></strong></td>
                                <td><a class="button-secondary balance-detail-button" href="my_balance.php?detail=<?= (int)$student['id'] ?><?= $search !== '' ? '&amp;q=' . rawurlencode($search) : '' ?>">Lihat detail</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php
    endpage();
    exit;
}

if ($currentUser['role'] !== 'santri' || empty($currentUser['student_id'])) {
    http_response_code(403);
    exit('Menu saldo pribadi hanya tersedia untuk akun santri yang terhubung dengan data santri.');
}

$statement = db()->prepare('SELECT * FROM students WHERE id = ?');
$statement->execute([$currentUser['student_id']]);
$student = $statement->fetch();

if (!$student) {
    http_response_code(404);
    exit('Data santri tidak ditemukan. Hubungi administrator.');
}

$statement = db()->prepare('SELECT * FROM transactions WHERE student_id = ? ORDER BY id DESC LIMIT 30');
$statement->execute([$student['id']]);
$transactions = $statement->fetchAll();

begin('Saldo Saya');
?>
<section class="hero">
    <small>Saldo tabungan terkini</small>
    <h2><?= e(money((int) $student['balance'])) ?></h2>
    <p><?= e($student['name']) ?> · <?= e($student['class_name']) ?></p>
</section>
<section class="panel">
    <h2>Riwayat transaksi</h2>
    <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jenis</th>
                    <th>Nominal</th>
                    <th>Saldo akhir</th>
                    <th>Kwitansi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?= e($transaction['created_at']) ?></td>
                        <td><?= e($transaction['type'] === 'topup' ? 'Top up' : 'Penarikan') ?></td>
                        <td><?= e(money((int) $transaction['amount'])) ?></td>
                        <td><?= e(money((int) $transaction['balance_after'])) ?></td>
                        <td><a href="receipt.php?id=<?= (int) $transaction['id'] ?>">Cetak</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endpage(); ?>