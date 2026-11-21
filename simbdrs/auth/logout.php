<?php
// Logout: catat log, hapus session, kembali ke login.
require_once __DIR__ . '/../includes/fungsi.php';

if (pengguna()) {
    catat_log($pdo, 'LOGOUT', 'pengguna', (int) pengguna()['id_pengguna'], 'Logout');
}

$_SESSION = [];
session_destroy();

session_start();
set_flash('success', 'Anda sudah keluar.');
redirect('auth/login.php');
