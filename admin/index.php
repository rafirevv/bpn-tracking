<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin', 'loket', 'seksi_1', 'seksi_2']);

// Statistik per status
$statCounts = array_fill_keys(
    ['loket', 'seksi_1', 'seksi_2', 'selesai', 'ditolak_ke_loket', 'ditolak_ke_seksi1'],
    0
);
$rows = $conn->query("SELECT status_posisi, COUNT(*) AS jumlah FROM berkas GROUP BY status_posisi")->fetchAll();
foreach ($rows as $r) {
    if (isset($statCounts[$r['status_posisi']])) {
        $statCounts[$r['status_posisi']] = (int) $r['jumlah'];
    }
}
$totalBerkas = array_sum($statCounts);

// Hitung total berkas berdasarkan posisi unit
$totalLoket  = $statCounts['loket'] + $statCounts['ditolak_ke_loket'];
$totalSeksi1 = $statCounts['seksi_1'] + $statCounts['ditolak_ke_seksi1'];
$totalSeksi2 = $statCounts['seksi_2'];
$totalSelesai = $statCounts['selesai'];

$nowStr = date('Y-m-d H:i:s');
$warningLimitStr = date('Y-m-d H:i:s', strtotime('+1 day'));

// Hitung total berkas dalam status Warning (kurang dari 1 hari / 24 jam dan belum terlewat, khusus berkas aktif belum selesai)
$stmtWarn = $conn->prepare("
    SELECT COUNT(*) FROM berkas 
    WHERE status_posisi IN ('loket','seksi_1','seksi_2','ditolak_ke_loket','ditolak_ke_seksi1')
      AND deadline_at IS NOT NULL 
      AND (
          (status_posisi = 'ditolak_ke_loket' AND sisa_sla_detik IS NOT NULL AND sisa_sla_detik >= 0 AND sisa_sla_detik <= 86400)
          OR (status_posisi != 'ditolak_ke_loket' AND deadline_at >= ? AND deadline_at <= ?)
      )
");
$stmtWarn->execute([$nowStr, $warningLimitStr]);
$totalWarningSla = (int) $stmtWarn->fetchColumn();

// Hitung total berkas yang terlewat batas waktu (SLA > 2 hari, khusus berkas aktif belum selesai)
$stmtLate = $conn->prepare("
    SELECT COUNT(*) FROM berkas 
    WHERE status_posisi IN ('loket','seksi_1','seksi_2','ditolak_ke_loket','ditolak_ke_seksi1')
      AND deadline_at IS NOT NULL 
      AND (
          (status_posisi = 'ditolak_ke_loket' AND sisa_sla_detik IS NOT NULL AND sisa_sla_detik < 0)
          OR (status_posisi != 'ditolak_ke_loket' AND deadline_at < ?)
      )
");
$stmtLate->execute([$nowStr]);
$totalTerlewatSla = (int) $stmtLate->fetchColumn();

$totalUserAktif = (int) $conn->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();

// Filter posisi berkas & Pengurutan (Sort)
$posisiFil = $_GET['posisi'] ?? 'semua';
$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'lama'; // 'lama' = terlama ke terbaru, 'baru' = terbaru ke terlama

$where = [];
$params = [];

if ($posisiFil === 'loket') {
    $where[] = "b.status_posisi IN ('loket', 'ditolak_ke_loket')";
} elseif ($posisiFil === 'seksi_1') {
    $where[] = "b.status_posisi IN ('seksi_1', 'ditolak_ke_seksi1')";
} elseif ($posisiFil === 'seksi_2') {
    $where[] = "b.status_posisi = 'seksi_2'";
} elseif ($posisiFil === 'selesai') {
    $where[] = "b.status_posisi = 'selesai'";
} elseif ($posisiFil === 'warning') {
    $where[] = "b.status_posisi IN ('loket','seksi_1','seksi_2','ditolak_ke_loket','ditolak_ke_seksi1') AND b.deadline_at IS NOT NULL AND (
        (b.status_posisi = 'ditolak_ke_loket' AND b.sisa_sla_detik IS NOT NULL AND b.sisa_sla_detik >= 0 AND b.sisa_sla_detik <= 86400)
        OR (b.status_posisi != 'ditolak_ke_loket' AND b.deadline_at >= ? AND b.deadline_at <= ?)
    )";
    $params[] = $nowStr;
    $params[] = $warningLimitStr;
} elseif ($posisiFil === 'terlewat') {
    $where[] = "b.status_posisi IN ('loket','seksi_1','seksi_2','ditolak_ke_loket','ditolak_ke_seksi1') AND b.deadline_at IS NOT NULL AND (
        (b.status_posisi = 'ditolak_ke_loket' AND b.sisa_sla_detik IS NOT NULL AND b.sisa_sla_detik < 0)
        OR (b.status_posisi != 'ditolak_ke_loket' AND b.deadline_at < ?)
    )";
    $params[] = $nowStr;
}

