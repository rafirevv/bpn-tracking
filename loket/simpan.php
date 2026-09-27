<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['loket']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    setFlash('danger', 'Permintaan tidak valid.');
    header('Location: tambah.php');
    exit;
}

$nomor      = trim($_POST['nomor_berkas'] ?? '');
$nama       = trim($_POST['nama_pemohon'] ?? '');
$jenis      = trim($_POST['jenis_layanan'] ?? '');
$sertifikat = trim($_POST['sertifikat_desa'] ?? '');
$aksiSimpan = $_POST['aksi_simpan'] ?? '';
$tujuan     = $_POST['tujuan_distribusi'] ?? '';

// Tentukan target tujuan berkas: 'seksi_1', 'seksi_2', atau 'loket'
if ($aksiSimpan === 'draft') {
    $target = 'loket';
} elseif ($tujuan === 'seksi_2' || $aksiSimpan === 'seksi_2' || $aksiSimpan === 'kirim_seksi2') {
    $target = 'seksi_2';
} elseif ($tujuan === 'loket') {
    $target = 'loket';
} else {
    $target = 'seksi_1';
}

if ($nomor === '') {
    $nomor = generateNomorPendaftaran($conn);
}

if ($nama === '' || $jenis === '' || $sertifikat === '') {
    setFlash('danger', 'Nomor berkas, nama pemohon, jenis layanan, dan sertifikat/desa wajib diisi.');
    header('Location: tambah.php');
    exit;
}

// Cek apakah nomor berkas sudah pernah digunakan
$checkStmt = $conn->prepare("SELECT id FROM berkas WHERE nomor_pendaftaran = ? LIMIT 1");
$checkStmt->execute([$nomor]);
if ($checkStmt->fetch()) {
    setFlash('danger', "Nomor berkas \"$nomor\" sudah terdaftar dalam sistem. Harap gunakan nomor berkas yang berbeda.");
    header('Location: tambah.php');
    exit;
}

