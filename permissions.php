<?php require 'config.php';
permission('permissions');
if (user()['role'] !== 'admin') exit('Admin saja');
$menus = ['dashboard' => 'Dashboard (khusus Admin/Pengurus)', 'students' => 'Data Santri', 'scan' => 'Top up / Penarikan', 'transactions' => 'Riwayat & Kwitansi', 'my_balance' => 'Saldo Saya / Rekap Saldo', 'users' => 'Pengguna', 'permissions' => 'Hak Akses'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach (['pengurus', 'santri'] as $role) {
        foreach ($menus as $key => $label) {
            $v = isset($_POST['p'][$role][$key]) ? 1 : 0;
            $s = db()->prepare('INSERT INTO menu_permissions(role,menu_key,allowed) VALUES(?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)');
            $s->execute([$role, $key, $v]);
        }
    }
    flash('Hak akses disimpan.');
    header('Location: permissions.php');
    exit;
}
$all = db()->query('SELECT role,menu_key,allowed FROM menu_permissions')->fetchAll();
$p = [];
foreach ($all as $a) $p[$a['role']][$a['menu_key']] = $a['allowed'];
begin('Hak Akses Menu'); ?><section class="panel">
    <p class="muted">Admin selalu memiliki akses penuh. Izin diperiksa saat membuka halaman.</p>
    <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <div class="tablewrap">
            <table>
                <tr>
                    <th>Menu</th>
                    <th>Pengurus</th>
                    <th>Santri</th>
                </tr><?php foreach ($menus as $k => $v): ?><tr>
                        <td><?= e($v) ?></td><?php foreach (['pengurus', 'santri'] as $role): ?><td><input type="checkbox" name="p[<?= $role ?>][<?= $k ?>]" <?= $p[$role][$k] ?? 0 ? 'checked' : '' ?>></td><?php endforeach; ?>
                    </tr><?php endforeach; ?>
            </table>
        </div><button>Simpan hak akses</button>
    </form>
</section><?php endpage(); ?>