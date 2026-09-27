<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['loket']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    setFlash('danger', 'Permintaan tidak valid.');
    header('Location: index.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM berkas WHERE id = ? AND status_posisi IN ('ditolak_ke_loket','selesai') AND is_diterima_loket = 0");
$stmt->execute([$id]);
$berkas = $stmt->fetch();

if (!$berkas) {
    setFlash('danger', 'Berkas tidak ditemukan atau sudah dikonfirmasi sebelumnya.');
    header('Location: index.php');
    exit;
}

try {
    $conn->beginTransaction();

    $upd = $conn->prepare("UPDATE berkas SET is_diterima_loket = 1, updated_at = NOW() WHERE id = ?");
    $upd->execute([$id]);

    $log = $conn->prepare("
        INSERT INTO log_pergerakan (id_berkas, pengirim_id, penerima_id, aksi, status_sebelum, status_sesudah, catatan, created_at)
        VALUES (?, ?, NULL, 'diterima', ?, ?, 'Berkas telah diterima kembali oleh Loket dan siap diserahkan/diinformasikan ke pemohon.', NOW())
    ");
    $log->execute([$id, $_SESSION['user_id'], $berkas['status_posisi'], $berkas['status_posisi']]);

    // Kirim notifikasi in-app
    kirimNotifikasi($conn, $id, 'admin', 'Berkas Diterima di Loket', "Berkas No. " . $berkas['nomor_pendaftaran'] . " telah dikonfirmasi diterima kembali di Loket.", 'info', "detail.php?id=$id");

    $conn->commit();
    setFlash('success', 'Berkas ' . $berkas['nomor_pendaftaran'] . ' telah dikonfirmasi diterima.');
} catch (Throwable $e) {
    $conn->rollBack();
    error_log('Terima berkas error: ' . $e->getMessage());
    setFlash('danger', 'Gagal memproses konfirmasi penerimaan.');
}

header('Location: index.php');
exit;
