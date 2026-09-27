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

$stmt = $conn->prepare("SELECT * FROM berkas WHERE id = ? AND status_posisi = 'loket' LIMIT 1");
$stmt->execute([$id]);
$berkas = $stmt->fetch();

if (!$berkas) {
    setFlash('danger', 'Berkas tidak ditemukan atau sudah dikirim ke seksi (tidak dapat dihapus).');
    header('Location: index.php');
    exit;
}

try {
    $conn->beginTransaction();

    // Hapus log pergerakan terkait
    $delLog = $conn->prepare("DELETE FROM log_pergerakan WHERE id_berkas = ?");
    $delLog->execute([$id]);

    // Hapus data berkas
    $delBerkas = $conn->prepare("DELETE FROM berkas WHERE id = ?");
    $delBerkas->execute([$id]);

    $conn->commit();
    setFlash('success', 'Berkas ' . e($berkas['nomor_pendaftaran']) . ' berhasil dihapus dari antrean Loket.');

} catch (Throwable $e) {
    $conn->rollBack();
    error_log('Hapus berkas loket error: ' . $e->getMessage());
    setFlash('danger', 'Gagal menghapus berkas.');
}

header('Location: index.php');
exit;