if ($q !== '') {
    $where[] = "(b.nomor_pendaftaran LIKE ? OR b.nama_pemohon LIKE ? OR b.jenis_layanan LIKE ? OR b.sertifikat_desa LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Pengurutan berkas (Sort) berdasarkan waktu input
if ($sort === 'baru') {
    // Paling baru di-input (terbaru -> terlama)
    $orderSql = "ORDER BY b.created_at DESC, b.id DESC";
} else {
    // Paling lama di-input (terlama -> terbaru) - default
    $sort = 'lama';
    $orderSql = "ORDER BY b.created_at ASC, b.id ASC";
}

$trackingQuery = $conn->prepare("
    SELECT b.*, u.nama_lengkap AS nama_penginput,
        up.nama_lengkap AS pemegang_nama, up.role AS pemegang_role,
        (SELECT CONCAT(us.nama_lengkap, IF(us.sub_bagian IS NOT NULL, CONCAT(' (Bagian ', us.sub_bagian, ')'), '')) FROM log_pergerakan l LEFT JOIN users us ON us.id = l.pengirim_id WHERE l.id_berkas = b.id ORDER BY l.created_at DESC, l.id DESC LIMIT 1) AS pemroses_terakhir,
        (SELECT l.catatan FROM log_pergerakan l WHERE l.id_berkas = b.id ORDER BY l.created_at DESC, l.id DESC LIMIT 1) AS catatan_terakhir
    FROM berkas b
    LEFT JOIN users u ON u.id = b.diinput_oleh
    LEFT JOIN users up ON up.id = b.petugas_tujuan_id
    $whereSql
    $orderSql
");
$trackingQuery->execute($params);
$trackingList = $trackingQuery->fetchAll();

// Aktivitas terbaru
$recentLogs = $conn->query("
    SELECT l.*, b.nomor_pendaftaran, b.nama_pemohon, us.nama_lengkap AS nama_pengirim, us.sub_bagian AS sub_bagian_pengirim, us.role AS role_pengirim
    FROM log_pergerakan l
    JOIN berkas b ON b.id = l.id_berkas
    LEFT JOIN users us ON us.id = l.pengirim_id
    ORDER BY l.created_at DESC, l.id DESC
    LIMIT 6
")->fetchAll();

$pageTitle = 'Dashboard Monitoring & Tracking Berkas Pradaftar';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Dashboard Monitoring &amp; Tracking Berkas Pradaftar</h1>
        <p>Pantau posisi, pemegang berkas, batas waktu pengerjaan (maks. 2 hari), dan status pergerakan secara real-time di Loket, Survei &amp; Pemetaan, Penetapan Hak &amp; Pendaftaran, hingga Selesai.</p>
    </div>
</div>


<!-- Kartu Ringkasan Posisi Berkas -->
<div class="row g-3 mb-4" id="statCardRow">
    <div class="col-6 col-md-4 col-xl">
        <a href="?posisi=semua<?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="d-block h-100 text-decoration-none stat-link" data-posisi="semua">
            <div class="stat-card <?= $posisiFil === 'semua' ? 'active' : '' ?>" style="--stat-color:#5D6D7E">
                <div class="stat-icon"><i class="bi bi-folder2-open"></i></div>
                <div><div class="stat-value"><?= $totalBerkas ?></div><div class="stat-label">Total Berkas</div></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <a href="?posisi=loket<?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="d-block h-100 text-decoration-none stat-link" data-posisi="loket">
            <div class="stat-card <?= $posisiFil === 'loket' ? 'active' : '' ?>" style="--stat-color:#0D6EFD">
                <div class="stat-icon"><i class="bi bi-inbox"></i></div>
                <div><div class="stat-value"><?= $totalLoket ?></div><div class="stat-label">Loket</div></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <a href="?posisi=seksi_1<?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="d-block h-100 text-decoration-none stat-link" data-posisi="seksi_1">
            <div class="stat-card <?= $posisiFil === 'seksi_1' ? 'active' : '' ?>" style="--stat-color:#0D6EFD">
                <div class="stat-icon"><i class="bi bi-file-earmark-check"></i></div>
                <div><div class="stat-value"><?= $totalSeksi1 ?></div><div class="stat-label">Survei &amp; Pemetaan</div></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <a href="?posisi=seksi_2<?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="d-block h-100 text-decoration-none stat-link" data-posisi="seksi_2">
            <div class="stat-card <?= $posisiFil === 'seksi_2' ? 'active' : '' ?>" style="--stat-color:#0D6EFD">
                <div class="stat-icon"><i class="bi bi-file-earmark-check"></i></div>
                <div><div class="stat-value"><?= $totalSeksi2 ?></div><div class="stat-label">Penetapan Hak &amp; Pendaftaran</div></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <a href="?posisi=selesai<?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="d-block h-100 text-decoration-none stat-link" data-posisi="selesai">
            <div class="stat-card <?= $posisiFil === 'selesai' ? 'active' : '' ?>" style="--stat-color:#1E8A5F">
                <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
                <div><div class="stat-value text-success"><?= $totalSelesai ?></div><div class="stat-label">Selesai</div></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <a href="?posisi=warning<?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="d-block h-100 text-decoration-none stat-link" data-posisi="warning">
            <div class="stat-card <?= $posisiFil === 'warning' ? 'active' : ($totalWarningSla > 0 ? 'border-warning' : '') ?>" style="--stat-color:#D97706">
                <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                <div><div class="stat-value text-warning-emphasis"><?= $totalWarningSla ?></div><div class="stat-label">Waspada</div></div>
            </div>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-xl">
        <a href="?posisi=terlewat<?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="d-block h-100 text-decoration-none stat-link" data-posisi="terlewat">
            <div class="stat-card <?= $posisiFil === 'terlewat' ? 'active' : ($totalTerlewatSla > 0 ? 'border-danger' : '') ?>" style="--stat-color:#DC3545">
                <div class="stat-icon"><i class="bi bi-exclamation-octagon"></i></div>
                <div><div class="stat-value text-danger"><?= $totalTerlewatSla ?></div><div class="stat-label">Kritis</div></div>
            </div>
        </a>
    </div>
</div>

<!-- Card Utama: Tabel Tracking Semua Berkas -->
<div class="card mb-4 shadow-sm" id="trackingTableCard">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="fw-semibold fs-6">
            <i class="bi bi-geo-fill text-primary me-1"></i> Tracking Posisi Semua Berkas
        </div>
        <div class="w-100 w-lg-auto">
            <!-- Filter Tabs Posisi Unit -->
            <div class="filter-pill-wrapper" role="group" aria-label="Filter Posisi Berkas">
                <a href="?posisi=semua<?= $q ? '&q='.urlencode($q) : '' ?><?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="btn <?= $posisiFil === 'semua' ? 'btn-secondary text-white' : 'btn-outline-secondary' ?>">
                    Semua (<?= $totalBerkas ?>)
                </a>
                <a href="?posisi=loket<?= $q ? '&q='.urlencode($q) : '' ?><?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="btn <?= $posisiFil === 'loket' ? 'btn-primary text-white' : 'btn-outline-primary' ?>">
                    Loket (<?= $totalLoket ?>)
                </a>
                <a href="?posisi=seksi_1<?= $q ? '&q='.urlencode($q) : '' ?><?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="btn <?= $posisiFil === 'seksi_1' ? 'btn-primary text-white' : 'btn-outline-primary' ?>">
                    Survei &amp; Pemetaan (<?= $totalSeksi1 ?>)
                </a>
                <a href="?posisi=seksi_2<?= $q ? '&q='.urlencode($q) : '' ?><?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="btn <?= $posisiFil === 'seksi_2' ? 'btn-primary text-white' : 'btn-outline-primary' ?>">
                    Penetapan Hak &amp; Pendaftaran (<?= $totalSeksi2 ?>)
                </a>
                <a href="?posisi=selesai<?= $q ? '&q='.urlencode($q) : '' ?><?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="btn <?= $posisiFil === 'selesai' ? 'btn-success text-white' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-check-circle-fill me-1"></i>Selesai (<?= $totalSelesai ?>)
                </a>
                <a href="?posisi=warning<?= $q ? '&q='.urlencode($q) : '' ?><?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="btn <?= $posisiFil === 'warning' ? 'btn-warning text-dark fw-semibold' : 'btn-outline-warning text-dark' ?>" title="Berkas yang sisa waktu pengerjaannya kurang dari 1 hari (zona waspada kuning)">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>Waspada (<?= $totalWarningSla ?>)
                </a>
                <a href="?posisi=terlewat<?= $q ? '&q='.urlencode($q) : '' ?><?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="btn <?= $posisiFil === 'terlewat' ? 'btn-danger' : 'btn-outline-danger' ?>" title="Berkas yang melewati batas pengerjaan 2 hari (zona kritis)">
                    <i class="bi bi-exclamation-octagon-fill me-1"></i>Kritis (<?= $totalTerlewatSla ?>)
                </a>
            </div>
        </div>
    </div>

    <!-- Bar Pencarian & Fitur Sort -->
    <div class="card-body border-bottom bg-light py-2">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="posisi" value="<?= e($posisiFil) ?>">
            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            
            <div class="col-12 col-md-6 col-lg-7">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari nomor berkas, pemohon, layanan, sertifikat..." value="<?= e($q) ?>">
                    <?php if ($q !== ''): ?>
                        <a href="?posisi=<?= urlencode($posisiFil) ?><?= $sort !== 'lama' ? '&sort='.urlencode($sort) : '' ?>" class="btn btn-outline-secondary" title="Hapus pencarian"><i class="bi bi-x"></i></a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-outline-secondary" title="Cari"><i class="bi bi-search me-1"></i>Cari</button>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-5 d-flex justify-content-md-end align-items-center gap-2">
                <!-- Dropdown Sort Simple -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle bg-white d-flex align-items-center gap-1 shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Pilih urutan berkas">
                        <i class="bi bi-arrow-down-up text-primary me-1"></i>
                        <span>Urutan: <strong class="text-primary"><?= $sort === 'baru' ? 'Paling Baru di-Input' : 'Paling Lama di-Input' ?></strong></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm py-1 border" style="font-size: 0.85rem; min-width: 220px;">
                        <li><h6 class="dropdown-header text-muted py-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">PILIH URUTAN BERKAS</h6></li>
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center justify-content-between <?= $sort === 'lama' ? 'active fw-bold' : '' ?>" 
                               href="?posisi=<?= urlencode($posisiFil) ?><?= $q ? '&q='.urlencode($q) : '' ?>&sort=lama">
                                <span><i class="bi bi-sort-numeric-down me-2 <?= $sort === 'lama' ? 'text-white' : 'text-primary' ?>"></i>Paling Lama di-Input</span>
                                <?php if ($sort === 'lama'): ?><i class="bi bi-check2 fw-bold"></i><?php endif; ?>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 d-flex align-items-center justify-content-between <?= $sort === 'baru' ? 'active fw-bold' : '' ?>" 
                               href="?posisi=<?= urlencode($posisiFil) ?><?= $q ? '&q='.urlencode($q) : '' ?>&sort=baru">
                                <span><i class="bi bi-sort-numeric-down-alt me-2 <?= $sort === 'baru' ? 'text-white' : 'text-primary' ?>"></i>Paling Baru di-Input</span>
                                <?php if ($sort === 'baru'): ?><i class="bi bi-check2 fw-bold"></i><?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <?php if ($q !== '' || $sort !== 'lama' || $posisiFil !== 'semua'): ?>
                    <a href="index.php" class="btn btn-sm btn-outline-secondary text-nowrap" title="Reset pencarian dan urutan">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
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
                    <th style="min-width: 220px;">Posisi &amp; Pemegang Berkas</th>
                    <th>Batas Waktu</th>
                    <th>Update Terakhir</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($trackingList)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state py-4">
                                <i class="bi bi-inbox fs-2 text-muted"></i>
                                <div class="mt-2 text-muted">Tidak ada berkas yang sesuai dengan kriteria saat ini.</div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($trackingList as $b): 
                    $sla = hitungSla($b['deadline_at'], $b['updated_at'], $b['status_posisi']);
                    $isOverdue = ($b['status_posisi'] !== 'selesai') && $sla['is_overdue'];
                    $isWarning = ($b['status_posisi'] !== 'selesai') && !empty($sla['is_warning']);
                    $rowClass = $isOverdue ? 'table-danger' : ($isWarning ? 'table-warning' : '');
                ?>
                <tr class="<?= $rowClass ?>">
                    <td class="mono fw-semibold">
                        <a href="<?= baseUrl('detail.php?id=' . (int) $b['id']) ?>" class="text-decoration-none">
                            <?= e($b['nomor_pendaftaran']) ?>
                        </a>
                        <div class="text-muted fw-normal small" style="font-size: 0.73rem;" title="Waktu berkas didaftarkan">
                            <i class="bi bi-clock-history me-1"></i>Input: <?= formatTanggal($b['created_at']) ?>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= e($b['nama_pemohon']) ?></div>
                        <?php if (!empty($b['sertifikat_desa'])): ?>
                            <div class="text-muted small"><i class="bi bi-award me-1"></i><?= e($b['sertifikat_desa']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= e($b['jenis_layanan']) ?></td>
                    <td>
                        <div class="mb-1">
                            <?= statusBadge($b['status_posisi']) ?>
                        </div>
                        <?php if (!empty($b['pemegang_nama'])): ?>
                            <span class="text-muted d-block small">
                                <i class="bi bi-person-check text-primary me-1"></i><strong><?= e($b['pemegang_nama']) ?></strong>
                                <?php if (!empty($b['pemegang_role'])): ?>
                                    <span class="text-muted small">(<?= e(roleLabel($b['pemegang_role'])) ?>)</span>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted d-block small">
                                <i class="bi bi-people text-primary me-1"></i><?= e(pemegangBerkasLabel(null, $b['status_posisi'])) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($b['catatan_terakhir'])): ?>
                            <span class="text-muted d-block small mt-1 text-truncate" style="max-width: 260px;" title="<?= e($b['catatan_terakhir']) ?>">
                                <em>Catatan: <?= e($b['catatan_terakhir']) ?></em>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= slaBadge($b['deadline_at'], $b['status_posisi'], false, $b['sisa_sla_detik'] ?? null) ?>
                    </td>
                    <td class="small text-muted">
                        <div><?= formatTanggal($b['updated_at']) ?></div>
                        <?php if (!empty($b['pemroses_terakhir'])): ?>
                            <div class="small text-muted">Oleh: <?= e($b['pemroses_terakhir']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="action-btns justify-content-end">
                            <a href="<?= baseUrl('detail.php?id=' . (int) $b['id']) ?>" class="btn btn-detail">
                                <i class="bi bi-clock-history"></i>Detail
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bagian Grafik & Log Aktivitas -->
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100 shadow-sm">
            <div class="card-header fw-semibold">
                <i class="bi bi-bar-chart-line me-1 text-primary"></i> Distribusi Status Berkas
            </div>
            <div class="card-body">
                <div style="position: relative; height: 230px;">
                    <canvas id="chartStatus"></canvas>
                </div>
                <div class="row g-2 pt-3 mt-2 border-top text-center">
                    <div class="col-6 col-sm-3">
                        <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                            <span class="d-inline-block rounded-circle" style="width: 10px; height: 10px; background-color: #0D6EFD;"></span>
                            <span class="small text-muted fw-semibold">Loket</span>
                        </div>
                        <div class="fw-bold" style="color: #0D6EFD;"><?= $totalLoket ?> <span class="small fw-normal text-muted">Berkas</span></div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                            <span class="d-inline-block rounded-circle" style="width: 10px; height: 10px; background-color: #0D6EFD;"></span>
                            <span class="small text-muted fw-semibold">Seksi 1</span>
                        </div>
                        <div class="fw-bold" style="color: #0D6EFD;"><?= $totalSeksi1 ?> <span class="small fw-normal text-muted">Berkas</span></div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                            <span class="d-inline-block rounded-circle" style="width: 10px; height: 10px; background-color: #0D6EFD;"></span>
                            <span class="small text-muted fw-semibold">Seksi 2</span>
                        </div>
                        <div class="fw-bold" style="color: #0D6EFD;"><?= $totalSeksi2 ?> <span class="small fw-normal text-muted">Berkas</span></div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                            <span class="d-inline-block rounded-circle" style="width: 10px; height: 10px; background-color: #16A34A;"></span>
                            <span class="small text-muted fw-semibold">Selesai</span>
                        </div>
                        <div class="fw-bold text-success"><?= $totalSelesai ?> <span class="small fw-normal text-muted">Berkas</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-activity me-1 text-primary"></i> Aktivitas Terbaru</span>
                <?php
                $roleRiwayat = match($_SESSION['role'] ?? '') {
                    'loket'   => baseUrl('loket/riwayat.php'),
                    'seksi_1' => baseUrl('seksi1/riwayat.php'),
                    'seksi_2' => baseUrl('seksi2/riwayat.php'),
                    default   => baseUrl('admin/riwayat.php'),
                };
                ?>
                <a href="<?= $roleRiwayat ?>" class="small text-decoration-none">Lihat semua &rarr;</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentLogs)): ?>
                    <div class="empty-state py-4"><i class="bi bi-activity"></i>Belum ada aktivitas.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentLogs as $log): ?>
                        <li class="list-group-item px-3 py-2">
                            <div class="d-flex justify-content-between">
                                <span class="mono small fw-semibold text-primary"><?= e($log['nomor_pendaftaran']) ?></span>
                                <span class="text-muted small"><?= formatTanggal($log['created_at']) ?></span>
                            </div>
                            <div class="small text-muted"><?= e($log['nama_pemohon']) ?> &mdash; <?= e(aksiLabel($log['aksi'])) ?> oleh <?= e($log['nama_pengirim'] ?? 'Sistem') ?><?= !empty($log['sub_bagian_pengirim']) ? ' <span class="badge text-bg-primary-subtle text-primary border" style="font-size:10px;">Bagian ' . e($log['sub_bagian_pengirim']) . '</span>' : '' ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartStatus'), {
    type: 'bar',
    data: {
        labels: [
            'Loket',
            ['Seksi 1', 'Survei & Pemetaan'],
            ['Seksi 2', 'Penetapan & Pendaftaran'],
            'Selesai'
        ],
        datasets: [{
            label: 'Jumlah Berkas',
            data: [
                <?= $totalLoket ?>,
                <?= $totalSeksi1 ?>,
                <?= $totalSeksi2 ?>,
                <?= $totalSelesai ?>
            ],
            backgroundColor: ['#0D6EFD', '#0D6EFD', '#0D6EFD', '#16A34A'],
            hoverBackgroundColor: ['#0B5ED7', '#0B5ED7', '#0B5ED7', '#15803D'],
            borderRadius: 8,
            maxBarThickness: 52
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(15, 23, 42, 0.9)',
                padding: 10,
                cornerRadius: 8,
                callbacks: {
                    title: function(context) {
                        const titles = [
                            'Loket (Antrean Berkas)',
                            'Seksi 1 (Survei & Pemetaan)',
                            'Seksi 2 (Penetapan Hak & Pendaftaran)',
                            'Berkas Selesai'
                        ];
                        return titles[context[0].dataIndex] || context[0].label;
                    },
                    label: function(context) {
                        return ' ' + context.parsed.y + ' Berkas';
                    }
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: {
                    maxRotation: 0,
                    minRotation: 0,
                    autoSkip: false,
                    color: '#475569',
                    font: {
                        size: 11,
                        weight: '600',
                        family: "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"
                    }
                }
            },
            y: {
                beginAtZero: true,
                grace: 1,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                },
                ticks: {
                    precision: 0,
                    color: '#64748B',
                    font: {
                        size: 11,
                        family: "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"
                    }
                }
            }
        }
    },
    plugins: [{
        id: 'barValues',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            chart.data.datasets.forEach((dataset, i) => {
                const meta = chart.getDatasetMeta(i);
                meta.data.forEach((bar, index) => {
                    const val = dataset.data[index];
                    if (val > 0) {
                        ctx.fillStyle = '#0F172A';
                        ctx.font = 'bold 12px system-ui, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        ctx.fillText(val, bar.x, bar.y - 4);
                    }
                });
            });
        }
    }]
});

