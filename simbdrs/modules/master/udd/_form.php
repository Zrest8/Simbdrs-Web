<?php
// Form bersama untuk tambah.php dan edit.php.
// Variabel yang dipakai: $u (array data form), $error, $aktif (edit), $mode_edit (edit)
$mode_edit = $mode_edit ?? false;
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-building"></i><h2><?= $mode_edit ? 'Edit' : 'Tambah' ?> UDD Asal</h2>
            </div>
            <div class="panel-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex gap-2 align-items-center"><i class="bi bi-x-circle"></i><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="nama_udd">Nama UDD / UTD <span class="text-danger">*</span></label>
                        <input class="form-control" id="nama_udd" name="nama_udd" maxlength="100"
                               value="<?= e($u['nama_udd']) ?>" placeholder="mis. UDD PMI Kota Contoh" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="alamat">Alamat</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="2" maxlength="255"
                                  placeholder="boleh kosong"><?= e($u['alamat']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="telepon">Telepon</label>
                        <input class="form-control" type="tel" id="telepon" name="telepon" maxlength="20"
                               value="<?= e($u['telepon']) ?>" placeholder="mis. (0274) 123456">
                        <div class="form-text">Dipakai untuk menghubungi UDD saat memesan atau mengembalikan kantong.</div>
                    </div>

                    <?php if ($mode_edit): ?>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="aktif" name="aktif" <?= $aktif ? 'checked' : '' ?>>
                        <label class="form-check-label" for="aktif">UDD aktif</label>
                        <div class="form-text">UDD nonaktif tidak bisa dipilih untuk penerimaan baru, tetapi riwayatnya tetap tersimpan.</div>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Simpan</button>
                        <a class="btn btn-outline-secondary" href="index.php">Batal</a>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
