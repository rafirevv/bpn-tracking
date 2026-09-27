<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);

$q         = trim($_GET['q'] ?? '');
$statusFil = $_GET['status'] ?? 'selesai'; // default riwayat: selesai

// Normalisasi alias status lama bila diakses via link/bookmark lama
if ($statusFil === 'ditolak_ke_seksi1') {
    $statusFil = 'seksi_1';
} elseif ($statusFil === 'ditolak_ke_loket') {
    $statusFil = 'loket';
} elseif ($statusFil === 'closed') {
    $statusFil = 'selesai';
}

$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = 10;

$where  = [];
$params = [];

if ($statusFil === 'seksi_1') {
    $where[] = "b.status_posisi IN ('seksi_1', 'ditolak_ke_seksi1')";
} elseif ($statusFil === 'seksi_2') {
    $where[] = "b.status_posisi = 'seksi_2'";
} elseif ($statusFil === 'loket') {
    $where[] = "b.status_posisi IN ('loket', 'ditolak_ke_loket')";
} elseif ($statusFil === 'selesai') {
    $where[] = "b.status_posisi = 'selesai'";
} elseif ($statusFil === 'all') {
    // Tampilkan semua status
} else {
    $statusFil = 'selesai';
    $where[] = "b.status_posisi = 'selesai'";
}

if ($q !== '') {
    $where[] = "(b.nomor_pendaftaran LIKE ? OR b.nama_pemohon LIKE ? OR b.jenis_layanan LIKE ? OR b.sertifikat_desa LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $conn->prepare("SELECT COUNT(*) FROM berkas b $whereSql");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT b.*, u.nama_lengkap AS nama_penginput
    FROM berkas b
    LEFT JOIN users u ON u.id = b.diinput_oleh
    $whereSql
    ORDER BY b.updated_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$berkasList = $stmt->fetchAll();

$pageTitle = 'Riwayat Berkas';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Riwayat Berkas</h1>
        <p>Seluruh berkas yang telah selesai maupun pernah ditolak/dikembalikan, di seluruh unit.</p>
    </div>
</div>

<form class="filter-bar row g-2 align-items-center" method="GET">
    <div class="col-md-6">
        <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Cari nomor berkas, nama pemohon, jenis layanan, atau sertifikat/desa..." value="<?= e($q) ?>">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i> Cari</button>
        </div>
    </div>
    <div class="col-md-4">
        <select name="status" class="form-select" onchange="this.form.submit()">
            <option value="selesai" <?= $statusFil === 'selesai' ? 'selected' : '' ?>>Selesai</option>
            <option value="all" <?= $statusFil === 'all' ? 'selected' : '' ?>>Semua Status</option>
            <option value="loket" <?= $statusFil === 'loket' ? 'selected' : '' ?>>Sedang di Loket</option>
            <option value="seksi_1" <?= $statusFil === 'seksi_1' ? 'selected' : '' ?>>Sedang di Seksi 1 (Survei &amp; Pemetaan)</option>
            <option value="seksi_2" <?= $statusFil === 'seksi_2' ? 'selected' : '' ?>>Sedang di Seksi 2 (Penetapan Hak &amp; Pendaftaran)</option>
        </select>
    </div>
    <div class="col-md-2">
        <a href="riwayat.php" class="btn btn-outline-secondary w-100">Reset</a>
    </div>
</form>

<div class="card">
    <div class="table-scroll-hint">
        <i class="bi bi-arrows-expand-vertical" style="transform: rotate(90deg);"></i> Geser tabel ke samping untuk melihat kolom selengkapnya
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead>
                <tr>
                    <th>No. Berkas</th>
                    <th>Nama Pemohon</th>
                    <th>Jenis Layanan</th>
                    <th>Status</th>
                    <th>Diinput Oleh</th>
                    <th>Update Terakhir</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($berkasList)): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox"></i>Tidak ada data yang cocok.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($berkasList as $b): ?>
                <tr>
                    <td class="mono fw-semibold"><?= e($b['nomor_pendaftaran']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($b['nama_pemohon']) ?></div>
                        <?php if (!empty($b['sertifikat_desa'])): ?>
                            <div class="text-muted small"><i class="bi bi-award me-1"></i><?= e($b['sertifikat_desa']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= e($b['jenis_layanan']) ?></td>
                    <td><?= statusBadge($b['status_posisi']) ?></td>
                    <td class="small"><?= e($b['nama_penginput'] ?? '-') ?></td>
                    <td class="small text-muted"><?= formatTanggal($b['updated_at']) ?></td>
                    <td class="text-end">
                        <a href="<?= baseUrl('detail.php?id=' . (int) $b['id']) ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?q=<?= urlencode($q) ?>&status=<?= urlencode($statusFil) ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
