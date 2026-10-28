# Tabungan Santri (PHP + MySQL)
1. Ekstrak folder ke `C:\xampp\htdocs\TabunganSantri`.
2. Import `database.sql` melalui phpMyAdmin.
3. Sesuaikan koneksi di `config.php`.
4. Buka `http://localhost/TabunganSantri`.
5. Login awal: `admin` / `password` (segera ganti password).

Fitur: level admin/pengurus/santri, data santri dengan edit dan hapus/penonaktifan aman, saldo dan transaksi, scan barcode dengan kamera, top up, penarikan, kwitansi, laporan transaksi PDF dan Excel-compatible CSV dengan filter tanggal/jenis, saldo santri, dan izin menu per level. Dashboard santri diarahkan ke halaman Saldo Saya agar tidak menampilkan ringkasan keuangan admin.

**Pendaftaran barcode:** saat Admin menambahkan santri, tekan **Scan barcode kartu**, izinkan kamera, lalu arahkan kamera ke barcode yang sudah ada pada kartu santri. Kode hasil scan masuk ke formulir dan disimpan sebagai identitas barcode untuk transaksi top up/penarikan. Barcode harus unik; satu barcode tidak dapat dipakai untuk dua santri. Jika belum ada barcode fisik, buat/cetak kartu QR melalui tombol **Lihat kartu** setelah santri terdaftar, lalu gunakan QR tersebut untuk transaksi berikutnya.

Jika Anda memakai database dari versi sebelumnya, jalankan `upgrade_barcode.sql` sekali melalui phpMyAdmin agar kolom kode barcode dapat menyimpan hasil scan sampai 128 karakter. Jangan impor ulang `database.sql` ke database berisi data karena itu untuk instalasi baru. Kamera memerlukan localhost atau HTTPS. Scanner/QR menggunakan CDN, jadi perlu internet saat membuka aplikasi.

Catatan: ini starter project yang perlu diuji sebelum produksi. Lakukan backup, HTTPS, pengamanan akun, audit kas, dan uji coba sebelum dipakai mengelola uang sungguhan.
