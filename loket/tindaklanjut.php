<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['loket']);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

// Ambil berkas yang berstatus 'ditolak_ke_loket' dan belum ditindaklanjuti
$stmt = $conn->prepare("
    SELECT b.*, (
        SELECT l.catatan FROM log_pergerakan l
        WHERE l.id_berkas = b.id ORDER BY l.id DESC LIMIT 1
    ) AS catatan_terakhir, (
        SELECT l.aksi FROM log_pergerakan l
        WHERE l.id_berkas = b.id ORDER BY l.id DESC LIMIT 1
    ) AS aksi_terakhir, (
        SELECT us.nama_lengkap FROM log_pergerakan l
        LEFT JOIN users us ON us.id = l.pengirim_id
        WHERE l.id_berkas = b.id ORDER BY l.id DESC LIMIT 1
    ) AS pengirim_terakhir, (
        SELECT l.status_sebelum FROM log_pergerakan l
        WHERE l.id_berkas = b.id AND l.aksi = 'dikembalikan'
        ORDER BY l.id DESC LIMIT 1
    ) AS asal_seksi_penolak
    FROM berkas b
    WHERE b.id = ? AND b.status_posisi = 'ditolak_ke_loket'
    LIMIT 1
");
$stmt->execute([$id]);
$berkas = $stmt->fetch();

if (!$berkas) {
    setFlash('danger', 'Berkas tidak ditemukan atau tidak dalam status yang dapat ditindaklanjuti.');
    header('Location: index.php');
    exit;
}

// Tentukan seksi asal penolakan (untuk menampilkan pilihan "Teruskan ke Seksi X")
$asalSeksi = $berkas['asal_seksi_penolak'] ?? '';
// Jika asal_seksi_penolak tidak ditemukan, coba tebak dari catatan terakhir
if (empty($asalSeksi)) {
    // Coba ambil dari log terakhir yang berstatus 'seksi_1' atau 'seksi_2'
    $stmtAsal = $conn->prepare("
        SELECT status_sebelum FROM log_pergerakan
        WHERE id_berkas = ? AND status_sebelum IN ('seksi_1', 'seksi_2')
        ORDER BY id DESC LIMIT 1
    ");
    $stmtAsal->execute([$id]);
    $asalSeksi = $stmtAsal->fetchColumn() ?: 'seksi_1';
}

$labelSeksiAsal = ($asalSeksi === 'seksi_2')
    ? 'Seksi 2 (Penetapan Hak & Pendaftaran)'
    : 'Seksi 1 (Survei & Pemetaan)';

$statusSeksiAsal = ($asalSeksi === 'seksi_2') ? 'seksi_2' : 'seksi_1';

/* ---------------- Proses submit ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('danger', 'Sesi tidak valid, silakan coba lagi.');
        header('Location: tindaklanjut.php?id=' . $id);
        exit;
    }

    $keputusan = $_POST['keputusan'] ?? '';
    $catatan   = trim($_POST['catatan'] ?? '');

    if (!in_array($keputusan, ['kembalikan_pemohon', 'teruskan_seksi'], true)) {
        setFlash('danger', 'Silakan pilih salah satu tindakan.');
        header('Location: tindaklanjut.php?id=' . $id);
        exit;
    }

    if ($keputusan === 'kembalikan_pemohon' && $catatan === '') {
        setFlash('danger', 'Catatan pengembalian ke pemohon wajib diisi.');
        header('Location: tindaklanjut.php?id=' . $id);
        exit;
    }

    if ($keputusan === 'teruskan_seksi' && $catatan === '') {
        setFlash('danger', 'Catatan perbaikan wajib diisi saat meneruskan berkas kembali ke Seksi.');
        header('Location: tindaklanjut.php?id=' . $id);
        exit;
    }

    $petugasNama = $_SESSION['nama_lengkap'] ?? 'Petugas Loket';

    try {
        $conn->beginTransaction();
        $statusSebelum = $berkas['status_posisi']; // 'ditolak_ke_loket'

        $deadlineAktif = !empty($berkas['deadline_at']) ? $berkas['deadline_at'] : date('Y-m-d H:i:s', strtotime('+2 days'));
        $sisaSlaUpdate = null;

        if ($keputusan === 'kembalikan_pemohon') {
            // Berkas dikembalikan ke pemohon: status tetap 'ditolak_ke_loket',
            // is_diterima_loket = 1 (menandai sudah dikonfirmasi / diselesaikan di loket)
            $statusBaru      = 'ditolak_ke_loket';
            $aksi            = 'dikembalikan';
            $finalDeadline   = $deadlineAktif;
            // Tetap simpan sisa detik SLA yang dijeda
            $sisaSlaUpdate   = isset($berkas['sisa_sla_detik']) ? (int) $berkas['sisa_sla_detik'] : (!empty($deadlineAktif) ? (strtotime($deadlineAktif) - time()) : (48 * 3600));
            $isDiterimaLoket = 1;
            $catatanFinal    = ($catatan !== '' ? $catatan . ' ' : '')
                . "[Berkas dikembalikan kepada pemohon oleh $petugasNama karena berkas perlu dilengkapi/diperbaiki oleh pemohon]";
            $pesanSukses     = 'Berkas ' . e($berkas['nomor_pendaftaran'])
                . ' berhasil dikembalikan kepada pemohon untuk dilengkapi.';
        } else {
            // Teruskan kembali ke seksi yang menolak (setelah berkas diperbaiki)
            $statusBaru      = $statusSeksiAsal; // 'seksi_1' atau 'seksi_2'
            $aksi            = 'diteruskan';

            // SLA BERJALAN KEMBALI: hitung deadline baru berdasarkan sisa SLA saat berkas dikembalikan ke loket (timer dijeda).
            // Jangan reset ke 2 hari! Gunakan sisa_sla_detik sebelum dikembalikan ke loket.
            $sisaDetik       = isset($berkas['sisa_sla_detik'])
                ? (int) $berkas['sisa_sla_detik']
                : (!empty($berkas['deadline_at']) ? (strtotime($berkas['deadline_at']) - strtotime($berkas['updated_at'])) : (48 * 3600));

            // Set deadline baru: NOW() + sisa waktu pengerjaan
            $finalDeadline   = date('Y-m-d H:i:s', time() + $sisaDetik);
            $sisaSlaUpdate   = null; // Timer aktif kembali di Seksi, kolom jeda direset ke NULL
            $isDiterimaLoket = 0;
            $catatanFinal    = ($catatan !== '' ? $catatan . ' ' : '')
                . "[Berkas diteruskan kembali ke $labelSeksiAsal oleh $petugasNama setelah berkas diperbaiki/dilengkapi. SLA melanjutkan sisa waktu sebelumnya]";
            $pesanSukses     = 'Berkas ' . e($berkas['nomor_pendaftaran'])
                . " berhasil diteruskan kembali ke $labelSeksiAsal. SLA berjalan kembali melanjutkan sisa waktu sebelumnya.";
        }

        // Update berkas
        $upd = $conn->prepare("
            UPDATE berkas
            SET status_posisi = ?, petugas_tujuan_id = NULL, deadline_at = ?, sisa_sla_detik = ?, is_diterima_loket = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([$statusBaru, $finalDeadline, $sisaSlaUpdate, $isDiterimaLoket, $id]);

        // Catat log pergerakan
        $log = $conn->prepare("
            INSERT INTO log_pergerakan (id_berkas, pengirim_id, penerima_id, aksi, status_sebelum, status_sesudah, catatan, created_at)
            VALUES (?, ?, NULL, ?, ?, ?, ?, NOW())
        ");
        $log->execute([$id, $_SESSION['user_id'], $aksi, $statusSebelum, $statusBaru, $catatanFinal]);

        // Kirim notifikasi in-app
        if ($keputusan === 'kembalikan_pemohon') {
            kirimNotifikasi($conn, $id, 'admin', 'Berkas Dikembalikan ke Pemohon',
                "Berkas No. " . $berkas['nomor_pendaftaran'] . " telah dikembalikan kepada pemohon oleh $petugasNama.",
                'info', "detail.php?id=$id");
            kirimNotifikasi($conn, $id, 'loket', 'Berkas Dikembalikan ke Pemohon',
                "Berkas No. " . $berkas['nomor_pendaftaran'] . " selesai dikonfirmasi dan dikembalikan ke pemohon.",
                'info', "detail.php?id=$id");
        } else {
            // Notifikasi ke seksi tujuan
            kirimNotifikasi($conn, $id, $statusSeksiAsal, 'Berkas Masuk Kembali (Setelah Perbaikan)',
                "Berkas No. " . $berkas['nomor_pendaftaran'] . " telah diperbaiki dan diteruskan kembali oleh Loket ke $labelSeksiAsal.",
                'masuk', "detail.php?id=$id");
            kirimNotifikasi($conn, $id, 'admin', 'Berkas Diteruskan Kembali ke Seksi',
                "Berkas No. " . $berkas['nomor_pendaftaran'] . " diteruskan kembali ke $labelSeksiAsal oleh $petugasNama setelah perbaikan.",
                'info', "detail.php?id=$id");
        }

        $conn->commit();
        setFlash('success', $pesanSukses);
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log('Loket tindaklanjut error: ' . $e->getMessage());
        setFlash('danger', 'Gagal memproses tindak lanjut berkas. Silakan coba lagi.');
        header('Location: tindaklanjut.php?id=' . $id);
        exit;
    }
}

$pageTitle = 'Tindak Lanjut Berkas Ditolak · Loket';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Tindak Lanjut Berkas &mdash; Loket</h1>
        <p>No. Berkas <span class="mono fw-semibold"><?= e($berkas['nomor_pendaftaran']) ?></span>
            &nbsp;<span class="badge text-bg-danger"><i class="bi bi-arrow-return-left me-1"></i>Dikembalikan ke Loket</span>
        </p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<div class="row g-4">
    <!-- Kolom kiri: info berkas -->
    <div class="col-lg-5">
        <div class="card mb-3">
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
                <?php if (!empty($berkas['sertifikat_desa'])): ?>
                <div class="mb-3">
                    <div class="text-muted small">Sertifikat / Desa</div>
                    <div class="fw-semibold text-primary"><?= e($berkas['sertifikat_desa']) ?></div>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <div class="text-muted small">Status Saat Ini</div>
                    <div class="mt-1"><?= statusBadge($berkas['status_posisi']) ?></div>
                </div>
                <div class="mb-0">
                    <div class="text-muted small">Batas Waktu</div>
                    <div class="mt-1"><?= slaBadge($berkas['deadline_at'], $berkas['status_posisi'], false, $berkas['sisa_sla_detik'] ?? null) ?></div>
                </div>
            </div>
        </div>

        <!-- Panel alasan penolakan dari seksi -->
        <div class="card border-danger-subtle">
            <div class="card-header bg-danger-subtle text-danger-emphasis">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                Alasan Penolakan dari <?= e($labelSeksiAsal) ?>
            </div>
            <div class="card-body">
                <?php if (!empty($berkas['pengirim_terakhir'])): ?>
                <div class="mb-2">
                    <div class="text-muted small">Dikembalikan oleh</div>
                    <div class="fw-semibold"><?= e($berkas['pengirim_terakhir']) ?></div>
                </div>
                <?php endif; ?>
                <div>
                    <div class="text-muted small mb-1">Catatan Penolakan</div>
                    <?php if (!empty($berkas['catatan_terakhir'])): ?>
                        <div class="p-2 bg-light rounded small border" style="white-space:pre-line;"><?= e($berkas['catatan_terakhir']) ?></div>
                    <?php else: ?>
                        <div class="text-muted small fst-italic">Tidak ada catatan penolakan.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom kanan: form tindak lanjut -->
    <div class="col-lg-7">
        <form action="tindaklanjut.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int) $berkas['id'] ?>">

            <div class="row g-3 mb-3">
                <!-- Opsi 1: Kembalikan ke Pemohon -->
                <div class="col-md-6">
                    <label class="action-choice h-100 is-checked" id="card-kembalikan_pemohon" for="opt-kembalikan-pemohon">
                        <input type="radio" name="keputusan" id="opt-kembalikan-pemohon" value="kembalikan_pemohon"
                               class="action-choice-radio"
                               data-requires-note="1"
                               data-btn-label="<i class='bi bi-person-x me-1'></i> Kembalikan ke Pemohon"
                               data-btn-class="btn-warning"
                               checked>
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-person-x fs-3 text-warning-emphasis flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="fw-bold text-warning-emphasis">Kembalikan ke Pemohon</div>
                                <div class="small text-muted">Berkas diserahkan kembali ke pemohon untuk dilengkapi sendiri</div>
                            </div>
                        </div>
                    </label>
                </div>

                <!-- Opsi 2: Teruskan ke Seksi (setelah diperbaiki) -->
                <div class="col-md-6">
                    <label class="action-choice h-100" id="card-teruskan_seksi" for="opt-teruskan-seksi">
                        <input type="radio" name="keputusan" id="opt-teruskan-seksi" value="teruskan_seksi"
                               class="action-choice-radio"
                               data-requires-note="1"
                               data-btn-label="<i class='bi bi-send-check me-1'></i> Teruskan ke <?= e($labelSeksiAsal) ?>"
                               data-btn-class="btn-primary">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-send-check fs-3 text-primary flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="fw-bold text-primary">Teruskan ke <?= e($labelSeksiAsal) ?></div>
                                <div class="small text-muted">Berkas sudah diperbaiki, kirim kembali ke seksi penolak</div>
                            </div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Panel keterangan aksi -->
            <div class="card mb-3 border-primary-subtle bg-light">
                <div class="card-body py-3">

                    <div id="wrapperKembalikanPemohon">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-person-x text-warning-emphasis fs-5"></i>
                            <span class="fw-semibold text-warning-emphasis">Keputusan: Berkas Dikembalikan ke Pemohon</span>
                        </div>
                        <div class="small text-muted">
                            Berkas akan dikonfirmasi telah diterima kembali di Loket, kemudian
                            <strong>diserahkan kepada pemohon</strong> untuk dilengkapi atau diperbaiki secara mandiri.
                            Status berkas dicatat sebagai <strong>dikembalikan ke pemohon</strong>.
                        </div>
                    </div>

                    <div id="wrapperTeruskanSeksi" style="display:none;">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-send-check text-primary fs-5"></i>
                            <span class="fw-semibold text-primary">Keputusan: Teruskan Kembali ke <?= e($labelSeksiAsal) ?></span>
                        </div>
                        <div class="small text-muted">
                            Pemohon telah menyerahkan dokumen yang kurang/diperbaiki kepada Loket.
                            Berkas akan <strong>diteruskan kembali ke <?= e($labelSeksiAsal) ?></strong>
                            untuk diproses ulang.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Area catatan -->
            <div class="mb-3" id="catatanWrapper">
                <label class="form-label fw-semibold" id="catatanLabel">
                    Catatan Pengembalian ke Pemohon <span class="text-danger">*</span>
                </label>
                <textarea name="catatan" id="catatanInput" class="form-control" rows="3"
                          placeholder="Tuliskan alasan dan rincian kekurangan yang harus dilengkapi oleh pemohon..." required></textarea>
                <div class="form-text" id="catatanHint">Wajib diisi — jelaskan rincian kekurangan atau instruksi perbaikan yang harus dipenuhi oleh pemohon.</div>
            </div>

            <button type="submit" class="btn w-100 btn-warning text-dark py-2 fw-semibold" id="btnSubmitTindakLanjut">
                <i class="bi bi-person-x me-1"></i> Kembalikan ke Pemohon
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const radioKembalikanPemohon = document.getElementById('opt-kembalikan-pemohon');
    const radioTeruskanSeksi     = document.getElementById('opt-teruskan-seksi');

    const cardKembalikan = document.getElementById('card-kembalikan_pemohon');
    const cardTeruskan   = document.getElementById('card-teruskan_seksi');

    const wrapperKembalikan = document.getElementById('wrapperKembalikanPemohon');
    const wrapperTeruskan   = document.getElementById('wrapperTeruskanSeksi');

    const btnSubmit      = document.getElementById('btnSubmitTindakLanjut');
    const catatanInput   = document.getElementById('catatanInput');
    const catatanHint    = document.getElementById('catatanHint');
    const catatanLabel   = document.getElementById('catatanLabel');

    const labelSeksi = <?= json_encode($labelSeksiAsal) ?>;

    function updateView() {
        [cardKembalikan, cardTeruskan].forEach(c => {
            if (c) c.classList.remove('is-checked');
        });

        if (radioKembalikanPemohon && radioKembalikanPemohon.checked) {
            if (cardKembalikan) cardKembalikan.classList.add('is-checked');
            if (wrapperKembalikan) wrapperKembalikan.style.display = 'block';
            if (wrapperTeruskan)   wrapperTeruskan.style.display   = 'none';

            btnSubmit.className = 'btn w-100 btn-warning text-dark py-2 fw-semibold';
            btnSubmit.innerHTML = '<i class="bi bi-person-x me-1"></i> Kembalikan ke Pemohon';

            catatanInput.required = true;
            catatanLabel.innerHTML = 'Catatan Pengembalian ke Pemohon <span class="text-danger">*</span>';
            catatanHint.textContent = 'Wajib diisi — jelaskan rincian kekurangan atau instruksi perbaikan yang harus dipenuhi oleh pemohon.';
            catatanInput.placeholder = 'Tuliskan alasan dan rincian kekurangan yang harus dilengkapi oleh pemohon...';

        } else if (radioTeruskanSeksi && radioTeruskanSeksi.checked) {
            if (cardTeruskan) cardTeruskan.classList.add('is-checked');
            if (wrapperKembalikan) wrapperKembalikan.style.display = 'none';
            if (wrapperTeruskan)   wrapperTeruskan.style.display   = 'block';

            btnSubmit.className = 'btn w-100 btn-primary py-2 fw-semibold';
            btnSubmit.innerHTML = '<i class="bi bi-send-check me-1"></i> Teruskan ke ' + labelSeksi;

            catatanInput.required = true;
            catatanLabel.innerHTML = 'Catatan Perbaikan <span class="text-danger">*</span>';
            catatanHint.textContent = 'Wajib diisi — jelaskan dokumen apa yang sudah diperbaiki/dilengkapi oleh pemohon.';
            catatanInput.placeholder = 'Tuliskan dokumen apa saja yang sudah diperbaiki/dilengkapi...';
        }
    }

    [radioKembalikanPemohon, radioTeruskanSeksi].forEach(r => {
        if (r) r.addEventListener('change', updateView);
    });
    updateView();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
