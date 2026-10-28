<?php
require 'config.php';
ensure_password_display_column();
permission('students');
$isAdmin = user()['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (!$isAdmin) {
        http_response_code(403);
        exit('Admin saja.');
    }
    $action = (string)($_POST['action'] ?? 'create');

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id,is_active FROM students WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            $student = $stmt->fetch();
            if (!$student) throw new RuntimeException('Data santri tidak ditemukan.');
            $newStatus = (int)!((int)$student['is_active']);
            $pdo->prepare('UPDATE students SET is_active=? WHERE id=?')->execute([$newStatus, $id]);
            $pdo->prepare("UPDATE users SET is_active=? WHERE student_id=? AND role='santri'")->execute([$newStatus, $id]);
            $pdo->commit();
            flash($newStatus ? 'Santri dan akun pengguna berhasil diaktifkan.' : 'Santri dan akun pengguna berhasil dinonaktifkan.');
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            flash($e instanceof RuntimeException ? $e->getMessage() : 'Status santri gagal diperbarui.', 'error');
        }
        header('Location: students.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id FROM students WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            if (!$stmt->fetch()) throw new RuntimeException('Data santri tidak ditemukan.');

            // Hapus transaksi santri dan transaksi yang mungkin dicatat oleh akun santri terkait.
            $accountStmt = $pdo->prepare("SELECT id FROM users WHERE student_id=? AND role='santri'");
            $accountStmt->execute([$id]);
            $accountIds = array_map('intval', $accountStmt->fetchAll(PDO::FETCH_COLUMN));
            $affectedStmt = $pdo->prepare('SELECT DISTINCT student_id FROM transactions WHERE student_id=? OR user_id IN (SELECT id FROM users WHERE student_id=?)');
            $affectedStmt->execute([$id, $id]);
            $affectedStudents = array_map('intval', $affectedStmt->fetchAll(PDO::FETCH_COLUMN));
            $pdo->prepare('DELETE FROM transactions WHERE student_id=? OR user_id IN (SELECT id FROM users WHERE student_id=?)')->execute([$id, $id]);
            $pdo->prepare('DELETE FROM users WHERE student_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM students WHERE id=?')->execute([$id]);

            // Jika akun santri pernah mencatat transaksi santri lain, hitung ulang saldo mereka.
            foreach ($affectedStudents as $affectedStudentId) {
                if ($affectedStudentId === $id) continue;
                $txStmt = $pdo->prepare('SELECT id,type,amount FROM transactions WHERE student_id=? ORDER BY created_at,id');
                $txStmt->execute([$affectedStudentId]);
                $running = 0;
                $updateTx = $pdo->prepare('UPDATE transactions SET balance_before=?,balance_after=? WHERE id=?');
                foreach ($txStmt->fetchAll() as $tx) {
                    $before = $running;
                    $amount = (int)$tx['amount'];
                    $running += $tx['type'] === 'topup' ? $amount : -$amount;
                    $updateTx->execute([$before, $running, (int)$tx['id']]);
                }
                $pdo->prepare('UPDATE students SET balance=? WHERE id=?')->execute([$running, $affectedStudentId]);
            }
            $pdo->commit();
            flash('Data santri, akun terkait, dan seluruh transaksi terkait berhasil dihapus permanen.');
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            flash($e instanceof RuntimeException ? $e->getMessage() : 'Data santri gagal dihapus. Periksa relasi database lalu coba lagi.', 'error');
        }
        header('Location: students.php');
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $barcode = trim((string)($_POST['student_code'] ?? ''));
    $nis = trim((string)($_POST['nis'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $className = trim((string)($_POST['class_name'] ?? ''));
    $pin = (string)($_POST['pin'] ?? '');

    if ($barcode === '' || strlen($barcode) > 128 || $nis === '' || $name === '' || $className === '') {
        flash('Kode barcode, NIS, nama, dan kelas wajib diisi.', 'error');
    } elseif ($action === 'create' && !preg_match('/^[0-9]{4,8}$/', $pin)) {
        flash('PIN harus terdiri dari 4–8 angka.', 'error');
    } elseif ($action === 'edit' && $pin !== '' && !preg_match('/^[0-9]{4,8}$/', $pin)) {
        flash('PIN baru harus terdiri dari 4–8 angka. Kosongkan jika tidak ingin mengubah PIN.', 'error');
    } else {
        try {
            if ($action === 'edit' && $id > 0) {
                $sql = 'UPDATE students SET student_code=?, nis=?, name=?, class_name=?';
                $params = [$barcode, $nis, $name, $className];
                if ($pin !== '') {
                    $sql .= ', pin_hash=?';
                    $params[] = password_hash($pin, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id=?';
                $params[] = $id;
                $s = db()->prepare($sql);
                $s->execute($params);
                // Username akun santri mengikuti nama santri; password awal distandarkan.
                $account = db()->prepare("SELECT id FROM users WHERE student_id=? AND role='santri' LIMIT 1");
                $account->execute([$id]); $accountId = $account->fetchColumn();
                $baseUsername = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '.', $name), '.'));
                $baseUsername = substr($baseUsername, 0, 55); if (strlen($baseUsername) < 3) $baseUsername = 'santri' . $id;
                $username = $baseUsername; $suffix = 1;
                while (true) { $check = db()->prepare('SELECT COUNT(*) FROM users WHERE username=? AND id<>?'); $check->execute([$username, (int)$accountId]); if (!(int)$check->fetchColumn()) break; $username = substr($baseUsername, 0, 52) . $suffix++; }
                if ($accountId) db()->prepare('UPDATE users SET username=?,password_hash=?,password_display=? WHERE id=?')->execute([$username,password_hash('1234567890', PASSWORD_DEFAULT),'1234567890',(int)$accountId]);
                flash('Data santri berhasil diperbarui. Username akun mengikuti nama santri.');
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                $s = $pdo->prepare('INSERT INTO students (student_code, nis, name, class_name, pin_hash) VALUES (?, ?, ?, ?, ?)');
                $s->execute([$barcode, $nis, $name, $className, password_hash($pin, PASSWORD_DEFAULT)]);
                $studentId = (int)$pdo->lastInsertId();
                // Akun santri otomatis dibuat dari nama santri dengan password awal 1234567890.
                $baseUsername = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '.', $name), '.'));
                $baseUsername = substr($baseUsername, 0, 55);
                if (strlen($baseUsername) < 3) $baseUsername = 'santri' . $studentId;
                $username = $baseUsername; $suffix = 1;
                while (true) {
                    $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username=?');
                    $check->execute([$username]);
                    if (!(int)$check->fetchColumn()) break;
                    $username = substr($baseUsername, 0, 52) . $suffix++;
                }
                $accountPassword = '1234567890';
                $createUser = $pdo->prepare("INSERT INTO users(username,password_hash,password_display,role,student_id) VALUES(?,?,?,'santri',?)");
                $createUser->execute([$username, password_hash($accountPassword, PASSWORD_DEFAULT), $accountPassword, $studentId]);
                $pdo->commit();
                flash('Data santri dan akun berhasil dibuat. Username: ' . $username . ' · Password awal: 1234567890.');
            }
        } catch (PDOException $exception) {
            if (db()->inTransaction()) db()->rollBack();
            flash($exception->getCode() === '23000' ? 'Barcode atau NIS sudah digunakan oleh santri lain.' : 'Data gagal disimpan. Periksa konfigurasi database.', 'error');
        }
    }
    header('Location: students.php');
    exit;
}

