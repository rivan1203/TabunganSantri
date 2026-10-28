-- Jalankan sekali di phpMyAdmin jika database tabungan_santri sudah dibuat sebelumnya.
USE tabungan_santri;
ALTER TABLE students MODIFY student_code VARCHAR(128) NOT NULL;
