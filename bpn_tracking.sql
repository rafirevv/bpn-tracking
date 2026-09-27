-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 27 Sep 2026 pada 16.00
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bpn_tracking`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `berkas`
--

CREATE TABLE `berkas` (
  `id` int(10) UNSIGNED NOT NULL,
  `nomor_pendaftaran` varchar(100) NOT NULL,
  `nama_pemohon` varchar(150) NOT NULL,
  `nik` varchar(20) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `jenis_layanan` varchar(100) NOT NULL,
  `sertifikat_desa` varchar(255) DEFAULT NULL,
  `deskripsi_berkas` text DEFAULT NULL,
  `status_posisi` enum('loket','seksi_1','seksi_2','selesai','ditolak_ke_loket','ditolak_ke_seksi1') NOT NULL DEFAULT 'loket',
  `diinput_oleh` int(10) UNSIGNED DEFAULT NULL,
  `petugas_tujuan_id` int(10) UNSIGNED DEFAULT NULL,
  `deadline_at` datetime DEFAULT NULL,
  `sisa_sla_detik` int(11) DEFAULT NULL COMMENT 'Sisa detik SLA saat berkas dijeda/dikembalikan ke loket',
  `is_diterima_loket` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'flag konfirmasi loket setelah berkas selesai/ditolak kembali',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `berkas`
--

INSERT INTO `berkas` (`id`, `nomor_pendaftaran`, `nama_pemohon`, `nik`, `no_hp`, `jenis_layanan`, `sertifikat_desa`, `deskripsi_berkas`, `status_posisi`, `diinput_oleh`, `petugas_tujuan_id`, `deadline_at`, `sisa_sla_detik`, `is_diterima_loket`, `created_at`, `updated_at`) VALUES
(53, 'BPN/2026/001', 'HAMDAN AHMAD', NULL, NULL, 'Pendaftaran Tanah Pertama Kali Penegasan Tanah Wakaf', 'MAKMUR ABADI', 'MAKMUR ABADI', 'ditolak_ke_loket', 2, NULL, '2026-09-24 10:38:57', 40409, 1, '2026-09-22 08:29:17', '2026-09-24 07:25:28'),
(54, 'BPN/2026/002', 'bada', NULL, NULL, 'Hapusnya Hak', 'tanggi', 'tanggi', 'selesai', 2, NULL, NULL, NULL, 1, '2026-09-23 01:26:39', '2026-09-23 02:06:12'),
(55, 'BPN/2026/003', 'porter', NULL, NULL, 'Pendaftaran SK Hak', 'shm 234', 'shm 234', 'ditolak_ke_loket', 2, NULL, '2026-09-25 03:29:48', 101060, 1, '2026-09-23 01:26:57', '2026-09-24 07:25:28'),
(56, 'BPN/2026/004', 'kere', NULL, NULL, 'Ganti Nama Pemegang Hak Tanggungan', 'desa sukadamai', 'desa sukadamai', 'ditolak_ke_loket', 2, NULL, '2026-09-26 07:47:16', -9262, 0, '2026-09-23 02:31:26', '2026-09-26 08:21:38'),
(57, 'BPN/2026/005', 'yeye', NULL, NULL, 'Hak Tanggungan', 'desa sukamaju', 'desa sukamaju', 'seksi_2', 2, NULL, '2026-09-25 05:00:48', NULL, 0, '2026-09-23 02:31:36', '2026-09-24 06:03:01'),
(58, 'BPN/2026/006', 'ahmad', NULL, NULL, 'Pelantikan PPAT Sementara', 'shm - 234565', 'shm - 234565', 'seksi_1', 2, NULL, '2026-09-25 05:02:42', NULL, 0, '2026-09-23 03:01:42', '2026-09-24 06:02:46'),
(59, 'BPN/2026/007', 'vivi', NULL, NULL, 'Blokir', 'tanggi', 'tanggi', 'selesai', 2, NULL, NULL, NULL, 1, '2026-09-23 04:36:38', '2026-09-23 06:48:00'),
(60, 'BPN/2026/008', 'desa', NULL, NULL, 'Blokir', 'tanggi', 'tanggi', 'seksi_1', 2, NULL, '2026-09-25 06:42:45', NULL, 0, '2026-09-23 04:42:45', '2026-09-23 04:42:45'),
(61, 'BPN/2026/009', 'rini', NULL, NULL, 'Ganti Nama', 'shm 2345', 'shm 2345', 'seksi_1', 2, NULL, '2026-09-26 07:47:13', NULL, 0, '2026-09-23 06:02:21', '2026-09-24 05:47:13'),
(62, 'BPN/2026/010', 'ishak', NULL, NULL, 'Peralihan Hak - Jual Beli', '2719.0/Boludawa', '2719.0/Boludawa', 'selesai', 2, NULL, NULL, NULL, 1, '2026-09-23 07:13:41', '2026-09-23 07:17:01'),
(63, 'BPN/2026/011', 'rafi', NULL, NULL, 'Blokir', 'tanggi', 'tanggi', 'selesai', 2, NULL, NULL, NULL, 1, '2026-09-23 07:36:49', '2026-09-23 07:38:43'),
(64, 'BPN/2026/012', 'haha', NULL, NULL, 'Penataan Batas', 'desa sukamaju', 'desa sukamaju', 'selesai', 2, NULL, NULL, NULL, 1, '2026-09-23 07:56:54', '2026-09-23 07:59:59'),
(65, 'BPN/2026/013', 'Budi Santoso', NULL, NULL, 'Peralihan Hak - Jual Beli', 'SHM 1029 / Desa Sukamaju', 'Peralihan hak atas tanah jual beli sertifikat SHM 1029.', 'loket', 2, NULL, '2026-09-26 06:13:57', NULL, 0, '2026-09-21 03:21:28', '2026-09-27 13:52:42'),
(66, 'BPN/2026/014', 'CHARLES LECLERC', NULL, NULL, 'Peralihan Hak - Jual Beli', 'Desa UK', 'Desa UK', 'ditolak_ke_loket', 2, NULL, '2026-09-26 07:46:16', 202848, 0, '2026-09-24 05:46:16', '2026-09-24 07:25:28'),
(67, 'BPN/2026/015', 'LEWIS HAMILTON', NULL, NULL, 'Pemecahan Bidang', 'Desa Ferrari', 'Desa Ferrari', 'seksi_2', 2, NULL, '2026-09-26 07:47:03', NULL, 0, '2026-09-24 05:47:03', '2026-09-24 05:47:03'),
(68, 'BPN/2026/016', 'gf', NULL, NULL, 'Ganti Nama Pemegang Hak Tanggungan', '122', '122', 'seksi_2', 2, NULL, '2026-09-28 14:50:06', NULL, 0, '2026-09-24 06:18:35', '2026-09-26 12:51:24'),
(69, 'BPN/2026/017', 'nnnn', NULL, NULL, 'Hak Tanggungan', '123', '123', 'loket', 2, NULL, NULL, NULL, 0, '2026-09-26 08:55:22', '2026-09-26 08:55:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `log_pergerakan`
--

CREATE TABLE `log_pergerakan` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_berkas` int(10) UNSIGNED NOT NULL,
  `pengirim_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'user yang melakukan aksi',
  `penerima_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'user tujuan spesifik (opsional, biasanya tujuan berupa unit/role)',
  `aksi` enum('diinput','diteruskan','dikembalikan','selesai','diterima') NOT NULL,
  `status_sebelum` varchar(30) DEFAULT NULL,
  `status_sesudah` varchar(30) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `log_pergerakan`
