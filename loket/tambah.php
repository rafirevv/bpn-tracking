<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['loket']);

$pageTitle = 'Input Berkas Baru';
$suggestedNomor = generateNomorPendaftaran($conn);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-head mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1 text-dark fw-bold">Input Berkas Baru</h1>
        <p class="small text-muted mb-0">Masukkan data berkas permohonan dan tentukan alur tujuan distribusi berkas.</p>
    </div>
    <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<form action="simpan.php" method="POST" id="formTambahBerkas">
    <?= csrfField() ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 px-3 fw-semibold d-flex align-items-center justify-content-between bg-white border-bottom">
            <span><i class="bi bi-file-earmark-plus text-primary me-2"></i>Formulir Pendaftaran Berkas Baru</span>
            <span class="badge bg-light text-primary border"><i class="bi bi-clock me-1"></i>Pradaftar ATR/BPN</span>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-4">
                <!-- Kolom Kiri: Informasi Berkas Permohonan -->
                <div class="col-lg-7 pe-lg-4 border-lg-end d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <span class="fw-bold text-dark small"><i class="bi bi-card-text text-primary me-1"></i> 1. Informasi Berkas Permohonan</span>
                            <span class="badge text-bg-light border text-muted fw-normal" style="font-size:0.7rem;">Wajib Diisi *</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small mb-1" for="nomor_berkas">
                                    Nomor Berkas <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-upc-scan text-muted"></i></span>
                                    <input type="text" name="nomor_berkas" id="nomor_berkas" class="form-control mono fw-bold text-primary" required maxlength="100" placeholder="BPN/<?= date('Y') ?>/001" value="<?= e($suggestedNomor) ?>">
                                    <button type="button" class="btn btn-outline-secondary" id="btnAutoNomor" title="Reset ke nomor urut sistem berikutnya">
                                        <span class="small"><i class="bi bi-arrow-clockwise me-1"></i>Otomatis</span>
                                    </button>
                                </div>
                                <div class="form-text text-muted" style="font-size: 0.72rem; margin-top: 3px;">Format otomatis (<strong>BPN/<?= date('Y') ?>/001</strong>).</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold small mb-1" for="nama_pemohon">
                                    Nama Pemohon <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-person text-muted"></i></span>
                                    <input type="text" name="nama_pemohon" id="nama_pemohon" class="form-control" required maxlength="150" placeholder="Nama lengkap pemohon">
                                </div>
                                <div class="form-text text-muted" style="font-size: 0.72rem; margin-top: 3px;">Nama perorangan atau badan hukum.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold small mb-1" for="jenis_layanan">
                                    Jenis Layanan <span class="text-danger">*</span>
                                </label>
                                <div class="input-group position-relative">
                                    <span class="input-group-text bg-light"><i class="bi bi-tags text-muted"></i></span>
                                    <input type="text" name="jenis_layanan" id="jenis_layanan" list="list_jenis_layanan" class="form-control" required maxlength="150" placeholder="Pilih dari daftar atau ketik..." autocomplete="off">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" title="Pilih layanan BPN">
                                        <span class="d-none d-sm-inline small">Pilih</span>
                                    </button>
                                    <ul class="dropdown-menu shadow-lg w-100" style="max-height: 280px; overflow-y: auto; z-index: 1050; top: 100%; left: 0; margin-top: 4px;">
                                        <?php foreach (jenisLayananOptions() as $opt): ?>
                                            <li><a class="dropdown-item py-2 px-3 small item-layanan text-wrap" href="javascript:void(0)" data-value="<?= e($opt) ?>"><?= e($opt) ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <datalist id="list_jenis_layanan">
                                    <?php foreach (jenisLayananOptions() as $opt): ?>
                                        <option value="<?= e($opt) ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                                <div class="form-text text-muted" style="font-size: 0.72rem; margin-top: 3px;">Ketik manual atau klik <strong>Pilih</strong> untuk memilih.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold small mb-1" for="sertifikat_desa">
                                    Sertifikat/Desa <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-award text-muted"></i></span>
                                    <input type="text" name="sertifikat_desa" id="sertifikat_desa" class="form-control" required maxlength="255" placeholder="Nomor Sertifikat (atau nama Desa)">
                                </div>
                                <div class="form-text text-muted" style="font-size: 0.72rem; margin-top: 3px;">Nomor sertifikat atau nama kelurahan/desa.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Petunjuk Singkat Loket -->
                    <div class="mt-3 pt-2">
                        <div class="p-2 px-3 rounded-2" style="background:#F8FAFC; border:1px dashed #CBD5E1;">
                            <div class="d-flex align-items-center gap-2 text-muted" style="font-size: 0.75rem;">
                                <i class="bi bi-shield-check text-primary fs-6"></i>
                                <span>Pastikan berkas fisik diperiksa sebelum diteruskan. Batas waktu pengerjaan otomatis aktif saat berkas dikirim ke Seksi.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Pilihan Alur Distribusi & Aksi -->
                <div class="col-lg-5 ps-lg-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <span class="fw-bold text-dark small"><i class="bi bi-signpost-split text-primary me-1"></i> 2. Alur Tujuan Distribusi Berkas</span>
                            <span class="badge text-bg-primary rounded-pill" style="font-size:0.7rem;">Pilih Satu</span>
                        </div>

                        <!-- Daftar Opsi Alur Distribusi Berkas Vertikal -->
                        <div class="d-flex flex-column gap-2 mb-3">
                            <!-- Opsi 1: Seksi 1 (Survei & Pemetaan) -->
                            <label class="action-choice py-2 px-3 border-primary bg-light" id="card-seksi1" for="opt-seksi1" style="min-height: auto; cursor:pointer;">
                                <input type="radio" name="tujuan_distribusi" id="opt-seksi1" value="seksi_1" class="action-choice-radio" checked>
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center" style="width:34px; height:34px; flex-shrink:0;">
                                            <i class="bi bi-file-earmark-check text-primary" style="font-size:0.95rem;"></i>
                                        </div>
                                        <div class="fw-bold text-primary small">Seksi 1 (Survei &amp; Pemetaan)</div>
                                    </div>
                                    <span class="badge text-bg-primary rounded-pill" style="font-size: 0.7rem;">2 Hari Kerja</span>
                                </div>
                            </label>

                            <!-- Opsi 2: Seksi 2 (Penetapan Hak & Pendaftaran) -->
                            <label class="action-choice py-2 px-3" id="card-seksi2" for="opt-seksi2" style="min-height: auto; cursor:pointer;">
                                <input type="radio" name="tujuan_distribusi" id="opt-seksi2" value="seksi_2" class="action-choice-radio">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center" style="width:34px; height:34px; flex-shrink:0;">
                                            <i class="bi bi-file-earmark-check text-primary" style="font-size:0.95rem;"></i>
                                        </div>
                                        <div class="fw-bold text-dark small">Seksi 2 (Penetapan Hak &amp; Pendaftaran)</div>
                                    </div>
                                    <span class="badge text-bg-primary rounded-pill" style="font-size: 0.7rem;">2 Hari Kerja</span>
                                </div>
                            </label>

                            <!-- Opsi 3: Simpan di Loket (Draft) -->
                            <label class="action-choice py-2 px-3" id="card-draft" for="opt-draft" style="min-height: auto; cursor:pointer;">
                                <input type="radio" name="tujuan_distribusi" id="opt-draft" value="loket" class="action-choice-radio">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center" style="width:34px; height:34px; flex-shrink:0;">
                                            <i class="bi bi-inbox text-secondary" style="font-size:0.95rem;"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark small">Simpan di Loket (Draft)</div>
                                            <div class="text-muted" style="font-size: 0.72rem;">Disimpan di loket, belum diteruskan</div>
                                        </div>
                                    </div>
                                    <span class="badge text-bg-secondary rounded-pill" style="font-size: 0.7rem;">Draft</span>
                                </div>
                            </label>
                        </div>

                        <!-- Alert Penjelasan Dinamis Ringkas -->
                        <div class="alert alert-info py-2 px-3 d-flex align-items-start gap-2 mb-3" id="infoDistribusi" style="font-size: 0.78rem;">
                            <i class="bi bi-info-circle-fill text-primary flex-shrink-0 mt-1"></i>
                            <div id="infoDistribusiText">
                                <div class="fw-semibold text-dark" id="infoTitle">Distribusi ke Tim Seksi 1 (Survei &amp; Pemetaan)</div>
                                <div class="text-muted" id="infoDesc">
                                    Berkas ditujukan ke Tim Seksi 1 (Survei &amp; Pemetaan). Seluruh staf Seksi 1 dapat memproses berkas ini.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Aksi Simpan & Batal -->
                    <div class="d-flex align-items-center gap-2 pt-3 border-top mt-auto">
                        <a href="index.php" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center px-3" style="height: 40px; font-size: 0.85rem; font-weight: 600;">
                            Batal
                        </a>
                        <button type="submit" name="aksi_simpan" value="kirim" class="btn btn-primary flex-grow-1 d-inline-flex align-items-center justify-content-center text-center" id="btnSubmitKirim" style="height: 40px; font-size: 0.85rem; font-weight: 600; white-space: nowrap;">
                            <i class="bi bi-send-check me-2"></i><span id="btnSubmitLabel">Simpan &amp; Kirim ke Seksi 1</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('btnAutoNomor')?.addEventListener('click', function() {
    const input = document.getElementById('nomor_berkas');
    input.value = '<?= e($suggestedNomor) ?>';
    input.focus();
});

