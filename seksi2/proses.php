<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['seksi_2']);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM berkas WHERE id = ? AND status_posisi = 'seksi_2' LIMIT 1");
$stmt->execute([$id]);
$berkas = $stmt->fetch();

if (!$berkas) {
    setFlash('danger', 'Berkas tidak ditemukan atau sudah tidak berada di antrean Seksi 2 (Penetapan Hak & Pendaftaran).');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Sesi tidak valid, silakan coba lagi.');
        header('Location: proses.php?id=' . $id);
        exit;
    }

    $keputusan = $_POST['keputusan'] ?? '';
    $catatan   = trim($_POST['catatan'] ?? '');

    if (!in_array($keputusan, ['selesai', 'kembalikan_seksi1', 'kembalikan_loket'], true)) {
        setFlash('danger', 'Silakan pilih salah satu keputusan.');
        header('Location: proses.php?id=' . $id);
        exit;
    }
    if (in_array($keputusan, ['kembalikan_seksi1', 'kembalikan_loket'], true) && $catatan === '') {
        setFlash('danger', 'Catatan kekurangan wajib diisi saat mengembalikan berkas.');
        header('Location: proses.php?id=' . $id);
        exit;
    }

    $namaUser = $_SESSION['nama_lengkap'] ?? 'Petugas';
    $subBagian = !empty($_SESSION['sub_bagian']) ? ' - Bagian ' . $_SESSION['sub_bagian'] : '';
    $identitasPetugas = "$namaUser (Seksi 2 Penetapan Hak & Pendaftaran$subBagian)";

    try {
        $conn->beginTransaction();
        $statusSebelum = $berkas['status_posisi'];
        $deadlineAktif = !empty($berkas['deadline_at']) ? $berkas['deadline_at'] : date('Y-m-d H:i:s', strtotime('+2 days'));

        $sisaSlaUpdate = null;
        if ($keputusan === 'selesai') {
            $statusBaru = 'loket';
            $aksi = 'diteruskan';
            $catatanFinal = ($catatan !== '' ? $catatan . ' ' : '') . "[Selesai diproses oleh $identitasPetugas, diteruskan ke Loket untuk verifikasi & penyerahan ke pemohon]";
            $pesanSukses  = 'Berkas ' . e($berkas['nomor_pendaftaran']) . ' berhasil diselesaikan di Seksi 2 dan diteruskan ke Loket untuk verifikasi & penyerahan.';
            $isDiterimaLoket = 0;
            $finalDeadline = $deadlineAktif;
        } elseif ($keputusan === 'kembalikan_seksi1') {
            $statusBaru = 'ditolak_ke_seksi1';
            $aksi = 'dikembalikan';
            $catatanFinal = $catatan . " [Dikembalikan ke Seksi 1 (Survei & Pemetaan) oleh $identitasPetugas untuk perbaikan]";
            $pesanSukses  = 'Berkas ' . e($berkas['nomor_pendaftaran']) . ' berhasil dikembalikan ke Seksi 1 (Survei & Pemetaan) untuk perbaikan.';
            $isDiterimaLoket = 0;
            $finalDeadline = $deadlineAktif;
        } else { // kembalikan_loket
            $statusBaru = 'ditolak_ke_loket';
            $aksi = 'dikembalikan';
            $catatanFinal = $catatan . " [Dikembalikan langsung ke Loket oleh $identitasPetugas — berkas perlu dilengkapi melalui Loket]";
            $pesanSukses  = 'Berkas ' . e($berkas['nomor_pendaftaran']) . ' berhasil dikembalikan ke Loket untuk ditindaklanjuti.';
            $isDiterimaLoket = 0;
            $finalDeadline = $deadlineAktif;
            // Jeda (pause) timer SLA: simpan sisa detik sebelum dikembalikan ke loket
            $sisaSlaUpdate = !empty($deadlineAktif) ? (strtotime($deadlineAktif) - time()) : (48 * 3600);
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
            kirimNotifikasi($conn, $id, 'loket', 'Berkas Siap Diverifikasi', "Berkas No. " . $berkas['nomor_pendaftaran'] . " telah selesai di Seksi 2 dan siap diverifikasi di Loket.", 'masuk', "detail.php?id=$id");
        } elseif ($keputusan === 'kembalikan_seksi1') {
            kirimNotifikasi($conn, $id, 'seksi_1', 'Berkas Dikembalikan dari Seksi 2', "Berkas No. " . $berkas['nomor_pendaftaran'] . " dikembalikan oleh Seksi 2 untuk perbaikan: $catatan", 'kembali', "detail.php?id=$id");
        } else { // kembalikan_loket
            kirimNotifikasi($conn, $id, 'loket', 'Berkas Ditolak — Perlu Ditindaklanjuti', "Berkas No. " . $berkas['nomor_pendaftaran'] . " dikembalikan langsung ke Loket oleh Seksi 2: $catatan", 'kembali', "detail.php?id=$id");
        }

        $conn->commit();
        setFlash('success', $pesanSukses);
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log('Seksi2 proses error: ' . $e->getMessage());
        setFlash('danger', 'Gagal memproses berkas. Silakan coba lagi.');
        header('Location: proses.php?id=' . $id);
        exit;
    }
}

