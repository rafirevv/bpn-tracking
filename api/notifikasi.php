<?php
/**
 * Endpoint JSON API untuk In-App Notification SITRACK BPN
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? 'check';

if ($action === 'check') {
    $unreadCount = hitungNotifikasiBelumDibaca($conn, $userId, $role);
    $rawItems = ambilNotifikasiUser($conn, $userId, $role, 10);
    $items = [];

    foreach ($rawItems as $n) {
        [$icon, $textColor, $bgColor, $styleAttr] = notifStyle($n['tipe']);
        $items[] = [
            'id'          => (int) $n['id'],
            'id_berkas'   => $n['id_berkas'] ? (int) $n['id_berkas'] : null,
            'judul'       => $n['judul'],
            'pesan'       => $n['pesan'],
            'tipe'        => $n['tipe'],
            'link'        => baseUrl('notifikasi_baca.php?id=' . (int) $n['id']),
            'is_read'     => (bool) $n['is_read'],
            'waktu_rel'   => waktuLalu($n['created_at']),
            'icon'        => $icon,
            'color'       => $textColor,
            'bg_color'    => $bgColor,
            'style_attr'  => $styleAttr,
            'nomor'       => $n['nomor_pendaftaran'] ?? null,
        ];
    }

    echo json_encode([
        'status'       => 'success',
        'unread_count' => $unreadCount,
        'items'        => $items,
    ]);
    exit;
}

if ($action === 'read') {
    $notifId = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($notifId > 0) {
        tandaiNotifikasiDibaca($conn, $notifId, $userId);
    }
    $unreadCount = hitungNotifikasiBelumDibaca($conn, $userId, $role);
    echo json_encode([
        'status'       => 'success',
        'unread_count' => $unreadCount,
    ]);
    exit;
}

if ($action === 'read_all') {
    tandaiSemuaNotifikasiDibaca($conn, $userId, $role);
    echo json_encode([
        'status'       => 'success',
        'unread_count' => 0,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Action tidak valid']);
exit;
