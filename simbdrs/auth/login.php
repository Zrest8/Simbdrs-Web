<?php
// =====================================================================
// Halaman login (F-01)
// Tabel: pengguna (SELECT), log_aktivitas (INSERT)
// =====================================================================

require_once __DIR__ . '/../includes/fungsi.php';

// Sudah login? langsung ke dashboard
if (pengguna()) {
    redirect('modules/dashboard/index.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        // Ambil akun berdasarkan username (prepared statement)
        $stmt = $pdo->prepare(
            "SELECT p.id_pengguna, p.nama, p.username, p.password_hash, p.role,
                    p.id_ruangan, p.aktif, r.nama_ruangan
             FROM pengguna p
             LEFT JOIN ruangan r ON r.id_ruangan = p.id_ruangan
             WHERE p.username = ?"
        );
        $stmt->execute([$username]);
        $akun = $stmt->fetch();

        // password_verify() mencocokkan password dengan hash di database
        if (!$akun || !password_verify($password, $akun['password_hash'])) {
            $error = 'Username atau password salah.';
            // Catat percobaan gagal (tanpa password) untuk audit keamanan
            catat_log($pdo, 'LOGIN_GAGAL', 'pengguna', $akun ? (int) $akun['id_pengguna'] : null,
                'Login gagal untuk username "' . mb_substr($username, 0, 50) . '"');
        } elseif ((int) $akun['aktif'] === 0) {
            $error = 'Akun ini sudah dinonaktifkan. Hubungi admin.';
            catat_log($pdo, 'LOGIN_GAGAL', 'pengguna', (int) $akun['id_pengguna'], 'Login ditolak: akun nonaktif');
        } else {
            session_regenerate_id(true);   // cegah session fixation
            unset($akun['password_hash']); // jangan simpan hash di session
            $_SESSION['pengguna'] = $akun;
            catat_log($pdo, 'LOGIN', 'pengguna', (int) $akun['id_pengguna'], 'Login berhasil');
            redirect('modules/dashboard/index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — <?= e(NAMA_APP) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="login-page">
<div class="login-wrap">
    <div class="login-brand">
        <div class="brand-logo"><i class="bi bi-droplet-fill"></i></div>
        <h1><?= e(NAMA_APP) ?></h1>
        <p class="lead">Sistem Informasi Manajemen Bank Darah Rumah Sakit</p>
        <ul class="login-points">
            <li><i class="bi bi-alarm"></i> Peringatan dini kedaluwarsa &amp; FEFO</li>
            <li><i class="bi bi-heart-pulse"></i> Pencatatan permintaan &amp; transfusi per pasien</li>
            <li><i class="bi bi-graph-up-arrow"></i> Rekomendasi pemesanan berbasis data</li>
        </ul>
        <small class="opacity-75"><?= e(NAMA_RS) ?> · Prototipe akademik</small>
    </div>

    <div class="login-form">
        <h2>Masuk</h2>
        <p class="text-muted mb-4">Gunakan akun yang diberikan admin.</p>

        <?= tampil_flash() ?>
        <?php if ($error): ?>
            <div class="alert alert-danger d-flex gap-2 align-items-center"><i class="bi bi-x-circle"></i><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input class="form-control" id="username" name="username" value="<?= e($username) ?>" autocomplete="username" autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input class="form-control" id="password" name="password" type="password" autocomplete="current-password">
                </div>
            </div>
            <button class="btn btn-primary w-100 py-2" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i> Masuk</button>
        </form>

        <details class="demo-akun mt-4">
            <summary>Akun demo (khusus presentasi lokal)</summary>
            <table class="table table-sm mb-0 mt-2">
                <tr><td><code>admin</code></td><td><code>admin123</code></td><td>Administrator</td></tr>
                <tr><td><code>petugas</code></td><td><code>petugas123</code></td><td>Petugas BDRS</td></tr>
                <tr><td><code>igd</code></td><td><code>igd123</code></td><td>Ruangan IGD</td></tr>
                <tr><td><code>kepala</code></td><td><code>kepala123</code></td><td>Kepala BDRS</td></tr>
            </table>
        </details>
    </div>
</div>
</body>
</html>
