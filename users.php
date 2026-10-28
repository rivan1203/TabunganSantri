<?php
require 'config.php';
ensure_password_display_column();
permission('users');
if (user()['role'] !== 'admin') exit('Admin saja');
// Sinkronkan status akun santri dengan status data santrinya, termasuk data lama.
try {
    db()->exec("UPDATE users u INNER JOIN students s ON s.id=u.student_id SET u.is_active=s.is_active WHERE u.role='santri'");
} catch (Throwable $e) {
    // Aksi di bawah tetap memeriksa dan menyelaraskan status saat diperlukan.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? 'create');

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id,username,role,student_id,is_active FROM users WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            $account = $stmt->fetch();
            if (!$account) throw new RuntimeException('Akun tidak ditemukan.');
            if ((int)$account['id'] === (int)(user()['id'] ?? 0)) throw new RuntimeException('Status akun yang sedang digunakan tidak dapat diubah.');
            $newStatus = (int)!((int)$account['is_active']);
            if ($account['role'] === 'admin' && $newStatus === 0) {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
                if ($count <= 1) throw new RuntimeException('Minimal harus ada satu akun administrator aktif.');
            }
            $pdo->prepare('UPDATE users SET is_active=? WHERE id=?')->execute([$newStatus, $id]);
            if ($account['role'] === 'santri' && $account['student_id']) {
                $pdo->prepare('UPDATE students SET is_active=? WHERE id=?')->execute([$newStatus, $account['student_id']]);
            }
            $pdo->commit();
            flash($newStatus ? 'Akun berhasil diaktifkan.' : 'Akun berhasil dinonaktifkan.');
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            flash($e instanceof RuntimeException ? $e->getMessage() : 'Status akun gagal diperbarui.', 'error');
        }
        header('Location: users.php'); exit;
    }

    if ($action === 'update_password') {
        $id = (int)($_POST['id'] ?? 0);
        $pw = (string)($_POST['password'] ?? '');
        if (strlen($pw) < 10 || strlen($pw) > 255) {
            flash('Password baru wajib 10–255 karakter.', 'error');
        } else {
            $stmt = db()->prepare('UPDATE users SET password_hash=?, password_display=? WHERE id=?');
            $stmt->execute([password_hash($pw, PASSWORD_DEFAULT), $pw, $id]);
            if ($stmt->rowCount() > 0) flash('Password pengguna berhasil diperbarui.');
            else {
                $check = db()->prepare('SELECT COUNT(*) FROM users WHERE id=?'); $check->execute([$id]);
                flash((int)$check->fetchColumn() ? 'Password pengguna berhasil diperbarui.' : 'Akun tidak ditemukan.', (int)$check->fetchColumn() ? 'ok' : 'error');
            }
        }
        header('Location: users.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id,username,role,student_id FROM users WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            $account = $stmt->fetch();
            if (!$account) throw new RuntimeException('Akun tidak ditemukan.');
            if ((int)$account['id'] === (int)(user()['id'] ?? 0)) throw new RuntimeException('Anda tidak dapat menghapus akun yang sedang digunakan.');
            if ($account['role'] === 'admin') {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
                if ($count <= 1 && (int)$pdo->query("SELECT COUNT(*) FROM users WHERE id=".(int)$id." AND is_active=1")->fetchColumn()) {
                    throw new RuntimeException('Administrator aktif terakhir tidak dapat dihapus.');
                }
            }
            // Catat saldo santri yang transaksinya dibuat oleh akun ini agar saldo dapat dihitung ulang.
            $affectedStmt = $pdo->prepare('SELECT DISTINCT student_id FROM transactions WHERE user_id=?');
            $affectedStmt->execute([$id]);
            $affectedStudents = array_map('intval', $affectedStmt->fetchAll(PDO::FETCH_COLUMN));
            $pdo->prepare('DELETE FROM transactions WHERE user_id=?')->execute([$id]);
            if ($account['student_id']) {
                $studentId = (int)$account['student_id'];
                $pdo->prepare('DELETE FROM transactions WHERE student_id=?')->execute([$studentId]);
                // Akun santri dan data santrinya dihapus bersama-sama.
                $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM students WHERE id=?')->execute([$studentId]);
                $message = 'Akun santri, data santri, dan seluruh transaksi tabungannya berhasil dihapus.';
            } else {
                $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
                // Hitung ulang saldo dan snapshot transaksi setelah transaksi pencatat yang dihapus dibuang.
                foreach ($affectedStudents as $affectedStudentId) {
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
                $message = 'Akun pengguna dan transaksi yang dicatat oleh akun tersebut berhasil dihapus; saldo terkait dihitung ulang.';
            }
            $pdo->commit();
            flash($message);
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            flash($e instanceof RuntimeException ? $e->getMessage() : 'Akun gagal dihapus. Pastikan tidak ada keterkaitan data lain yang menghalangi.', 'error');
        }
        header('Location: users.php'); exit;
    }

    $un = trim((string)($_POST['username'] ?? ''));
    $pw = (string)($_POST['password'] ?? '');
    $role = (string)($_POST['role'] ?? '');
    $sid = (int)($_POST['student_id'] ?? 0);
    if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $un) || !in_array($role, ['admin','pengurus'], true) || ($role === 'santri' && !$sid)) {
        flash('Username atau level tidak valid.', 'error');
    } else {
        if ($pw === '') $pw = generate_account_password();
        if (strlen($pw) < 10) {
            flash('Password minimal 10 karakter.', 'error');
        } else {
            try {
                $s = db()->prepare('INSERT INTO users(username,password_hash,password_display,role,student_id,is_active) VALUES(?,?,?,?,?,?)');
                $active = 1;
                if ($role === 'santri') {
                    $studentCheck = db()->prepare('SELECT is_active FROM students WHERE id=?'); $studentCheck->execute([$sid]);
                    $studentStatus = $studentCheck->fetchColumn();
                    if ($studentStatus === false) throw new RuntimeException('Data santri tidak ditemukan.');
                    $active = (int)$studentStatus;
                }
                $s->execute([$un, password_hash($pw, PASSWORD_DEFAULT), $pw, $role, $role === 'santri' ? $sid : null, $active]);
                flash('Akun berhasil dibuat. Password awal tersedia pada kolom Password di bawah.');
            } catch (Throwable $e) {
                flash($e instanceof RuntimeException ? $e->getMessage() : 'Gagal membuat akun; username mungkin sudah digunakan atau santri sudah memiliki akun.', 'error');
            }
        }
    }
    header('Location: users.php'); exit;
}
$students = db()->query('SELECT id,name,nis,class_name FROM students WHERE is_active=1 ORDER BY name')->fetchAll();
$users = db()->query('SELECT u.id,u.username,u.role,u.student_id,u.password_display,u.is_active,s.name AS student_name,s.nis FROM users u LEFT JOIN students s ON s.id=u.student_id ORDER BY FIELD(u.role,\'admin\',\'pengurus\',\'santri\'),u.username')->fetchAll();
$groups = ['admin'=>[], 'pengurus'=>[], 'santri'=>[]];
foreach ($users as $account) $groups[$account['role']][] = $account;
begin('Pengguna');
?>
<section class="panel user-create-panel">
  <div class="section-heading"><div><span class="eyebrow">MANAJEMEN AKUN</span><h2>Buat akun pengguna</h2></div><span class="level-badge">Admin · Pengurus</span></div>
  <p class="muted">Password boleh dikosongkan untuk membuat password acak otomatis. Akun level Santri dibuat dan dikelola langsung melalui menu Data Santri.</p>
  <form method="post" class="grid user-create-form">
    <input type="hidden" name="csrf" value="<?=e(csrf())?>">
    <label>Username<input name="username" required minlength="3" maxlength="60" pattern="[A-Za-z0-9_.-]{3,60}" placeholder="contoh: pengurus01" autocomplete="off"></label>
    <label>Password awal (opsional)<span class="password-field"><input name="password" type="password" minlength="10" placeholder="Kosongkan untuk acak" autocomplete="new-password"><button type="button" class="password-toggle" aria-label="Tampilkan password" title="Tampilkan password">◉</button></span></label>
    <label>Level akses<select name="role" id="role" required><option value="pengurus">Pengurus</option><option value="admin">Admin</option></select></label>
    <label id="studentwrap" hidden>Data santri<select name="student_id"><option value="">Pilih santri</option><?php foreach($students as $st):?><option value="<?=$st['id']?>"><?=e($st['name'])?> — <?=e($st['nis'])?> / <?=e($st['class_name'])?></option><?php endforeach;?></select></label>
    <div class="form-submit"><button type="submit">＋ Buat akun</button></div>
  </form>
