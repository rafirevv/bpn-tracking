<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['seksi_1']);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT b.*, (
        SELECT l.catatan FROM log_pergerakan l
        WHERE l.id_berkas = b.id ORDER BY l.id DESC LIMIT 1
    ) AS catatan_terakhir, (
        SELECT l.aksi FROM log_pergerakan l
        WHERE l.id_berkas = b.id ORDER BY l.id DESC LIMIT 1
    ) AS aksi_terakhir, (
        SELECT l.status_sebelum FROM log_pergerakan l
        WHERE l.id_berkas = b.id AND l.aksi = 'dikembalikan' ORDER BY l.id DESC LIMIT 1
    ) AS asal_penolakan, (
        SELECT us.nama_lengkap FROM log_pergerakan l
        LEFT JOIN users us ON us.id = l.pengirim_id
        WHERE l.id_berkas = b.id ORDER BY l.id DESC LIMIT 1
    ) AS pengirim_terakhir
    FROM berkas b 
    WHERE b.id = ? AND b.status_posisi IN ('seksi_1','ditolak_ke_seksi1') 
    LIMIT 1
");
$stmt->execute([$id]);
$berkas = $stmt->fetch();

if (!$berkas) {
    setFlash('danger', 'Berkas tidak ditemukan atau sudah tidak berada di antrean Seksi 1 (Survei & Pemetaan).');
    header('Location: index.php');
    exit;
}

