<?php
// Halaman awal: arahkan ke dashboard jika sudah login, ke login jika belum.
require_once __DIR__ . '/includes/fungsi.php';

redirect(pengguna() ? 'modules/dashboard/index.php' : 'auth/login.php');