try {
    $conn->beginTransaction();

    $petugasNama = $_SESSION['nama_lengkap'] ?? 'Petugas Loket';

    if ($target === 'seksi_1') {
        $statusPosisi = 'seksi_1';
        $deadlineAt   = date('Y-m-d H:i:s', strtotime('+2 days'));

        $stmt = $conn->prepare("
            INSERT INTO berkas (nomor_pendaftaran, nama_pemohon, jenis_layanan, sertifikat_desa, deskripsi_berkas, status_posisi, diinput_oleh, petugas_tujuan_id, deadline_at, is_diterima_loket, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, 0, NOW(), NOW())
        ");
        $stmt->execute([$nomor, $nama, $jenis, $sertifikat, $sertifikat, $statusPosisi, $_SESSION['user_id'], $deadlineAt]);
        $idBerkas = (int) $conn->lastInsertId();

        $logInput = $conn->prepare("
            INSERT INTO log_pergerakan (id_berkas, pengirim_id, penerima_id, aksi, status_sebelum, status_sesudah, catatan, created_at)
            VALUES (?, ?, NULL, 'diinput', NULL, 'loket', ?, NOW())
        ");
        $logInput->execute([$idBerkas, $_SESSION['user_id'], "Berkas didaftarkan di loket oleh $petugasNama."]);

        $logKirim = $conn->prepare("
            INSERT INTO log_pergerakan (id_berkas, pengirim_id, penerima_id, aksi, status_sebelum, status_sesudah, catatan, created_at)
            VALUES (?, ?, NULL, 'diteruskan', 'loket', 'seksi_1', ?, NOW())
        ");
        $logKirim->execute([$idBerkas, $_SESSION['user_id'], "Berkas dikonfirmasi dan langsung dikirim ke Seksi 1 (Survei & Pemetaan) untuk pemeriksaan dan pengukuran."]);

        // Kirim notifikasi in-app
        kirimNotifikasi($conn, $idBerkas, 'seksi_1', 'Berkas Masuk Baru', "Berkas No. $nomor dari Loket siap diproses pengukuran & pemetaan.", 'masuk', "detail.php?id=$idBerkas");
        kirimNotifikasi($conn, $idBerkas, 'admin', 'Berkas Baru Diinput', "Berkas No. $nomor telah diinput di Loket dan diteruskan ke Seksi 1.", 'info', "detail.php?id=$idBerkas");

        $conn->commit();
        setFlash('success', "Berkas berhasil disimpan dengan nomor $nomor dan diteruskan ke Seksi 1 (Survei & Pemetaan). Batas waktu pengerjaan: 2 hari kerja.");

    } elseif ($target === 'seksi_2') {
        $statusPosisi = 'seksi_2';
        $deadlineAt   = date('Y-m-d H:i:s', strtotime('+2 days'));

        $stmt = $conn->prepare("
            INSERT INTO berkas (nomor_pendaftaran, nama_pemohon, jenis_layanan, sertifikat_desa, deskripsi_berkas, status_posisi, diinput_oleh, petugas_tujuan_id, deadline_at, is_diterima_loket, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, 0, NOW(), NOW())
        ");
        $stmt->execute([$nomor, $nama, $jenis, $sertifikat, $sertifikat, $statusPosisi, $_SESSION['user_id'], $deadlineAt]);
        $idBerkas = (int) $conn->lastInsertId();

        $logInput = $conn->prepare("
            INSERT INTO log_pergerakan (id_berkas, pengirim_id, penerima_id, aksi, status_sebelum, status_sesudah, catatan, created_at)
            VALUES (?, ?, NULL, 'diinput', NULL, 'loket', ?, NOW())
        ");
        $logInput->execute([$idBerkas, $_SESSION['user_id'], "Berkas didaftarkan di loket oleh $petugasNama."]);

        $logKirim = $conn->prepare("
            INSERT INTO log_pergerakan (id_berkas, pengirim_id, penerima_id, aksi, status_sebelum, status_sesudah, catatan, created_at)
            VALUES (?, ?, NULL, 'diteruskan', 'loket', 'seksi_2', ?, NOW())
        ");
        $logKirim->execute([$idBerkas, $_SESSION['user_id'], "Berkas dikonfirmasi dan langsung dikirim ke Seksi 2 (Penetapan Hak & Pendaftaran) untuk pemrosesan yuridis/pendaftaran."]);

        // Kirim notifikasi in-app
        kirimNotifikasi($conn, $idBerkas, 'seksi_2', 'Berkas Masuk Baru', "Berkas No. $nomor dari Loket siap diproses penetapan hak & pendaftaran.", 'masuk', "detail.php?id=$idBerkas");
        kirimNotifikasi($conn, $idBerkas, 'admin', 'Berkas Baru Diinput', "Berkas No. $nomor telah diinput di Loket dan diteruskan ke Seksi 2.", 'info', "detail.php?id=$idBerkas");

        $conn->commit();
        setFlash('success', "Berkas berhasil disimpan dengan nomor $nomor dan diteruskan ke Seksi 2 (Penetapan Hak & Pendaftaran). Batas waktu pengerjaan: 2 hari kerja.");

    } else {
        // Simpan sebagai draft di Loket (belum dikirim)
        $statusPosisi = 'loket';
        $deadlineAt   = null;

        $stmt = $conn->prepare("
            INSERT INTO berkas (nomor_pendaftaran, nama_pemohon, jenis_layanan, sertifikat_desa, deskripsi_berkas, status_posisi, diinput_oleh, petugas_tujuan_id, deadline_at, is_diterima_loket, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, 0, NOW(), NOW())
        ");
        $stmt->execute([$nomor, $nama, $jenis, $sertifikat, $sertifikat, $statusPosisi, $_SESSION['user_id'], $deadlineAt]);
        $idBerkas = (int) $conn->lastInsertId();

        $logInput = $conn->prepare("
            INSERT INTO log_pergerakan (id_berkas, pengirim_id, penerima_id, aksi, status_sebelum, status_sesudah, catatan, created_at)
            VALUES (?, ?, NULL, 'diinput', NULL, 'loket', ?, NOW())
        ");
        $logInput->execute([$idBerkas, $_SESSION['user_id'], "Berkas didaftarkan di loket oleh $petugasNama (menunggu konfirmasi pengiriman ke seksi)."]);

        // Kirim notifikasi in-app untuk admin
        kirimNotifikasi($conn, $idBerkas, 'admin', 'Berkas Baru Diinput (Draft)', "Berkas No. $nomor diinput di Loket (menunggu pengiriman ke seksi).", 'info', "detail.php?id=$idBerkas");

        $conn->commit();
        setFlash('success', "Berkas $nomor berhasil disimpan di Loket. Anda dapat mengonfirmasi pengiriman ke Seksi 1 atau Seksi 2 kapan saja melalui tabel antrean Loket.");
    }

    header('Location: index.php');
    exit;

} catch (Throwable $e) {
    $conn->rollBack();
    error_log('Simpan berkas error: ' . $e->getMessage());
    setFlash('danger', 'Gagal menyimpan berkas. Silakan coba lagi: ' . $e->getMessage());
    header('Location: tambah.php');
    exit;
}
