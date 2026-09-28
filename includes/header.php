<?php
// $pageTitle harus di-set oleh halaman pemanggil sebelum include file ini.
$pageTitle = $pageTitle ?? 'Dashboard';
$user = currentUser();
$flash = getFlash();

global $conn;
if (!isset($conn)) {
    require_once __DIR__ . '/../config/database.php';
}

$notifUnreadCount = 0;
$recentNotifs = [];
if ($user && isset($conn)) {
    $notifUnreadCount = hitungNotifikasiBelumDibaca($conn, (int)$user['id'], $user['role']);
    $recentNotifs = ambilNotifikasiUser($conn, (int)$user['id'], $user['role'], 8);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · SITRACK BPN</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= baseUrl('assets/css/style.css') ?>?v=<?= time() ?>" rel="stylesheet">
<script>
    window.BASE_URL = '<?= rtrim(baseUrl(), '/') ?>/';
</script>
<style>
/* Robust In-App Notification Styling */
.btn-notif {
    width: 38px !important;
    height: 38px !important;
    border-radius: 50% !important;
    background: rgba(255, 255, 255, 0.12) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
    color: #FFFFFF !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0 !important;
    position: relative !important;
    transition: all 0.2s ease !important;
    box-shadow: 0 2px 5px rgba(0,0,0,0.12) !important;
    cursor: pointer !important;
}
.btn-notif:hover, .btn-notif:focus {
    background: rgba(255, 255, 255, 0.22) !important;
    border-color: rgba(255, 255, 255, 0.45) !important;
    color: #FFFFFF !important;
    outline: none !important;
}
.btn-notif i {
    font-size: 1.1rem !important;
    line-height: 1 !important;
}
.btn-notif::after {
    display: none !important;
}
.notif-badge {
    position: absolute !important;
    top: -3px !important;
    right: -3px !important;
    background: #EF4444 !important;
    color: #FFFFFF !important;
    font-size: 0.65rem !important;
    font-weight: 800 !important;
    line-height: 1 !important;
    padding: 3px 6px !important;
    min-width: 18px !important;
    text-align: center !important;
    border-radius: 999px !important;
    border: 2px solid #0E2A47 !important;
    box-shadow: 0 0 10px rgba(239, 68, 68, 0.7) !important;
    animation: notifPulse 2.2s infinite ease-in-out !important;
}
@keyframes notifPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.15); }
}

.notif-dropdown-menu {
    width: 390px !important;
    min-width: 280px !important;
    max-width: calc(100vw - 20px) !important;
    border-radius: 14px !important;
    border: 1px solid rgba(0, 0, 0, 0.08) !important;
    box-shadow: 0 18px 38px -10px rgba(15, 23, 42, 0.25), 0 0 1px 1px rgba(0,0,0,0.05) !important;
    padding: 0 !important;
    margin-top: 10px !important;
    overflow: hidden !important;
}
@media (max-width: 575.98px) {
    .notif-dropdown-menu {
        width: calc(100vw - 20px) !important;
        max-width: 360px !important;
        min-width: 280px !important;
        right: -8px !important;
        left: auto !important;
    }
}
.notif-header {
    padding: 12px 18px !important;
    background: #F8FAFC !important;
    border-bottom: 1px solid #E2E8F0 !important;
}
.notif-list {
    max-height: 380px !important;
    overflow-y: auto !important;
}
.notif-item {
    display: flex !important;
    flex-direction: row !important;
    align-items: flex-start !important;
    gap: 12px !important;
    padding: 12px 16px !important;
    border-bottom: 1px solid #F1F5F9 !important;
    text-decoration: none !important;
    color: inherit !important;
    position: relative !important;
    transition: background-color 0.15s ease !important;
}
.notif-item:hover {
    background-color: #F8FAFC !important;
    color: inherit !important;
}
.notif-item.is-unread {
    background-color: #F0F7FF !important;
}
.notif-item.is-unread:hover {
    background-color: #E2EFFF !important;
}
.notif-icon-circle {
    width: 38px !important;
    min-width: 38px !important;
    height: 38px !important;
    border-radius: 50% !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
    font-size: 1.05rem !important;
}
.notif-title {
    font-size: 0.85rem !important;
    color: #1E293B !important;
}
.notif-desc {
    font-size: 0.78rem !important;
    line-height: 1.4 !important;
    color: #64748B !important;
}
.notif-time {
    font-size: 0.72rem !important;
    white-space: nowrap !important;
    margin-left: 8px !important;
    color: #94A3B8 !important;
}
.notif-unread-dot {
    width: 8px !important;
    min-width: 8px !important;
    height: 8px !important;
    border-radius: 50% !important;
    background-color: #0D6EFD !important;
    flex-shrink: 0 !important;
    margin-top: 6px !important;
}
.notif-footer {
    background: #F8FAFC !important;
    padding: 10px 16px !important;
}
/* Waspada Filter Pill Icon Fix for Hover & Active */
.icon-filter-waspada {
    color: #D97706;
    transition: color 0.15s ease-in-out;
}
.btn-warning .icon-filter-waspada,
.btn-warning:hover .icon-filter-waspada,
.btn-outline-warning:hover .icon-filter-waspada,
.btn-outline-warning:focus .icon-filter-waspada,
.btn-outline-warning:active .icon-filter-waspada,
a.btn-outline-warning:hover i,
a.btn-warning i {
    color: #0F172A !important;
}
</style>
</head>
<body>

