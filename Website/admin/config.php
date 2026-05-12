<?php

// -----------------------------------------------
// Konfigurasi Database
// Sesuaikan jika berbeda di komputer kamu
// -----------------------------------------------
define('DB_HOST',     'localhost');
define('DB_USER',     'root');       // default XAMPP
define('DB_PASSWORD', '');           // default XAMPP = kosong
define('DB_NAME',     'shop');

// -----------------------------------------------
// Koneksi ke MySQL
// -----------------------------------------------
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8');

// -----------------------------------------------
// Konfigurasi Umum Website
// -----------------------------------------------
define('SITE_NAME',  'E-Commerce UMKM Makanan Lokal');
define('SITE_URL',   'http://localhost/Website');   // sesuaikan nama folder kamu
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/Website/uploads/');

// -----------------------------------------------
// Mulai session (cukup dipanggil sekali)
// -----------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}