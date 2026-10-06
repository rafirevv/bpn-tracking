<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('danger', 'Berkas tidak ditemukan.');
    header('Location: ' . baseUrl(roleHome($_SESSION['role'])));
    exit;
}

$stmt = $conn->prepare("
    SELECT b.*, u.nama_lengkap AS nama_penginput,
           up.nama_lengkap AS nama_pemegang, up.role AS role_pemegang
    FROM berkas b
    LEFT JOIN users u ON u.id = b.diinput_oleh
    LEFT JOIN users up ON up.id = b.petugas_tujuan_id
    WHERE b.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$berkas = $stmt->fetch();

if (!$berkas) {
    setFlash('danger', 'Berkas tidak ditemukan.');
    header('Location: ' . baseUrl(roleHome($_SESSION['role'])));
    exit;
}

$logStmt = $conn->prepare("
    SELECT l.*,
           us.nama_lengkap AS nama_pengirim, us.role AS role_pengirim, us.sub_bagian AS sub_bagian_pengirim,
           up.nama_lengkap AS nama_penerima, up.role AS role_penerima, up.sub_bagian AS sub_bagian_penerima
    FROM log_pergerakan l
    LEFT JOIN users us ON us.id = l.pengirim_id
    LEFT JOIN users up ON up.id = l.penerima_id
    WHERE l.id_berkas = ?
    ORDER BY l.created_at ASC, l.id ASC
");
$logStmt->execute([$id]);
$logs = $logStmt->fetchAll();

$pageTitle = 'Detail Berkas ' . $berkas['nomor_pendaftaran'];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$isKembaliPemohon = ($berkas['status_posisi'] === 'ditolak_ke_loket' && (int)($berkas['is_diterima_loket'] ?? 0) === 1);
[$statusLabel, $statusColor, $statusIcon] = statusInfo($berkas['status_posisi'], (int)($berkas['is_diterima_loket'] ?? 0));

$catatanPengembalian = '';
$petugasPengembali = '';
$tglPengembalian = null;

if ($isKembaliPemohon) {
    // Cari log pengembalian terakhir ke pemohon
    for ($i = count($logs) - 1; $i >= 0; $i--) {
        $l = $logs[$i];
        if ($l['status_sesudah'] === 'ditolak_ke_loket' && ($l['aksi'] === 'dikembalikan' || str_contains($l['catatan'] ?? '', 'dikembalikan kepada pemohon'))) {
            $petugasPengembali = $l['nama_pengirim'] ?? '';
            $tglPengembalian = $l['created_at'] ?? null;
            $rawCatatan = $l['catatan'] ?? '';
            if (preg_match('/^(.*?)\s*\[Berkas dikembalikan kepada pemohon/si', $rawCatatan, $m) && trim($m[1]) !== '') {
                $catatanPengembalian = trim($m[1]);
            } else {
                $catatanPengembalian = trim($rawCatatan);
            }
            break;
        }
    }
}
?>

<div class="page-head no-print">
    <div>
        <h1>Detail &amp; Riwayat Pergerakan Berkas</h1>
        <p>Nomor Berkas <span class="mono fw-semibold"><?= e($berkas['nomor_pendaftaran']) ?></span></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($_SESSION['role'] === 'loket' && $berkas['status_posisi'] === 'loket'): 
            $dariSeksi = false;
            foreach ($logs as $l) {
                if (in_array($l['status_sebelum'], ['seksi_1', 'seksi_2'], true)) {
                    $dariSeksi = true;
                    break;
                }
            }
        ?>
            <?php if ($dariSeksi): ?>
                <a href="<?= baseUrl('loket/proses.php?id=' . (int)$berkas['id']) ?>" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Verifikasi / Proses Berkas</a>
            <?php else: ?>
                <a href="<?= baseUrl('loket/kirim.php?id=' . (int)$berkas['id']) ?>" class="btn btn-primary"><i class="bi bi-send me-1"></i>Kirim ke Seksi</a>
            <?php endif; ?>
        <?php elseif ($_SESSION['role'] === 'loket' && $berkas['status_posisi'] === 'ditolak_ke_loket' && (int)($berkas['is_diterima_loket'] ?? 0) === 0): ?>
            <a href="<?= baseUrl('loket/tindaklanjut.php?id=' . (int)$berkas['id']) ?>" class="btn btn-warning"><i class="bi bi-arrow-repeat me-1"></i>Tindak Lanjuti Berkas</a>
        <?php elseif ($_SESSION['role'] === 'seksi_1' && in_array($berkas['status_posisi'], ['seksi_1', 'ditolak_ke_seksi1'], true)): ?>
            <a href="<?= baseUrl('seksi1/proses.php?id=' . (int)$berkas['id']) ?>" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Proses Berkas</a>
        <?php elseif ($_SESSION['role'] === 'seksi_2' && $berkas['status_posisi'] === 'seksi_2'): ?>
            <a href="<?= baseUrl('seksi2/proses.php?id=' . (int)$berkas['id']) ?>" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Proses Berkas</a>
        <?php endif; ?>
        <a href="javascript:history.back()" class="btn btn-primary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>
</div>

<?php if ($isKembaliPemohon): ?>
<div class="alert alert-danger d-flex align-items-start gap-3 p-3 mb-4 rounded-3 shadow-sm border-danger-subtle">
    <div class="fs-1 text-danger lh-1"><i class="bi bi-arrow-return-left"></i></div>
    <div class="flex-grow-1">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
            <h5 class="alert-heading mb-0 fw-bold text-danger">Berkas Dikembalikan ke Pemohon</h5>
            <span class="badge text-bg-danger px-2.5 py-1.5"><i class="bi bi-arrow-return-left me-1"></i>Kembali ke Pemohon</span>
        </div>
        <div class="text-danger-emphasis small mb-2">
            Berkas ini telah diserahkan kembali kepada pemohon untuk diperbaiki atau dilengkapi sesuai catatan di bawah.
            <?php if (!empty($petugasPengembali)): ?>
                (Petugas Loket: <strong><?= e($petugasPengembali) ?></strong><?php if (!empty($tglPengembalian)): ?> &bull; <?= formatTanggal($tglPengembalian) ?><?php endif; ?>)
            <?php endif; ?>
        </div>
        <?php if (!empty($catatanPengembalian)): ?>
            <div class="p-3 bg-white rounded border border-danger-subtle shadow-sm mt-2">
                <div class="fw-bold text-danger small mb-1">
                    <i class="bi bi-sticky-fill me-1"></i>Catatan Pengembalian ke Pemohon (Wajib):
                </div>
                <div class="text-dark fw-medium" style="white-space: pre-line;"><?= e($catatanPengembalian) ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">Informasi Berkas</div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="text-muted small">Status &amp; Posisi</div>
                    <div class="mt-1"><?= statusBadge($berkas['status_posisi'], (int)($berkas['is_diterima_loket'] ?? 0)) ?></div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Batas Waktu</div>
                    <div class="mt-1"><?= slaBadge($berkas['deadline_at'], $berkas['status_posisi'], (bool)($berkas['is_diterima_loket'] ?? 0), $berkas['sisa_sla_detik'] ?? null) ?></div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Pemegang Berkas Saat Ini</div>
                    <div class="fw-semibold mt-1">
                        <?php if ($isKembaliPemohon): ?>
                            <span class="text-danger">
                                <i class="bi bi-person-x me-1"></i><?= e(pemegangBerkasLabel(null, $berkas['status_posisi'], (int)$berkas['is_diterima_loket'])) ?>
                            </span>
                        <?php elseif (!empty($berkas['nama_pemegang'])): ?>
                            <i class="bi bi-person-check me-1 text-primary"></i>
                            <?= e($berkas['nama_pemegang']) ?>
                            <?php if (!empty($berkas['role_pemegang'])): ?>
                                <span class="info-chip ms-1"><?= e(roleLabel($berkas['role_pemegang'])) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <i class="bi bi-people me-1 text-primary"></i>
                            <?= e(pemegangBerkasLabel(null, $berkas['status_posisi'], (int)($berkas['is_diterima_loket'] ?? 0))) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($isKembaliPemohon && !empty($catatanPengembalian)): ?>
                <div class="mb-3 p-3 bg-danger-subtle border border-danger-subtle rounded-3">
                    <div class="text-danger fw-bold small mb-1">
                        <i class="bi bi-sticky-fill me-1"></i>Catatan Pengembalian ke Pemohon
                    </div>
                    <div class="text-dark small fw-medium" style="white-space: pre-line;"><?= e($catatanPengembalian) ?></div>
                    <?php if (!empty($petugasPengembali)): ?>
                        <div class="text-muted small mt-2 pt-2 border-top border-danger-subtle">
                            <i class="bi bi-person me-1"></i><?= e($petugasPengembali) ?><?php if (!empty($tglPengembalian)): ?> &bull; <?= formatTanggal($tglPengembalian) ?><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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
                        <span><i class="bi bi-image text-primary me-1"></i>Foto Bidang</span>
                        <a href="<?= e($fotoUrl) ?>" target="_blank" class="small text-muted text-decoration-none d-inline-flex align-items-center gap-1" title="Buka gambar ukuran penuh">
                            <span>Buka Penuh</span>
                            <i class="bi bi-arrow-up-right" style="font-size: 0.72rem;"></i>
                        </a>
                    </div>
                    <div class="position-relative border rounded-3 overflow-hidden bg-light shadow-sm text-center" style="max-height: 200px; cursor: pointer;" data-bs-toggle="modal" data-bs-target="#modalFotoBidang" title="Klik untuk memperbesar">
                        <img src="<?= e($fotoUrl) ?>" alt="Foto Bidang <?= e($berkas['nomor_pendaftaran']) ?>" class="img-fluid w-100 object-fit-cover" style="max-height: 200px; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                        <div class="position-absolute bottom-0 start-0 end-0 py-1 px-2 text-white small text-center" style="background: rgba(0,0,0,0.55); font-size: 0.72rem;">
                            <span><i class="bi bi-zoom-in me-1"></i>Klik untuk melihat</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($berkas['nik'])): ?>
                <div class="mb-3">
                    <div class="text-muted small">NIK</div>
                    <div><?= e($berkas['nik']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($berkas['no_hp'])): ?>
                <div class="mb-3">
                    <div class="text-muted small">No. HP</div>
                    <div><?= e($berkas['no_hp']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($berkas['deskripsi_berkas']) && $berkas['deskripsi_berkas'] !== $berkas['sertifikat_desa']): ?>
                <div class="mb-3">
                    <div class="text-muted small">Catatan / Kelengkapan</div>
                    <div style="white-space:pre-line;"><?= e($berkas['deskripsi_berkas']) ?></div>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <div class="text-muted small">Diinput Oleh</div>
                    <div><?= e($berkas['nama_penginput'] ?? '-') ?></div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="text-muted small">Dibuat</div>
                        <div class="small"><?= formatTanggal($berkas['created_at']) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Update Terakhir</div>
                        <div class="small"><?= formatTanggal($berkas['updated_at']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Jejak Pergerakan Berkas (Log Tracking)</div>
            <div class="card-body">
                <?php if (empty($logs)): ?>
                    <div class="empty-state">
                        <i class="bi bi-clock-history"></i>
                        Belum ada riwayat pergerakan untuk berkas ini.
                    </div>
                <?php else: ?>
                    <ul class="timeline">
                        <?php foreach ($logs as $log):
                            $itemClass = '';
                            if ($log['aksi'] === 'dikembalikan') $itemClass = 'is-reject';
                            if ($log['aksi'] === 'selesai') $itemClass = 'is-success';
                            $icon = match ($log['aksi']) {
                                'diinput'      => 'bi-file-earmark-plus',
                                'diteruskan'   => 'bi-send',
                                'dikembalikan' => 'bi-arrow-return-left',
                                'selesai'      => 'bi-check-lg',
                                'diterima'     => 'bi-box-arrow-in-down',
                                default        => 'bi-dot',
                            };
                        ?>
                        <li class="timeline-item <?= $itemClass ?>">
                            <span class="timeline-dot"><i class="bi <?= $icon ?>"></i></span>
                            <div class="timeline-body">
                                <div class="d-flex justify-content-between flex-wrap gap-2">
                                    <?php
                                        $judulAksi = aksiLabel($log['aksi']);
                                        $isLogKembaliPemohon = ($log['aksi'] === 'dikembalikan' && str_contains($log['catatan'] ?? '', 'dikembalikan kepada pemohon'));
                                        if ($isLogKembaliPemohon) {
                                            $judulAksi = 'Dikembalikan ke Pemohon';
                                        }
                                    ?>
                                    <strong class="<?= $isLogKembaliPemohon ? 'text-danger' : '' ?>">
                                        <?php if ($isLogKembaliPemohon): ?><i class="bi bi-person-x me-1"></i><?php endif; ?>
                                        <?= e($judulAksi) ?>
                                    </strong>
                                    <span class="text-muted small"><?= formatTanggal($log['created_at']) ?></span>
                                </div>
                                <div class="small text-muted mt-1">
                                    <?php if ($log['nama_pengirim']): ?>
                                        Oleh <strong><?= e($log['nama_pengirim']) ?></strong>
                                        <span class="info-chip ms-1"><?= e(roleLabel($log['role_pengirim'])) ?><?= !empty($log['sub_bagian_pengirim']) ? ' &bull; Bagian ' . e($log['sub_bagian_pengirim']) : '' ?></span>
                                    <?php endif; ?>
                                    <?php if ($isLogKembaliPemohon): ?>
                                        &rarr; diserahkan kembali kepada <strong>Pemohon</strong>
                                    <?php elseif ($log['nama_penerima']): ?>
                                        &rarr; ditujukan ke <strong><?= e($log['nama_penerima']) ?></strong>
                                        <span class="info-chip ms-1"><?= e(roleLabel($log['role_penerima'])) ?><?= !empty($log['sub_bagian_penerima']) ? ' &bull; Bagian ' . e($log['sub_bagian_penerima']) : '' ?></span>
                                    <?php elseif (!empty($log['status_sesudah']) && $log['aksi'] !== 'diinput' && $log['aksi'] !== 'diterima'): ?>
                                        &rarr; ditujukan ke <strong><?= e(unitTujuanLabel($log['status_sesudah'])) ?></strong>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($log['catatan'])): ?>
                                    <div class="timeline-note <?= $isLogKembaliPemohon ? 'border-danger bg-danger-subtle text-danger-emphasis' : '' ?>">
                                        <i class="bi <?= $isLogKembaliPemohon ? 'bi-exclamation-circle-fill text-danger' : 'bi-sticky' ?> me-1"></i><?= nl2br(e($log['catatan'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($berkas['foto_bidang'])): ?>
<div class="modal fade" id="modalFotoBidang" tabindex="-1" aria-labelledby="modalFotoBidangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2 px-3">
                <h5 class="modal-title fs-6 fw-bold text-dark" id="modalFotoBidangLabel">
                    <i class="bi bi-image text-primary me-2"></i>Foto Bidang &mdash; No. Berkas <?= e($berkas['nomor_pendaftaran']) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body text-center p-2 bg-dark">
                <img src="<?= e(fotoBidangUrl($berkas['foto_bidang'])) ?>" alt="Foto Bidang" class="img-fluid rounded" style="max-height: 75vh; object-fit: contain;">
            </div>
            <div class="modal-footer py-2 px-3 d-flex justify-content-between align-items-center bg-light">
                <span class="text-muted small">Pemohon: <strong><?= e($berkas['nama_pemohon']) ?></strong> (<?= e($berkas['sertifikat_desa']) ?>)</span>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
