<?php
/**
 * Helper Notifikasi In-App untuk SITRACK BPN
 * Mengelola pembuatan, pembacaan, dan counter notifikasi per-user / per-role.
 */

/**
 * Format timestamp menjadi teks waktu relatif yang bersahabat (bahasa Indonesia)
 */
function waktuLalu(string|int|null $datetime): string
{
    if (empty($datetime)) return '-';
    $time = is_numeric($datetime) ? (int) $datetime : strtotime($datetime);
    if (!$time) return '-';

    $diff = time() - $time;
    if ($diff < 0) $diff = 0;

    if ($diff < 60) {
        return 'Baru saja';
    } elseif ($diff < 3600) {
        $menit = max(1, (int) round($diff / 60));
        return $menit . ' mnt lalu';
    } elseif ($diff < 86400) {
        $jam = max(1, (int) round($diff / 3600));
        return $jam . ' jam lalu';
    } elseif ($diff < 172800) {
        return 'Kemarin, ' . date('H:i', $time);
    } else {
        $bulanIndo = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agt', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
        ];
        $m = (int) date('n', $time);
        return date('d', $time) . ' ' . ($bulanIndo[$m] ?? date('M', $time)) . ' ' . date('H:i', $time);
    }
}

/**
 * Mendapatkan icon, warna, dan background subtle berdasarkan tipe notifikasi
 */
function notifStyle(string $tipe): array
{
    return match ($tipe) {
        'masuk'   => ['bi-box-arrow-in-down-right', 'text-primary', 'bg-primary-subtle', 'style="background-color:#EFF6FF;color:#2563EB;"'],
        'kembali' => ['bi-arrow-return-left', 'text-warning-emphasis', 'bg-warning-subtle', 'style="background-color:#FFFBEB;color:#D97706;"'],
        'selesai' => ['bi-check-circle-fill', 'text-success', 'bg-success-subtle', 'style="background-color:#F0FDF4;color:#16A34A;"'],
        'warning' => ['bi-exclamation-triangle-fill', 'text-warning-emphasis', 'bg-warning-subtle', 'style="background-color:#FFF7ED;color:#EA580C;"'],
        'kritis'  => ['bi-exclamation-octagon-fill', 'text-danger', 'bg-danger-subtle', 'style="background-color:#FEF2F2;color:#DC2626;"'],
        default   => ['bi-bell-fill', 'text-secondary', 'bg-light', 'style="background-color:#F1F5F9;color:#475569;"'],
    };
}

/**
 * Buat entri notifikasi baru
 */
function kirimNotifikasi(
    PDO $conn, 
    ?int $idBerkas, 
    string $targetRole, 
    string $judul, 
    string $pesan, 
    string $tipe = 'masuk', 
    string $link = '', 
    ?int $targetUserId = null
): bool {
    try {
        $stmt = $conn->prepare("
            INSERT INTO notifikasi (id_berkas, target_role, target_user_id, judul, pesan, tipe, link, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $idBerkas,
            $targetRole,
            $targetUserId,
            mb_substr($judul, 0, 150),
            $pesan,
            $tipe,
            $link
        ]);
    } catch (Throwable $e) {
        error_log('Error kirimNotifikasi: ' . $e->getMessage());
        return false;
    }
}

/**
 * Hitung jumlah notifikasi belum dibaca untuk user yang login
 */
function hitungNotifikasiBelumDibaca(PDO $conn, int $userId, string $role): int
{
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM notifikasi n
            LEFT JOIN notifikasi_read nr ON nr.notifikasi_id = n.id AND nr.user_id = ?
            WHERE (n.target_role = ? OR n.target_role = 'all' OR n.target_user_id = ?)
              AND (n.target_user_id IS NULL OR n.target_user_id = ?)
              AND nr.id IS NULL
        ");
        $stmt->execute([$userId, $role, $userId, $userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Error hitungNotifikasiBelumDibaca: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Ambil daftar notifikasi untuk user tertentu
 */
function ambilNotifikasiUser(PDO $conn, int $userId, string $role, int $limit = 10, bool $unreadOnly = false): array
{
    try {
        $whereUnread = $unreadOnly ? "AND nr.id IS NULL" : "";
        $stmt = $conn->prepare("
            SELECT n.*, 
                   (nr.id IS NOT NULL) AS is_read,
                   b.nomor_pendaftaran,
                   b.nama_pemohon
            FROM notifikasi n
            LEFT JOIN notifikasi_read nr ON nr.notifikasi_id = n.id AND nr.user_id = ?
            LEFT JOIN berkas b ON b.id = n.id_berkas
            WHERE (n.target_role = ? OR n.target_role = 'all' OR n.target_user_id = ?)
              AND (n.target_user_id IS NULL OR n.target_user_id = ?)
              $whereUnread
            ORDER BY n.created_at DESC
            LIMIT " . (int) $limit . "
        ");
        $stmt->execute([$userId, $role, $userId, $userId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('Error ambilNotifikasiUser: ' . $e->getMessage());
        return [];
    }
}

/**
 * Tandai satu notifikasi telah dibaca oleh user
 */
function tandaiNotifikasiDibaca(PDO $conn, int $notifId, int $userId): bool
{
    try {
        $stmt = $conn->prepare("
            INSERT INTO notifikasi_read (notifikasi_id, user_id, read_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE read_at = NOW()
        ");
        return $stmt->execute([$notifId, $userId]);
    } catch (Throwable $e) {
        error_log('Error tandaiNotifikasiDibaca: ' . $e->getMessage());
        return false;
    }
}

/**
 * Tandai seluruh notifikasi user sebagai sudah dibaca
 */
function tandaiSemuaNotifikasiDibaca(PDO $conn, int $userId, string $role): bool
{
    try {
        $stmt = $conn->prepare("
            INSERT IGNORE INTO notifikasi_read (notifikasi_id, user_id, read_at)
            SELECT n.id, ?, NOW()
            FROM notifikasi n
            LEFT JOIN notifikasi_read nr ON nr.notifikasi_id = n.id AND nr.user_id = ?
            WHERE (n.target_role = ? OR n.target_role = 'all' OR n.target_user_id = ?)
              AND (n.target_user_id IS NULL OR n.target_user_id = ?)
              AND nr.id IS NULL
        ");
        return $stmt->execute([$userId, $userId, $role, $userId, $userId]);
    } catch (Throwable $e) {
        error_log('Error tandaiSemuaNotifikasiDibaca: ' . $e->getMessage());
        return false;
    }
}
