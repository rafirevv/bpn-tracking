<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['loket']);

// Ambil parameter IDs berkas
$rawIds = [];
if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
    $rawIds = $_POST['ids'];
} elseif (!empty($_GET['ids']) && is_array($_GET['ids'])) {
    $rawIds = $_GET['ids'];
} elseif (!empty($_POST['id'])) {
    $rawIds = [$_POST['id']];
} elseif (!empty($_GET['id'])) {
    $rawIds = [$_GET['id']];
}

$ids = array_filter(array_map('intval', $rawIds), fn($id) => $id > 0);

if (empty($ids)) {
    setFlash('warning', 'Pilih berkas yang akan dikirim ke Seksi.');
    header('Location: index.php');
    exit;
}

// Ambil berkas dari database yang masih berstatus 'loket'
$inPlaceholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $conn->prepare("
    SELECT b.*, u.nama_lengkap AS nama_penginput 
    FROM berkas b 
    LEFT JOIN users u ON u.id = b.diinput_oleh 
    WHERE b.id IN ($inPlaceholders) AND b.status_posisi = 'loket'
    ORDER BY b.id ASC
");
$stmt->execute($ids);
$berkasList = $stmt->fetchAll();

if (empty($berkasList)) {
    setFlash('warning', 'Berkas yang dipilih tidak ditemukan atau sudah tidak berada di antrean Simpan Loket.');
    header('Location: index.php');
    exit;
}

$isSingle = (count($berkasList) === 1);
$singleBerkas = $isSingle ? $berkasList[0] : null;

// ================= PROSES SUBMIT PENGIRIMAN (POST) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_submit'])) {
    if (!verifyCsrf()) {
        setFlash('danger', 'Sesi tidak valid atau telah kedaluwarsa. Silakan coba lagi.');
        $redirectUrl = $isSingle ? ('kirim.php?id=' . $singleBerkas['id']) : 'index.php';
        header('Location: ' . $redirectUrl);
        exit;
    }

    $tujuan = $_POST['tujuan'] ?? 'seksi_1';
    if (!in_array($tujuan, ['seksi_1', 'seksi_2'], true)) {
        $tujuan = 'seksi_1';
    }

    $catatanTambahan = trim($_POST['catatan'] ?? '');
    $namaTujuan = ($tujuan === 'seksi_2') 
        ? 'Seksi 2 (Penetapan Hak & Pendaftaran)' 
        : 'Seksi 1 (Survei & Pemetaan)';
    $petugasNama = $_SESSION['nama_lengkap'] ?? 'Petugas Loket';
    $deadlineAt  = date('Y-m-d H:i:s', strtotime('+2 days'));

    try {
        $conn->beginTransaction();

        $updateStmt = $conn->prepare("
            UPDATE berkas 
            SET status_posisi = ?, petugas_tujuan_id = NULL, deadline_at = COALESCE(deadline_at, ?), updated_at = NOW() 
            WHERE id = ?
        ");

        $logStmt = $conn->prepare("
            INSERT INTO log_pergerakan (id_berkas, pengirim_id, penerima_id, aksi, status_sebelum, status_sesudah, catatan, created_at)
            VALUES (?, ?, NULL, 'diteruskan', 'loket', ?, ?, NOW())
        ");

        $logText = "Berkas dikonfirmasi dan dikirim oleh Petugas Loket ($petugasNama) ke $namaTujuan.";
        if ($catatanTambahan !== '') {
            $logText .= " Catatan Loket: " . $catatanTambahan;
        }

        foreach ($berkasList as $b) {
            $updateStmt->execute([$tujuan, $deadlineAt, (int) $b['id']]);
            $logStmt->execute([(int) $b['id'], $_SESSION['user_id'], $tujuan, $logText]);

            // Kirim notifikasi in-app
            $pesanNotif = "Berkas No. " . $b['nomor_pendaftaran'] . " dikirim dari Loket ke " . $namaTujuan . ".";
            if ($catatanTambahan !== '') {
                $pesanNotif .= " Catatan: " . $catatanTambahan;
            }
            kirimNotifikasi($conn, (int) $b['id'], $tujuan, 'Berkas Masuk Baru', $pesanNotif, 'masuk', "detail.php?id=" . (int) $b['id']);
        }

        $conn->commit();

        if ($isSingle) {
            setFlash('success', "Berkas " . e($singleBerkas['nomor_pendaftaran']) . " berhasil dikirim ke $namaTujuan. Batas waktu pengerjaan: 2 hari kerja.");
        } else {
            setFlash('success', "Sebanyak " . count($berkasList) . " berkas berhasil dikirim ke $namaTujuan. Batas waktu pengerjaan: 2 hari kerja.");
        }

        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log('Kirim berkas error: ' . $e->getMessage());
        setFlash('danger', 'Gagal memproses pengiriman berkas. Silakan coba lagi.');
        $redirectUrl = $isSingle ? ('kirim.php?id=' . $singleBerkas['id']) : 'index.php';
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// ================= TAMPILAN HALAMAN (GET) =================
$pageTitle = 'Kirim Berkas ke Seksi · Loket';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Kirim Berkas ke Seksi</h1>
        <p>Pilih seksi tujuan pemrosesan untuk berkas yang tersimpan di Loket.</p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke Antrean
    </a>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Informasi Berkas -->
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold bg-light">
                <i class="bi bi-file-earmark-text me-1 text-primary"></i>
                <?= $isSingle ? 'Informasi Berkas' : 'Daftar Berkas Terpilih (' . count($berkasList) . ' Berkas)' ?>
            </div>
            <div class="card-body">
                <?php if ($isSingle): ?>
                    <div class="mb-3">
                        <div class="text-muted small">Nomor Berkas</div>
                        <div class="mono fw-bold fs-5 text-primary"><?= e($singleBerkas['nomor_pendaftaran']) ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Nama Pemohon</div>
                        <div class="fw-semibold fs-6"><?= e($singleBerkas['nama_pemohon']) ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Jenis Layanan</div>
                        <div><?= e($singleBerkas['jenis_layanan']) ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Sertifikat / Desa</div>
                        <div class="fw-semibold text-primary"><?= e($singleBerkas['sertifikat_desa'] ?: ($singleBerkas['deskripsi_berkas'] ?: '-')) ?></div>
                    </div>
                    <?php if (!empty($singleBerkas['foto_bidang'])): 
                        $fotoUrl = fotoBidangUrl($singleBerkas['foto_bidang']);
                    ?>
                    <div class="mb-3">
                        <div class="text-muted small mb-1 d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-image text-primary me-1"></i>Foto Bidang Tanah</span>
                            <a href="<?= e($fotoUrl) ?>" target="_blank" class="small text-muted text-decoration-none d-inline-flex align-items-center gap-1" title="Buka gambar ukuran penuh">
                                <span>Buka Penuh</span>
                                <i class="bi bi-arrow-up-right" style="font-size: 0.72rem;"></i>
                            </a>
                        </div>
                        <div class="position-relative border rounded overflow-hidden bg-light shadow-sm text-center" style="max-height: 160px;">
                            <a href="<?= e($fotoUrl) ?>" target="_blank">
                                <img src="<?= e($fotoUrl) ?>" alt="Foto Bidang" class="img-fluid w-100 object-fit-cover" style="max-height: 160px;">
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <div class="text-muted small">Waktu Input</div>
                        <div class="small"><?= formatTanggal($singleBerkas['created_at']) ?></div>
                    </div>
                    <div class="mb-0">
                        <div class="text-muted small">Status Posisi</div>
                        <div class="mt-1"><span class="badge text-bg-secondary"><i class="bi bi-inbox me-1"></i>Simpan di Loket (Draft)</span></div>
                    </div>
                <?php else: ?>
                    <div class="mb-3 text-muted small">
                        Daftar berkas yang dipilih untuk dikirim bersamaan:
                    </div>
                    <div class="list-group list-group-flush border rounded overflow-auto" style="max-height: 380px;">
                        <?php foreach ($berkasList as $idx => $b): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="mono fw-bold text-primary"><?= e($b['nomor_pendaftaran']) ?></span>
                                    <span class="badge bg-light text-dark border">#<?= $idx + 1 ?></span>
                                </div>
                                <div class="fw-semibold small"><?= e($b['nama_pemohon']) ?></div>
                                <div class="text-muted small"><?= e($b['jenis_layanan']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Pilihan Seksi & Formulir Pengiriman -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold bg-light">
                <i class="bi bi-send me-1 text-primary"></i> Formulir Pengiriman ke Seksi
            </div>
            <div class="card-body">
                <form action="kirim.php" method="POST" id="formKirimSeksi">
                    <?= csrfField() ?>
                    <input type="hidden" name="action_submit" value="1">
                    <?php foreach ($berkasList as $b): ?>
                        <input type="hidden" name="ids[]" value="<?= (int) $b['id'] ?>">
                    <?php endforeach; ?>

                    <label class="form-label fw-bold small text-uppercase text-muted mb-2">Pilih Seksi Tujuan</label>
                    <div class="row g-3 mb-3">
                        <!-- Opsi 1: Seksi 1 -->
                        <div class="col-md-6">
                            <label class="action-choice is-checked h-100 p-3" id="card-seksi1" for="opt-seksi1" style="cursor: pointer;">
                                <input type="radio" name="tujuan" id="opt-seksi1" value="seksi_1" class="action-choice-radio" checked>
                                <div class="d-flex align-items-center gap-3">
                                    <i class="bi bi-file-earmark-check fs-2 text-primary flex-shrink-0"></i>
                                    <div>
                                        <div class="fw-bold text-primary fs-6">Seksi 1 (Survei &amp; Pemetaan)</div>
                                        <span class="badge text-bg-primary mt-1">2 Hari Kerja</span>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <!-- Opsi 2: Seksi 2 -->
                        <div class="col-md-6">
                            <label class="action-choice h-100 p-3" id="card-seksi2" for="opt-seksi2" style="cursor: pointer;">
                                <input type="radio" name="tujuan" id="opt-seksi2" value="seksi_2" class="action-choice-radio">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="bi bi-file-earmark-check fs-2 text-primary flex-shrink-0"></i>
                                    <div>
                                        <div class="fw-bold text-primary fs-6">Seksi 2 (Penetapan Hak &amp; Pendaftaran)</div>
                                        <span class="badge text-bg-primary mt-1">2 Hari Kerja</span>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Alert Penjelasan Dinamis -->
                    <div class="alert alert-info d-flex align-items-start gap-2 mb-3" id="infoBox">
                        <i class="bi bi-info-circle-fill fs-5 text-primary flex-shrink-0 mt-0"></i>
                        <div class="small" id="infoText">
                            Berkas akan diteruskan ke antrean <strong>Seksi 1 (Survei &amp; Pemetaan)</strong>. Batas waktu pengerjaan (maksimal <strong>2 hari kerja</strong>) akan otomatis mulai dihitung.
                        </div>
                    </div>

                    <!-- Catatan Pengiriman (Opsional) -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold small" for="catatan">Catatan Pengiriman (Opsional)</label>
                        <textarea name="catatan" id="catatan" class="form-control" rows="3" placeholder="Tulis instruksi khusus atau catatan tambahan untuk petugas seksi penerima..."></textarea>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg py-2 fw-bold" id="btnSubmitKirim">
                            <i class="bi bi-send-fill me-2"></i>Kirim Berkas ke Seksi 1 (Survei &amp; Pemetaan)
                        </button>
                        <a href="index.php" class="btn btn-link text-muted text-decoration-none text-center small mt-1">
                            Batal dan Kembali ke Antrean
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const optSeksi1 = document.getElementById('opt-seksi1');
    const optSeksi2 = document.getElementById('opt-seksi2');
    const cardSeksi1 = document.getElementById('card-seksi1');
    const cardSeksi2 = document.getElementById('card-seksi2');
    const infoText = document.getElementById('infoText');
    const btnSubmit = document.getElementById('btnSubmitKirim');

    function updateSelection() {
        if (optSeksi1 && optSeksi1.checked) {
            cardSeksi1.classList.add('is-checked');
            cardSeksi2.classList.remove('is-checked');
            infoText.innerHTML = 'Berkas akan diteruskan ke antrean <strong>Seksi 1 (Survei &amp; Pemetaan)</strong>. Batas waktu pengerjaan (maksimal <strong>2 hari kerja</strong>) akan otomatis mulai dihitung.';
            btnSubmit.innerHTML = '<i class="bi bi-send-fill me-2"></i>Kirim Berkas ke Seksi 1 (Survei &amp; Pemetaan)';
        } else if (optSeksi2 && optSeksi2.checked) {
            cardSeksi2.classList.add('is-checked');
            cardSeksi1.classList.remove('is-checked');
            infoText.innerHTML = 'Berkas akan diteruskan ke antrean <strong>Seksi 2 (Penetapan Hak &amp; Pendaftaran)</strong>. Batas waktu pengerjaan (maksimal <strong>2 hari kerja</strong>) akan otomatis mulai dihitung.';
            btnSubmit.innerHTML = '<i class="bi bi-send-fill me-2"></i>Kirim Berkas ke Seksi 2 (Penetapan Hak &amp; Pendaftaran)';
        }
    }

    if (optSeksi1 && optSeksi2) {
        optSeksi1.addEventListener('change', updateSelection);
        optSeksi2.addEventListener('change', updateSelection);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
