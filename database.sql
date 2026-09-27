-- =========================================================================
-- SITRACK — Sistem Monitoring dan Tracking Berkas Pradaftar ATR/BPN
-- Skema Database MySQL
-- =========================================================================

CREATE DATABASE IF NOT EXISTS `bpn_tracking` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bpn_tracking`;

-- -------------------------------------------------------------------------
-- Tabel: users
-- -------------------------------------------------------------------------
CREATE TABLE `users` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username`      VARCHAR(50)  NOT NULL UNIQUE,
    `password`      VARCHAR(255) NOT NULL COMMENT 'di-hash dengan password_hash() / bcrypt',
    `nama_lengkap`  VARCHAR(150) NOT NULL,
    `role`          ENUM('admin','loket','seksi_1','seksi_2') NOT NULL,
    `sub_bagian`    ENUM('Pendaftaran','Peralihan','Penetapan') NULL DEFAULT NULL COMMENT 'Khusus Seksi 2: Pendaftaran, Peralihan, Penetapan',
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- Tabel: berkas
-- -------------------------------------------------------------------------
CREATE TABLE `berkas` (
    `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nomor_pendaftaran`  VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nomor Berkas / Registrasi',
    `nama_pemohon`       VARCHAR(150) NOT NULL,
    `nik`                VARCHAR(20)  NULL,
    `no_hp`              VARCHAR(20)  NULL,
    `jenis_layanan`      VARCHAR(100) NOT NULL,
    `sertifikat_desa`    VARCHAR(255) NULL COMMENT 'Informasi Sertifikat / Desa / Kelurahan',
    `deskripsi_berkas`   TEXT NULL,
    `status_posisi`      ENUM('loket','seksi_1','seksi_2','selesai','ditolak_ke_loket','ditolak_ke_seksi1') NOT NULL DEFAULT 'loket',
    `diinput_oleh`       INT UNSIGNED NULL,
    `petugas_tujuan_id`  INT UNSIGNED NULL COMMENT 'petugas yang saat ini ditugaskan memegang berkas',
    `deadline_at`        DATETIME NULL COMMENT 'batas waktu pengerjaan SLA (maks 2 hari sejak masuk posisi)',
    `sisa_sla_detik`     INT NULL DEFAULT NULL COMMENT 'Sisa detik SLA saat berkas dijeda/dikembalikan ke loket',
    `is_diterima_loket`  TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'flag konfirmasi loket setelah berkas selesai/ditolak kembali',
    `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_status_posisi` (`status_posisi`),
    KEY `idx_nomor_pendaftaran` (`nomor_pendaftaran`),
    KEY `idx_sertifikat_desa` (`sertifikat_desa`),
    KEY `idx_deadline_at` (`deadline_at`),
    CONSTRAINT `fk_berkas_diinput_oleh` FOREIGN KEY (`diinput_oleh`) REFERENCES `users`(`id`),
    CONSTRAINT `fk_berkas_petugas_tujuan` FOREIGN KEY (`petugas_tujuan_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- Tabel: log_pergerakan (tabel tracking / jejak audit)
-- -------------------------------------------------------------------------
CREATE TABLE `log_pergerakan` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `id_berkas`       INT UNSIGNED NOT NULL,
    `pengirim_id`     INT UNSIGNED NULL COMMENT 'user yang melakukan aksi',
    `penerima_id`     INT UNSIGNED NULL COMMENT 'user tujuan spesifik (opsional, biasanya tujuan berupa unit/role)',
    `aksi`            ENUM('diinput','diteruskan','dikembalikan','selesai','diterima') NOT NULL,
    `status_sebelum`  VARCHAR(30) NULL,
    `status_sesudah`  VARCHAR(30) NULL,
    `catatan`         TEXT NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_id_berkas` (`id_berkas`),
    CONSTRAINT `fk_log_berkas` FOREIGN KEY (`id_berkas`) REFERENCES `berkas`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_log_pengirim` FOREIGN KEY (`pengirim_id`) REFERENCES `users`(`id`),
    CONSTRAINT `fk_log_penerima` FOREIGN KEY (`penerima_id`) REFERENCES `users`(`id`)
-- -------------------------------------------------------------------------
-- Tabel: notifikasi (pemberitahuan in-app)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifikasi` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `id_berkas`      INT UNSIGNED NULL,
    `target_role`    ENUM('admin','loket','seksi_1','seksi_2','all') NOT NULL,
    `target_user_id` INT UNSIGNED NULL COMMENT 'Jika ditujukan ke user spesifik',
    `judul`          VARCHAR(150) NOT NULL,
    `pesan`          TEXT NOT NULL,
    `tipe`           ENUM('masuk','kembali','selesai','warning','kritis','info') NOT NULL DEFAULT 'masuk',
    `link`           VARCHAR(255) NOT NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_notif_target` (`target_role`, `target_user_id`),
    KEY `idx_notif_created` (`created_at`),
    CONSTRAINT `fk_notif_berkas` FOREIGN KEY (`id_berkas`) REFERENCES `berkas`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notif_target_user` FOREIGN KEY (`target_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- Tabel: notifikasi_read (jejak status dibaca per-user)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifikasi_read` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `notifikasi_id` INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED NOT NULL,
    `read_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_notif_user` (`notifikasi_id`, `user_id`),
    KEY `idx_read_user` (`user_id`),
    CONSTRAINT `fk_read_notifikasi` FOREIGN KEY (`notifikasi_id`) REFERENCES `notifikasi`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_read_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================================
-- DATA AWAL: Dummy Users
-- Password untuk SEMUA akun di bawah ini: bpn12345
-- (hash bcrypt sudah dibuat dengan password_hash(), WAJIB diganti setelah instalasi)
-- =========================================================================
INSERT INTO `users` (`username`, `password`, `nama_lengkap`, `role`, `sub_bagian`, `is_active`) VALUES
('admin',    '$2y$10$B2FFLaNM1aBMgHL.D/QB.OkH..yPjL/8rwjbfNYb9nCe/RbBucfSm', 'Andi Wijaya',    'admin',   NULL,          1),
('loket1',   '$2y$10$B2FFLaNM1aBMgHL.D/QB.OkH..yPjL/8rwjbfNYb9nCe/RbBucfSm', 'Siti Rahma',     'loket',   NULL,          1),
('loket2',   '$2y$10$B2FFLaNM1aBMgHL.D/QB.OkH..yPjL/8rwjbfNYb9nCe/RbBucfSm', 'Dewi Lestari',   'loket',   NULL,          1),
('seksi1_1', '$2y$10$B2FFLaNM1aBMgHL.D/QB.OkH..yPjL/8rwjbfNYb9nCe/RbBucfSm', 'Budi Santoso',   'seksi_1', NULL,          1),
('seksi1_2', '$2y$10$B2FFLaNM1aBMgHL.D/QB.OkH..yPjL/8rwjbfNYb9nCe/RbBucfSm', 'Fajar Hidayat',  'seksi_1', NULL,          1),
('seksi2_1', '$2y$10$B2FFLaNM1aBMgHL.D/QB.OkH..yPjL/8rwjbfNYb9nCe/RbBucfSm', 'Rina Kartika',   'seksi_2', 'Penetapan',   1),
('seksi2_2', '$2y$10$B2FFLaNM1aBMgHL.D/QB.OkH..yPjL/8rwjbfNYb9nCe/RbBucfSm', 'Eko Prasetyo',   'seksi_2', 'Peralihan',   1),
('seksi2_3', '$2y$10$B2FFLaNM1aBMgHL.D/QB.OkH..yPjL/8rwjbfNYb9nCe/RbBucfSm', 'Sri Wahyuni',   'seksi_2', 'Pendaftaran', 1);

-- =========================================================================
-- DATA CONTOH (opsional) — beberapa berkas contoh agar sistem dapat langsung
-- dicoba di setiap antrean role. Hapus/TRUNCATE tabel `berkas` dan
-- `log_pergerakan` sebelum digunakan di lingkungan produksi.
-- =========================================================================

-- Berkas 1: baru masuk, menunggu diperiksa Seksi 1
INSERT INTO `berkas` (`nomor_pendaftaran`, `nama_pemohon`, `nik`, `no_hp`, `jenis_layanan`, `sertifikat_desa`, `deskripsi_berkas`, `status_posisi`, `diinput_oleh`) VALUES
('REG-20260915-0001', 'Ahmad Fauzi', '3502011234560001', '081234567801', 'Peralihan Hak (Jual Beli)', 'SHM No. 1205 / Desa Sukamaju', 'KTP, KK, Sertifikat asli, Akta Jual Beli, SPPT PBB tahun berjalan', 'seksi_1', 2);
INSERT INTO `log_pergerakan` (`id_berkas`, `pengirim_id`, `aksi`, `status_sebelum`, `status_sesudah`, `catatan`) VALUES
(1, 2, 'diteruskan', 'loket', 'seksi_1', 'Berkas baru diinput dan diteruskan ke Seksi 1 untuk pemeriksaan.');

-- Berkas 2: sedang diproses Seksi 2
INSERT INTO `berkas` (`nomor_pendaftaran`, `nama_pemohon`, `nik`, `no_hp`, `jenis_layanan`, `sertifikat_desa`, `deskripsi_berkas`, `status_posisi`, `diinput_oleh`) VALUES
('REG-20260915-0002', 'Maya Sari', '3502011234560002', '081234567802', 'Hak Tanggungan (APHT/Roya)', 'M.00892 / Kelurahan Karangsari', 'KTP, KK, Sertifikat asli, APHT, Surat Kuasa', 'seksi_2', 2);
INSERT INTO `log_pergerakan` (`id_berkas`, `pengirim_id`, `aksi`, `status_sebelum`, `status_sesudah`, `catatan`) VALUES
(2, 2, 'diteruskan', 'loket', 'seksi_1', 'Berkas baru diinput dan diteruskan ke Seksi 1 untuk pemeriksaan.'),
(2, 4, 'diteruskan', 'seksi_1', 'seksi_2', 'Berkas dinyatakan lengkap dan diteruskan ke Seksi 2.');

-- Berkas 3: selesai, menunggu dikonfirmasi diterima Loket
INSERT INTO `berkas` (`nomor_pendaftaran`, `nama_pemohon`, `nik`, `no_hp`, `jenis_layanan`, `sertifikat_desa`, `deskripsi_berkas`, `status_posisi`, `diinput_oleh`) VALUES
('REG-20260914-0001', 'Joko Prasetyo', '3502011234560003', '081234567803', 'Pendaftaran Tanah Pertama Kali', 'Letter C No. 450 / Desa Tirto', 'KTP, KK, Surat Tanah, SPPT PBB, Surat Pernyataan Penguasaan Fisik', 'selesai', 3);
INSERT INTO `log_pergerakan` (`id_berkas`, `pengirim_id`, `aksi`, `status_sebelum`, `status_sesudah`, `catatan`) VALUES
(3, 3, 'diteruskan', 'loket', 'seksi_1', 'Berkas baru diinput dan diteruskan ke Seksi 1 untuk pemeriksaan.'),
(3, 4, 'diteruskan', 'seksi_1', 'seksi_2', 'Berkas dinyatakan lengkap dan diteruskan ke Seksi 2.'),
(3, 5, 'selesai', 'seksi_2', 'selesai', 'Sertipikat telah selesai dicetak dan siap diserahkan ke pemohon.');

-- Berkas 4: ditolak ke Loket karena tidak lengkap
INSERT INTO `berkas` (`nomor_pendaftaran`, `nama_pemohon`, `nik`, `no_hp`, `jenis_layanan`, `sertifikat_desa`, `deskripsi_berkas`, `status_posisi`, `diinput_oleh`) VALUES
('REG-20260914-0002', 'Lina Marlina', '3502011234560004', '081234567804', 'Peralihan Hak (Waris/Hibah)', 'SHM No. 341 / Desa Margomulyo', 'KTP, KK, Sertifikat asli, Surat Keterangan Waris', 'ditolak_ke_loket', 3);
INSERT INTO `log_pergerakan` (`id_berkas`, `pengirim_id`, `aksi`, `status_sebelum`, `status_sesudah`, `catatan`) VALUES
(4, 3, 'diteruskan', 'loket', 'seksi_1', 'Berkas baru diinput dan diteruskan ke Seksi 1 untuk pemeriksaan.'),
(4, 4, 'dikembalikan', 'seksi_1', 'ditolak_ke_loket', 'Surat Keterangan Waris belum dilegalisir oleh kelurahan. Mohon dilengkapi.');

-- Berkas 5: baru diinput di Loket, menunggu konfirmasi pengiriman ke Seksi 1
INSERT INTO `berkas` (`nomor_pendaftaran`, `nama_pemohon`, `nik`, `no_hp`, `jenis_layanan`, `sertifikat_desa`, `deskripsi_berkas`, `status_posisi`, `diinput_oleh`) VALUES
('REG-20260916-0001', 'Bambang Tri', '3502011234560005', '081234567805', 'Pengecekan Sertipikat', 'SHM No. 901 / Desa Sumberrejo', 'KTP, Fotokopi Sertipikat', 'loket', 2);
INSERT INTO `log_pergerakan` (`id_berkas`, `pengirim_id`, `aksi`, `status_sebelum`, `status_sesudah`, `catatan`) VALUES
(5, 2, 'diinput', NULL, 'loket', 'Berkas baru didaftarkan di loket (menunggu konfirmasi pengiriman ke Seksi 1).');
