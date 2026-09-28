<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    setFlash('danger', 'Permintaan tidak valid.');
    header('Location: users.php');
    exit;
}

$action = $_POST['action'] ?? '';
$validRoles = ['admin', 'loket', 'seksi_1', 'seksi_2'];
$validSubBagian = ['Pendaftaran', 'Peralihan', 'Penetapan'];

try {
    if ($action === 'create') {
        $nama     = trim($_POST['nama_lengkap'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role     = $_POST['role'] ?? '';
        $password = (string) ($_POST['password'] ?? '');

        if ($nama === '' || $username === '' || !in_array($role, $validRoles, true) || strlen($password) < 6) {
            setFlash('danger', 'Lengkapi semua data dengan benar. Kata sandi minimal 6 karakter.');
            header('Location: users.php');
            exit;
        }

        $subBagian = null;
        if ($role === 'seksi_2') {
            $subBagian = trim($_POST['sub_bagian'] ?? '');
            if (!in_array($subBagian, $validSubBagian, true)) {
                setFlash('danger', 'Silakan pilih Bagian yang valid untuk Seksi 2 (Pendaftaran, Peralihan, atau Penetapan).');
                header('Location: users.php');
                exit;
            }
        }

        $check = $conn->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetch()) {
            setFlash('danger', 'Username sudah digunakan, silakan pilih username lain.');
            header('Location: users.php');
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('INSERT INTO users (username, password, password_plain, nama_lengkap, role, sub_bagian, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())');
        $stmt->execute([$username, $hash, $password, $nama, $role, $subBagian]);
        setFlash('success', 'User baru berhasil ditambahkan.');

    } elseif ($action === 'update') {
        $id       = (int) ($_POST['id'] ?? 0);
        $nama     = trim($_POST['nama_lengkap'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role     = $_POST['role'] ?? '';
        $password = (string) ($_POST['password'] ?? '');

        if ($id <= 0 || $nama === '' || $username === '' || !in_array($role, $validRoles, true)) {
            setFlash('danger', 'Lengkapi semua data dengan benar.');
            header('Location: users.php');
            exit;
        }

        $subBagian = null;
        if ($role === 'seksi_2') {
            $subBagian = trim($_POST['sub_bagian'] ?? '');
            if (!in_array($subBagian, $validSubBagian, true)) {
                setFlash('danger', 'Silakan pilih Bagian yang valid untuk Seksi 2 (Pendaftaran, Peralihan, atau Penetapan).');
                header('Location: users.php');
                exit;
            }
        }

        $check = $conn->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $check->execute([$username, $id]);
        if ($check->fetch()) {
            setFlash('danger', 'Username sudah digunakan oleh akun lain.');
            header('Location: users.php');
            exit;
        }

        if ($password !== '') {
            if (strlen($password) < 6) {
                setFlash('danger', 'Kata sandi baru minimal 6 karakter.');
                header('Location: users.php');
                exit;
            }
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('UPDATE users SET nama_lengkap=?, username=?, role=?, sub_bagian=?, password=?, password_plain=? WHERE id=?');
            $stmt->execute([$nama, $username, $role, $subBagian, $hash, $password, $id]);
        } else {
            $stmt = $conn->prepare('UPDATE users SET nama_lengkap=?, username=?, role=?, sub_bagian=? WHERE id=?');
            $stmt->execute([$nama, $username, $role, $subBagian, $id]);
        }

        // Perbarui session jika admin mengedit akunnya sendiri
        if ($id === (int) $_SESSION['user_id']) {
            $_SESSION['nama_lengkap'] = $nama;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['sub_bagian'] = $subBagian;
        }

        setFlash('success', 'Data user berhasil diperbarui.');

    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $_SESSION['user_id']) {
            setFlash('danger', 'Anda tidak dapat menonaktifkan akun sendiri.');
            header('Location: users.php');
            exit;
        }
        $stmt = $conn->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('success', 'Status user berhasil diperbarui.');

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $_SESSION['user_id']) {
            setFlash('danger', 'Anda tidak dapat menghapus akun sendiri.');
            header('Location: users.php');
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('success', 'User berhasil dihapus.');

    } else {
        setFlash('danger', 'Aksi tidak dikenali.');
    }
} catch (PDOException $e) {
    // Kemungkinan besar disebabkan oleh foreign key constraint (user masih memiliki riwayat berkas)
    error_log('User CRUD error: ' . $e->getMessage());
    setFlash('danger', 'Gagal memproses: user ini masih memiliki riwayat berkas terkait. Nonaktifkan saja alih-alih menghapus.');
}

header('Location: users.php');
exit;