document.querySelectorAll('.item-layanan').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        const input = document.getElementById('jenis_layanan');
        input.value = this.getAttribute('data-value');
        input.focus();
    });
});

// Penanganan Pilihan Tujuan Distribusi Berkas
const radios = document.querySelectorAll('input[name="tujuan_distribusi"]');
const infoTitle = document.getElementById('infoTitle');
const infoDesc = document.getElementById('infoDesc');
const btnSubmitLabel = document.getElementById('btnSubmitLabel');
const btnSubmitKirim = document.getElementById('btnSubmitKirim');

const infoData = {
    seksi_1: {
        title: 'Distribusi ke Tim Seksi 1 (Survei & Pemetaan)',
        desc: 'Berkas ditujukan ke Tim Seksi 1 (Survei &amp; Pemetaan). Seluruh staf Seksi 1 dapat memproses berkas ini.',
        btnLabel: 'Simpan & Kirim ke Seksi 1',
        btnClass: 'btn-primary'
    },
    seksi_2: {
        title: 'Distribusi ke Tim Seksi 2 (Penetapan Hak & Pendaftaran)',
        desc: 'Berkas langsung ditujukan ke Tim Seksi 2 (Penetapan Hak &amp; Pendaftaran)',
        btnLabel: 'Simpan & Kirim ke Seksi 2',
        btnClass: 'btn-primary'
    },
    loket: {
        title: 'Simpan Sementara di Loket (Draft)',
        desc: 'Berkas disimpan dalam antrean Loket. Anda dapat mengirimkannya ke Seksi kapan saja melalui tabel antrean.',
        btnLabel: 'Simpan di Loket (Draft)',
        btnClass: 'btn-secondary'
    }
};