$pageTitle = 'Proses Berkas · Seksi 2 (Penetapan Hak & Pendaftaran)';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Proses Penyelesaian Berkas &mdash; Seksi 2 (Penetapan Hak &amp; Pendaftaran)</h1>
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
    </div>

    <div class="col-lg-7">
        <form action="proses.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int) $berkas['id'] ?>">

            <div class="row g-3 mb-3">
                <!-- Opsi 1: Selesai → Loket -->
                <div class="col-md-4">
                    <label class="action-choice h-100 is-checked" id="card-selesai" for="opt-selesai">
                        <input type="radio" name="keputusan" id="opt-selesai" value="selesai" class="action-choice-radio"
                               data-requires-note="1"
                               data-btn-label="<i class='bi bi-check-circle me-1'></i> Selesai &amp; Kembalikan ke Loket"
                               data-btn-class="btn-success" checked>
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-check-circle-fill fs-3 text-success flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="fw-bold text-success">Lengkap / Selesai</div>
                                <div class="small text-muted">Proses selesai, teruskan ke Loket untuk diserahkan ke pemohon</div>
                            </div>
                        </div>
                    </label>
                </div>

                <!-- Opsi 2: Tolak → Seksi 1 -->
                <div class="col-md-4">
                    <label class="action-choice h-100" id="card-kembalikan_seksi1" for="opt-kembalikan-seksi1">
                        <input type="radio" name="keputusan" id="opt-kembalikan-seksi1" value="kembalikan_seksi1" class="action-choice-radio"
                               data-requires-note="1"
                               data-btn-label="<i class='bi bi-arrow-90deg-left me-1'></i> Kembalikan ke Seksi 1 (Survei &amp; Pemetaan)"
                               data-btn-class="btn-warning">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-arrow-90deg-left fs-3 text-warning-emphasis flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="fw-bold text-warning-emphasis">Tolak &rarr; Seksi 1</div>
                                <div class="small text-muted">Ada kekurangan pengukuran/pemetaan, kembalikan ke Seksi 1</div>
                            </div>
                        </div>
                    </label>
                </div>

                <!-- Opsi 3: Tolak → Loket langsung -->
                <div class="col-md-4">
                    <label class="action-choice h-100" id="card-kembalikan_loket" for="opt-kembalikan-loket">
                        <input type="radio" name="keputusan" id="opt-kembalikan-loket" value="kembalikan_loket" class="action-choice-radio"
                               data-requires-note="1"
                               data-btn-label="<i class='bi bi-arrow-return-left me-1'></i> Kembalikan ke Loket"
                               data-btn-class="btn-danger">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-arrow-return-left fs-3 text-danger flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="fw-bold text-danger">Tolak &rarr; Loket</div>
                                <div class="small text-muted">Berkas langsung dikembalikan ke Loket (tanpa melalui Seksi 1)</div>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Panel keterangan aksi -->
            <div class="card mb-3 border-primary-subtle bg-light">
                <div class="card-body py-3">

                    <!-- Info: Selesai → Loket -->
                    <div id="wrapperPenerimaLoket">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <span class="fw-semibold text-success">Tujuan: Petugas Loket (Penyerahan ke Pemohon)</span>
                        </div>
                        <div class="small text-muted">
                            Berkas telah selesai diproses dan diteruskan ke Loket. Petugas Loket mana saja dapat mengonfirmasi penerimaan berkas dan menyerahkannya ke pemohon.
                        </div>
                    </div>

                    <!-- Info: Tolak → Seksi 1 -->
                    <div id="wrapperPenerimaSeksi1" style="display:none;">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-arrow-90deg-left text-warning-emphasis fs-5"></i>
                            <span class="fw-semibold text-warning-emphasis">Tujuan: Tim Seksi 1 (Survei &amp; Pemetaan &mdash; Perbaikan Berkas)</span>
                        </div>
                        <div class="small text-muted">
                            Ditemukan kekurangan pada data pengukuran fisik atau gambar ukur/peta. Berkas akan dikembalikan ke antrean <strong>Seksi 1 (Survei &amp; Pemetaan)</strong> untuk diperbaiki. Catatan kekurangan wajib diisi.
                        </div>
                    </div>

                    <!-- Info: Tolak → Loket langsung -->
                    <div id="wrapperPenerimaLoketTolak" style="display:none;">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-arrow-return-left text-danger fs-5"></i>
                            <span class="fw-semibold text-danger">Tujuan: Petugas Loket (Tindak Lanjut Penolakan)</span>
                        </div>
                        <div class="small text-muted">
                            Berkas dikembalikan <strong>langsung ke Loket</strong> tanpa melalui Seksi 1. Digunakan ketika berkas pradaftar masuk ke Seksi 2 langsung melalui Loket dan kekurangan dokumen bersifat administratif. Petugas Loket akan menindaklanjuti (mengembalikan ke pemohon atau meneruskan kembali ke Seksi 2 setelah diperbaiki).
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3" id="catatanWrapper">
                <label class="form-label fw-semibold" id="catatanLabel">Catatan Penyelesaian <span class="text-muted fw-normal">(Opsional)</span></label>
                <textarea name="catatan" id="catatanInput" class="form-control" rows="3"
                          placeholder="Tuliskan catatan penyelesaian berkas jika ada..."></textarea>
                <div class="form-text" id="catatanHint">Opsional — tambahkan catatan penyelesaian berkas untuk Loket jika diperlukan.</div>
            </div>

            <button type="submit" class="btn w-100 btn-success py-2 fw-semibold" id="btnSubmitProses">
                <i class="bi bi-check-circle me-1"></i> Selesai &amp; Kembalikan ke Loket
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const radioSelesai         = document.getElementById('opt-selesai');
    const radioKembalikanS1    = document.getElementById('opt-kembalikan-seksi1');
    const radioKembalikanLoket = document.getElementById('opt-kembalikan-loket');

    const cardSelesai         = document.getElementById('card-selesai');
    const cardKembalikanS1    = document.getElementById('card-kembalikan_seksi1');
    const cardKembalikanLoket = document.getElementById('card-kembalikan_loket');

    const wrapperLoket      = document.getElementById('wrapperPenerimaLoket');
    const wrapperSeksi1     = document.getElementById('wrapperPenerimaSeksi1');
    const wrapperLoketTolak = document.getElementById('wrapperPenerimaLoketTolak');

    const btnSubmit      = document.getElementById('btnSubmitProses');
    const catatanInput   = document.getElementById('catatanInput');
    const catatanHint    = document.getElementById('catatanHint');
    const catatanLabel   = document.getElementById('catatanLabel');

    function updateView() {
        // Reset semua card
        [cardSelesai, cardKembalikanS1, cardKembalikanLoket].forEach(c => {
            if (c) c.classList.remove('is-checked');
        });

        if (radioSelesai && radioSelesai.checked) {
            if (cardSelesai) cardSelesai.classList.add('is-checked');
            wrapperLoket.style.display      = 'block';
            wrapperSeksi1.style.display     = 'none';
            wrapperLoketTolak.style.display = 'none';

            btnSubmit.className = 'btn w-100 btn-success py-2 fw-semibold';
            btnSubmit.innerHTML = '<i class="bi bi-check-circle me-1"></i> Selesai &amp; Serahkan ke Loket';

            catatanInput.required    = false;
            catatanLabel.innerHTML   = 'Catatan Penyelesaian <span class="text-muted fw-normal">(Opsional)</span>';
            catatanInput.placeholder = 'Tuliskan catatan penyelesaian berkas jika ada...';
            catatanHint.textContent  = 'Opsional — tambahkan catatan penyelesaian berkas untuk Loket jika diperlukan.';

        } else if (radioKembalikanS1 && radioKembalikanS1.checked) {
            if (cardKembalikanS1) cardKembalikanS1.classList.add('is-checked');
            wrapperLoket.style.display      = 'none';
            wrapperSeksi1.style.display     = 'block';
            wrapperLoketTolak.style.display = 'none';

            btnSubmit.className = 'btn w-100 btn-warning text-dark py-2 fw-semibold';
            btnSubmit.innerHTML = '<i class="bi bi-arrow-90deg-left me-1"></i> Kembalikan ke Seksi 1 (Survei &amp; Pemetaan)';

            catatanInput.required    = true;
            catatanLabel.innerHTML   = 'Catatan Kekurangan <span class="text-danger">*</span>';
            catatanInput.placeholder = 'Jelaskan kekurangan atau hal yang perlu diperbaiki oleh Seksi 1 (Survei & Pemetaan)...';
            catatanHint.textContent  = 'Wajib diisi — jelaskan rincian kekurangan berkas untuk Seksi 1 (Survei & Pemetaan).';

        } else if (radioKembalikanLoket && radioKembalikanLoket.checked) {
            if (cardKembalikanLoket) cardKembalikanLoket.classList.add('is-checked');
            wrapperLoket.style.display      = 'none';
            wrapperSeksi1.style.display     = 'none';
            wrapperLoketTolak.style.display = 'block';

            btnSubmit.className = 'btn w-100 btn-danger py-2 fw-semibold';
            btnSubmit.innerHTML = '<i class="bi bi-arrow-return-left me-1"></i> Kembalikan ke Loket';

            catatanInput.required    = true;
            catatanLabel.innerHTML   = 'Catatan Kekurangan <span class="text-danger">*</span>';
            catatanInput.placeholder = 'Jelaskan kekurangan dokumen yang perlu dilengkapi pemohon melalui Loket...';
            catatanHint.textContent  = 'Wajib diisi — Loket akan menindaklanjuti berkas ini (dikembalikan ke pemohon atau diteruskan kembali ke Seksi 2).';
        }
    }

    [radioSelesai, radioKembalikanS1, radioKembalikanLoket].forEach(r => {
        if (r) r.addEventListener('change', updateView);
    });
    updateView();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
