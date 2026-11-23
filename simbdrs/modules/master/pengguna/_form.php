<?php
// Form bersama untuk tambah.php dan edit.php.
// Variabel: $p (data form), $error, $ruangan (daftar), $mode_edit, $aktif, $akun_sendiri
$mode_edit    = $mode_edit ?? false;
$akun_sendiri = $akun_sendiri ?? false;
$akun_dokter  = $akun_dokter ?? false;
$kunci_peran  = $akun_sendiri || $akun_dokter;
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-person-gear"></i><h2><?= $mode_edit ? 'Edit' : 'Tambah' ?> Pengguna</h2>
            </div>
            <div class="panel-body">
                <?php if ($akun_sendiri): ?>
                    <div class="alert alert-info small d-flex gap-2 align-items-start py-2">
                        <i class="bi bi-person-check mt-1"></i>
                        <span>Ini akun Anda sendiri. Peran dan status aktif dikunci supaya Anda tidak terkunci keluar dari sistem.</span>
                    </div>
                <?php endif; ?>

                <?php if ($akun_dokter): ?>
                    <div class="alert alert-info small d-flex gap-2 align-items-start py-2">
                        <i class="bi bi-person-badge mt-1"></i>
                        <span>Ini akun dokter. Perannya dikunci; data spesialis dikelola di menu
                            <a href="<?= url('modules/master/dokter/index.php') ?>">Dokter</a>.</span>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex gap-2 align-items-center"><i class="bi bi-x-circle"></i><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" novalidate autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-sm-7">
                            <label class="form-label" for="nama">Nama lengkap <span class="text-danger">*</span></label>
                            <input class="form-control" id="nama" name="nama" maxlength="100" value="<?= e($p['nama']) ?>"
                                   placeholder="mis. Siti Petugas" required autofocus>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
                            <input class="form-control" id="username" name="username" maxlength="50" value="<?= e($p['username']) ?>"
                                   placeholder="mis. siti.bdrs" autocomplete="off" required>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label" for="role">Peran <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" <?= $kunci_peran ? 'disabled' : '' ?> required>
                                <option value="">— pilih peran —</option>
                                <?php foreach ($akun_dokter ? ['DOKTER'] : PERAN_VALID as $r): ?>
                                    <option value="<?= $r ?>" <?= $p['role'] === $r ? 'selected' : '' ?>><?= e(nama_role($r)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($kunci_peran): ?><input type="hidden" name="role" value="<?= e($p['role']) ?>"><?php else: ?>
                                <div class="form-text">Akun dokter dibuat lewat menu <a href="<?= url('modules/master/dokter/tambah.php') ?>">Dokter</a>.</div><?php endif; ?>
                        </div>
                        <div class="col-sm-6" id="grup-ruangan">
                            <label class="form-label" for="id_ruangan">Ruangan <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_ruangan" name="id_ruangan">
                                <option value="0">— pilih ruangan —</option>
                                <?php foreach ($ruangan as $r): ?>
                                    <option value="<?= (int) $r['id_ruangan'] ?>" <?= $p['id_ruangan'] === (int) $r['id_ruangan'] ? 'selected' : '' ?>>
                                        <?= e($r['nama_ruangan']) ?><?= $r['aktif'] ? '' : ' (nonaktif)' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Perawat hanya melihat permintaan dari ruangan ini (BR-11).</div>
                        </div>

                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-sm-6">
                            <label class="form-label" for="password">Password <?= $mode_edit ? 'baru' : '<span class="text-danger">*</span>' ?></label>
                            <input class="form-control" type="password" id="password" name="password" maxlength="72" autocomplete="new-password">
                            <div class="form-text"><?= $mode_edit ? 'Kosongkan jika tidak ingin mengganti password.' : 'Minimal ' . PANJANG_PASSWORD_MIN . ' karakter.' ?></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="password2">Ulangi password</label>
                            <input class="form-control" type="password" id="password2" name="password2" maxlength="72" autocomplete="new-password">
                        </div>
                    </div>

                    <?php if ($mode_edit): ?>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="aktif" name="aktif" <?= $aktif ? 'checked' : '' ?> <?= $akun_sendiri ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="aktif">Akun aktif</label>
                        <?php if ($akun_sendiri): ?><input type="hidden" name="aktif" value="1"><?php endif; ?>
                        <div class="form-text">Akun nonaktif tidak bisa login, tetapi jejak aktivitasnya tetap tersimpan.</div>
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
<?php
// Pilihan ruangan hanya ditampilkan untuk peran PERAWAT
$script_tambahan = <<<'JS'
<script>
(function () {
    const role = document.getElementById('role');
    const grup = document.getElementById('grup-ruangan');
    const atur = () => { grup.style.display = role.value === 'PERAWAT' ? '' : 'none'; };
    role.addEventListener('change', atur);
    atur();
})();
</script>
JS;
