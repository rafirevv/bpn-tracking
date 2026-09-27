<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['seksi_1']);

$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

// Hanya menampilkan berkas yang sudah selesai dan telah diserahkan ke pemohon
$where  = [
    "b.status_posisi = 'selesai'",
    "b.is_diterima_loket = 1"
];
$params = [];
if ($q !== '') {
    $where[] = "(b.nomor_pendaftaran LIKE ? OR b.nama_pemohon LIKE ? OR b.jenis_layanan LIKE ? OR b.sertifikat_desa LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = $conn->prepare("SELECT COUNT(*) FROM berkas b $whereSql");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $conn->prepare("
    SELECT b.* FROM berkas b
    $whereSql
    ORDER BY b.updated_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$list = $stmt->fetchAll();

$pageTitle = 'Riwayat Berkas Selesai';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Riwayat Berkas Selesai</h1>
        <p>Daftar berkas yang sudah selesai diproses dan telah diserahkan kepada pemohon.</p>
    </div>
</div>

<form class="filter-bar row g-2" method="GET">
    <div class="col-md-10">
        <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Cari nomor berkas, nama pemohon, jenis layanan, atau sertifikat/desa..." value="<?= e($q) ?>">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i> Cari</button>
        </div>
    </div>
    <div class="col-md-2">
        <a href="riwayat.php" class="btn btn-outline-secondary w-100">Reset</a>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead>
                <tr>
                    <th>No. Berkas</th>
                    <th>Nama Pemohon</th>
                    <th>Jenis Layanan</th>
                    <th>Status Akhir</th>
                    <th>Penyerahan</th>
                    <th>Tanggal Selesai</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($list)): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-check2-circle"></i>Belum ada riwayat berkas yang telah selesai dan diserahkan.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($list as $b): ?>
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
                    <td><span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Diserahkan</span></td>
                    <td class="small text-muted"><?= formatTanggal($b['updated_at']) ?></td>
                    <td class="text-end"><a href="<?= baseUrl('detail.php?id=' . (int) $b['id']) ?>" class="btn btn-sm btn-outline-primary">Detail</a></td>
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
            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $i ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
