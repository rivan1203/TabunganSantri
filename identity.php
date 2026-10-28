<?php
require 'config.php';
login_required();
if (user()['role'] !== 'admin') { http_response_code(403); exit('Hanya administrator yang dapat mengubah identitas pesantren.'); }
$site = identity();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = trim((string)($_POST['pesantren_name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 180) {
        $error = 'Nama pesantren wajib diisi dan maksimal 180 karakter.';
    } else {
        $logoPath = $site['logo_path'] ?? null;
        if (!empty($_POST['remove_logo'])) $logoPath = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['logo'];
            if ($file['error'] !== UPLOAD_ERR_OK) $error = 'Logo gagal diunggah. Silakan coba lagi.';
            elseif ($file['size'] > 2 * 1024 * 1024) $error = 'Ukuran logo maksimal 2 MB.';
            else {
                $info = @getimagesize($file['tmp_name']);
                $mime = $info['mime'] ?? '';
                $exts = ['image/png'=>'png', 'image/jpeg'=>'jpg', 'image/webp'=>'webp'];
                if (!$info || !isset($exts[$mime])) $error = 'Format logo harus PNG, JPG, atau WEBP.';
                else {
                    $dir = __DIR__ . '/uploads/identity';
                    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) $error = 'Folder upload logo tidak dapat dibuat.';
                    else {
                        $filename = 'logo-' . bin2hex(random_bytes(8)) . '.' . $exts[$mime];
                        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) $error = 'Logo tidak dapat disimpan. Periksa izin folder uploads.';
                        else {
                            if ($logoPath && str_starts_with($logoPath, 'uploads/identity/')) { $old = __DIR__ . '/' . $logoPath; if (is_file($old)) @unlink($old); }
                            $logoPath = 'uploads/identity/' . $filename;
                        }
                    }
                }
            }
        }
        if ($error === '') {
            if (!empty($_POST['remove_logo']) && !empty($site['logo_path']) && str_starts_with($site['logo_path'], 'uploads/identity/')) { $old = __DIR__ . '/' . $site['logo_path']; if (is_file($old)) @unlink($old); }
            ensure_identity_table();
            $stmt = db()->prepare('UPDATE site_identity SET pesantren_name=?, logo_path=? WHERE id=1');
            $stmt->execute([$name, $logoPath]);
            flash('Identitas pesantren berhasil diperbarui.');
            header('Location: identity.php'); exit;
        }
    }
}
$site = identity();
begin('Identitas Pesantren');
?>
<section class="panel identity-panel">
  <div class="identity-intro"><span class="section-kicker">PENGATURAN SISTEM</span><h2>Identitas Pesantren</h2><p class="muted">Atur nama dan logo yang digunakan pada header aplikasi dan halaman masuk.</p></div>
  <?php if ($error): ?><p class="flash error"><?=e($error)?></p><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="identity-form">
    <input type="hidden" name="csrf" value="<?=e(csrf())?>">
    <label for="pesantren_name">Nama pesantren</label>
    <input id="pesantren_name" name="pesantren_name" maxlength="180" required value="<?=e($_POST['pesantren_name'] ?? $site['pesantren_name'])?>" placeholder="Contoh: Pondok Pesantren Mambaul Huda">
    <label>Logo pesantren</label>
    <div class="identity-logo-preview"><?php if (!empty($site['logo_path'])): ?><img src="<?=e($site['logo_path'])?>?v=<?=e((string)@filemtime(__DIR__ . '/' . $site['logo_path']))?>" alt="Logo pesantren saat ini"><?php else: ?><span>✦</span><?php endif; ?></div>
    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
    <small class="muted">Format PNG, JPG, atau WEBP. Maksimal 2 MB. PNG transparan disarankan agar logo tampak rapi.</small>
    <?php if (!empty($site['logo_path'])): ?><label class="identity-remove"><input type="checkbox" name="remove_logo" value="1"> Hapus logo saat ini</label><?php endif; ?>
    <div class="identity-actions"><button type="submit">Simpan identitas</button></div>
  </form>
</section>
<?php endpage(); ?>
