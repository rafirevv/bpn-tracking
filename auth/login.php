<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . baseUrl(roleHome($_SESSION['role'])));
    exit;
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk · SITRACK BPN</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= baseUrl('assets/css/style.css') ?>?v=<?= time() ?>" rel="stylesheet">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-panel-info d-none d-lg-flex">
        <!-- Komponen Header Logo Instansi Berdampingan -->
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="d-inline-flex align-items-center justify-content-center p-2 rounded-4 flex-shrink-0" 
                 style="background: rgba(201, 154, 46, 0.12); border: 1.5px solid rgba(201, 154, 46, 0.4); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);">
                <img src="<?= baseUrl('assets/img/logo-bpn-white.png') ?>?v=<?= time() ?>" alt="Logo ATR/BPN" style="width: 58px; height: 58px; object-fit: contain;">
            </div>
            <div class="d-flex flex-column text-start">
                <span class="text-white text-uppercase fw-semibold" style="font-size: 0.78rem; letter-spacing: 0.05em; line-height: 1.3;">
                    KEMENTERIAN AGRARIA DAN TATA RUANG
                </span>
                <span class="fw-bold text-uppercase" style="font-size: 0.96rem; color: #FAB728; letter-spacing: 0.03em; line-height: 1.25;">
                    BADAN PERTANAHAN NASIONAL
                </span>
            </div>
        </div>

        <!-- Judul Utama & Deskripsi -->
        <h1 class="fw-bold text-white mb-3" style="font-size: 2.15rem; line-height: 1.25; max-width: 520px;">
            Monitoring &amp; Pelacakan Berkas Pradaftar Terpadu
        </h1>
        <p class="text-white-50 mb-4" style="max-width: 480px; font-size: 0.98rem; line-height: 1.6;">
            Mengoptimalkan akuntabilitas alur pendaftaran tanah melalui pemantauan posisi berkas secara langsung, lengkap dengan rekam jejak digital serta catatan disposisi antar-seksi kerja.
        </p>

        <!-- Poin Unggulan dengan Garis Pemisah Halus -->
        <div class="row g-3 pt-4 border-top border-white border-opacity-10" style="max-width: 520px;">
            <div class="col-4">
                <div class="fw-bold text-white fs-5 mb-1">Multi-Akses</div>
                <div class="text-white-50 small" style="font-size: 0.82rem; line-height: 1.4;">Pembagian wewenang berbasis peran</div>
            </div>
            <div class="col-4">
                <div class="fw-bold text-white fs-5 mb-1">Real-Time</div>
                <div class="text-white-50 small" style="font-size: 0.82rem; line-height: 1.4;">Pemantauan disposisi langsung</div>
            </div>
            <div class="col-4">
                <div class="fw-bold text-white fs-5 mb-1">Audit Trail</div>
                <div class="text-white-50 small" style="font-size: 0.82rem; line-height: 1.4;">Jejak rekam terverifikasi</div>
            </div>
        </div>
    </div>

    <div class="auth-panel-form">
        <div class="auth-card">
            <!-- Logo Polos Tanpa Background/Wadah Melingkar -->
            <div class="text-center mb-2">
                <img src="<?= baseUrl('assets/img/logo-bpn.png') ?>?v=<?= time() ?>" alt="Logo ATR/BPN" class="auth-card-logo">
                <h2 class="auth-card-title">Masuk Sistem</h2>
                <p class="auth-card-desc">
                    Silakan masukkan akun Anda untuk mengakses sistem monitoring berkas.
                </p>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
                    <div><?= e($flash['message']) ?></div>
                </div>
            <?php endif; ?>

            <form action="proses_login.php" method="POST" novalidate>
                <?= csrfField() ?>
                
                <!-- Input Username -->
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small mb-1">Username</label>
                    <div class="auth-input-group">
                        <span class="auth-input-icon">
                            <i class="bi bi-person"></i>
                        </span>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
                    </div>
                </div>

                <!-- Input Kata Sandi -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark small mb-1">Kata Sandi</label>
                    <div class="auth-input-group">
                        <span class="auth-input-icon">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input type="password" name="password" id="inputPassword" class="form-control" placeholder="Masukkan kata sandi" required>
                        <button type="button" class="btn-toggle-pwd" id="btnTogglePassword" title="Tampilkan/sembunyikan kata sandi" tabindex="-1">
                            <i class="bi bi-eye" id="iconEye"></i>
                        </button>
                    </div>
                </div>

                <!-- Tombol Submit -->
                <button type="submit" class="btn btn-auth-submit w-100">
                    <span>Masuk ke Sistem</span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>

            <!-- Footer Catatan Keamanan -->
            <div class="auth-card-footer">
                <i class="bi bi-shield-check text-primary me-1"></i>
                <span>Gunakan akun yang diberikan oleh administrator kantor.</span>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnToggle = document.getElementById('btnTogglePassword');
    const inputPwd = document.getElementById('inputPassword');
    const iconEye = document.getElementById('iconEye');
    if (btnToggle && inputPwd && iconEye) {
        btnToggle.addEventListener('click', function() {
            const isPassword = inputPwd.type === 'password';
            inputPwd.type = isPassword ? 'text' : 'password';
            iconEye.className = isPassword ? 'bi bi-eye-slash text-primary' : 'bi bi-eye';
        });
    }
});
</script>

</body>
</html>
