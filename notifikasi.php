<?php
/**
 * Halaman Riwayat Semua Pemberitahuan
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';
$tab    = $_GET['tab'] ?? 'semua';

// Handle aksi tandai semua dibaca via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_mark_all'])) {
    if (verifyCsrf()) {
        tandaiSemuaNotifikasiDibaca($conn, $userId, $role);
        setFlash('success', 'Semua pemberitahuan berhasil ditandai sebagai sudah dibaca.');
    }
    header('Location: notifikasi.php?tab=' . urlencode($tab));
    exit;
}

$whereClause = "(n.target_role = ? OR n.target_role = 'all' OR n.target_user_id = ?) AND (n.target_user_id IS NULL OR n.target_user_id = ?)";
$params = [$userId, $role, $userId, $userId];

if ($tab === 'unread') {
    $whereClause .= " AND nr.id IS NULL";
} elseif ($tab === 'masuk') {
    $whereClause .= " AND n.tipe = 'masuk'";
} elseif ($tab === 'kembali') {
    $whereClause .= " AND n.tipe = 'kembali'";
}

$stmt = $conn->prepare("
    SELECT n.*, 
           (nr.id IS NOT NULL) AS is_read,
           b.nomor_pendaftaran,
           b.nama_pemohon
    FROM notifikasi n
    LEFT JOIN notifikasi_read nr ON nr.notifikasi_id = n.id AND nr.user_id = ?
    LEFT JOIN berkas b ON b.id = n.id_berkas
    WHERE $whereClause
    ORDER BY n.created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$notifikasiList = $stmt->fetchAll();

$totalUnread = hitungNotifikasiBelumDibaca($conn, $userId, $role);

$pageTitle = 'Semua Pemberitahuan';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Semua Pemberitahuan</h1>
        <p>Pantau seluruh aktivitas berkas masuk, tindak lanjut, dan peringatan batas waktu.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <?php if ($totalUnread > 0): ?>
            <form action="notifikasi.php?tab=<?= urlencode($tab) ?>" method="POST" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action_mark_all" value="1">
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-check-all me-1"></i>Tandai Semua Dibaca
                </button>
            </form>
        <?php endif; ?>
        <a href="javascript:history.back()" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs card-header-tabs m-0 px-3 border-0">
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'semua' ? 'active fw-bold' : '' ?> py-3" href="notifikasi.php?tab=semua">
                    Semua
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'unread' ? 'active fw-bold' : '' ?> py-3" href="notifikasi.php?tab=unread">
                    Belum Dibaca
                    <?php if ($totalUnread > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-1"><?= $totalUnread ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'masuk' ? 'active fw-bold' : '' ?> py-3" href="notifikasi.php?tab=masuk">
                    <i class="bi bi-box-arrow-in-down-right me-1 text-primary"></i>Berkas Masuk
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'kembali' ? 'active fw-bold' : '' ?> py-3" href="notifikasi.php?tab=kembali">
                    <i class="bi bi-arrow-return-left me-1 text-warning"></i>Pengembalian / Perbaikan
                </a>
            </li>
        </ul>
    </div>

    <div class="card-body p-0">
        <?php if (empty($notifikasiList)): ?>
            <div class="text-center py-5">
                <i class="bi bi-bell-slash text-muted" style="font-size: 2.5rem;"></i>
                <div class="fw-semibold mt-2 text-muted">Tidak ada pemberitahuan pada kategori ini.</div>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifikasiList as $item): 
                    [$icon, $textColor, $bgColor, $styleAttr] = notifStyle($item['tipe']);
                    $isUnread = !(bool)$item['is_read'];
                ?>
                <a href="notifikasi_baca.php?id=<?= (int)$item['id'] ?>" 
                   class="list-group-item list-group-item-action p-3 d-flex align-items-start gap-3 transition-all <?= $isUnread ? 'bg-light-subtle' : '' ?>"
                   style="<?= $isUnread ? 'border-left: 4px solid #0D6EFD; background-color: #F8FAFC;' : '' ?>">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" 
                         <?= $styleAttr ?>
                         style="width: 42px; height: 42px; font-size: 1.15rem;">
                        <i class="bi <?= $icon ?>"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="mb-0 fw-bold <?= $isUnread ? 'text-dark' : 'text-secondary' ?>">
                                <?= e($item['judul']) ?>
                                <?php if ($isUnread): ?>
                                    <span class="badge text-bg-primary ms-1" style="font-size: 0.65rem;">Baru</span>
                                <?php endif; ?>
                            </h6>
                            <span class="small text-muted" title="<?= formatTanggal($item['created_at']) ?>">
                                <i class="bi bi-clock me-1"></i><?= waktuLalu($item['created_at']) ?>
                            </span>
                        </div>
                        <p class="mb-1 small text-muted text-break" style="line-height: 1.45;">
                            <?= e($item['pesan']) ?>
                        </p>
                        <?php if (!empty($item['nomor_pendaftaran'])): ?>
                            <div class="mt-1">
                                <span class="badge text-bg-secondary mono" style="font-size: 0.72rem;">
                                    <?= e($item['nomor_pendaftaran']) ?>
                                </span>
                                <?php if (!empty($item['nama_pemohon'])): ?>
                                    <span class="small text-muted ms-1">&bull; <?= e($item['nama_pemohon']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <i class="bi bi-chevron-right text-muted opacity-50"></i>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
