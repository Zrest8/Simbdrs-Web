<?php
// =====================================================================
// Koneksi database SIMBDRS (PDO)
// File ini di-require oleh setiap halaman yang butuh database.
// Setelah di-require, objek $pdo siap dipakai untuk query.
// =====================================================================

$host = 'localhost';
$db   = 'simbdrs';
$user = 'root';
$pass = '';   // default XAMPP (tanpa password)

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // error SQL dilempar sebagai exception
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // hasil query berupa array asosiatif
        ]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