// Dynamic filtering for dashboard stat cards & table tabs (preserves smooth floating transition without page reload)
document.addEventListener('DOMContentLoaded', function() {
    const tableCard = document.getElementById('trackingTableCard');
    const statRow   = document.getElementById('statCardRow');
    if (!tableCard || !statRow) return;

    function applyActiveCard(posisi) {
        if (!posisi) return;
        statRow.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
        const link = statRow.querySelector('a[data-posisi="' + posisi + '"]');
        if (link) {
            const card = link.querySelector('.stat-card');
            if (card) card.classList.add('active');
        }
    }

    function loadTrackingAjax(url, posisi) {
        // Immediate visual update for the stat cards so it lifts/floats instantly without wait
        applyActiveCard(posisi);

        tableCard.style.transition = 'opacity 0.15s ease';
        tableCard.style.opacity = '0.5';

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newTable = doc.getElementById('trackingTableCard');
                if (newTable) {
                    tableCard.innerHTML = newTable.innerHTML;
                }
                tableCard.style.opacity = '1';
                window.history.pushState({ posisi: posisi }, '', url);
            })
            .catch(() => {
                window.location.href = url;
            });
    }

    statRow.addEventListener('click', function(e) {
        const link = e.target.closest('a.stat-link');
        if (!link) return;
        e.preventDefault();
        const url = link.getAttribute('href');
        const posisi = link.getAttribute('data-posisi');
        loadTrackingAjax(url, posisi);
    });

    tableCard.addEventListener('click', function(e) {
        const link = e.target.closest('.card-header .filter-pill-wrapper a, .card-header .btn-group a, .dropdown-menu a.dropdown-item');
        if (!link) return;
        const href = link.getAttribute('href');
        if (href && href.startsWith('?posisi=')) {
            e.preventDefault();
            const u = new URL(link.href);
            const posisi = u.searchParams.get('posisi') || 'semua';
            loadTrackingAjax(href, posisi);
        }
    });

    window.addEventListener('popstate', function() {
        window.location.reload();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
