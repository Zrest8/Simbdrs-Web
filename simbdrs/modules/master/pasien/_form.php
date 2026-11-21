<?php
// Form bersama untuk tambah.php dan edit.php.
// Variabel yang dipakai: $p (array data form), $error, $aktif (edit), $mode_edit (edit)
$mode_edit = $mode_edit ?? false;
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <section class="panel">
            <div class="panel-head">
                <i class="bi bi-people"></i><h2><?= $mode_edit ? 'Edit' : 'Tambah' ?> Pasien</h2>
            </div>
            <div class="panel-body">
                <div class="alert alert-warning small d-flex gap-2 align-items-start py-2">
                    <i class="bi bi-shield-exclamation mt-1"></i>
                    <span>Isi <strong>data fiktif</strong> saja. Jangan memasukkan data pasien asli (UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi).</span>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex gap-2 align-items-center"><i class="bi bi-x-circle"></i><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-sm-5">
                            <label class="form-label" for="no_rm">No. Rekam Medis <span class="text-danger">*</span></label>
                            <input class="form-control" id="no_rm" name="no_rm" maxlength="20"
                                   value="<?= e($p['no_rm']) ?>" placeholder="mis. RM-000123" required autofocus>
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label" for="nama_pasien">Nama pasien <span class="text-danger">*</span></label>
                            <input class="form-control" id="nama_pasien" name="nama_pasien" maxlength="100"
                                   value="<?= e($p['nama_pasien']) ?>" placeholder="mis. Andi Pratama" required>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label" for="tgl_lahir">Tanggal lahir <span class="text-danger">*</span></label>
                            <input class="form-control" type="date" id="tgl_lahir" name="tgl_lahir"
                                   value="<?= e($p['tgl_lahir']) ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-sm-7">
                            <span class="form-label d-block">Jenis kelamin <span class="text-danger">*</span></span>
                            <div class="d-flex gap-4 pt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_l" value="L" <?= $p['jenis_kelamin'] === 'L' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="jk_l">Laki-laki</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_p" value="P" <?= $p['jenis_kelamin'] === 'P' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="jk_p">Perempuan</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label" for="gol_darah">Golongan darah</label>
                            <select class="form-select" id="gol_darah" name="gol_darah">
                                <option value="">— belum diperiksa —</option>
                                <?php foreach (['A', 'B', 'AB', 'O'] as $g): ?>
                                    <option value="<?= $g ?>" <?= $p['gol_darah'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label" for="rhesus">Rhesus</label>
                            <select class="form-select" id="rhesus" name="rhesus">
                                <option value="">— belum diperiksa —</option>
                                <option value="+" <?= $p['rhesus'] === '+' ? 'selected' : '' ?>>Positif (+)</option>
                                <option value="-" <?= $p['rhesus'] === '-' ? 'selected' : '' ?>>Negatif (−)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-text mt-0">Golongan darah dan rhesus wajib sudah tercatat sebelum permintaan darah untuk pasien ini bisa diproses (BR-13).</div>
                        </div>
                    </div>

                    <?php if ($mode_edit): ?>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="aktif" name="aktif" <?= $aktif ? 'checked' : '' ?>>
                        <label class="form-check-label" for="aktif">Pasien aktif</label>
                        <div class="form-text">Pasien nonaktif tidak bisa dipilih untuk permintaan baru, tetapi riwayatnya tetap tersimpan.</div>
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
