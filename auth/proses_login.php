<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

if (!verifyCsrf()) {
    setFlash('danger', 'Sesi tidak valid, silakan coba lagi.');
    header('Location: login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    setFlash('danger', 'Username dan kata sandi wajib diisi.');
    header('Location: login.php');
    exit;
}

// Rate-limit sederhana berbasis session untuk memperlambat brute-force
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
if ($_SESSION['login_attempts'] >= 10) {
    setFlash('danger', 'Terlalu banyak percobaan gagal. Coba lagi beberapa saat lagi.');
    header('Location: login.php');
    exit;
}

$stmt = $conn->prepare('SELECT id, username, password, nama_lengkap, role, sub_bagian, is_active FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['login_attempts']++;
    setFlash('danger', 'Username atau kata sandi salah.');
    header('Location: login.php');
    exit;
}

if ((int) $user['is_active'] !== 1) {
    setFlash('danger', 'Akun Anda telah dinonaktifkan. Hubungi administrator.');
    header('Location: login.php');
    exit;
}

// Login berhasil
session_regenerate_id(true);
unset($_SESSION['login_attempts']);

$_SESSION['user_id']       = $user['id'];
$_SESSION['username']      = $user['username'];
$_SESSION['nama_lengkap']  = $user['nama_lengkap'];
$_SESSION['role']          = $user['role'];
$_SESSION['sub_bagian']     = $user['sub_bagian'] ?? null;

setFlash('success', 'Selamat datang, ' . $user['nama_lengkap'] . '.');
header('Location: ' . baseUrl(roleHome($user['role'])));
exit;