/* ---------------- Proses submit ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Sesi tidak valid, silakan coba lagi.');
        header('Location: proses.php?id=' . $id);
        exit;
    }

    $keputusan = $_POST['keputusan'] ?? '';
    $catatan   = trim($_POST['catatan'] ?? '');

    if (!in_array($keputusan, ['teruskan', 'teruskan_loket', 'kembalikan'], true)) {
        setFlash('danger', 'Silakan pilih salah satu keputusan.');
        header('Location: proses.php?id=' . $id);
        exit;
    }
    if ($keputusan === 'kembalikan' && $catatan === '') {
        setFlash('danger', 'Catatan kekurangan wajib diisi saat mengembalikan berkas.');
        header('Location: proses.php?id=' . $id);
        exit;
    }

    $petugasNama = $_SESSION['nama_lengkap'] ?? 'Petugas Seksi 1 (Survei & Pemetaan)';

    try {
        $conn->beginTransaction();
        $statusSebelum = $berkas['status_posisi'];
        $deadlineAktif = !empty($berkas['deadline_at']) ? $berkas['deadline_at'] : date('Y-m-d H:i:s', strtotime('+2 days'));

        $sisaSlaUpdate = null;
        if ($keputusan === 'teruskan') {
            $statusBaru = 'seksi_2';
            $aksi = 'diteruskan';
            $finalDeadline = $deadlineAktif;
            $isDiterimaLoket = 0;
            $catatanFinal = ($catatan !== '' ? $catatan . ' ' : '') . "[Diteruskan ke Seksi 2 (Penetapan Hak & Pendaftaran) oleh $petugasNama]";
            $pesanSukses  = "Berkas " . e($berkas['nomor_pendaftaran']) . " dinyatakan LENGKAP dan diteruskan ke Seksi 2 (Penetapan Hak & Pendaftaran).";
        } elseif ($keputusan === 'teruskan_loket') {
            $statusBaru = 'loket';
            $aksi = 'diteruskan';
            $finalDeadline = $deadlineAktif;
            $isDiterimaLoket = 0;
            $catatanFinal = ($catatan !== '' ? $catatan . ' ' : '') . "[Diteruskan ke Loket oleh $petugasNama untuk verifikasi & penyerahan]";
            $pesanSukses  = "Berkas " . e($berkas['nomor_pendaftaran']) . " berhasil diteruskan ke Loket untuk verifikasi & penyerahan.";
        } else {
            $statusBaru = 'ditolak_ke_loket';
            $aksi = 'dikembalikan';
            $finalDeadline = $deadlineAktif;
            // Jeda (pause) timer SLA: simpan sisa detik sebelum dikembalikan ke loket
            $sisaSlaUpdate = !empty($deadlineAktif) ? (strtotime($deadlineAktif) - time()) : (48 * 3600);
            $isDiterimaLoket = 0;
            $catatanFinal = $catatan . " [Dikembalikan ke Loket oleh $petugasNama karena berkas belum lengkap]";
            $pesanSukses  = "Berkas " . e($berkas['nomor_pendaftaran']) . " berhasil dikembalikan ke Loket untuk dilengkapi pemohon.";
        }

        $upd = $conn->prepare("
            UPDATE berkas 
            SET status_posisi = ?, petugas_tujuan_id = NULL, deadline_at = ?, sisa_sla_detik = ?, is_diterima_loket = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $upd->execute([$statusBaru, $finalDeadline, $sisaSlaUpdate, $isDiterimaLoket, $id]);

        $log = $conn->prepare("
            INSERT INTO log_pergerakan (id_berkas, pengirim_id, penerima_id, aksi, status_sebelum, status_sesudah, catatan, created_at)
            VALUES (?, ?, NULL, ?, ?, ?, ?, NOW())
        ");
        $log->execute([$id, $_SESSION['user_id'], $aksi, $statusSebelum, $statusBaru, $catatanFinal]);

        // Kirim notifikasi in-app
        if ($keputusan === 'teruskan') {
            kirimNotifikasi($conn, $id, 'seksi_2', 'Berkas Masuk dari Seksi 1', "Berkas No. " . $berkas['nomor_pendaftaran'] . " telah selesai di Seksi 1 dan diteruskan ke Seksi 2.", 'masuk', "detail.php?id=$id");
        } elseif ($keputusan === 'teruskan_loket') {
            kirimNotifikasi($conn, $id, 'loket', 'Berkas Siap Diverifikasi', "Berkas No. " . $berkas['nomor_pendaftaran'] . " telah selesai di Seksi 1 dan diteruskan ke Loket.", 'masuk', "detail.php?id=$id");
        } else {
            kirimNotifikasi($conn, $id, 'loket', 'Berkas Perlu Perbaikan', "Berkas No. " . $berkas['nomor_pendaftaran'] . " dikembalikan oleh Seksi 1 ke Loket: $catatan", 'kembali', "detail.php?id=$id");
        }

        $conn->commit();
        setFlash('success', $pesanSukses);
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log('Seksi1 proses error: ' . $e->getMessage());
        setFlash('danger', 'Gagal memproses berkas. Silakan coba lagi.');
        header('Location: proses.php?id=' . $id);
        exit;
    }
}

$pageTitle = 'Proses Berkas · Seksi 1 (Survei & Pemetaan)';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Proses Berkas &mdash; Seksi 1 (Survei &amp; Pemetaan)</h1>
        <p>No. Berkas <span class="mono fw-semibold"><?= e($berkas['nomor_pendaftaran']) ?></span></p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Data Berkas</div>
            <div class="card-body">
                <div class="mb-3"><div class="text-muted small">Nomor Berkas</div><div class="mono fw-semibold"><?= e($berkas['nomor_pendaftaran']) ?></div></div>
                <div class="mb-3"><div class="text-muted small">Nama Pemohon</div><div class="fw-semibold"><?= e($berkas['nama_pemohon']) ?></div></div>
                <div class="mb-3"><div class="text-muted small">Jenis Layanan</div><div><?= e($berkas['jenis_layanan']) ?></div></div>
                <div class="mb-3"><div class="text-muted small">Sertifikat / Desa</div><div class="fw-semibold text-primary"><?= e($berkas['sertifikat_desa'] ?: ($berkas['deskripsi_berkas'] ?: '-')) ?></div></div>
                <?php if (!empty($berkas['deskripsi_berkas']) && $berkas['deskripsi_berkas'] !== $berkas['sertifikat_desa']): ?>
                <div class="mb-3"><div class="text-muted small">Kelengkapan / Catatan</div><div style="white-space:pre-line;"><?= e($berkas['deskripsi_berkas']) ?></div></div>
                <?php endif; ?>
                <div class="mb-3"><div class="text-muted small">Status Saat Ini</div><div class="mt-1"><?= statusBadge($berkas['status_posisi']) ?></div></div>
                <div class="mb-0">
                    <div class="text-muted small">Batas Waktu</div>
                    <div class="mt-1"><?= slaBadge($berkas['deadline_at'], $berkas['status_posisi'], false, $berkas['sisa_sla_detik'] ?? null) ?></div>
                </div>
            </div>
        </div>

        <?php if ($berkas['status_posisi'] === 'ditolak_ke_seksi1' || $berkas['aksi_terakhir'] === 'dikembalikan'): ?>
            <?php 
                $asalLabel = ($berkas['asal_penolakan'] === 'loket') ? 'Loket' : 'Seksi 2 (Penetapan Hak & Pendaftaran)';
            ?>
            <div class="card border-danger-subtle mt-3">
                <div class="card-header bg-danger-subtle text-danger-emphasis fw-semibold">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Catatan Pengembalian dari <?= e($asalLabel) ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($berkas['pengirim_terakhir'])): ?>
                    <div class="mb-2">
                        <div class="text-muted small">Dikembalikan oleh</div>
                        <div class="fw-semibold"><?= e($berkas['pengirim_terakhir']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div>
                        <div class="text-muted small mb-1">Catatan Kekurangan</div>
                        <?php if (!empty($berkas['catatan_terakhir'])): ?>
                            <div class="p-2 bg-light rounded small border text-danger-emphasis" style="white-space:pre-line;"><?= e($berkas['catatan_terakhir']) ?></div>
                        <?php else: ?>
                            <div class="text-muted small fst-italic">Tidak ada catatan penolakan.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-7">
        <form action="proses.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int) $berkas['id'] ?>">

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="action-choice h-100" id="card-teruskan" for="opt-teruskan">
                        <input type="radio" name="keputusan" id="opt-teruskan" value="teruskan" class="action-choice-radio"
                               data-requires-note="0" data-btn-label="<i class='bi bi-send-check me-1'></i> Teruskan ke Seksi 2 (Penetapan Hak &amp; Pendaftaran)" data-btn-class="btn-primary" checked>
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-send-check fs-3 text-primary flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="fw-bold">Teruskan ke Seksi 2</div>
                                <div class="small text-muted">Penetapan Hak &amp; Pendaftaran</div>
                            </div>
                        </div>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="action-choice h-100" id="card-teruskan_loket" for="opt-teruskan-loket">
                        <input type="radio" name="keputusan" id="opt-teruskan-loket" value="teruskan_loket" class="action-choice-radio"
                               data-requires-note="0" data-btn-label="<i class='bi bi-inbox-fill me-1'></i> Teruskan ke Loket" data-btn-class="btn-success">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-inbox-fill fs-3 text-success flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="fw-bold text-success">Teruskan ke Loket</div>
                                <div class="small text-muted">Kirim ke Loket untuk verifikasi &amp; penyerahan</div>
                            </div>
                        </div>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="action-choice h-100" id="card-kembalikan" for="opt-kembalikan">
                        <input type="radio" name="keputusan" id="opt-kembalikan" value="kembalikan" class="action-choice-radio"
                               data-requires-note="1" data-btn-label="<i class='bi bi-arrow-return-left me-1'></i> Kembalikan ke Loket" data-btn-class="btn-danger">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-x-circle-fill fs-3 text-danger flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="fw-bold">Tidak Lengkap</div>
                                <div class="small text-muted">Kembalikan ke Loket (Catatan wajib)</div>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Keterangan Unit Tujuan Pengiriman -->
            <div class="card mb-3 border-primary-subtle bg-light">
                <div class="card-body py-3">
                    <div id="wrapperPenerimaSeksi2">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-send-check text-primary fs-5"></i>
                            <span class="fw-semibold text-primary">Tujuan: Tim Seksi 2 (Penetapan Hak &amp; Pendaftaran)</span>
                        </div>
                        <div class="small text-muted">
                            Berkas lengkap akan masuk ke antrean Seksi 2 (Penetapan Hak &amp; Pendaftaran) dan dapat diproses oleh seluruh staf Seksi 2 tanpa dibatasi ke user tertentu.
                        </div>
                    </div>

                    <div id="wrapperTeruskanLoket" style="display:none;">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-inbox-fill text-success fs-5"></i>
                            <span class="fw-semibold text-success">Tujuan: Loket (Verifikasi &amp; Penyerahan)</span>
                        </div>
                        <div class="small text-muted">
                            Pekerjaan Survei &amp; Pemetaan telah selesai diproses di Seksi 1. Berkas diteruskan ke Loket agar petugas Loket dapat memeriksa kelengkapan akhir sebelum diserahkan ke pemohon atau ditindaklanjuti.
                        </div>
                    </div>

                    <div id="wrapperPenerimaLoket" style="display:none;">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-arrow-return-left text-danger fs-5"></i>
                            <span class="fw-semibold text-danger">Tujuan: Loket (Perbaikan Berkas)</span>
                        </div>
                        <div class="small text-muted">
                            Berkas belum lengkap dan akan dikembalikan ke Loket. Petugas Loket akan mengonfirmasi penerimaan dan menghubungi pemohon agar melengkapi kekurangan.
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3" id="catatanWrapper">
                <label class="form-label fw-semibold" id="catatanLabel">Catatan</label>
                <textarea name="catatan" id="catatanInput" class="form-control" rows="3" placeholder="Tuliskan catatan penyelesaian atau kekurangan berkas..."></textarea>
                <div class="form-text" id="catatanHint">Opsional — tambahkan catatan jika diperlukan.</div>
            </div>

            <button type="submit" class="btn w-100 btn-primary" id="btnSubmitProses">
                <i class="bi bi-send-check me-1"></i> Teruskan ke Seksi 2 (Penetapan Hak &amp; Pendaftaran)
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const radioTeruskan = document.getElementById('opt-teruskan');
    const radioTeruskanLoket = document.getElementById('opt-teruskan-loket');
    const radioKembalikan = document.getElementById('opt-kembalikan');
    const wrapperSeksi2 = document.getElementById('wrapperPenerimaSeksi2');
    const wrapperTeruskanLoket = document.getElementById('wrapperTeruskanLoket');
    const wrapperLoket = document.getElementById('wrapperPenerimaLoket');
    const btnSubmit = document.getElementById('btnSubmitProses');
    const catatanInput = document.getElementById('catatanInput');
    const catatanHint = document.getElementById('catatanHint');
    const catatanLabel = document.getElementById('catatanLabel');

    function updateView() {
        if (radioTeruskan && radioTeruskan.checked) {
            if (wrapperSeksi2) wrapperSeksi2.style.display = 'block';
            if (wrapperTeruskanLoket) wrapperTeruskanLoket.style.display = 'none';
            if (wrapperLoket) wrapperLoket.style.display = 'none';
            btnSubmit.className = 'btn w-100 btn-primary';
            btnSubmit.innerHTML = '<i class="bi bi-send-check me-1"></i> Teruskan ke Seksi 2 (Penetapan Hak &amp; Pendaftaran)';
            catatanInput.required = false;
            catatanLabel.innerHTML = 'Catatan <span class="text-muted fw-normal">(Opsional)</span>';
            catatanHint.textContent = 'Opsional — tambahkan catatan pemeriksaan berkas jika diperlukan.';
        } else if (radioTeruskanLoket && radioTeruskanLoket.checked) {
            if (wrapperSeksi2) wrapperSeksi2.style.display = 'none';
            if (wrapperTeruskanLoket) wrapperTeruskanLoket.style.display = 'block';
            if (wrapperLoket) wrapperLoket.style.display = 'none';
            btnSubmit.className = 'btn w-100 btn-success';
            btnSubmit.innerHTML = '<i class="bi bi-inbox-fill me-1"></i> Teruskan ke Loket';
            catatanInput.required = false;
            catatanLabel.innerHTML = 'Catatan <span class="text-muted fw-normal">(Opsional)</span>';
            catatanHint.textContent = 'Opsional — tambahkan catatan hasil survei atau informasi untuk petugas loket.';
        } else {
            if (wrapperSeksi2) wrapperSeksi2.style.display = 'none';
            if (wrapperTeruskanLoket) wrapperTeruskanLoket.style.display = 'none';
            if (wrapperLoket) wrapperLoket.style.display = 'block';
            btnSubmit.className = 'btn w-100 btn-danger';
            btnSubmit.innerHTML = '<i class="bi bi-arrow-return-left me-1"></i> Kembalikan ke Loket';
            catatanInput.required = true;
            catatanLabel.innerHTML = 'Catatan Kekurangan <span class="text-danger">*</span>';
            catatanHint.textContent = 'Wajib diisi — jelaskan rincian kekurangan atau dokumen yang belum lengkap.';
        }
    }

    [radioTeruskan, radioTeruskanLoket, radioKembalikan].forEach(r => {
        if (r) r.addEventListener('change', updateView);
    });
    updateView();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
