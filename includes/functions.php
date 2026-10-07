<?php
/**
 * Kumpulan fungsi bantu (helper) yang dipakai di seluruh sistem.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Perkuat konfigurasi session sebelum dimulai
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

/* ==========================================================
   AUTENTIKASI & OTORISASI
   ========================================================== */

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) return null;
    return [
        'id'           => $_SESSION['user_id'],
        'username'     => $_SESSION['username'],
        'nama_lengkap' => $_SESSION['nama_lengkap'],
        'role'         => $_SESSION['role'],
    ];
}

/**
 * Wajibkan pengguna sudah login. Jika belum, lempar ke halaman login.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . baseUrl('auth/login.php'));
        exit;
    }
}

/**
 * Wajibkan role tertentu untuk mengakses halaman ini.
 * @param string[] $allowedRoles
 */
function requireRole(array $allowedRoles): void
{
    requireLogin();
    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        setFlash('danger', 'Anda tidak memiliki akses ke halaman tersebut.');
        header('Location: ' . baseUrl(roleHome($_SESSION['role'])));
        exit;
    }
}

function roleHome(string $role): string
{
    return match ($role) {
        'admin'   => 'admin/index.php',
        'loket'   => 'loket/index.php',
        'seksi_1' => 'seksi1/index.php',
        'seksi_2' => 'seksi2/index.php',
        default   => 'auth/login.php',
    };
}

function roleLabel(string $role): string
{
    return match ($role) {
        'admin'   => 'Administrator',
        'loket'   => 'Loket',
        'seksi_1' => 'Seksi 1 (Survei & Pemetaan)',
        'seksi_2' => 'Seksi 2 (Penetapan Hak & Pendaftaran)',
        default   => ucfirst($role),
    };
}

/**
 * Menghasilkan URL root aplikasi secara dinamis, baik saat diakses lewat Virtual Host
 * (mis. http://bpn-tracking.test/) maupun subfolder (mis. http://localhost/bpn-tracking/).
 */
function baseUrl(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';

        $docRoot = rtrim($docRoot, '/');
        $projectRoot = rtrim($projectRoot, '/');

        if (!empty($docRoot) && stripos($projectRoot, $docRoot) === 0) {
            $subPath = substr($projectRoot, strlen($docRoot));
            $base = '/' . trim($subPath, '/');
            if ($base === '/') {
                $base = '';
            }
        } else {
            // Deteksi berdasarkan folder script jika DOCUMENT_ROOT tidak cocok
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            $folderName = basename($projectRoot);
            $pos = strpos($scriptDir, '/' . $folderName);
            if ($pos !== false) {
                $base = substr($scriptDir, 0, $pos + strlen($folderName) + 1);
            } else {
                $base = '';
            }
        }
    }

    $cleanPath = ltrim($path, '/');
    if ($base === '') {
        return '/' . $cleanPath;
    }
    return rtrim($base, '/') . '/' . $cleanPath;
}

/* ==========================================================
   CSRF PROTECTION
   ========================================================== */

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function verifyCsrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/* ==========================================================
   FLASH MESSAGE (notifikasi sekali tampil)
   ========================================================== */

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/* ==========================================================
   STATUS BERKAS
   ========================================================== */

/**
 * Mengembalikan [label, warna_badge_bootstrap, ikon] untuk sebuah status_posisi.
 */
function statusInfo(string $status, int $isDiterimaLoket = 0): array
{
    if ($status === 'ditolak_ke_loket' && $isDiterimaLoket === 1) {
        return ['Kembali ke Pemohon', 'danger', 'bi-arrow-return-left'];
    }
    return match ($status) {
        'loket'              => ['Di Loket',                              'primary',   'bi-inbox'],
        'seksi_1'            => ['Seksi 1 (Survei & Pemetaan)',           'primary',   'bi-file-earmark-check'],
        'seksi_2'            => ['Seksi 2 (Penetapan Hak & Pendaftaran)', 'primary',   'bi-file-earmark-check'],
        'selesai'            => ['Selesai',                               'success',   'bi-check-circle'],
        'ditolak_ke_loket'   => ['Dikembalikan ke Loket',                 'danger',    'bi-arrow-return-left'],
        'ditolak_ke_seksi1'  => ['Dikembalikan ke Seksi 1',               'warning',   'bi-arrow-90deg-left'],
        default              => [ucfirst($status),                        'secondary', 'bi-question-circle'],
    };
}

