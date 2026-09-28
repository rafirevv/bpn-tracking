<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['loket']);

$q       = trim($_GET['q'] ?? '');
$sort    = trim($_GET['sort'] ?? 'default');
if (!in_array($sort, ['default', 'selesai', 'dikembalikan'], true)) {
    $sort = 'default';
}

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

// Menampilkan berkas yang sudah selesai atau telah dikembalikan ke pemohon
$where  = [
    "b.status_posisi IN ('selesai', 'ditolak_ke_loket')",
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

$orderSql = match ($sort) {
    'selesai'      => "ORDER BY (b.status_posisi = 'selesai') DESC, b.updated_at DESC, b.id DESC",
    'dikembalikan' => "ORDER BY (b.status_posisi = 'ditolak_ke_loket') DESC, b.updated_at DESC, b.id DESC",
    default        => "ORDER BY b.updated_at DESC, b.id DESC",
};

$stmt = $conn->prepare("
    SELECT b.* FROM berkas b
    $whereSql
    $orderSql
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$list = $stmt->fetchAll();

$pageTitle = 'Riwayat Berkas';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Riwayat Berkas</h1>
        <p>Daftar berkas yang telah diserahkan atau dikembalikan kepada pemohon.</p>
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
        <select name="sort" class="form-select" onchange="this.form.submit()">
            <option value="default" <?= $sort === 'default' ? 'selected' : '' ?>>Urutkan: Waktu Masuk Riwayat (Default)</option>
            <option value="selesai" <?= $sort === 'selesai' ? 'selected' : '' ?>>Urutkan: Berkas Selesai</option>
            <option value="dikembalikan" <?= $sort === 'dikembalikan' ? 'selected' : '' ?>>Urutkan: Kembali ke Pemohon</option>
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
                    <th>Status Akhir</th>
                    <th>Penyerahan</th>
                    <th>Tanggal</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($list)): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox"></i>Belum ada riwayat berkas.</div></td></tr>
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
                    <td><?= statusBadge($b['status_posisi'], (int)$b['is_diterima_loket']) ?></td>
                    <td>
                        <?php if ($b['status_posisi'] === 'ditolak_ke_loket'): ?>
                            <span class="badge text-bg-danger"><i class="bi bi-arrow-return-left me-1"></i>Dikembalikan</span>
                        <?php else: ?>
                            <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Diserahkan</span>
                        <?php endif; ?>
                    </td>
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
                <a class="page-link" href="?q=<?= urlencode($q) ?>&sort=<?= urlencode($sort) ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
