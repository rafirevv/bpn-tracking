<?php
/**
 * Konfigurasi Koneksi Database
 * Sistem Monitoring dan Tracking Berkas Pradaftar - ATR/BPN
 *
 * Menggunakan PDO dengan prepared statements untuk mencegah SQL Injection.
 * Sesuaikan konstanta di bawah ini dengan konfigurasi server Anda.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'bpn_tracking');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // gunakan native prepared statement (aman dari SQL Injection)
    ];

    $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Sinkronisasi timezone MySQL dengan PHP agar kalkulasi SLA (NOW(), deadline) konsisten
    $conn->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    // Jangan tampilkan detail error database ke publik (best practice keamanan)
    error_log('Database Connection Error: ' . $e->getMessage());
    die('Koneksi database gagal. Silakan hubungi administrator sistem.');
}