</section>
<?php foreach (['admin'=>'Administrator','pengurus'=>'Pengurus','santri'=>'Santri'] as $roleKey=>$roleLabel): ?>
<section class="panel users-level-panel">
  <div class="section-heading"><div><span class="eyebrow">LEVEL PENGGUNA</span><h2><?=e($roleLabel)?></h2></div><span class="count-pill"><?=count($groups[$roleKey])?> akun</span></div>
  <?php if (!$groups[$roleKey]): ?><p class="empty-state">Belum ada akun pada level ini.</p><?php else: ?>
  <div class="tablewrap"><table class="users-table"><thead><tr><th>Username</th><th>Password</th><th>Data terkait</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
  <?php foreach($groups[$roleKey] as $account): ?>
    <tr><td data-label="Username"><strong><?=e($account['username'])?></strong><small class="mobile-subtitle"><?=e($roleLabel)?></small></td>
    <td data-label="Password"><div class="password-display"><input class="stored-password" type="password" readonly value="<?=e($account['password_display'] ?? '')?>" data-password-state="hidden" aria-label="Password <?=e($account['username'])?>"><button type="button" class="password-toggle" aria-label="Tampilkan password" title="Tampilkan password">◉</button><button type="button" class="button-secondary copy-password" title="Salin password">Salin</button></div><?php if (empty($account['password_display'])): ?><small class="muted">Password lama tidak tersedia.</small><?php endif; ?>
      <details class="user-password-edit"><summary>Edit password</summary><form method="post" class="password-edit-form" onsubmit="return confirm('Simpan password baru untuk akun ini?');"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="update_password"><input type="hidden" name="id" value="<?=(int)$account['id']?>"><label>Password baru<input type="password" name="password" required minlength="10" maxlength="255" autocomplete="new-password" placeholder="Minimal 10 karakter"></label><button type="submit" class="button-secondary">Simpan password</button></form></details>
    </td>
    <td data-label="Data terkait"><?=e($account['student_name'] ? $account['student_name'].' · '.$account['nis'] : '—')?></td>
    <td data-label="Status"><span class="account-status <?=$account['is_active']?'is-active':'is-inactive'?>"><?=$account['is_active']?'Aktif':'Nonaktif'?></span></td>
    <td data-label="Aksi"><div class="button-row user-actions">
      <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?=(int)$account['id']?>"><button type="submit" class="button-secondary" <?=((int)$account['id']===(int)(user()['id']??0))?'disabled':''?>><?=$account['is_active']?'Nonaktifkan':'Aktifkan'?></button></form>
      <form method="post" onsubmit="return confirm(<?=json_encode($account['role']==='santri'?'PERINGATAN: Menghapus akun ini juga menghapus data santri dan seluruh transaksi tabungannya. Tindakan tidak dapat dibatalkan. Lanjutkan?':'PERINGATAN: Menghapus akun ini juga menghapus transaksi yang dicatat oleh akun tersebut. Tindakan tidak dapat dibatalkan. Lanjutkan?', JSON_HEX_APOS|JSON_HEX_QUOT)?>);"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$account['id']?>"><button type="submit" class="danger-button" <?=((int)$account['id']===(int)(user()['id']??0))?'disabled':''?>>Hapus permanen</button></form>
    </div></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</section>
<?php endforeach; ?>
<script>
document.getElementById('role')?.addEventListener('change', function(){const wrap=document.getElementById('studentwrap');wrap.hidden=this.value!=='santri';wrap.querySelector('select').required=this.value==='santri';});
document.querySelectorAll('.password-toggle').forEach(button=>button.addEventListener('click',()=>{const field=button.parentElement.querySelector('input');if(!field)return;const visible=field.type==='text';field.type=visible?'password':'text';button.setAttribute('aria-label',visible?'Tampilkan password':'Sembunyikan password');button.title=visible?'Tampilkan password':'Sembunyikan password';button.textContent=visible?'◉':'◎';}));
</script>
<?php endpage(); ?>