$query = trim((string)($_GET['q'] ?? ''));
$editId = $isAdmin ? (int)($_GET['edit'] ?? 0) : 0;
$editing = null;
if ($editId > 0) {
    $s = db()->prepare('SELECT * FROM students WHERE id=?');
    $s->execute([$editId]);
    $editing = $s->fetch() ?: null;
}
$sql = "SELECT st.*, u.username AS account_username, u.password_display AS account_password_display FROM students st LEFT JOIN users u ON u.student_id=st.id AND u.role='santri'";
$params = [];
if ($query !== '') {
    $sql .= ' WHERE st.name LIKE ? OR st.nis LIKE ? OR st.class_name LIKE ? OR st.student_code LIKE ?';
    $like = '%' . $query . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY st.is_active DESC, st.class_name, st.name';
$s = db()->prepare($sql);
$s->execute($params);
$students = $s->fetchAll();
$cardIdentity = identity();
begin('Data Santri');
if ($isAdmin):
?>
    <section class="panel">
        <h2><?= $editing ? 'Edit data santri' : 'Daftarkan santri baru' ?></h2>
        <p class="muted"><?= $editing ? 'Perbarui identitas santri. PIN boleh dikosongkan jika tidak ingin menggantinya.' : 'Pindai barcode kartu santri terlebih dahulu, lalu lengkapi identitas dan PIN transaksi.' ?></p>
        <?php if (!$editing): ?>
            <div class="barcode-registration">
                <div>
                    <div id="register-reader" class="registration-reader"></div>
                    <div class="button-row"><button type="button" id="register-start">📷 Scan barcode kartu</button><button type="button" id="register-stop" class="button-secondary">Matikan kamera</button></div>
                    <p id="register-status" class="muted">Kamera belum aktif.</p>
                </div>
            <?php endif; ?>
            <form method="post" class="grid" id="student-form">
                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="<?= $editing ? 'edit' : 'create' ?>">
                <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
                <label>Kode barcode<input type="text" id="register-barcode" name="student_code" maxlength="128" placeholder="Kode barcode" required autocomplete="off" value="<?= e($editing['student_code'] ?? '') ?>"></label>
                <label>NIS<input name="nis" maxlength="40" required value="<?= e($editing['nis'] ?? '') ?>"></label>
                <label>Nama santri<input name="name" maxlength="120" required value="<?= e($editing['name'] ?? '') ?>"></label>
                <label>Kelas<input name="class_name" maxlength="80" required value="<?= e($editing['class_name'] ?? '') ?>"></label>
                <label>PIN transaksi <?= $editing ? 'baru (opsional)' : '(4–8 angka)' ?><input name="pin" type="password" inputmode="numeric" pattern="[0-9]{4,8}" minlength="4" maxlength="8" <?= $editing ? '' : 'required' ?>></label>
                <div class="button-row"><button type="submit"><?= $editing ? 'Simpan perubahan' : 'Simpan pendaftaran santri' ?></button><?php if ($editing): ?><a class="button-secondary" href="students.php">Batal</a><?php endif; ?></div>
            </form>
            <?php if (!$editing): ?>
            </div><?php endif; ?>
    </section>
<?php endif; ?>
<section class="panel">
    <form class="search"><input name="q" placeholder="Cari nama / NIS / kelas / barcode" value="<?= e($query) ?>"><button type="submit">Cari</button><?php if ($query !== ''): ?><a href="students.php">Reset</a><?php endif; ?></form>
    <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>NIS</th>
                    <th>Kelas</th>
                    <th>Saldo</th>
                    <th>Barcode / kartu</th><?php if ($isAdmin): ?><th>Status</th>
                        <th>Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!$students): ?><tr>
                        <td colspan="<?= $isAdmin ? 7 : 5 ?>">Data santri tidak ditemukan.</td>
                    </tr><?php endif; ?>
                <?php foreach ($students as $student): ?><tr>
                        <td><?= e($student['name']) ?></td>
                        <td><?= e($student['nis']) ?></td>
                        <td><?= e($student['class_name']) ?></td>
                        <td><?= e(money((int)$student['balance'])) ?></td>
                        <td><button type="button" onclick='card(<?= json_encode($student['name'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,<?= json_encode($student['nis'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,<?= json_encode($student['class_name'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,<?= json_encode($student['student_code'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,<?= json_encode($student['account_username'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT) ?>,<?= json_encode($student['account_password_display'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Lihat kartu</button>
                            <div class="code"><?= e($student['student_code']) ?></div>
                        </td>
                        <?php if ($isAdmin): ?><td><?= $student['is_active'] ? '<span class="status-active">Aktif</span>' : '<span class="status-inactive">Nonaktif</span>' ?></td>
                            <td>
                                <div class="button-row">
                                    <a href="students.php?edit=<?= (int)$student['id'] ?>" class="button-secondary">Edit</a>
                                    <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= (int)$student['id'] ?>"><button type="submit" class="button-secondary"><?= $student['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
                                    <form method="post" onsubmit="return confirm('HAPUS PERMANEN: Data santri, akun pengguna, dan seluruh transaksi tabungan terkait akan dihapus dan tidak dapat dipulihkan. Lanjutkan?');"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$student['id'] ?>"><button type="submit" class="danger-button">Hapus permanen</button></form>
                                </div>
                            </td><?php endif; ?>
                    </tr><?php endforeach; ?></tbody>
        </table>
    </div>