function statusBadge(string $status, int $isDiterimaLoket = 0): string
{
    [$label, $color, $icon] = statusInfo($status, $isDiterimaLoket);
    return '<span class="badge badge-status text-bg-' . $color . '"><i class="bi ' . $icon . '"></i> ' . htmlspecialchars($label) . '</span>';
}

function aksiLabel(string $aksi): string
{
    return match ($aksi) {
        'diinput'     => 'Berkas diinput',
        'diteruskan'  => 'Diteruskan',
        'dikembalikan'=> 'Dikembalikan',
        'selesai'     => 'Diselesaikan',
        'diterima'    => 'Dikonfirmasi diterima',
        default       => ucfirst($aksi),
    };
}

function unitTujuanLabel(string $status): string
{
    return match ($status) {
        'loket'             => 'Loket',
        'seksi_1'           => 'Tim Seksi 1 (Survei & Pemetaan)',
        'seksi_2'           => 'Tim Seksi 2 (Penetapan Hak & Pendaftaran)',
        'selesai'           => 'Loket (Penyerahan ke Pemohon)',
        'ditolak_ke_loket'  => 'Loket (Perbaikan Dokumen)',
        'ditolak_ke_seksi1' => 'Tim Seksi 1 (Survei & Pemetaan)',
        default             => ucfirst($status),
    };
}

function pemegangBerkasLabel(?string $namaPemegang, string $statusPosisi, int $isDiterimaLoket = 0): string
{
    if (!empty($namaPemegang)) {
        return $namaPemegang;
    }
    if ($statusPosisi === 'ditolak_ke_loket' && $isDiterimaLoket === 1) {
        return 'Pemohon (Berkas Dikembalikan)';
    }
    return match ($statusPosisi) {
        'loket'             => 'Tim Loket (Menunggu Kirim)',
        'seksi_1'           => 'Tim Seksi 1 (Survei & Pemetaan)',
        'seksi_2'           => 'Tim Seksi 2 (Penetapan Hak & Pendaftaran)',
        'selesai'           => 'Tim Loket (Penyerahan)',
        'ditolak_ke_loket'  => 'Tim Loket',
        'ditolak_ke_seksi1' => 'Tim Seksi 1 (Survei & Pemetaan)',
        default             => 'Semua Petugas',
    };
}

/* ==========================================================
   NOMOR PENDAFTARAN / NOMOR BERKAS
   ========================================================== */

/**
 * Membuat nomor berkas otomatis dengan format: BPN/YYYY/001
 */
