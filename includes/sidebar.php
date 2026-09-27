<?php
/**
 * Sidebar menu dinamis berdasarkan role user yang sedang login.
 * Membutuhkan $conn (config/database.php) dan session aktif.
 */
$role = $_SESSION['role'] ?? '';
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

function navActive(string $needle, string $currentPath): string
{
    return (strpos($currentPath, $needle) !== false) ? 'active' : '';
}

// Hitung badge notifikasi tugas tertunda sesuai role
$pendingCount = 0;
try {
    if ($role === 'loket') {
        $pendingCount = (int) $conn->query("
            SELECT COUNT(*) FROM berkas 
            WHERE (status_posisi = 'ditolak_ke_loket' AND is_diterima_loket = 0)
               OR (status_posisi = 'loket')
        ")->fetchColumn();
    } elseif ($role === 'seksi_1') {
        $pendingCount = (int) $conn->query("SELECT COUNT(*) FROM berkas WHERE status_posisi IN ('seksi_1','ditolak_ke_seksi1')")->fetchColumn();
    } elseif ($role === 'seksi_2') {
        $pendingCount = (int) $conn->query("SELECT COUNT(*) FROM berkas WHERE status_posisi = 'seksi_2'")->fetchColumn();
    }
} catch (Throwable $e) {
    $pendingCount = 0;
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-mobile-head">
        <div class="d-flex align-items-center gap-2">
            <img src="<?= baseUrl('assets/img/logo-bpn-white.png') ?>" alt="Logo ATR/BPN" style="width: 28px; height: 28px; object-fit: contain;">
            <div class="d-flex flex-column text-white">
                <span class="fw-bold" style="font-size: 0.95rem; letter-spacing: 0.02em;">SITRACK</span>
                <span class="text-white-50" style="font-size: 0.68rem;">ATR/BPN</span>
            </div>
        </div>
        <button type="button" class="btn-close-sidebar" id="sidebarCloseBtn" aria-label="Tutup menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <nav class="sidebar-nav">

        <?php if ($role === 'admin'): ?>
            <div class="nav-section">Monitoring</div>
            <a class="nav-link <?= navActive('/admin/index.php', $currentPath) ?>" href="<?= baseUrl('admin/index.php') ?>">
                <i class="bi bi-speedometer2"></i><span>Dashboard</span>
            </a>
            <div class="nav-section">Manajemen</div>
            <a class="nav-link <?= navActive('/admin/users.php', $currentPath) ?>" href="<?= baseUrl('admin/users.php') ?>">
                <i class="bi bi-people"></i><span>Manajemen User</span>
            </a>
            <a class="nav-link <?= navActive('/admin/riwayat.php', $currentPath) ?>" href="<?= baseUrl('admin/riwayat.php') ?>">
                <i class="bi bi-clock-history"></i><span>Riwayat Berkas</span>
            </a>

        <?php elseif ($role === 'loket'): ?>
            <div class="nav-section">Monitoring</div>
            <a class="nav-link <?= navActive('/admin/index.php', $currentPath) ?>" href="<?= baseUrl('admin/index.php') ?>">
                <i class="bi bi-speedometer2"></i><span>Dashboard</span>
            </a>
            <div class="nav-section">Loket</div>
            <a class="nav-link <?= navActive('/loket/index.php', $currentPath) ?>" href="<?= baseUrl('loket/index.php') ?>">
                <i class="bi bi-speedometer2"></i><span>Antrean Tugas</span>
                <?php if ($pendingCount > 0): ?><span class="nav-badge"><?= $pendingCount ?></span><?php endif; ?>
            </a>
            <a class="nav-link <?= navActive('/loket/tambah.php', $currentPath) ?>" href="<?= baseUrl('loket/tambah.php') ?>">
                <i class="bi bi-file-earmark-plus"></i><span>Input Berkas Baru</span>
            </a>
            <div class="nav-section">Arsip</div>
            <a class="nav-link <?= navActive('/loket/riwayat.php', $currentPath) ?>" href="<?= baseUrl('loket/riwayat.php') ?>">
                <i class="bi bi-clock-history"></i><span>Riwayat Berkas</span>
            </a>

        <?php elseif ($role === 'seksi_1'): ?>
            <div class="nav-section">Monitoring</div>
            <a class="nav-link <?= navActive('/admin/index.php', $currentPath) ?>" href="<?= baseUrl('admin/index.php') ?>">
                <i class="bi bi-speedometer2"></i><span>Dashboard</span>
            </a>
            <div class="nav-section">Survei &amp; Pemetaan</div>
            <a class="nav-link <?= navActive('/seksi1/index.php', $currentPath) ?>" href="<?= baseUrl('seksi1/index.php') ?>">
                <i class="bi bi-inboxes"></i><span>Antrean Berkas</span>
                <?php if ($pendingCount > 0): ?><span class="nav-badge"><?= $pendingCount ?></span><?php endif; ?>
            </a>
            <div class="nav-section">Arsip</div>
            <a class="nav-link <?= navActive('/seksi1/riwayat.php', $currentPath) ?>" href="<?= baseUrl('seksi1/riwayat.php') ?>">
                <i class="bi bi-clock-history"></i><span>Riwayat Berkas</span>
            </a>

        <?php elseif ($role === 'seksi_2'): ?>
            <div class="nav-section">Monitoring</div>
            <a class="nav-link <?= navActive('/admin/index.php', $currentPath) ?>" href="<?= baseUrl('admin/index.php') ?>">
                <i class="bi bi-speedometer2"></i><span>Dashboard</span>
            </a>
            <div class="nav-section">Penetapan Hak &amp; Pendaftaran</div>
            <a class="nav-link <?= navActive('/seksi2/index.php', $currentPath) ?>" href="<?= baseUrl('seksi2/index.php') ?>">
                <i class="bi bi-inboxes"></i><span>Antrean Berkas</span>
                <?php if ($pendingCount > 0): ?><span class="nav-badge"><?= $pendingCount ?></span><?php endif; ?>
            </a>
            <div class="nav-section">Arsip</div>
            <a class="nav-link <?= navActive('/seksi2/riwayat.php', $currentPath) ?>" href="<?= baseUrl('seksi2/riwayat.php') ?>">
                <i class="bi bi-clock-history"></i><span>Riwayat Berkas</span>
            </a>
        <?php endif; ?>

        <div class="nav-section">Akun</div>
        <a class="nav-link text-danger" href="<?= baseUrl('auth/logout.php') ?>">
            <i class="bi bi-box-arrow-right"></i><span>Keluar</span>
        </a>
    </nav>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<main class="main-content">
<div class="container-fluid page-container">

<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