<nav class="topbar">
    <button class="btn-burger" id="sidebarToggle" type="button" aria-label="Buka menu">
        <i class="bi bi-list"></i>
    </button>
    <div class="topbar-brand">
        <img src="<?= baseUrl('assets/img/logo-bpn-white.png') ?>?v=<?= time() ?>" alt="Logo ATR/BPN" class="brand-logo">
        <div class="brand-text">
            <span class="brand-mark">SITRACK</span>
            <span class="brand-sub">Sistem Tracking Berkas Pradaftar</span>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3 ms-auto">
        <!-- In-App Notification Dropdown -->
        <?php if ($user): ?>
        <div class="topbar-notif dropdown">
            <button class="btn-notif position-relative" type="button" id="notifDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Pemberitahuan" style="width:38px; height:38px; border-radius:50%; background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.25); color:#ffffff; display:inline-flex; align-items:center; justify-content:center; padding:0; cursor:pointer; box-shadow:0 2px 5px rgba(0,0,0,0.12);">
                <i class="bi bi-bell-fill" style="font-size:1.1rem; line-height:1;"></i>
                <span class="notif-badge <?= $notifUnreadCount > 0 ? '' : 'd-none' ?>" id="notifBadgeCounter" style="position:absolute; top:-3px; right:-3px; background:#EF4444; color:#ffffff; font-size:0.65rem; font-weight:800; line-height:1; padding:3px 6px; min-width:18px; text-align:center; border-radius:999px; border:2px solid #0E2A47; box-shadow:0 0 10px rgba(239,68,68,0.7);">
                    <?= $notifUnreadCount > 99 ? '99+' : $notifUnreadCount ?>
                </span>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow-lg notif-dropdown-menu" aria-labelledby="notifDropdown" style="width:min(390px, calc(100vw - 20px)); min-width:280px; max-width:calc(100vw - 20px); border-radius:14px; border:1px solid rgba(0,0,0,0.08); box-shadow:0 18px 38px -10px rgba(15,23,42,0.25); padding:0; margin-top:10px; overflow:hidden;">
                <div class="notif-header d-flex justify-content-between align-items-center" style="padding:12px 18px; background:#F8FAFC; border-bottom:1px solid #E2E8F0;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold text-dark"><i class="bi bi-bell me-1 text-primary"></i>Pemberitahuan</span>
                        <span class="badge bg-danger rounded-pill <?= $notifUnreadCount > 0 ? '' : 'd-none' ?>" id="notifBadgeHeader"><?= $notifUnreadCount ?> Baru</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 text-primary small fw-semibold <?= $notifUnreadCount > 0 ? '' : 'd-none' ?>" id="btnMarkAllRead">
                        Tandai semua dibaca
                    </button>
                </div>

                <div class="notif-list" id="notifListContainer">
                    <?php if (empty($recentNotifs)): ?>
                        <div class="notif-empty py-4 text-center text-muted" id="notifEmptyState">
                            <i class="bi bi-bell-slash fs-3 d-block mb-1 text-secondary opacity-50"></i>
                            <span class="small">Belum ada pemberitahuan</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentNotifs as $notif): 
                            [$icon, $textColor, $bgColor, $styleAttr] = notifStyle($notif['tipe']);
                            $isUnread = !(bool)$notif['is_read'];
                        ?>
                        <a href="<?= baseUrl('notifikasi_baca.php?id=' . (int)$notif['id']) ?>" 
                           class="notif-item <?= $isUnread ? 'is-unread' : '' ?>" 
                           data-id="<?= (int)$notif['id'] ?>"
                           style="display:flex !important; flex-direction:row !important; align-items:flex-start !important; gap:12px !important; padding:12px 16px !important; border-bottom:1px solid #F1F5F9 !important; text-decoration:none !important; color:inherit !important;">
                            <div class="notif-icon-circle" <?= $styleAttr ?> style="width:38px !important; min-width:38px !important; height:38px !important; border-radius:50% !important; display:inline-flex !important; align-items:center !important; justify-content:center !important; flex-shrink:0 !important; font-size:1.05rem !important;">
                                <i class="bi <?= $icon ?>"></i>
                            </div>
                            <div class="notif-content" style="flex:1 1 auto !important; min-width:0 !important;">
                                <div class="d-flex justify-content-between align-items-baseline gap-2 mb-1">
                                    <span class="notif-title <?= $isUnread ? 'fw-bold text-dark' : 'fw-semibold text-secondary' ?>" style="font-size:0.86rem !important; line-height:1.35 !important;"><?= e($notif['judul']) ?></span>
                                    <span class="notif-time text-muted small flex-shrink-0" style="font-size:0.72rem !important; white-space:nowrap !important;"><?= waktuLalu($notif['created_at']) ?></span>
                                </div>
                                <div class="notif-desc small text-muted mb-1" style="font-size:0.78rem !important; line-height:1.4 !important;"><?= e($notif['pesan']) ?></div>
                                <?php if (!empty($notif['nomor_pendaftaran'])): ?>
                                    <div class="mt-1">
                                        <span class="badge text-bg-light border text-muted mono" style="font-size:11px !important; padding:2px 7px !important;"><?= e($notif['nomor_pendaftaran']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($isUnread): ?>
                                <span class="notif-unread-dot" style="width:8px !important; min-width:8px !important; height:8px !important; border-radius:50% !important; background-color:#0D6EFD !important; flex-shrink:0 !important; margin-top:6px !important;"></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="notif-footer text-center border-top py-2 bg-light rounded-bottom">
                    <a href="<?= baseUrl('notifikasi.php') ?>" class="small text-decoration-none fw-semibold text-primary">
                        Lihat Semua Pemberitahuan &rarr;
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- User Dropdown -->
        <div class="topbar-user dropdown">
            <button class="btn-user dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="user-avatar"><?= e(strtoupper(substr($user['nama_lengkap'] ?? '?', 0, 1))) ?></span>
                <span class="user-info d-none d-md-flex">
                    <strong><?= e($user['nama_lengkap'] ?? '') ?></strong>
                    <small><?= e(roleLabel($user['role'] ?? '')) ?></small>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><a class="dropdown-item disabled" href="#"><i class="bi bi-person-badge me-2"></i><?= e($user['username'] ?? '') ?></a></li>
                <li><a class="dropdown-item" href="<?= baseUrl('notifikasi.php') ?>"><i class="bi bi-bell me-2"></i>Pemberitahuan</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= baseUrl('auth/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="app-body">