--

INSERT INTO `log_pergerakan` (`id`, `id_berkas`, `pengirim_id`, `penerima_id`, `aksi`, `status_sebelum`, `status_sesudah`, `catatan`, `created_at`) VALUES
(100, 53, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini (menunggu konfirmasi pengiriman ke seksi).', '2026-09-22 08:29:17'),
(101, 53, 2, NULL, 'diteruskan', 'loket', 'seksi_1', 'Berkas dikonfirmasi dan dikirim oleh Petugas Loket (Rini) ke Seksi 1 (Survei & Pemetaan).', '2026-09-22 08:37:41'),
(102, 53, 4, NULL, 'dikembalikan', 'seksi_1', 'ditolak_ke_loket', 'revisi [Dikembalikan ke Loket oleh lulu karena berkas belum lengkap]', '2026-09-22 08:38:57'),
(103, 53, 2, NULL, 'diterima', 'ditolak_ke_loket', 'ditolak_ke_loket', 'Berkas telah diterima kembali oleh Loket dan siap diserahkan/diinformasikan ke pemohon.', '2026-09-22 08:39:34'),
(104, 54, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini (menunggu konfirmasi pengiriman ke seksi).', '2026-09-23 01:26:39'),
(105, 55, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini (menunggu konfirmasi pengiriman ke seksi).', '2026-09-23 01:26:57'),
(106, 55, 2, NULL, 'diteruskan', 'loket', 'seksi_1', 'Berkas dikonfirmasi dan dikirim oleh Petugas Loket (Rini) ke Seksi 1 (Survei & Pemetaan).', '2026-09-23 01:28:50'),
(107, 54, 2, NULL, 'diteruskan', 'loket', 'seksi_2', 'Berkas dikonfirmasi dan dikirim oleh Petugas Loket (Rini) ke Seksi 2 (Penetapan Hak & Pendaftaran).', '2026-09-23 01:28:55'),
(108, 55, 4, NULL, 'dikembalikan', 'seksi_1', 'ditolak_ke_loket', 'revisi [Dikembalikan ke Loket oleh lulu karena berkas belum lengkap]', '2026-09-23 01:29:48'),
(109, 54, 5, NULL, 'dikembalikan', 'seksi_2', 'ditolak_ke_seksi1', 'revisi [Dikembalikan ke Seksi 1 (Survei & Pemetaan) oleh vivi (Seksi 2 Penetapan Hak & Pendaftaran - Bagian Penetapan) untuk perbaikan]', '2026-09-23 01:31:13'),
(110, 54, 4, NULL, 'diteruskan', 'ditolak_ke_seksi1', 'seksi_2', '[Diteruskan ke Seksi 2 (Penetapan Hak & Pendaftaran) oleh lulu]', '2026-09-23 01:40:50'),
(111, 54, 6, NULL, 'diteruskan', 'seksi_2', 'loket', 'sudah tahede [Selesai diproses oleh raini (Seksi 2 Penetapan Hak & Pendaftaran - Bagian Peralihan), diteruskan ke Loket untuk verifikasi & penyerahan ke pemohon]', '2026-09-23 01:45:13'),
(112, 55, 2, NULL, 'diterima', 'ditolak_ke_loket', 'ditolak_ke_loket', 'Berkas telah diterima kembali oleh Loket dan siap diserahkan/diinformasikan ke pemohon.', '2026-09-23 01:50:52'),
(113, 54, 2, NULL, 'selesai', 'loket', 'selesai', '[Berkas telah diverifikasi dan diserahkan kepada pemohon oleh Rini. Status berkas dinyatakan SELESAI dan waktu SLA otomatis berhenti]', '2026-09-23 02:06:12'),
(114, 56, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini (menunggu konfirmasi pengiriman ke seksi).', '2026-09-23 02:31:26'),
(115, 57, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini (menunggu konfirmasi pengiriman ke seksi).', '2026-09-23 02:31:36'),
(116, 57, 2, NULL, 'diteruskan', 'loket', 'seksi_1', 'Berkas dikonfirmasi dan dikirim oleh Petugas Loket (Rini) ke Seksi 1 (Survei & Pemetaan).', '2026-09-23 02:32:45'),
(117, 57, 4, NULL, 'dikembalikan', 'seksi_1', 'ditolak_ke_loket', 'kurang ajar [Dikembalikan ke Loket oleh lulu karena berkas belum lengkap]', '2026-09-23 02:42:18'),
(118, 57, 2, NULL, 'diteruskan', 'ditolak_ke_loket', 'seksi_1', 'udah beb [Berkas diteruskan kembali ke Seksi 1 (Survei & Pemetaan) oleh Rini setelah berkas diperbaiki/dilengkapi]', '2026-09-23 02:51:52'),
(119, 57, 4, NULL, 'diteruskan', 'seksi_1', 'seksi_2', '[Diteruskan ke Seksi 2 (Penetapan Hak & Pendaftaran) oleh lulu]', '2026-09-23 02:52:10'),
(120, 57, 5, NULL, 'dikembalikan', 'seksi_2', 'ditolak_ke_loket', 'monok [Dikembalikan langsung ke Loket oleh vivi (Seksi 2 Penetapan Hak & Pendaftaran - Bagian Penetapan) — berkas perlu dilengkapi melalui Loket]', '2026-09-23 03:00:48'),
(121, 58, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini.', '2026-09-23 03:01:42'),
(122, 58, 2, NULL, 'diteruskan', 'loket', 'seksi_2', 'Berkas dikonfirmasi dan langsung dikirim ke Seksi 2 (Penetapan Hak & Pendaftaran) untuk pemrosesan yuridis/pendaftaran.', '2026-09-23 03:01:42'),
(123, 58, 5, NULL, 'dikembalikan', 'seksi_2', 'ditolak_ke_seksi1', 'cuks [Dikembalikan ke Seksi 1 (Survei & Pemetaan) oleh vivi (Seksi 2 Penetapan Hak & Pendaftaran - Bagian Penetapan) untuk perbaikan]', '2026-09-23 03:02:06'),
(124, 58, 4, NULL, 'dikembalikan', 'ditolak_ke_seksi1', 'ditolak_ke_loket', 'kokok [Dikembalikan ke Loket oleh lulu karena berkas belum lengkap]', '2026-09-23 03:02:42'),
(125, 59, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini.', '2026-09-23 04:36:38'),
(126, 59, 2, NULL, 'diteruskan', 'loket', 'seksi_1', 'Berkas dikonfirmasi dan langsung dikirim ke Seksi 1 (Survei & Pemetaan) untuk pemeriksaan dan pengukuran.', '2026-09-23 04:36:38'),
(127, 59, 4, NULL, 'diteruskan', 'seksi_1', 'seksi_2', '[Diteruskan ke Seksi 2 (Penetapan Hak & Pendaftaran) oleh lulu]', '2026-09-23 04:37:59'),
(128, 59, 6, NULL, 'diteruskan', 'seksi_2', 'loket', 'oke [Selesai diproses oleh raini (Seksi 2 Penetapan Hak & Pendaftaran - Bagian Peralihan), diteruskan ke Loket untuk verifikasi & penyerahan ke pemohon]', '2026-09-23 04:39:43'),
(129, 60, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini.', '2026-09-23 04:42:45'),
(130, 60, 2, NULL, 'diteruskan', 'loket', 'seksi_1', 'Berkas dikonfirmasi dan langsung dikirim ke Seksi 1 (Survei & Pemetaan) untuk pemeriksaan dan pengukuran.', '2026-09-23 04:42:45'),
(131, 61, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini (menunggu konfirmasi pengiriman ke seksi).', '2026-09-23 06:02:21'),
(132, 59, 2, NULL, 'selesai', 'loket', 'selesai', '[Berkas telah diverifikasi dan diserahkan kepada pemohon oleh Rini. Status berkas dinyatakan SELESAI dan waktu SLA otomatis berhenti]', '2026-09-23 06:48:00'),
(133, 62, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini.', '2026-09-23 07:13:41'),
(134, 62, 2, NULL, 'diteruskan', 'loket', 'seksi_2', 'Berkas dikonfirmasi dan langsung dikirim ke Seksi 2 (Penetapan Hak & Pendaftaran) untuk pemrosesan yuridis/pendaftaran.', '2026-09-23 07:13:41'),
(135, 62, 6, NULL, 'diteruskan', 'seksi_2', 'loket', 'oke [Selesai diproses oleh raini (Seksi 2 Penetapan Hak & Pendaftaran - Bagian Peralihan), diteruskan ke Loket untuk verifikasi & penyerahan ke pemohon]', '2026-09-23 07:15:01'),
(136, 62, 2, NULL, 'selesai', 'loket', 'selesai', '[Berkas telah diverifikasi dan diserahkan kepada pemohon oleh Rini. Status berkas dinyatakan SELESAI dan waktu SLA otomatis berhenti]', '2026-09-23 07:17:01'),
(137, 63, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini.', '2026-09-23 07:36:49'),
(138, 63, 2, NULL, 'diteruskan', 'loket', 'seksi_1', 'Berkas dikonfirmasi dan langsung dikirim ke Seksi 1 (Survei & Pemetaan) untuk pemeriksaan dan pengukuran.', '2026-09-23 07:36:49'),
(139, 63, 4, NULL, 'diteruskan', 'seksi_1', 'loket', '[Diteruskan ke Loket oleh lulu untuk verifikasi & penyerahan]', '2026-09-23 07:37:35'),
(140, 63, 2, NULL, 'selesai', 'loket', 'selesai', '[Berkas telah diverifikasi dan diserahkan kepada pemohon oleh Rini. Status berkas dinyatakan SELESAI dan waktu SLA otomatis berhenti]', '2026-09-23 07:38:43'),
(141, 64, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini.', '2026-09-23 07:56:54'),
(142, 64, 2, NULL, 'diteruskan', 'loket', 'seksi_1', 'Berkas dikonfirmasi dan langsung dikirim ke Seksi 1 (Survei & Pemetaan) untuk pemeriksaan dan pengukuran.', '2026-09-23 07:56:54'),
(143, 64, 4, NULL, 'diteruskan', 'seksi_1', 'seksi_2', '[Diteruskan ke Seksi 2 (Penetapan Hak & Pendaftaran) oleh lulu]', '2026-09-23 07:57:45'),
(144, 64, 6, NULL, 'diteruskan', 'seksi_2', 'loket', 'oke [Selesai diproses oleh raini (Seksi 2 Penetapan Hak & Pendaftaran - Bagian Peralihan), diteruskan ke Loket untuk verifikasi & penyerahan ke pemohon]', '2026-09-23 07:58:46'),
(145, 64, 2, NULL, 'selesai', 'loket', 'selesai', 'oke [Berkas telah diverifikasi dan diserahkan kepada pemohon oleh Rini. Status berkas dinyatakan SELESAI dan waktu SLA otomatis berhenti]', '2026-09-23 07:59:59'),
(146, 65, 2, NULL, 'diinput', 'loket', 'loket', 'Berkas didaftarkan di loket.', '2026-09-21 03:21:28'),
(147, 65, 2, 4, 'diteruskan', 'loket', 'seksi_1', 'Berkas dikirim ke Seksi 1 (Survei & Pemetaan) untuk proses pengukuran.', '2026-09-21 03:21:28'),
(148, 65, 4, NULL, 'dikembalikan', 'seksi_1', 'ditolak_ke_loket', 'ok [Dikembalikan ke Loket oleh lulu karena berkas belum lengkap]', '2026-09-24 04:12:17'),
(149, 65, 2, NULL, 'diteruskan', 'ditolak_ke_loket', 'seksi_1', 'ini sudah [Berkas diteruskan kembali ke Seksi 1 (Survei & Pemetaan) oleh Rini setelah berkas diperbaiki/dilengkapi]', '2026-09-24 04:13:27'),
(150, 65, 4, NULL, 'diteruskan', 'seksi_1', 'seksi_2', '[Diteruskan ke Seksi 2 (Penetapan Hak & Pendaftaran) oleh lulu]', '2026-09-24 04:13:57'),
(151, 66, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini.', '2026-09-24 05:46:16'),
(152, 66, 2, NULL, 'diteruskan', 'loket', 'seksi_1', 'Berkas dikonfirmasi dan langsung dikirim ke Seksi 1 (Survei & Pemetaan) untuk pemeriksaan dan pengukuran.', '2026-09-24 05:46:16'),
(153, 67, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini.', '2026-09-24 05:47:03'),
(154, 67, 2, NULL, 'diteruskan', 'loket', 'seksi_2', 'Berkas dikonfirmasi dan langsung dikirim ke Seksi 2 (Penetapan Hak & Pendaftaran) untuk pemrosesan yuridis/pendaftaran.', '2026-09-24 05:47:03'),
(155, 61, 2, NULL, 'diteruskan', 'loket', 'seksi_1', 'Berkas dikonfirmasi dan dikirim oleh Petugas Loket (Rini) ke Seksi 1 (Survei & Pemetaan).', '2026-09-24 05:47:13'),
(156, 56, 2, NULL, 'diteruskan', 'loket', 'seksi_2', 'Berkas dikonfirmasi dan dikirim oleh Petugas Loket (Rini) ke Seksi 2 (Penetapan Hak & Pendaftaran).', '2026-09-24 05:47:16'),
(157, 66, 4, NULL, 'dikembalikan', 'seksi_1', 'ditolak_ke_loket', 'ass [Dikembalikan ke Loket oleh lulu karena berkas belum lengkap]', '2026-09-24 06:02:18'),
(158, 58, 2, NULL, 'diteruskan', 'ditolak_ke_loket', 'seksi_1', 'ini sudaj diperbaiki [Berkas diteruskan kembali ke Seksi 1 (Survei & Pemetaan) oleh Rini setelah berkas diperbaiki/dilengkapi]', '2026-09-24 06:02:46'),
(159, 57, 2, NULL, 'diteruskan', 'ditolak_ke_loket', 'seksi_2', 'in sudah di perbaiki [Berkas diteruskan kembali ke Seksi 2 (Penetapan Hak & Pendaftaran) oleh Rini setelah berkas diperbaiki/dilengkapi]', '2026-09-24 06:03:01'),
(160, 68, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini (menunggu konfirmasi pengiriman ke seksi).', '2026-09-24 06:18:35'),
(161, 56, 5, NULL, 'dikembalikan', 'seksi_2', 'ditolak_ke_seksi1', 'hede [Dikembalikan ke Seksi 1 (Survei & Pemetaan) oleh vivi (Seksi 2 Penetapan Hak & Pendaftaran - Bagian Penetapan) untuk perbaikan]', '2026-09-24 08:34:54'),
(162, 56, 4, NULL, 'dikembalikan', 'ditolak_ke_seksi1', 'ditolak_ke_loket', 'gb [Dikembalikan ke Loket oleh lulu karena berkas belum lengkap]', '2026-09-26 08:21:38'),
(163, 69, 2, NULL, 'diinput', NULL, 'loket', 'Berkas didaftarkan di loket oleh Rini (menunggu konfirmasi pengiriman ke seksi).', '2026-09-26 08:55:22'),
(164, 68, 2, NULL, 'diteruskan', 'loket', 'seksi_2', 'Berkas dikonfirmasi dan dikirim oleh Petugas Loket (Rini) ke Seksi 2 (Penetapan Hak & Pendaftaran).', '2026-09-26 12:50:06'),
(165, 68, 6, NULL, 'diteruskan', 'seksi_2', 'loket', 'afafffafafa [Selesai diproses oleh raini (Seksi 2 Penetapan Hak & Pendaftaran - Bagian Peralihan), diteruskan ke Loket untuk verifikasi & penyerahan ke pemohon]', '2026-09-26 12:50:56'),
(166, 68, 2, NULL, 'dikembalikan', 'loket', 'seksi_2', 'sgsgd [Dikembalikan ke Seksi 2 (Penetapan Hak & Pendaftaran) oleh Rini karena berkas belum lengkap. SLA melanjutkan waktu tersisa]', '2026-09-26 12:51:24'),
(167, 65, 5, NULL, 'diteruskan', 'seksi_2', 'loket', '[Selesai diproses oleh vivi (Seksi 2 Penetapan Hak & Pendaftaran - Bagian Penetapan), diteruskan ke Loket untuk verifikasi & penyerahan ke pemohon]', '2026-09-27 13:52:42');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_berkas` int(10) UNSIGNED DEFAULT NULL,
  `target_role` enum('admin','loket','seksi_1','seksi_2','all') NOT NULL,
  `target_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Jika ditujukan ke user spesifik',
  `judul` varchar(150) NOT NULL,
  `pesan` text NOT NULL,
  `tipe` enum('masuk','kembali','selesai','warning','kritis','info') NOT NULL DEFAULT 'masuk',
  `link` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `notifikasi`
--

INSERT INTO `notifikasi` (`id`, `id_berkas`, `target_role`, `target_user_id`, `judul`, `pesan`, `tipe`, `link`, `created_at`) VALUES
(1, 53, 'admin', NULL, 'Berkas Baru Diinput', 'Berkas No. BPN/2026/001 telah diinput di Loket oleh Rini.', 'info', 'detail.php?id=53', '2026-09-22 08:29:17'),
(2, 53, 'seksi_1', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/001 dari Loket siap diproses pengukuran & pemetaan.', 'masuk', 'detail.php?id=53', '2026-09-22 08:37:41'),
(3, 53, 'loket', NULL, 'Berkas Dikembalikan ke Loket', 'Berkas No. BPN/2026/001 dikembalikan oleh Seksi.', 'kembali', 'detail.php?id=53', '2026-09-22 08:38:57'),
(5, 53, 'seksi_1', NULL, 'Uji Coba Berkas Masuk', 'Berkas uji coba telah dikirim ke Seksi 1.', 'masuk', 'detail.php?id=53', '2026-09-22 08:47:46'),
(6, 54, 'admin', NULL, 'Berkas Baru Diinput (Draft)', 'Berkas No. BPN/2026/002 diinput di Loket (menunggu pengiriman ke seksi).', 'info', 'detail.php?id=54', '2026-09-23 01:26:39'),
(7, 55, 'admin', NULL, 'Berkas Baru Diinput (Draft)', 'Berkas No. BPN/2026/003 diinput di Loket (menunggu pengiriman ke seksi).', 'info', 'detail.php?id=55', '2026-09-23 01:26:57'),
(8, 55, 'seksi_1', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/003 dikirim dari Loket ke Seksi 1 (Survei & Pemetaan).', 'masuk', 'detail.php?id=55', '2026-09-23 01:28:50'),
(9, 54, 'seksi_2', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/002 dikirim dari Loket ke Seksi 2 (Penetapan Hak & Pendaftaran).', 'masuk', 'detail.php?id=54', '2026-09-23 01:28:55'),
(10, 55, 'loket', NULL, 'Berkas Perlu Perbaikan', 'Berkas No. BPN/2026/003 dikembalikan oleh Seksi 1 ke Loket: revisi', 'kembali', 'detail.php?id=55', '2026-09-23 01:29:48'),
(11, 54, 'seksi_1', NULL, 'Berkas Dikembalikan dari Seksi 2', 'Berkas No. BPN/2026/002 dikembalikan oleh Seksi 2 untuk perbaikan: revisi', 'kembali', 'detail.php?id=54', '2026-09-23 01:31:13'),
(12, 54, 'seksi_2', NULL, 'Berkas Masuk dari Seksi 1', 'Berkas No. BPN/2026/002 telah selesai di Seksi 1 dan diteruskan ke Seksi 2.', 'masuk', 'detail.php?id=54', '2026-09-23 01:40:50'),
(13, 54, 'loket', NULL, 'Berkas Siap Diverifikasi', 'Berkas No. BPN/2026/002 telah selesai di Seksi 2 dan siap diverifikasi di Loket.', 'masuk', 'detail.php?id=54', '2026-09-23 01:45:13'),
(14, 55, 'admin', NULL, 'Berkas Diterima di Loket', 'Berkas No. BPN/2026/003 telah dikonfirmasi diterima kembali di Loket.', 'info', 'detail.php?id=55', '2026-09-23 01:50:52'),
(15, 54, 'admin', NULL, 'Berkas Selesai Diserahkan', 'Berkas No. BPN/2026/002 telah diserahkan kepada pemohon oleh Rini (Status: Selesai).', 'selesai', 'detail.php?id=54', '2026-09-23 02:06:12'),
(16, 54, 'loket', NULL, 'Berkas Selesai Diserahkan', 'Berkas No. BPN/2026/002 sukses diserahkan kepada pemohon.', 'selesai', 'detail.php?id=54', '2026-09-23 02:06:12'),
(17, 56, 'admin', NULL, 'Berkas Baru Diinput (Draft)', 'Berkas No. BPN/2026/004 diinput di Loket (menunggu pengiriman ke seksi).', 'info', 'detail.php?id=56', '2026-09-23 02:31:26'),
(18, 57, 'admin', NULL, 'Berkas Baru Diinput (Draft)', 'Berkas No. BPN/2026/005 diinput di Loket (menunggu pengiriman ke seksi).', 'info', 'detail.php?id=57', '2026-09-23 02:31:36'),
(19, 57, 'seksi_1', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/005 dikirim dari Loket ke Seksi 1 (Survei & Pemetaan).', 'masuk', 'detail.php?id=57', '2026-09-23 02:32:45'),
(20, 57, 'loket', NULL, 'Berkas Perlu Perbaikan', 'Berkas No. BPN/2026/005 dikembalikan oleh Seksi 1 ke Loket: kurang ajar', 'kembali', 'detail.php?id=57', '2026-09-23 02:42:18'),
(21, 57, 'seksi_1', NULL, 'Berkas Masuk Kembali (Setelah Perbaikan)', 'Berkas No. BPN/2026/005 telah diperbaiki dan diteruskan kembali oleh Loket ke Seksi 1 (Survei & Pemetaan).', 'masuk', 'detail.php?id=57', '2026-09-23 02:51:52'),
(22, 57, 'admin', NULL, 'Berkas Diteruskan Kembali ke Seksi', 'Berkas No. BPN/2026/005 diteruskan kembali ke Seksi 1 (Survei & Pemetaan) oleh Rini setelah perbaikan.', 'info', 'detail.php?id=57', '2026-09-23 02:51:52'),
(23, 57, 'seksi_2', NULL, 'Berkas Masuk dari Seksi 1', 'Berkas No. BPN/2026/005 telah selesai di Seksi 1 dan diteruskan ke Seksi 2.', 'masuk', 'detail.php?id=57', '2026-09-23 02:52:10'),
(24, 57, 'loket', NULL, 'Berkas Ditolak — Perlu Ditindaklanjuti', 'Berkas No. BPN/2026/005 dikembalikan langsung ke Loket oleh Seksi 2: monok', 'kembali', 'detail.php?id=57', '2026-09-23 03:00:48'),
(25, 58, 'seksi_2', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/006 dari Loket siap diproses penetapan hak & pendaftaran.', 'masuk', 'detail.php?id=58', '2026-09-23 03:01:42'),
(26, 58, 'admin', NULL, 'Berkas Baru Diinput', 'Berkas No. BPN/2026/006 telah diinput di Loket dan diteruskan ke Seksi 2.', 'info', 'detail.php?id=58', '2026-09-23 03:01:42'),
(27, 58, 'seksi_1', NULL, 'Berkas Dikembalikan dari Seksi 2', 'Berkas No. BPN/2026/006 dikembalikan oleh Seksi 2 untuk perbaikan: cuks', 'kembali', 'detail.php?id=58', '2026-09-23 03:02:06'),
(28, 58, 'loket', NULL, 'Berkas Perlu Perbaikan', 'Berkas No. BPN/2026/006 dikembalikan oleh Seksi 1 ke Loket: kokok', 'kembali', 'detail.php?id=58', '2026-09-23 03:02:42'),
(29, 59, 'seksi_1', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/007 dari Loket siap diproses pengukuran & pemetaan.', 'masuk', 'detail.php?id=59', '2026-09-23 04:36:38'),
(30, 59, 'admin', NULL, 'Berkas Baru Diinput', 'Berkas No. BPN/2026/007 telah diinput di Loket dan diteruskan ke Seksi 1.', 'info', 'detail.php?id=59', '2026-09-23 04:36:38'),
(31, 59, 'seksi_2', NULL, 'Berkas Masuk dari Seksi 1', 'Berkas No. BPN/2026/007 telah selesai di Seksi 1 dan diteruskan ke Seksi 2.', 'masuk', 'detail.php?id=59', '2026-09-23 04:37:59'),
(32, 59, 'loket', NULL, 'Berkas Siap Diverifikasi', 'Berkas No. BPN/2026/007 telah selesai di Seksi 2 dan siap diverifikasi di Loket.', 'masuk', 'detail.php?id=59', '2026-09-23 04:39:43'),
(33, 60, 'seksi_1', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/008 dari Loket siap diproses pengukuran & pemetaan.', 'masuk', 'detail.php?id=60', '2026-09-23 04:42:45'),
(34, 60, 'admin', NULL, 'Berkas Baru Diinput', 'Berkas No. BPN/2026/008 telah diinput di Loket dan diteruskan ke Seksi 1.', 'info', 'detail.php?id=60', '2026-09-23 04:42:45'),
(35, 61, 'admin', NULL, 'Berkas Baru Diinput (Draft)', 'Berkas No. BPN/2026/009 diinput di Loket (menunggu pengiriman ke seksi).', 'info', 'detail.php?id=61', '2026-09-23 06:02:21'),
(36, 59, 'admin', NULL, 'Berkas Selesai Diserahkan', 'Berkas No. BPN/2026/007 telah diserahkan kepada pemohon oleh Rini (Status: Selesai).', 'selesai', 'detail.php?id=59', '2026-09-23 06:48:00'),
(37, 59, 'loket', NULL, 'Berkas Selesai Diserahkan', 'Berkas No. BPN/2026/007 sukses diserahkan kepada pemohon.', 'selesai', 'detail.php?id=59', '2026-09-23 06:48:00'),
(38, 62, 'seksi_2', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/010 dari Loket siap diproses penetapan hak & pendaftaran.', 'masuk', 'detail.php?id=62', '2026-09-23 07:13:41'),
(39, 62, 'admin', NULL, 'Berkas Baru Diinput', 'Berkas No. BPN/2026/010 telah diinput di Loket dan diteruskan ke Seksi 2.', 'info', 'detail.php?id=62', '2026-09-23 07:13:41'),
(40, 62, 'loket', NULL, 'Berkas Siap Diverifikasi', 'Berkas No. BPN/2026/010 telah selesai di Seksi 2 dan siap diverifikasi di Loket.', 'masuk', 'detail.php?id=62', '2026-09-23 07:15:01'),
(41, 62, 'admin', NULL, 'Berkas Selesai Diserahkan', 'Berkas No. BPN/2026/010 telah diserahkan kepada pemohon oleh Rini (Status: Selesai).', 'selesai', 'detail.php?id=62', '2026-09-23 07:17:01'),
(42, 62, 'loket', NULL, 'Berkas Selesai Diserahkan', 'Berkas No. BPN/2026/010 sukses diserahkan kepada pemohon.', 'selesai', 'detail.php?id=62', '2026-09-23 07:17:01'),
(43, 63, 'seksi_1', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/011 dari Loket siap diproses pengukuran & pemetaan.', 'masuk', 'detail.php?id=63', '2026-09-23 07:36:49'),
(44, 63, 'admin', NULL, 'Berkas Baru Diinput', 'Berkas No. BPN/2026/011 telah diinput di Loket dan diteruskan ke Seksi 1.', 'info', 'detail.php?id=63', '2026-09-23 07:36:49'),
(45, 63, 'loket', NULL, 'Berkas Siap Diverifikasi', 'Berkas No. BPN/2026/011 telah selesai di Seksi 1 dan diteruskan ke Loket.', 'masuk', 'detail.php?id=63', '2026-09-23 07:37:35'),
(46, 63, 'admin', NULL, 'Berkas Selesai Diserahkan', 'Berkas No. BPN/2026/011 telah diserahkan kepada pemohon oleh Rini (Status: Selesai).', 'selesai', 'detail.php?id=63', '2026-09-23 07:38:43'),
(47, 63, 'loket', NULL, 'Berkas Selesai Diserahkan', 'Berkas No. BPN/2026/011 sukses diserahkan kepada pemohon.', 'selesai', 'detail.php?id=63', '2026-09-23 07:38:43'),
(48, 64, 'seksi_1', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/012 dari Loket siap diproses pengukuran & pemetaan.', 'masuk', 'detail.php?id=64', '2026-09-23 07:56:54'),
(49, 64, 'admin', NULL, 'Berkas Baru Diinput', 'Berkas No. BPN/2026/012 telah diinput di Loket dan diteruskan ke Seksi 1.', 'info', 'detail.php?id=64', '2026-09-23 07:56:54'),
(50, 64, 'seksi_2', NULL, 'Berkas Masuk dari Seksi 1', 'Berkas No. BPN/2026/012 telah selesai di Seksi 1 dan diteruskan ke Seksi 2.', 'masuk', 'detail.php?id=64', '2026-09-23 07:57:45'),
(51, 64, 'loket', NULL, 'Berkas Siap Diverifikasi', 'Berkas No. BPN/2026/012 telah selesai di Seksi 2 dan siap diverifikasi di Loket.', 'masuk', 'detail.php?id=64', '2026-09-23 07:58:46'),
(52, 64, 'admin', NULL, 'Berkas Selesai Diserahkan', 'Berkas No. BPN/2026/012 telah diserahkan kepada pemohon oleh Rini (Status: Selesai).', 'selesai', 'detail.php?id=64', '2026-09-23 07:59:59'),
(53, 64, 'loket', NULL, 'Berkas Selesai Diserahkan', 'Berkas No. BPN/2026/012 sukses diserahkan kepada pemohon.', 'selesai', 'detail.php?id=64', '2026-09-23 07:59:59'),
(54, 65, 'loket', NULL, 'Berkas Perlu Perbaikan', 'Berkas No. BPN/2026/013 dikembalikan oleh Seksi 1 ke Loket: ok', 'kembali', 'detail.php?id=65', '2026-09-24 04:12:17'),
(55, 65, 'seksi_1', NULL, 'Berkas Masuk Kembali (Setelah Perbaikan)', 'Berkas No. BPN/2026/013 telah diperbaiki dan diteruskan kembali oleh Loket ke Seksi 1 (Survei & Pemetaan).', 'masuk', 'detail.php?id=65', '2026-09-24 04:13:27'),
(56, 65, 'admin', NULL, 'Berkas Diteruskan Kembali ke Seksi', 'Berkas No. BPN/2026/013 diteruskan kembali ke Seksi 1 (Survei & Pemetaan) oleh Rini setelah perbaikan.', 'info', 'detail.php?id=65', '2026-09-24 04:13:27'),
(57, 65, 'seksi_2', NULL, 'Berkas Masuk dari Seksi 1', 'Berkas No. BPN/2026/013 telah selesai di Seksi 1 dan diteruskan ke Seksi 2.', 'masuk', 'detail.php?id=65', '2026-09-24 04:13:57'),
(58, 66, 'seksi_1', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/014 dari Loket siap diproses pengukuran & pemetaan.', 'masuk', 'detail.php?id=66', '2026-09-24 05:46:16'),
(59, 66, 'admin', NULL, 'Berkas Baru Diinput', 'Berkas No. BPN/2026/014 telah diinput di Loket dan diteruskan ke Seksi 1.', 'info', 'detail.php?id=66', '2026-09-24 05:46:16'),
(60, 67, 'seksi_2', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/015 dari Loket siap diproses penetapan hak & pendaftaran.', 'masuk', 'detail.php?id=67', '2026-09-24 05:47:03'),
(61, 67, 'admin', NULL, 'Berkas Baru Diinput', 'Berkas No. BPN/2026/015 telah diinput di Loket dan diteruskan ke Seksi 2.', 'info', 'detail.php?id=67', '2026-09-24 05:47:03'),
(62, 61, 'seksi_1', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/009 dikirim dari Loket ke Seksi 1 (Survei & Pemetaan).', 'masuk', 'detail.php?id=61', '2026-09-24 05:47:13'),
(63, 56, 'seksi_2', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/004 dikirim dari Loket ke Seksi 2 (Penetapan Hak & Pendaftaran).', 'masuk', 'detail.php?id=56', '2026-09-24 05:47:16'),
(64, 66, 'loket', NULL, 'Berkas Perlu Perbaikan', 'Berkas No. BPN/2026/014 dikembalikan oleh Seksi 1 ke Loket: ass', 'kembali', 'detail.php?id=66', '2026-09-24 06:02:18'),
(65, 58, 'seksi_1', NULL, 'Berkas Masuk Kembali (Setelah Perbaikan)', 'Berkas No. BPN/2026/006 telah diperbaiki dan diteruskan kembali oleh Loket ke Seksi 1 (Survei & Pemetaan).', 'masuk', 'detail.php?id=58', '2026-09-24 06:02:46'),
(66, 58, 'admin', NULL, 'Berkas Diteruskan Kembali ke Seksi', 'Berkas No. BPN/2026/006 diteruskan kembali ke Seksi 1 (Survei & Pemetaan) oleh Rini setelah perbaikan.', 'info', 'detail.php?id=58', '2026-09-24 06:02:46'),
(67, 57, 'seksi_2', NULL, 'Berkas Masuk Kembali (Setelah Perbaikan)', 'Berkas No. BPN/2026/005 telah diperbaiki dan diteruskan kembali oleh Loket ke Seksi 2 (Penetapan Hak & Pendaftaran).', 'masuk', 'detail.php?id=57', '2026-09-24 06:03:01'),
(68, 57, 'admin', NULL, 'Berkas Diteruskan Kembali ke Seksi', 'Berkas No. BPN/2026/005 diteruskan kembali ke Seksi 2 (Penetapan Hak & Pendaftaran) oleh Rini setelah perbaikan.', 'info', 'detail.php?id=57', '2026-09-24 06:03:01'),
(69, 68, 'admin', NULL, 'Berkas Baru Diinput (Draft)', 'Berkas No. BPN/2026/016 diinput di Loket (menunggu pengiriman ke seksi).', 'info', 'detail.php?id=68', '2026-09-24 06:18:35'),
(70, 56, 'seksi_1', NULL, 'Berkas Dikembalikan dari Seksi 2', 'Berkas No. BPN/2026/004 dikembalikan oleh Seksi 2 untuk perbaikan: hede', 'kembali', 'detail.php?id=56', '2026-09-24 08:34:54'),
(71, 56, 'loket', NULL, 'Berkas Perlu Perbaikan', 'Berkas No. BPN/2026/004 dikembalikan oleh Seksi 1 ke Loket: gb', 'kembali', 'detail.php?id=56', '2026-09-26 08:21:38'),
(72, 69, 'admin', NULL, 'Berkas Baru Diinput (Draft)', 'Berkas No. BPN/2026/017 diinput di Loket (menunggu pengiriman ke seksi).', 'info', 'detail.php?id=69', '2026-09-26 08:55:22'),
(73, 68, 'seksi_2', NULL, 'Berkas Masuk Baru', 'Berkas No. BPN/2026/016 dikirim dari Loket ke Seksi 2 (Penetapan Hak & Pendaftaran).', 'masuk', 'detail.php?id=68', '2026-09-26 12:50:06'),
(74, 68, 'loket', NULL, 'Berkas Siap Diverifikasi', 'Berkas No. BPN/2026/016 telah selesai di Seksi 2 dan siap diverifikasi di Loket.', 'masuk', 'detail.php?id=68', '2026-09-26 12:50:56'),
(75, 68, 'seksi_2', NULL, 'Berkas Dikembalikan (Perlu Perbaikan)', 'Berkas No. BPN/2026/016 dikembalikan oleh Loket karena belum lengkap: sgsgd', 'kembali', 'detail.php?id=68', '2026-09-26 12:51:24'),
(76, 65, 'loket', NULL, 'Berkas Siap Diverifikasi', 'Berkas No. BPN/2026/013 telah selesai di Seksi 2 dan siap diverifikasi di Loket.', 'masuk', 'detail.php?id=65', '2026-09-27 13:52:42');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi_read`
--

CREATE TABLE `notifikasi_read` (
  `id` int(10) UNSIGNED NOT NULL,
  `notifikasi_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `notifikasi_read`
--

INSERT INTO `notifikasi_read` (`id`, `notifikasi_id`, `user_id`, `read_at`) VALUES
(1, 5, 4, '2026-09-22 08:47:46'),
(2, 3, 2, '2026-09-22 09:07:04'),
(5, 1, 1, '2026-09-22 09:09:24'),
(6, 32, 2, '2026-09-23 06:47:50');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'di-hash dengan password_hash() / bcrypt',
  `nama_lengkap` varchar(150) NOT NULL,
  `role` enum('admin','loket','seksi_1','seksi_2') NOT NULL,
  `sub_bagian` enum('Pendaftaran','Peralihan','Penetapan') DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`, `sub_bagian`, `is_active`, `created_at`) VALUES
(1, 'admin', '$2y$10$B2FFLaNM1aBMgHL.D/QB.OkH..yPjL/8rwjbfNYb9nCe/RbBucfSm', 'iksan', 'admin', NULL, 1, '2026-09-21 05:13:12'),
(2, 'loket1', '$2y$10$B2FFLaNM1aBMgHL.D/QB.OkH..yPjL/8rwjbfNYb9nCe/RbBucfSm', 'Rini', 'loket', NULL, 1, '2026-09-21 05:13:12'),
(4, 'seksi1', '$2y$10$B2FFLaNM1aBMgHL.D/QB.OkH..yPjL/8rwjbfNYb9nCe/RbBucfSm', 'lulu', 'seksi_1', NULL, 1, '2026-09-21 05:13:12'),
(5, 'seksi2_1', '$2y$10$B2FFLaNM1aBMgHL.D/QB.OkH..yPjL/8rwjbfNYb9nCe/RbBucfSm', 'vivi', 'seksi_2', 'Penetapan', 1, '2026-09-21 05:13:12'),
(6, 'seksi2_2', '$2y$10$86RTp8Zfozx0w.ll8lArSOlFcnOZyg2zz/0G4p4HmQdqtciLucRFu', 'raini', 'seksi_2', 'Peralihan', 1, '2026-09-22 01:56:40'),
(7, 'seksi2_3', '$2y$10$86RTp8Zfozx0w.ll8lArSOlFcnOZyg2zz/0G4p4HmQdqtciLucRFu', 'ulan', 'seksi_2', 'Pendaftaran', 1, '2026-09-22 01:56:40');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `berkas`
--
ALTER TABLE `berkas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_pendaftaran` (`nomor_pendaftaran`),
  ADD KEY `idx_status_posisi` (`status_posisi`),
  ADD KEY `idx_nomor_pendaftaran` (`nomor_pendaftaran`),
  ADD KEY `fk_berkas_diinput_oleh` (`diinput_oleh`),
  ADD KEY `fk_berkas_petugas_tujuan` (`petugas_tujuan_id`),
  ADD KEY `idx_deadline_at` (`deadline_at`);

--
-- Indeks untuk tabel `log_pergerakan`
--
ALTER TABLE `log_pergerakan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_berkas` (`id_berkas`),
  ADD KEY `fk_log_pengirim` (`pengirim_id`),
  ADD KEY `fk_log_penerima` (`penerima_id`);

--
-- Indeks untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_target` (`target_role`,`target_user_id`),
  ADD KEY `idx_notif_created` (`created_at`),
  ADD KEY `fk_notif_berkas` (`id_berkas`),
  ADD KEY `fk_notif_target_user` (`target_user_id`);

--
-- Indeks untuk tabel `notifikasi_read`
--
ALTER TABLE `notifikasi_read`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_notif_user` (`notifikasi_id`,`user_id`),
  ADD KEY `idx_read_user` (`user_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `berkas`
--
ALTER TABLE `berkas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT untuk tabel `log_pergerakan`
--
ALTER TABLE `log_pergerakan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

--
-- AUTO_INCREMENT untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT untuk tabel `notifikasi_read`
--
ALTER TABLE `notifikasi_read`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `berkas`
--
ALTER TABLE `berkas`
  ADD CONSTRAINT `fk_berkas_diinput_oleh` FOREIGN KEY (`diinput_oleh`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_berkas_petugas_tujuan` FOREIGN KEY (`petugas_tujuan_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `log_pergerakan`
--
ALTER TABLE `log_pergerakan`
  ADD CONSTRAINT `fk_log_berkas` FOREIGN KEY (`id_berkas`) REFERENCES `berkas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_log_penerima` FOREIGN KEY (`penerima_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_log_pengirim` FOREIGN KEY (`pengirim_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `fk_notif_berkas` FOREIGN KEY (`id_berkas`) REFERENCES `berkas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notif_target_user` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `notifikasi_read`
--
ALTER TABLE `notifikasi_read`
  ADD CONSTRAINT `fk_read_notifikasi` FOREIGN KEY (`notifikasi_id`) REFERENCES `notifikasi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_read_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