function updateTujuanUI() {
    radios.forEach(r => {
        const cardId = r.value === 'seksi_1' ? 'card-seksi1' : (r.value === 'seksi_2' ? 'card-seksi2' : 'card-draft');
        const card = document.getElementById(cardId);
        if (card) {
            const titleEl = card.querySelector('.fw-bold');
            if (r.checked) {
                card.classList.add('border-primary', 'bg-light');
                if (titleEl) {
                    titleEl.classList.remove('text-dark', 'text-secondary');
                    titleEl.classList.add(r.value === 'loket' ? 'text-secondary' : 'text-primary');
                }
            } else {
                card.classList.remove('border-primary', 'bg-light');
                if (titleEl) {
                    titleEl.classList.remove('text-primary');
                    titleEl.classList.add(r.value === 'loket' ? 'text-secondary' : 'text-dark');
                }
            }
        }
        if (r.checked && infoData[r.value]) {
            const data = infoData[r.value];
            infoTitle.innerHTML = data.title;
            infoDesc.innerHTML = data.desc;
            btnSubmitLabel.textContent = data.btnLabel;
            btnSubmitKirim.className = 'btn flex-grow-1 d-inline-flex align-items-center justify-content-center text-center ' + data.btnClass;
        }
    });
}

radios.forEach(r => r.addEventListener('change', updateTujuanUI));
updateTujuanUI();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
