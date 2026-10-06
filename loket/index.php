<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['loket']);



// 1. Berkas di antrean loket (berkas baru siap kirim maupun berkas dari seksi yang perlu diverifikasi)
$berkasBaruLoket = $conn->query("
    SELECT b.*, u.nama_lengkap AS penginput_nama,
    (
        SELECT l.status_sebelum FROM log_pergerakan l
        WHERE l.id_berkas = b.id ORDER BY l.id DESC LIMIT 1
    ) AS asal_seksi,
    (
        SELECT l.aksi FROM log_pergerakan l
        WHERE l.id_berkas = b.id ORDER BY l.id DESC LIMIT 1
    ) AS aksi_terakhir,
    (
        SELECT l.catatan FROM log_pergerakan l
        WHERE l.id_berkas = b.id ORDER BY l.id DESC LIMIT 1
    ) AS catatan_terakhir,
    (
        SELECT us.nama_lengkap FROM log_pergerakan l
        LEFT JOIN users us ON us.id = l.pengirim_id
        WHERE l.id_berkas = b.id ORDER BY l.id DESC LIMIT 1
    ) AS pengirim_terakhir
    FROM berkas b
    LEFT JOIN users u ON u.id = b.diinput_oleh
    WHERE b.status_posisi = 'loket'
    ORDER BY (b.deadline_at IS NOT NULL AND b.deadline_at < NOW()) DESC, 
             (b.deadline_at IS NOT NULL AND b.deadline_at >= NOW() AND b.deadline_at <= DATE_ADD(NOW(), INTERVAL 1 DAY)) DESC,
             b.updated_at DESC, b.created_at DESC
")->fetchAll();

// Pisahkan berkas menjadi:
// A. Berkas Simpan di Loket (berkas baru / draft yang disimpan di Loket, siap dikirim ke seksi)
// B. Berkas Siap Daftar (berkas yang telah selesai dikerjakan oleh Seksi dan diteruskan ke Loket)
$berkasSimpanLoket = [];
$berkasSiapDaftar = [];

foreach ($berkasBaruLoket as $b) {
    if (in_array($b['asal_seksi'], ['seksi_1', 'seksi_2'], true)) {
        $berkasSiapDaftar[] = $b;
    } else {
        $berkasSimpanLoket[] = $b;
    }
}

// 2. Berkas ditolak yang perlu dikonfirmasi diterima / diperbaiki di Loket
$perluDiterima = $conn->query("
    SELECT b.*, (
        SELECT l.catatan FROM log_pergerakan l
        WHERE l.id_berkas = b.id ORDER BY l.created_at DESC, l.id DESC LIMIT 1
    ) AS catatan_terakhir, (
        SELECT us.nama_lengkap FROM log_pergerakan l
        LEFT JOIN users us ON us.id = l.pengirim_id
        WHERE l.id_berkas = b.id ORDER BY l.created_at DESC, l.id DESC LIMIT 1
    ) AS pengirim_terakhir
    FROM berkas b
    WHERE b.status_posisi = 'ditolak_ke_loket' AND b.is_diterima_loket = 0
    ORDER BY b.updated_at ASC
")->fetchAll();

// 3. Berkas sedang berjalan di unit lain (Seksi 1 atau Seksi 2)
$dalamProses = $conn->query("
    SELECT b.*, u.nama_lengkap AS pemegang_nama, u.role AS pemegang_role 
    FROM berkas b
    LEFT JOIN users u ON u.id = b.petugas_tujuan_id
    WHERE b.status_posisi IN ('seksi_1','seksi_2','ditolak_ke_seksi1')
    ORDER BY b.created_at DESC
    LIMIT 20
")->fetchAll();

$totalDalamProses = (int) $conn->query("SELECT COUNT(*) FROM berkas WHERE status_posisi IN ('seksi_1','seksi_2','ditolak_ke_seksi1')")->fetchColumn();

$pageTitle = 'Antrean Tugas Loket';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Antrean Tugas Loket</h1>
        <p>Kelola berkas baru yang siap dikirim, verifikasi berkas dari seksi untuk diserahkan ke pemohon atau dikembalikan, dan pantau berkas berjalan.</p>
    </div>
    <a href="tambah.php" class="btn btn-primary"><i class="bi bi-file-earmark-plus me-1"></i>Input Berkas Baru</a>
</div>

<style>
.stat-card-tab {
    cursor: pointer;
}
.loket-section { display: none; }
.loket-section.active-section {
    display: block;
    animation: fadeIn .2s ease;
}
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="row g-3 mb-4" id="statCardRow">
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card-tab active-tab" style="--stat-color:#0D9488" data-target="section-simpan" tabindex="0" role="button" aria-pressed="true">
            <div class="stat-icon"><i class="bi bi-inbox"></i></div>
            <div>
                <div class="stat-value"><?= count($berkasSimpanLoket) ?></div>
                <div class="stat-label">Simpan di Loket</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card-tab" style="--stat-color:#16A34A" data-target="section-siap" tabindex="0" role="button">
            <div class="stat-icon"><i class="bi bi-folder-check"></i></div>
            <div>
                <div class="stat-value text-success"><?= count($berkasSiapDaftar) ?></div>
                <div class="stat-label">Siap Daftar</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card-tab" style="--stat-color:#2563A8" data-target="section-proses" tabindex="0" role="button">
            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-value"><?= $totalDalamProses ?></div>
                <div class="stat-label">Sedang Diproses</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card-tab" style="--stat-color:#C0392B" data-target="section-perbaikan" tabindex="0" role="button">
            <div class="stat-icon"><i class="bi bi-arrow-return-left"></i></div>
            <div>
                <div class="stat-value <?= count($perluDiterima) > 0 ? 'text-danger' : '' ?>"><?= count($perluDiterima) ?></div>
                <div class="stat-label">Perlu Perbaikan</div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================
     TABEL 1: BERKAS SIMPAN DI LOKET (MENUNGGU KIRIM KE SEKSI)
     ========================================================== -->
<div id="section-simpan" class="loket-section active-section">
<div class="card mb-4 shadow-sm" style="border-top: 3px solid #0D9488;">
    <div class="card-header py-3 px-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-inbox fs-5 flex-shrink-0" style="color:#0D9488;"></i>
                <span class="fw-bold fs-6 text-dark">Berkas Simpan di Loket &mdash; Menunggu Pengiriman ke Seksi</span>
            </div>
            <span class="badge rounded-pill text-white px-2.5" style="background-color:#0D9488; font-size: 0.75rem;"><?= count($berkasSimpanLoket) ?> Berkas</span>
        </div>
        <div class="text-muted small mt-1" style="font-size: 0.83rem;">
            Daftar berkas baru yang diinput dan disimpan di Loket. Pilih berkas untuk dikirim ke Seksi 1 atau Seksi 2.
        </div>
    </div>
    <div class="card-body p-0">
        <form action="kirim.php" method="GET" id="formBatchKirim">
            <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="checkAllLoket">
                    <label class="form-check-label fw-semibold small" for="checkAllLoket">Pilih Semua Berkas</label>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnKirimBatch" disabled>
                        <i class="bi bi-send me-1"></i>Kirim Berkas Terpilih (<span id="countSelected">0</span>) &rarr;
                    </button>
                </div>
            </div>

            <div class="table-scroll-hint">
                <i class="bi bi-arrows-expand-vertical" style="transform: rotate(90deg);"></i> Geser tabel ke samping untuk melihat kolom selengkapnya
            </div>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>No. Berkas</th>
                            <th>Nama Pemohon</th>
                            <th>Jenis Layanan</th>
                            <th>Sertifikat / Desa</th>
                            <th>Waktu Input</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($berkasSimpanLoket)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state py-4">
                                        <i class="bi bi-check2-circle text-success fs-3 mb-2 d-block"></i>
                                        Tidak ada berkas yang disimpan di Loket saat ini. Semua berkas baru sudah diteruskan ke seksi.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($berkasSimpanLoket as $b): ?>
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" name="ids[]" value="<?= (int) $b['id'] ?>" class="form-check-input check-berkas">
                            </td>
                            <td class="mono fw-semibold">
                                <a href="<?= baseUrl('detail.php?id=' . (int) $b['id']) ?>" class="text-decoration-none">
                                    <?= e($b['nomor_pendaftaran']) ?>
                                </a>
                                <?php if (!empty($b['foto_bidang'])): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" title="Disertai upload foto bidang" style="font-size: 0.65rem;"><i class="bi bi-camera me-1"></i>Foto</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($b['nama_pemohon']) ?></div>
                            </td>
                            <td class="small"><?= e($b['jenis_layanan']) ?></td>
                            <td>
                                <?php if (!empty($b['sertifikat_desa'])): ?>
                                    <div class="text-muted small"><i class="bi bi-award me-1"></i><?= e($b['sertifikat_desa']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= formatTanggal($b['created_at']) ?></td>
                            <td class="text-end text-nowrap">
                                <div class="action-btns justify-content-end">
                                    <a href="<?= baseUrl('detail.php?id=' . (int) $b['id']) ?>" class="btn btn-detail" title="Detail">
                                        <i class="bi bi-eye"></i>Detail
                                    </a>
                                    <a href="kirim.php?id=<?= (int) $b['id'] ?>" class="btn btn-kirim" title="Kirim Berkas ke Seksi">
                                        <i class="bi bi-send"></i>Kirim
                                    </a>
                                    <button type="button" class="btn btn-hapus btn-hapus-single" data-id="<?= (int) $b['id'] ?>" data-nomor="<?= e($b['nomor_pendaftaran']) ?>" title="Hapus Berkas">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>
</div><!-- /#section-simpan -->

<!-- ==========================================================
     TABEL 2: BERKAS SIAP DAFTAR (DITERUSKAN DARI SEKSI KE LOKET)
     ========================================================== -->
<div id="section-siap" class="loket-section">
<div class="card mb-4 shadow-sm" style="border-top: 3px solid #16A34A;">
    <div class="card-header py-3 px-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-folder-check fs-5 flex-shrink-0 text-success"></i>
                <span class="fw-bold fs-6 text-dark">Berkas Siap Daftar &mdash; Diteruskan ke Loket</span>
            </div>
            <span class="badge rounded-pill bg-success px-2.5" style="font-size: 0.75rem;"><?= count($berkasSiapDaftar) ?> Berkas</span>
        </div>
        <div class="text-muted small mt-1" style="font-size: 0.83rem;">
            Berkas yang telah selesai dikerjakan oleh Seksi dan diteruskan ke Loket untuk diverifikasi kelengkapannya sebelum diserahkan ke pemohon.
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-scroll-hint">
            <i class="bi bi-arrows-expand-vertical" style="transform: rotate(90deg);"></i> Geser tabel ke samping untuk melihat kolom selengkapnya
        </div>
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead>
                    <tr>
                        <th>No. Berkas</th>
                        <th>Nama Pemohon</th>
                        <th>Jenis Layanan</th>
                        <th>Diteruskan Dari</th>
                        <th>Catatan Pengirim</th>
                        <th>Waktu Diteruskan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($berkasSiapDaftar)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state py-4">
                                    <i class="bi bi-inbox text-muted fs-3 mb-2 d-block"></i>
                                    Belum ada berkas yang diteruskan ke Loket (Berkas Siap Daftar).
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($berkasSiapDaftar as $b): ?>
                    <tr>
                        <td class="mono fw-semibold">
                            <a href="<?= baseUrl('detail.php?id=' . (int) $b['id']) ?>" class="text-decoration-none">
                                <?= e($b['nomor_pendaftaran']) ?>
                            </a>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= e($b['nama_pemohon']) ?></div>
                            <?php if (!empty($b['sertifikat_desa'])): ?>
                                <div class="text-muted small"><i class="bi bi-award me-1"></i><?= e($b['sertifikat_desa']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= e($b['jenis_layanan']) ?></td>
                        <td>
                            <?php if ($b['asal_seksi'] === 'seksi_1'): ?>
                                <span class="badge text-bg-info text-dark"><i class="bi bi-arrow-down-left me-1"></i>Dari Seksi 1</span>
                                <div class="small text-muted mt-1">Survei &amp; Pemetaan</div>
                            <?php elseif ($b['asal_seksi'] === 'seksi_2'): ?>
                                <span class="badge text-bg-primary"><i class="bi bi-arrow-down-left me-1"></i>Dari Seksi 2</span>
                                <div class="small text-muted mt-1">Penetapan Hak &amp; Pendaftaran</div>
                            <?php else: ?>
                                <span class="badge text-bg-secondary"><i class="bi bi-inbox me-1"></i>Seksi</span>
                            <?php endif; ?>
                        </td>
                        <td class="small" style="max-width: 260px;">
                            <?php if (!empty($b['catatan_terakhir'])): ?>
                                <div class="text-truncate" title="<?= e($b['catatan_terakhir']) ?>">
                                    <i class="bi bi-chat-left-text me-1 text-muted"></i><?= e($b['catatan_terakhir']) ?>
                                </div>
                                <?php if (!empty($b['pengirim_terakhir'])): ?>
                                    <div class="small text-muted">Oleh: <?= e($b['pengirim_terakhir']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= formatTanggal($b['updated_at'] ?: $b['created_at']) ?></td>
                        <td class="text-end text-nowrap">
                            <div class="action-btns justify-content-end">
                                <a href="<?= baseUrl('detail.php?id=' . (int) $b['id']) ?>" class="btn btn-detail" title="Detail">
                                    <i class="bi bi-eye"></i>Detail
                                </a>
                                <a href="proses.php?id=<?= (int) $b['id'] ?>" class="btn btn-proses" title="Verifikasi / Proses Penyerahan Berkas">
                                    <i class="bi bi-check2-circle"></i>Proses
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div><!-- /#section-siap -->

<!-- ==========================================================
     TABEL 3: PERLU DITINDAKLANJUTI (DIKEMBALIKAN KE LOKET)
     ========================================================== -->
<div id="section-perbaikan" class="loket-section">
<div class="card mb-4 shadow-sm" style="border-top: 3px solid #C0392B;">
    <div class="card-header py-3 px-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-arrow-return-left fs-5 flex-shrink-0 text-danger"></i>
                <span class="fw-bold fs-6 text-dark">Perlu Ditindaklanjuti &mdash; Berkas Dikembalikan ke Loket</span>
            </div>
            <span class="badge rounded-pill bg-danger px-2.5" style="font-size: 0.75rem;"><?= count($perluDiterima) ?> Berkas</span>
        </div>
        <div class="text-muted small mt-1" style="font-size: 0.83rem;">
            Pilih tindakan: kembalikan berkas ke pemohon, atau teruskan kembali ke seksi setelah berkas diperbaiki.
        </div>
    </div>
    <div class="table-scroll-hint">
        <i class="bi bi-arrows-expand-vertical" style="transform: rotate(90deg);"></i> Geser tabel ke samping untuk melihat kolom selengkapnya
    </div>
    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0">
            <thead>
                <tr>
                    <th>No. Berkas</th>
                    <th>Nama Pemohon</th>
                    <th>Status</th>
                    <th>Batas Waktu</th>
                    <th>Dikembalikan Oleh</th>
                    <th>Catatan Penolakan</th>
                    <th class="text-end text-nowrap" style="min-width: 180px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($perluDiterima)): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-check2-circle"></i>Tidak ada berkas yang perlu ditindaklanjuti saat ini.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($perluDiterima as $b):
                    $sla = hitungSla($b['deadline_at'], $b['updated_at']);
                ?>
                <tr class="<?= $sla['is_overdue'] ? 'table-danger' : (!empty($sla['is_warning']) ? 'table-warning' : '') ?>">
                    <td class="mono fw-semibold">
                        <a href="<?= baseUrl('detail.php?id=' . (int) $b['id']) ?>" class="text-decoration-none">
                            <?= e($b['nomor_pendaftaran']) ?>
                        </a>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= e($b['nama_pemohon']) ?></div>
                        <?php if (!empty($b['sertifikat_desa'])): ?>
                            <div class="text-muted small"><i class="bi bi-award me-1"></i><?= e($b['sertifikat_desa']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= statusBadge($b['status_posisi']) ?></td>
                    <td>
                        <?= slaBadge($b['deadline_at'], $b['status_posisi'], false, $b['sisa_sla_detik'] ?? null) ?>
                    </td>
                    <td class="small"><?= e($b['pengirim_terakhir'] ?? '-') ?></td>
                    <td class="small" style="max-width:220px;">
                        <?php if (!empty($b['catatan_terakhir'])): ?>
                            <div class="text-truncate text-danger-emphasis" title="<?= e($b['catatan_terakhir']) ?>">
                                <i class="bi bi-exclamation-circle me-1 text-danger"></i><?= e($b['catatan_terakhir']) ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="action-btns justify-content-end">
                            <a href="<?= baseUrl('detail.php?id=' . (int) $b['id']) ?>" class="btn btn-detail">
                                <i class="bi bi-eye"></i>Detail
                            </a>
                            <a href="tindaklanjut.php?id=<?= (int) $b['id'] ?>" class="btn btn-proses">
                                <i class="bi bi-arrow-right-circle"></i>Tindak Lanjuti
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div><!-- /#section-perbaikan -->



<!-- ==========================================================
     TABEL 4: BERKAS SEDANG BERJALAN (MONITORING)
     ========================================================== -->
<div id="section-proses" class="loket-section">
<div class="card mb-4 shadow-sm" style="border-top: 3px solid #2563A8;">
    <div class="card-header py-3 px-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-hourglass-split fs-5 flex-shrink-0 text-primary"></i>
                <span class="fw-bold fs-6 text-dark">Berkas Sedang Berjalan di Unit Lain (Monitoring)</span>
            </div>
            <span class="badge rounded-pill bg-primary px-2.5" style="font-size: 0.75rem;"><?= $totalDalamProses ?> Berkas</span>
        </div>
        <div class="text-muted small mt-1" style="font-size: 0.83rem;">
            Pantau posisi dan status berkas yang sedang diproses oleh Seksi 1 atau Seksi 2 secara real-time.
        </div>
    </div>
    <div class="table-scroll-hint">
        <i class="bi bi-arrows-expand-vertical" style="transform: rotate(90deg);"></i> Geser tabel ke samping untuk melihat kolom selengkapnya
    </div>
    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0">
            <thead>
                <tr>
                    <th>No. Berkas</th>
                    <th>Nama Pemohon</th>
                    <th>Jenis Layanan</th>
                    <th>Posisi &amp; Pemegang Berkas</th>
                    <th>Batas Waktu</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dalamProses)): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="bi bi-inbox"></i>Belum ada berkas yang sedang diproses di seksi.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($dalamProses as $b): 
                    $sla = hitungSla($b['deadline_at'], $b['updated_at'], $b['status_posisi'], $b['sisa_sla_detik'] ?? null);
                ?>
                <tr class="<?= $sla['is_overdue'] ? 'table-danger' : (!empty($sla['is_warning']) ? 'table-warning' : '') ?>">
                    <td class="mono fw-semibold">
                        <a href="<?= baseUrl('detail.php?id=' . (int) $b['id']) ?>" class="text-decoration-none">
                            <?= e($b['nomor_pendaftaran']) ?>
                        </a>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= e($b['nama_pemohon']) ?></div>
                        <?php if (!empty($b['sertifikat_desa'])): ?>
                            <div class="text-muted small"><i class="bi bi-award me-1"></i><?= e($b['sertifikat_desa']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= e($b['jenis_layanan']) ?></td>
                    <td>
                        <div><?= statusBadge($b['status_posisi']) ?></div>
                        <div class="small text-muted mt-1">
                            <i class="bi bi-people me-1 text-primary"></i>
                            <?= e(pemegangBerkasLabel($b['pemegang_nama'], $b['status_posisi'])) ?>
                        </div>
                    </td>
                    <td>
                        <?= slaBadge($b['deadline_at'], $b['status_posisi'], false, $b['sisa_sla_detik'] ?? null) ?>
                        <div class="small text-muted mt-1"><?= formatTanggal($b['updated_at']) ?></div>
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="action-btns justify-content-end">
                            <a href="<?= baseUrl('detail.php?id=' . (int) $b['id']) ?>" class="btn btn-detail">
                                <i class="bi bi-eye"></i>Detail
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div><!-- /#section-proses -->

<!-- Form tersembunyi untuk aksi satuan (Hapus) -->
<form id="formActionSingle" method="POST" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="id" id="singleActionId" value="">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {

    /* ---- Tab / Stat-card switching ---- */
    const tabs    = document.querySelectorAll('.stat-card-tab');
    const sections = document.querySelectorAll('.loket-section');

    function switchTab(targetId) {
        // Deactivate all
        tabs.forEach(t => { t.classList.remove('active-tab'); t.setAttribute('aria-pressed', 'false'); });
        sections.forEach(s => s.classList.remove('active-section'));

        // Activate selected
        const activeTab = document.querySelector('[data-target="' + targetId + '"]');
        const activeSection = document.getElementById(targetId);
        if (activeTab) { activeTab.classList.add('active-tab'); activeTab.setAttribute('aria-pressed', 'true'); }
        if (activeSection) activeSection.classList.add('active-section');

        // Save to sessionStorage so refresh keeps the active tab
        sessionStorage.setItem('loket_active_tab', targetId);
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            switchTab(this.getAttribute('data-target'));
        });
        // Keyboard support
        tab.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                switchTab(this.getAttribute('data-target'));
            }
        });
    });

    // Restore last active tab from session, default to 'section-simpan'
    const saved = sessionStorage.getItem('loket_active_tab');
    if (saved && document.getElementById(saved)) {
        switchTab(saved);
    }


    /* ---- Batch checkbox logic (Table 1 — Simpan di Loket) ---- */
    const checkAll      = document.getElementById('checkAllLoket');
    const checkboxes    = document.querySelectorAll('.check-berkas');
    const btnKirimBatch = document.getElementById('btnKirimBatch');
    const countSelected = document.getElementById('countSelected');
    const formSingle    = document.getElementById('formActionSingle');
    const inputSingleId = document.getElementById('singleActionId');

    function updateBtnState() {
        const totalChecked = Array.from(checkboxes).filter(cb => cb.checked).length;
        if (btnKirimBatch) btnKirimBatch.disabled = (totalChecked === 0);
        if (countSelected) countSelected.textContent = totalChecked;
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBtnState();
        });
    }
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (checkAll) checkAll.checked = Array.from(checkboxes).every(c => c.checked);
            updateBtnState();
        });
    });

    /* ---- Single delete ---- */
    document.querySelectorAll('.btn-hapus-single').forEach(btn => {
        btn.addEventListener('click', function() {
            const id    = this.getAttribute('data-id');
            const nomor = this.getAttribute('data-nomor');
            if (confirm(`Yakin ingin menghapus berkas ${nomor} dari loket? Berkas yang dihapus tidak dapat dikembalikan.`)) {
                formSingle.action = 'hapus.php';
                inputSingleId.value = id;
                formSingle.submit();
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
