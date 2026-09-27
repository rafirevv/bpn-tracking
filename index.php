<?php
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . baseUrl(roleHome($_SESSION['role'])));
} else {
    header('Location: ' . baseUrl('auth/login.php'));
}
exit;