</section>
<div id="card" class="modal card-modal" hidden role="dialog" aria-modal="true" aria-labelledby="cn">
    <section class="idcard" id="printable-card">
        <div class="idcard-top"><div class="idcard-brand"><?php if (!empty($cardIdentity['logo_path'])): ?><img class="idcard-logo" src="<?= e($cardIdentity['logo_path']) ?>" alt="Logo pesantren"><?php else: ?><span class="idcard-brand-mark">✦</span><?php endif; ?><span><strong id="c-school-name"><?= e($cardIdentity['pesantren_name'] ?: 'Nama Pesantren') ?></strong><small>KARTU IDENTITAS SANTRI</small></span></div><span class="idcard-chip">SANTRI</span></div>
        <div class="idcard-content"><div class="idcard-label">KARTU ANGGOTA</div><h2 id="cn"></h2><p id="ci"></p><p id="cc"></p></div>
        <div class="idcard-code-area"><div id="qr" aria-label="QR code kartu santri"></div><div class="idcard-code-text"><small>KODE KARTU</small><code id="c-code"></code><p>Tunjukkan kode ini saat transaksi.</p></div></div>
        <div class="idcard-password"><div><small>AKUN LOGIN SANTRI</small><p>Username: <code id="c-username">-</code></p><p>Password: <code id="c-password">Belum tersedia</code></p><p id="c-password-note" class="idcard-note">Gunakan untuk masuk ke aplikasi.</p></div><button type="button" class="button-secondary" id="copy-card-password" onclick="copyCardPassword()">Salin akun</button></div>
        <div class="idcard-bottom"><span><?= e($cardIdentity['pesantren_name'] ?: 'Nama Pesantren') ?> · TABUNGAN SANTRI</span><span>✦</span></div>
        <div class="idcard-actions no-print"><button type="button" class="button-secondary" onclick="closeCard()">Tutup</button><button type="button" onclick="printStudentCard()">Cetak / Simpan PDF</button></div>
    </section>
</div>
<?php endpage(); ?>