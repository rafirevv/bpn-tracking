<?php
/**
 * Handler penandaan baca dan pengalihan (redirect) notifikasi
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$targetUrl = baseUrl(roleHome($_SESSION['role']));

if ($id > 0) {
    tandaiNotifikasiDibaca($conn, $id, $userId);

    $stmt = $conn->prepare("SELECT link FROM notifikasi WHERE id = ?");
    $stmt->execute([$id]);
    $link = $stmt->fetchColumn();

    if (!empty($link)) {
        if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
            $targetUrl = $link;
        } else {
            $targetUrl = baseUrl($link);
        }
    }
}

header('Location: ' . $targetUrl);
exit;