function generateNomorBerkas(PDO $conn): string
{
    $year = date('Y');
    $prefix = 'BPN/' . $year . '/';

    $stmt = $conn->prepare("SELECT nomor_pendaftaran FROM berkas WHERE nomor_pendaftaran LIKE ?");
    $stmt->execute([$prefix . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $maxNum = 0;
    foreach ($rows as $nomor) {
        $parts = explode('/', $nomor);
        if (count($parts) >= 3 && is_numeric($parts[2])) {
            $num = (int) $parts[2];
            if ($num > $maxNum) {
                $maxNum = $num;
            }
        }
    }

    $next = $maxNum + 1;
    $padLength = max(3, strlen((string) $next));
    return $prefix . str_pad((string) $next, $padLength, '0', STR_PAD_LEFT);
}

function generateNomorPendaftaran(PDO $conn): string
{
    return generateNomorBerkas($conn);
}

/* ==========================================================
   UTIL TAMPILAN
   ========================================================== */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatTanggal(?string $datetime): string
{
    if (!$datetime) return '-';
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $ts = strtotime($datetime);
    return date('d', $ts) . ' ' . $bulan[(int) date('n', $ts) - 1] . ' ' . date('Y H:i', $ts);
}

function jenisLayananOptions(): array
{
    return [
        'Blokir',
        'Ganti Nama',
        'Ganti Nama Pemegang Hak Tanggungan',
        'Hak Tanggungan',
        'Hapusnya Hak',
        'Informasi Titik Koordinat',
        'Pelantikan PPAT Sementara',
        'Pemecahan Bidang',
        'Pemisahan Bidang',
        'Penataan Batas',
        'Pencatatan Perubahan Penggunaan Tanah',
        'Pendaftaran SK Hak',
        'Pendaftaran Tanah Pertama Kali Pemberian Hak',
        'Pendaftaran Tanah Pertama Kali Penegasan Tanah Wakaf',
        'Pengecekan Sertipikat',
        'Pengembalian Batas',
        'Penggabungan Bidang',
        'Pengukuran - ASN',
        'Pengukuran Dan Pemetaan Kadastral',
        'Peralihan Hak - Hibah',
        'Peralihan Hak - Jual Beli',
        'Peralihan Hak - Lelang',
        'Peralihan Hak - Pembagian Hak Bersama',
        'Peralihan Hak - Pewarisan',
        'Permohonan SK Pemberian Hak Guna Bangunan Badan Hukum',
        'Permohonan SK Pemberian Hak Milik Perorangan',
        'Permohonan SK Perpanjangan Hak Guna Bangunan Badan Hukum',
        'Perubahan Data Berdasarkan Penetapan atau Putusan Pengadilan',
        'Perubahan Hak Atas Tanah',
        'Peta Analisis Penatagunaan Tanah',
        'PTF PKKPR Untuk Kegiatan Berusaha',
        'PTF PKKPR Untuk Kegiatan Non Berusaha',
        'Roya',
        'Sertipikat Pengganti Karena Blangko Lama',
        'Sertipikat Pengganti Karena Hilang',
        'Sertipikat Pengganti Karena Rusak',
        'Surat Keterangan Pendaftaran Tanah',
        'Wakaf Dari Tanah Yang Sudah Bersertipikat',
    ];
}

/* ==========================================================
   SLA & HANDOVER PENERIMA BERKAS (BATAS WAKTU 2 HARI)
   ========================================================== */

/**
 * Mengambil daftar petugas aktif berdasarkan role.
 */
function getUsersByRole(PDO $conn, string $role): array
{
    $stmt = $conn->prepare("SELECT id, username, nama_lengkap, role FROM users WHERE role = ? AND is_active = 1 ORDER BY nama_lengkap ASC");
    $stmt->execute([$role]);
    return $stmt->fetchAll();
}

/**
 * Menghitung status SLA (batas waktu pengerjaan 2 hari / 48 jam).
 * Mengembalikan status, badge class (success = hijau, danger = merah), dan teks sisa/keterlambatan.
 */
function hitungSla(?string $deadlineAt, ?string $updatedAt = null, string $statusPosisi = '', ?int $sisaSlaDetik = null): array
{
    // Jika berkas sudah selesai diproses, waktu otomatis BERHENTI dan tidak ada countdown
    if ($statusPosisi === 'selesai') {
        return [
            'is_overdue'  => false,
            'is_warning'  => false,
            'is_selesai'  => true,
            'is_paused'   => false,
            'label'       => 'Selesai',
            'class'       => 'success',
            'diff_text'   => 'Waktu Berhenti',
            'hours_left'  => 0,
        ];
    }

    // Jika berkas berada di loket saat input awal/draft, batas waktu SLA belum berjalan
    if ($statusPosisi === 'loket') {
        return [
            'is_overdue'  => false,
            'is_warning'  => false,
            'is_selesai'  => false,
            'is_paused'   => false,
            'label'       => 'Belum Berjalan',
            'class'       => 'secondary',
            'diff_text'   => 'Belum Berjalan',
            'hours_left'  => 0,
        ];
    }

    // Jika berkas dikembalikan ke loket (perbaikan/revisi), timer SLA DIJEDA (di-stop)
    if ($statusPosisi === 'ditolak_ke_loket') {
        if ($sisaSlaDetik !== null) {
            $diff = (int) $sisaSlaDetik;
        } elseif (!empty($deadlineAt)) {
            $baseTs = !empty($updatedAt) ? strtotime($updatedAt) : time();
            $diff   = strtotime($deadlineAt) - $baseTs;
        } else {
            $diff = 48 * 3600;
        }

        if ($diff >= 0) {
            $hours = (int) floor($diff / 3600);
            $days  = (int) floor($diff / 86400);

            if ($days >= 1) {
                $remHours = $hours % 24;
                $diffText = "Sisa {$days} hr" . ($remHours > 0 ? " {$remHours} jam" : '');
            } elseif ($hours >= 1) {
                $diffText = "Sisa {$hours} jam";
            } else {
                $mins = max(1, (int) floor($diff / 60));
                $diffText = "Sisa {$mins} mnt";
            }

            return [
                'is_overdue'  => false,
                'is_warning'  => ($diff <= 86400),
                'is_selesai'  => false,
                'is_paused'   => true,
                'label'       => 'Dijeda',
                'class'       => 'secondary',
                'diff_text'   => $diffText,
                'hours_left'  => $hours,
            ];
        } else {
            $lateSecs  = abs($diff);
            $lateHours = (int) floor($lateSecs / 3600);
            $lateDays  = (int) floor($lateSecs / 86400);

            if ($lateDays >= 1) {
                $remLateHours = $lateHours % 24;
                $diffText = "Telat {$lateDays} hr" . ($remLateHours > 0 ? " {$remLateHours} jam" : '');
            } elseif ($lateHours >= 1) {
                $diffText = "Telat {$lateHours} jam";
            } else {
                $mins = max(1, (int) floor($lateSecs / 60));
                $diffText = "Telat {$mins} mnt";
            }

            return [
                'is_overdue'  => true,
                'is_warning'  => false,
                'is_selesai'  => false,
                'is_paused'   => true,
                'label'       => 'Dijeda (Kritis)',
                'class'       => 'danger',
                'diff_text'   => $diffText,
                'hours_left'  => -$lateHours,
            ];
        }
    }

    if (empty($deadlineAt)) {
        if (!empty($updatedAt)) {
            $deadlineAt = date('Y-m-d H:i:s', strtotime($updatedAt . ' +2 days'));
        } else {
            return [
                'is_overdue'  => false,
                'is_warning'  => false,
                'is_selesai'  => false,
                'is_paused'   => false,
                'label'       => 'Tepat Waktu',
                'class'       => 'success',
                'diff_text'   => 'Tepat Waktu',
                'hours_left'  => 48,
            ];
        }
    }

    $deadlineTs = strtotime($deadlineAt);
    $nowTs      = time();
    $diff       = $deadlineTs - $nowTs;

    if ($diff >= 0) {
        // Belum terlewat
        $hours = (int) floor($diff / 3600);
        $days  = (int) floor($diff / 86400);

        if ($days >= 1) {
            $remHours = $hours % 24;
            $diffText = "Sisa {$days} hr" . ($remHours > 0 ? " {$remHours} jam" : '');
        } elseif ($hours >= 1) {
            $diffText = "Sisa {$hours} jam";
        } else {
            $mins = max(1, (int) floor($diff / 60));
            $diffText = "Sisa {$mins} mnt";
        }

        // Jika sisa waktu <= 1 hari / 24 jam (86400 detik), masuk status Waspada (Kuning)
        $isWarning = ($diff <= 86400);

        return [
            'is_overdue'  => false,
            'is_warning'  => $isWarning,
            'is_selesai'  => false,
            'is_paused'   => false,
            'label'       => $isWarning ? 'Waspada' : 'Tepat Waktu',
            'class'       => $isWarning ? 'warning' : 'success',
            'diff_text'   => $diffText,
            'hours_left'  => $hours,
        ];
    } else {
        // Terlewat batas waktu (MERAH)
        $lateSecs  = abs($diff);
        $lateHours = (int) floor($lateSecs / 3600);
        $lateDays  = (int) floor($lateSecs / 86400);

        if ($lateDays >= 1) {
            $remLateHours = $lateHours % 24;
            $diffText = "Telat {$lateDays} hr" . ($remLateHours > 0 ? " {$remLateHours} jam" : '');
        } elseif ($lateHours >= 1) {
            $diffText = "Telat {$lateHours} jam";
        } else {
            $mins = max(1, (int) floor($lateSecs / 60));
            $diffText = "Telat {$mins} mnt";
        }

        return [
            'is_overdue'  => true,
            'is_warning'  => false,
            'is_selesai'  => false,
            'is_paused'   => false,
            'label'       => 'Kritis',
            'class'       => 'danger',
            'diff_text'   => $diffText,
            'hours_left'  => -$lateHours,
        ];
    }
}

/**
 * Merender badge visual status batas waktu.
 */
function slaBadge(?string $deadlineAt, string $statusPosisi, $isDiterimaLoket = false, ?int $sisaSlaDetik = null): string
{
    if ($sisaSlaDetik === null && is_numeric($isDiterimaLoket) && !is_bool($isDiterimaLoket)) {
        $sisaSlaDetik = (int) $isDiterimaLoket;
    }

    if ($statusPosisi === 'selesai') {
        return '<span class="badge text-bg-success fw-semibold" title="Berkas telah selesai diproses. Waktu otomatis berhenti.">'
             . '<i class="bi bi-check-circle-fill me-1"></i>Selesai</span>';
    }

    if ($statusPosisi === 'loket') {
        return '<span class="badge text-bg-secondary fw-semibold" title="Batas waktu SLA belum berjalan (berkas masih tersimpan di Loket)">'
             . '<i class="bi bi-dash-circle me-1"></i>Belum Berjalan</span>';
    }

    if ($statusPosisi === 'ditolak_ke_loket') {
        if ($isDiterimaLoket === true || $isDiterimaLoket === 1 || $isDiterimaLoket === '1') {
            return '<span class="badge text-bg-danger fw-semibold" title="Berkas telah dikembalikan kepada pemohon. Proses berkas selesai.">'
                 . '<i class="bi bi-arrow-return-left me-1"></i>Kembali ke Pemohon</span>';
        }
        $sla = hitungSla($deadlineAt, null, $statusPosisi, $sisaSlaDetik);
        $badgeClass = $sla['is_overdue'] ? 'text-bg-danger' : 'text-bg-secondary';
        return '<span class="badge ' . $badgeClass . ' fw-semibold" title="Timer SLA dijeda selama masa perbaikan di Loket/pemohon. Timer akan melanjutkan sisa waktu saat berkas dikirimkan kembali ke Seksi.">'
             . '<i class="bi bi-pause-circle-fill me-1"></i>Dijeda (' . e($sla['diff_text']) . ')</span>';
    }

    $sla = hitungSla($deadlineAt, null, $statusPosisi, $sisaSlaDetik);

    if ($sla['is_overdue']) {
        return '<span class="badge text-bg-danger fw-semibold" title="Batas waktu pengerjaan 2 hari telah terlampaui (Kritis)!">'
             . '<i class="bi bi-exclamation-octagon-fill me-1"></i>Kritis (' . e($sla['diff_text']) . ')</span>';
    }

    if (!empty($sla['is_warning'])) {
        return '<span class="badge text-bg-warning text-dark fw-semibold" title="Peringatan: Sisa waktu pengerjaan kurang dari 1 hari (Waspada)!">'
             . '<i class="bi bi-exclamation-triangle-fill me-1"></i>Waspada (' . e($sla['diff_text']) . ')</span>';
    }

    return '<span class="badge text-bg-success fw-semibold" title="Masih dalam batas waktu pengerjaan (maks. 2 hari)">'
         . '<i class="bi bi-clock-history me-1"></i>Tepat Waktu (' . e($sla['diff_text']) . ')</span>';
}

/**
 * Mengambil daftar berkas yang telah melewati batas waktu SLA (> 2 hari) untuk user/role saat ini.
 */
function getOverdueBerkasUser(PDO $conn, int $userId, string $role): array
{
    $now = date('Y-m-d H:i:s');
    $sql = '';
    $params = [];

    if ($role === 'loket') {
        $sql = "SELECT b.*, u.nama_lengkap AS pemegang_nama 
                FROM berkas b 
                LEFT JOIN users u ON u.id = b.petugas_tujuan_id
                WHERE b.status_posisi = 'ditolak_ke_loket' 
                  AND b.is_diterima_loket = 0 
                  AND (
                    (b.sisa_sla_detik IS NOT NULL AND b.sisa_sla_detik < 0)
                    OR (b.sisa_sla_detik IS NULL AND b.deadline_at IS NOT NULL AND b.deadline_at < ?)
                  )
                ORDER BY b.deadline_at ASC";
        $params = [$now];
    } elseif ($role === 'seksi_1') {
        $sql = "SELECT b.*, u.nama_lengkap AS pemegang_nama 
                FROM berkas b 
                LEFT JOIN users u ON u.id = b.petugas_tujuan_id
                WHERE b.status_posisi IN ('seksi_1','ditolak_ke_seksi1') 
                  AND b.deadline_at IS NOT NULL 
                  AND b.deadline_at < ?
                ORDER BY b.deadline_at ASC";
        $params = [$now];
    } elseif ($role === 'seksi_2') {
        $sql = "SELECT b.*, u.nama_lengkap AS pemegang_nama 
                FROM berkas b 
                LEFT JOIN users u ON u.id = b.petugas_tujuan_id
                WHERE b.status_posisi = 'seksi_2' 
                  AND b.deadline_at IS NOT NULL 
                  AND b.deadline_at < ?
                ORDER BY b.deadline_at ASC";
        $params = [$now];
    } elseif ($role === 'admin') {
        $sql = "SELECT b.*, u.nama_lengkap AS pemegang_nama 
                FROM berkas b 
                LEFT JOIN users u ON u.id = b.petugas_tujuan_id
                WHERE (
                    (b.status_posisi IN ('seksi_1','seksi_2','ditolak_ke_seksi1') AND b.deadline_at IS NOT NULL AND b.deadline_at < ?)
                    OR (b.status_posisi = 'ditolak_ke_loket' AND b.is_diterima_loket = 0 AND (
                        (b.sisa_sla_detik IS NOT NULL AND b.sisa_sla_detik < 0)
                        OR (b.sisa_sla_detik IS NULL AND b.deadline_at IS NOT NULL AND b.deadline_at < ?)
                    ))
                )
                ORDER BY b.deadline_at ASC";
        $params = [$now, $now];
    }

    if ($sql === '') return [];

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Mengambil daftar berkas dalam status Warning (sisa waktu <= 1 hari dan belum terlewat) untuk role saat ini.
 */
function getWarningBerkasUser(PDO $conn, int $userId, string $role): array
{
    $now = date('Y-m-d H:i:s');
    $oneDayLater = date('Y-m-d H:i:s', strtotime('+1 day'));
    $sql = '';
    $params = [$now, $oneDayLater];

    if ($role === 'loket') {
        $sql = "SELECT b.*, u.nama_lengkap AS pemegang_nama 
                FROM berkas b 
                LEFT JOIN users u ON u.id = b.petugas_tujuan_id
                WHERE b.status_posisi = 'ditolak_ke_loket' 
                  AND b.is_diterima_loket = 0 
                  AND (
                    (b.sisa_sla_detik IS NOT NULL AND b.sisa_sla_detik >= 0 AND b.sisa_sla_detik <= 86400)
                    OR (b.sisa_sla_detik IS NULL AND b.deadline_at IS NOT NULL AND b.deadline_at >= ? AND b.deadline_at <= ?)
                  )
                ORDER BY b.deadline_at ASC";
    } elseif ($role === 'seksi_1') {
        $sql = "SELECT b.*, u.nama_lengkap AS pemegang_nama 
                FROM berkas b 
                LEFT JOIN users u ON u.id = b.petugas_tujuan_id
                WHERE b.status_posisi IN ('seksi_1','ditolak_ke_seksi1') 
                  AND b.deadline_at IS NOT NULL 
                  AND b.deadline_at >= ? AND b.deadline_at <= ?
                ORDER BY b.deadline_at ASC";
    } elseif ($role === 'seksi_2') {
        $sql = "SELECT b.*, u.nama_lengkap AS pemegang_nama 
                FROM berkas b 
                LEFT JOIN users u ON u.id = b.petugas_tujuan_id
                WHERE b.status_posisi = 'seksi_2' 
                  AND b.deadline_at IS NOT NULL 
                  AND b.deadline_at >= ? AND b.deadline_at <= ?
                ORDER BY b.deadline_at ASC";
    } elseif ($role === 'admin') {
        $sql = "SELECT b.*, u.nama_lengkap AS pemegang_nama 
                FROM berkas b 
                LEFT JOIN users u ON u.id = b.petugas_tujuan_id
                WHERE (
                    (b.status_posisi IN ('seksi_1','seksi_2','ditolak_ke_seksi1') AND b.deadline_at IS NOT NULL AND b.deadline_at >= ? AND b.deadline_at <= ?)
                    OR (b.status_posisi = 'ditolak_ke_loket' AND b.is_diterima_loket = 0 AND (
                        (b.sisa_sla_detik IS NOT NULL AND b.sisa_sla_detik >= 0 AND b.sisa_sla_detik <= 86400)
                        OR (b.sisa_sla_detik IS NULL AND b.deadline_at IS NOT NULL AND b.deadline_at >= ? AND b.deadline_at <= ?)
                    ))
                )
                ORDER BY b.deadline_at ASC";
        $params = [$now, $oneDayLater, $now, $oneDayLater];
    }

    if ($sql === '') return [];

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/* ==========================================================
   HELPER NOTIFIKASI IN-APP
   ========================================================== */
require_once __DIR__ . '/notification_helper.php';

/* ==========================================================
   FOTO BIDANG HELPER
   ========================================================== */

/**
 * Direktori penyimpanan upload foto bidang tanah
 */
function getFotoBidangDir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'foto_bidang' . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Mendapatkan URL publik untuk file foto bidang tanah
 */
function fotoBidangUrl(?string $filename): ?string
{
    if (empty($filename)) {
        return null;
    }
    return baseUrl('uploads/foto_bidang/' . rawurlencode($filename));
}

/**
 * Menghapus file foto bidang tanah dari direktori upload
 */
function hapusFotoBidang(?string $filename): bool
{
    if (empty($filename)) {
        return false;
    }
    $file = getFotoBidangDir() . basename($filename);
    if (is_file($file)) {
        return @unlink($file);
    }
    return false;
}

/**
 * Memvalidasi dan menyimpan upload foto bidang
 * Mendukung format JPG, JPEG, PNG, WEBP dengan ukuran maks. 10MB.
 *
 * @param array $file Array dari $_FILES['foto_bidang']
 * @return array ['success' => bool, 'filename' => ?string, 'error' => ?string]
 */
function uploadFotoBidang(array $file): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'filename' => null, 'error' => 'Parameter file upload tidak valid.'];
    }

    // Jika tidak ada file yang dipilih (opsional)
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filename' => null, 'error' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi batas upload server (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas maksimum formulir.',
            UPLOAD_ERR_PARTIAL    => 'File hanya terunggah sebagian. Silakan ulangi upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary server tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke media penyimpanan server.',
            UPLOAD_ERR_EXTENSION  => 'Proses upload dihentikan oleh modul PHP.',
        ];
        $msg = $uploadErrors[$file['error']] ?? 'Terjadi kesalahan saat mengunggah foto.';
        return ['success' => false, 'filename' => null, 'error' => $msg];
    }

    // Batas ukuran 10 MB (10 * 1024 * 1024 byte)
    $maxBytes = 10 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'filename' => null, 'error' => 'Ukuran foto maksimal adalah 10 MB.'];
    }

    // Validasi ekstensi
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExts, true)) {
        return ['success' => false, 'filename' => null, 'error' => 'Format file tidak didukung. Harap upload foto dengan format JPG, JPEG, PNG, atau WEBP.'];
    }

    // Validasi MIME type jika finfo tersedia
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/webp'];
        if (!in_array($mime, $allowedMimes, true)) {
            return ['success' => false, 'filename' => null, 'error' => 'File yang dipilih bukan berkas gambar yang valid.'];
        }
    }

    $uploadDir = getFotoBidangDir();
    $uniqueName = 'bidang_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath = $uploadDir . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'filename' => null, 'error' => 'Gagal memindahkan file foto ke direktori tujuan.'];
    }

    return ['success' => true, 'filename' => $uniqueName, 'error' => null];
}

