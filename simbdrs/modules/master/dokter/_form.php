<?php
// Form bersama untuk tambah.php dan edit.php.
// Variabel: $f (nama, spesialis, username), $error, $aktif (edit), $mode_edit (edit)
// Setiap dokter WAJIB punya akun login, jadi username & password ada di form ini.
$mode_edit = $mode_edit ?? false;
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-person-badge"></i><h2><?= $mode_edit ? 'Edit' : 'Tambah' ?> Dokter</h2>
            </div>
            <div class="panel-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex gap-2 align-items-center"><i class="bi bi-x-circle"></i><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" novalidate autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-sm-7">
                            <label class="form-label" for="nama">Nama dokter <span class="text-danger">*</span></label>
                            <input class="form-control" id="nama" name="nama" maxlength="100"
                                   value="<?= e($f['nama']) ?>" placeholder="mis. dr. Budi Santoso, Sp.PD" required autofocus>
                            <div class="form-text">Gunakan nama fiktif (data dummy).</div>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label" for="spesialis">Spesialis</label>
                            <input class="form-control" id="spesialis" name="spesialis" maxlength="100"
                                   value="<?= e($f['spesialis']) ?>" placeholder="mis. Penyakit Dalam (boleh kosong)">
                        </div>

                        <div class="col-12">
                            <hr class="my-1">
                            <div class="small fw-semibold mt-2"><i class="bi bi-key"></i> Akun login dokter</div>
                            <div class="form-text mt-0">Dokter login untuk memantau permintaan darah dan transfusi pasien yang ia tangani.</div>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
                            <input class="form-control" id="username" name="username" maxlength="50"
                                   value="<?= e($f['username']) ?>" placeholder="mis. dr.budi" autocomplete="off" required>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="password">Password <?= $mode_edit ? 'baru' : '<span class="text-danger">*</span>' ?></label>
                            <input class="form-control" type="password" id="password" name="password" maxlength="72" autocomplete="new-password">
                            <div class="form-text"><?= $mode_edit ? 'Kosongkan jika tidak diganti.' : 'Minimal ' . PANJANG_PASSWORD_MIN . ' karakter.' ?></div>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="password2">Ulangi password</label>
                            <input class="form-control" type="password" id="password2" name="password2" maxlength="72" autocomplete="new-password">
                        </div>
                    </div>

                    <?php if ($mode_edit): ?>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="aktif" name="aktif" <?= $aktif ? 'checked' : '' ?>>
                        <label class="form-check-label" for="aktif">Dokter aktif</label>
                        <div class="form-text">Dokter nonaktif tidak bisa login dan tidak bisa dipilih untuk permintaan baru, tetapi riwayatnya tetap tersimpan.</div>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Simpan</button>
                        <a class="btn btn-outline-secondary" href="index.php">Batal</a>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
