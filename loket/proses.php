<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['loket']);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT b.*, u.nama_lengkap AS pemegang_nama, (
        SELECT l.catatan FROM log_pergerakan l
        WHERE l.id_berkas = b.id ORDER BY l.id DESC LIMIT 1
    ) AS catatan_terakhir, (
        SELECT l.aksi FROM log_pergerakan l
        WHERE l.id_berkas = b.id ORDER BY l.id DESC LIMIT 1
    ) AS aksi_terakhir, (
        SELECT us.nama_lengkap FROM log_pergerakan l
        LEFT JOIN users us ON us.id = l.pengirim_id
        WHERE l.id_berkas = b.id ORDER BY l.id DESC LIMIT 1
    ) AS pengirim_terakhir
    FROM berkas b 
    LEFT JOIN users u ON u.id = b.petugas_tujuan_id 
    WHERE b.id = ? AND b.status_posisi = 'loket' 
    LIMIT 1
");
$stmt->execute([$id]);
$berkas = $stmt->fetch();

if (!$berkas) {
    setFlash('danger', 'Berkas tidak ditemukan atau sudah tidak berada di antrean Loket.');
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

    if (!in_array($keputusan, ['selesai', 'kembalikan_seksi1', 'kembalikan_seksi2'], true)) {
        setFlash('danger', 'Silakan pilih salah satu keputusan.');
        header('Location: proses.php?id=' . $id);
        exit;
    }

    if (in_array($keputusan, ['kembalikan_seksi1', 'kembalikan_seksi2'], true) && $catatan === '') {
        setFlash('danger', 'Catatan kekurangan wajib diisi saat mengembalikan berkas ke Seksi.');
        header('Location: proses.php?id=' . $id);
        exit;
    }

    $petugasNama = $_SESSION['nama_lengkap'] ?? 'Petugas Loket';

    try {
        $conn->beginTransaction();
        $statusSebelum = $berkas['status_posisi'];

        $deadlineAktif = !empty($berkas['deadline_at']) ? $berkas['deadline_at'] : date('Y-m-d H:i:s', strtotime('+2 days'));
        $sisaSlaUpdate = null;

        if ($keputusan === 'selesai') {
            $statusBaru = 'selesai';
            $aksi = 'selesai';
            $finalDeadline = null; // Waktu otomatis berhenti
            $sisaSlaUpdate = null;
            $isDiterimaLoket = 1;
            $catatanFinal = ($catatan !== '' ? $catatan . ' ' : '') . "[Berkas telah diverifikasi dan diserahkan kepada pemohon oleh $petugasNama. Status berkas dinyatakan SELESAI]";
            $pesanSukses  = "Berkas " . e($berkas['nomor_pendaftaran']) . " berhasil diverifikasi dan diserahkan kepada pemohon. Status berkas dinyatakan SELESAI.";
        } elseif ($keputusan === 'kembalikan_seksi1') {
            $statusBaru = 'ditolak_ke_seksi1';
            $aksi = 'dikembalikan';
            if (isset($berkas['sisa_sla_detik']) && $berkas['sisa_sla_detik'] !== null) {
                $finalDeadline = date('Y-m-d H:i:s', time() + (int) $berkas['sisa_sla_detik']);
            } else {
                $finalDeadline = $deadlineAktif;
            }
            $sisaSlaUpdate = null;
            $isDiterimaLoket = 0;
            $catatanFinal = $catatan . " [Dikembalikan ke Seksi 1 (Survei & Pemetaan) oleh $petugasNama karena berkas belum lengkap. SLA melanjutkan waktu tersisa]";
            $pesanSukses  = "Berkas " . e($berkas['nomor_pendaftaran']) . " dikembalikan ke Seksi 1 (Survei & Pemetaan) untuk perbaikan.";
        } else { // kembalikan_seksi2
            $statusBaru = 'seksi_2';
            $aksi = 'dikembalikan';
            if (isset($berkas['sisa_sla_detik']) && $berkas['sisa_sla_detik'] !== null) {
                $finalDeadline = date('Y-m-d H:i:s', time() + (int) $berkas['sisa_sla_detik']);
            } else {
                $finalDeadline = $deadlineAktif;
            }
            $sisaSlaUpdate = null;
            $isDiterimaLoket = 0;
            $catatanFinal = $catatan . " [Dikembalikan ke Seksi 2 (Penetapan Hak & Pendaftaran) oleh $petugasNama karena berkas belum lengkap. SLA melanjutkan waktu tersisa]";
            $pesanSukses  = "Berkas " . e($berkas['nomor_pendaftaran']) . " dikembalikan ke Seksi 2 (Penetapan Hak & Pendaftaran) untuk perbaikan.";
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
        if ($keputusan === 'selesai') {
            kirimNotifikasi($conn, $id, 'admin', 'Berkas Selesai Diserahkan', "Berkas No. " . $berkas['nomor_pendaftaran'] . " telah diserahkan kepada pemohon oleh $petugasNama (Status: Selesai).", 'selesai', "detail.php?id=$id");
            kirimNotifikasi($conn, $id, 'loket', 'Berkas Selesai Diserahkan', "Berkas No. " . $berkas['nomor_pendaftaran'] . " sukses diserahkan kepada pemohon.", 'selesai', "detail.php?id=$id");
        } elseif ($keputusan === 'kembalikan_seksi1') {
            kirimNotifikasi($conn, $id, 'seksi_1', 'Berkas Dikembalikan (Perlu Perbaikan)', "Berkas No. " . $berkas['nomor_pendaftaran'] . " dikembalikan oleh Loket karena belum lengkap: $catatan", 'kembali', "detail.php?id=$id");
        } else { // kembalikan_seksi2
            kirimNotifikasi($conn, $id, 'seksi_2', 'Berkas Dikembalikan (Perlu Perbaikan)', "Berkas No. " . $berkas['nomor_pendaftaran'] . " dikembalikan oleh Loket karena belum lengkap: $catatan", 'kembali', "detail.php?id=$id");
        }

        $conn->commit();
        setFlash('success', $pesanSukses);
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log('Loket proses error: ' . $e->getMessage());
        setFlash('danger', 'Gagal memproses berkas. Silakan coba lagi.');
        header('Location: proses.php?id=' . $id);
        exit;
    }
}

$pageTitle = 'Verifikasi & Proses Berkas · Loket';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Verifikasi &amp; Proses Berkas &mdash; Loket</h1>
        <p>No. Berkas <span class="mono fw-semibold"><?= e($berkas['nomor_pendaftaran']) ?></span></p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Data Berkas</div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="text-muted small">Nomor Berkas</div>
                    <div class="mono fw-semibold"><?= e($berkas['nomor_pendaftaran']) ?></div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Nama Pemohon</div>
                    <div class="fw-semibold"><?= e($berkas['nama_pemohon']) ?></div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Jenis Layanan</div>
                    <div><?= e($berkas['jenis_layanan']) ?></div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Sertifikat / Desa</div>
                    <div class="fw-semibold text-primary"><?= e($berkas['sertifikat_desa'] ?: ($berkas['deskripsi_berkas'] ?: '-')) ?></div>
                </div>
                <?php if (!empty($berkas['foto_bidang'])): 
                    $fotoUrl = fotoBidangUrl($berkas['foto_bidang']);
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
                <?php if (!empty($berkas['catatan_terakhir'])): ?>
                <div class="mb-3">
                    <div class="text-muted small">Catatan Pengirim Terakhir (<?= e($berkas['pengirim_terakhir'] ?? '-') ?>)</div>
                    <div class="p-2 bg-light rounded small border" style="white-space:pre-line;"><?= e($berkas['catatan_terakhir']) ?></div>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <div class="text-muted small">Status Saat Ini</div>
                    <div class="mt-1"><?= statusBadge($berkas['status_posisi']) ?></div>
                </div>
                <div class="mb-0">
                    <div class="text-muted small">Batas Waktu di Loket</div>
                    <div class="mt-1"><?= slaBadge($berkas['deadline_at'], $berkas['status_posisi'], false, $berkas['sisa_sla_detik'] ?? null) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <form action="proses.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int) $berkas['id'] ?>">

            <div class="row g-3 mb-3">
                <!-- Opsi 1: Serahkan ke Pemohon (Selesai) -->
                <div class="col-md-4">
                    <label class="action-choice h-100 is-checked" id="card-selesai" for="opt-selesai">
                        <input type="radio" name="keputusan" id="opt-selesai" value="selesai" class="action-choice-radio"
                               data-requires-note="0" data-btn-label="<i class='bi bi-check2-circle me-1'></i> Konfirmasi Selesai &amp; Serahkan ke Pemohon" data-btn-class="btn-success" checked>
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-check-circle-fill fs-3 text-success flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="fw-bold text-success">Serahkan ke Pemohon</div>
                                <div class="small text-muted">Berkas lengkap &amp; selesai</div>
                            </div>
                        </div>
                    </label>
                </div>

                <!-- Opsi 2: Belum Lengkap -> Seksi 1 -->
                <div class="col-md-4">
                    <label class="action-choice h-100" id="card-kembalikan_seksi1" for="opt-kembalikan-seksi1">
                        <input type="radio" name="keputusan" id="opt-kembalikan-seksi1" value="kembalikan_seksi1" class="action-choice-radio"
                               data-requires-note="1" data-btn-label="<i class='bi bi-arrow-return-left me-1'></i> Kembalikan ke Seksi 1 (Survei &amp; Pemetaan)" data-btn-class="btn-warning">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-arrow-return-left fs-3 text-warning-emphasis flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="fw-bold text-warning-emphasis">Belum Lengkap &rarr; Seksi 1</div>
                                <div class="small text-muted">Survei &amp; Pemetaan</div>
                            </div>
                        </div>
                    </label>
                </div>

                <!-- Opsi 3: Belum Lengkap -> Seksi 2 -->
                <div class="col-md-4">
                    <label class="action-choice h-100" id="card-kembalikan_seksi2" for="opt-kembalikan-seksi2">
                        <input type="radio" name="keputusan" id="opt-kembalikan-seksi2" value="kembalikan_seksi2" class="action-choice-radio"
                               data-requires-note="1" data-btn-label="<i class='bi bi-arrow-return-left me-1'></i> Kembalikan ke Seksi 2 (Penetapan Hak &amp; Pendaftaran)" data-btn-class="btn-danger">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-arrow-return-left fs-3 text-danger flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="fw-bold text-danger">Belum Lengkap &rarr; Seksi 2</div>
                                <div class="small text-muted">Penetapan Hak &amp; Pendaftaran</div>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Keterangan Unit Tujuan Pengiriman -->
            <div class="card mb-3 border-primary-subtle bg-light">
                <div class="card-body py-3">
                    <div id="wrapperSelesai">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <span class="fw-semibold text-success">Keputusan: Berkas Selesai &mdash; Diserahkan ke Pemohon</span>
                        </div>
                        <div class="small text-muted">
                            Berkas telah diverifikasi dan dipastikan <strong>benar-benar lengkap dan selesai</strong>. Dokumen siap diserahkan kepada pemohon di Loket. Status berkas otomatis menjadi <strong>Selesai</strong>.
                        </div>
                    </div>

                    <div id="wrapperKembalikanSeksi1" style="display:none;">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-arrow-return-left text-warning-emphasis fs-5"></i>
                            <span class="fw-semibold text-warning-emphasis">Tujuan: Kembalikan ke Seksi 1 (Survei &amp; Pemetaan)</span>
                        </div>
                        <div class="small text-muted">
                            Ditemukan kekurangan pada pengukuran fisik / gambar ukur / pemetaan. Berkas akan dikembalikan ke antrean <strong>Seksi 1 (Survei &amp; Pemetaan)</strong> untuk diperbaiki atau dilengkapi.
                        </div>
                    </div>

                    <div id="wrapperKembalikanSeksi2" style="display:none;">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-arrow-return-left text-danger fs-5"></i>
                            <span class="fw-semibold text-danger">Tujuan: Kembalikan ke Seksi 2 (Penetapan Hak &amp; Pendaftaran)</span>
                        </div>
                        <div class="small text-muted">
                            Ditemukan kekurangan pada dokumen yuridis, penetapan hak, atau administrasi pendaftaran. Berkas akan dikembalikan ke antrean <strong>Seksi 2 (Penetapan Hak &amp; Pendaftaran)</strong> untuk diperbaiki atau dilengkapi.
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3" id="catatanWrapper">
                <label class="form-label fw-semibold" id="catatanLabel">Catatan <span class="text-muted fw-normal">(Opsional)</span></label>
                <textarea name="catatan" id="catatanInput" class="form-control" rows="3" placeholder="Tuliskan keterangan penyerahan atau rincian kekurangan berkas..."></textarea>
                <div class="form-text" id="catatanHint">Opsional — tambahkan catatan penyerahan berkas kepada pemohon.</div>
            </div>

            <button type="submit" class="btn w-100 btn-success py-2" id="btnSubmitProses">
                <i class="bi bi-check2-circle me-1"></i> Konfirmasi Selesai &amp; Serahkan ke Pemohon
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const radioSelesai = document.getElementById('opt-selesai');
    const radioKembalikanSeksi1 = document.getElementById('opt-kembalikan-seksi1');
    const radioKembalikanSeksi2 = document.getElementById('opt-kembalikan-seksi2');

    const cardSelesai = document.getElementById('card-selesai');
    const cardSeksi1 = document.getElementById('card-kembalikan_seksi1');
    const cardSeksi2 = document.getElementById('card-kembalikan_seksi2');

    const wrapperSelesai = document.getElementById('wrapperSelesai');
    const wrapperKembalikanSeksi1 = document.getElementById('wrapperKembalikanSeksi1');
    const wrapperKembalikanSeksi2 = document.getElementById('wrapperKembalikanSeksi2');

    const btnSubmit = document.getElementById('btnSubmitProses');
    const catatanInput = document.getElementById('catatanInput');
    const catatanHint = document.getElementById('catatanHint');
    const catatanLabel = document.getElementById('catatanLabel');

    function updateView() {
        [cardSelesai, cardSeksi1, cardSeksi2].forEach(c => {
            if (c) c.classList.remove('is-checked', 'border-primary', 'bg-light');
        });

        if (radioSelesai && radioSelesai.checked) {
            if (cardSelesai) cardSelesai.classList.add('is-checked');
            if (wrapperSelesai) wrapperSelesai.style.display = 'block';
            if (wrapperKembalikanSeksi1) wrapperKembalikanSeksi1.style.display = 'none';
            if (wrapperKembalikanSeksi2) wrapperKembalikanSeksi2.style.display = 'none';

            btnSubmit.className = 'btn w-100 btn-success py-2';
            btnSubmit.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Konfirmasi Selesai &amp; Serahkan ke Pemohon';

            catatanInput.required = false;
            catatanLabel.innerHTML = 'Catatan Penyerahan <span class="text-muted fw-normal">(Opsional)</span>';
            catatanHint.textContent = 'Opsional — nomor tanda terima atau catatan penyerahan kepada pemohon.';
        } else if (radioKembalikanSeksi1 && radioKembalikanSeksi1.checked) {
            if (cardSeksi1) cardSeksi1.classList.add('is-checked');
            if (wrapperSelesai) wrapperSelesai.style.display = 'none';
            if (wrapperKembalikanSeksi1) wrapperKembalikanSeksi1.style.display = 'block';
            if (wrapperKembalikanSeksi2) wrapperKembalikanSeksi2.style.display = 'none';

            btnSubmit.className = 'btn w-100 btn-warning text-dark py-2 fw-semibold';
            btnSubmit.innerHTML = '<i class="bi bi-arrow-return-left me-1"></i> Kembalikan ke Seksi 1 (Survei &amp; Pemetaan)';

            catatanInput.required = true;
            catatanLabel.innerHTML = 'Catatan Kekurangan <span class="text-danger">*</span>';
            catatanHint.textContent = 'Wajib diisi — jelaskan rincian kekurangan pengukuran fisik/dokumen yang perlu dilengkapi Seksi 1.';
        } else {
            if (cardSeksi2) cardSeksi2.classList.add('is-checked');
            if (wrapperSelesai) wrapperSelesai.style.display = 'none';
            if (wrapperKembalikanSeksi1) wrapperKembalikanSeksi1.style.display = 'none';
            if (wrapperKembalikanSeksi2) wrapperKembalikanSeksi2.style.display = 'block';

            btnSubmit.className = 'btn w-100 btn-danger py-2 fw-semibold';
            btnSubmit.innerHTML = '<i class="bi bi-arrow-return-left me-1"></i> Kembalikan ke Seksi 2 (Penetapan Hak &amp; Pendaftaran)';

            catatanInput.required = true;
            catatanLabel.innerHTML = 'Catatan Kekurangan <span class="text-danger">*</span>';
            catatanHint.textContent = 'Wajib diisi — jelaskan rincian kekurangan yuridis/administrasi yang perlu dilengkapi Seksi 2.';
        }
    }

    [radioSelesai, radioKembalikanSeksi1, radioKembalikanSeksi2].forEach(r => {
        if (r) r.addEventListener('change', updateView);
    });
    updateView();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
