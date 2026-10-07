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

<form action="simpan.php" method="POST" id="formTambahBerkas" enctype="multipart/form-data">
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

                            <!-- Upload Foto Bidang & Keterangan Foto -->
                            <div class="col-12">
                                <label class="form-label fw-semibold small mb-1" for="foto_bidang">
                                    <i class="bi bi-camera me-1 text-primary"></i>Upload Foto Bidang
                                    <span class="badge bg-light text-secondary border ms-1 fw-normal" style="font-size:0.68rem;">Opsional</span>
                                </label>
                                
                                <div class="upload-dropzone p-3 rounded-3 text-center border position-relative" id="dropzoneFoto" style="border: 1.5px dashed #CBD5E1 !important; background: #F8FAFC; transition: all 0.2s ease;">
                                    <input type="file" name="foto_bidang" id="foto_bidang" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer; z-index: 5;" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                    
                                    <!-- State Default (Belum Ada File Dipilih) -->
                                    <div id="uploadPrompt" class="d-flex align-items-center justify-content-center gap-2 py-1">
                                        <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; flex-shrink: 0;">
                                            <i class="bi bi-cloud-arrow-up fs-5"></i>
                                        </div>
                                        <div class="text-start">
                                            <div class="fw-semibold text-dark small">Pilih foto bidang atau seret file ke sini</div>
                                            <div class="text-muted" style="font-size: 0.72rem;">JPG, JPEG, PNG, WEBP &bull; Maks. 10 MB</div>
                                        </div>
                                    </div>

                                    <!-- State Preview (File Sudah Dipilih) -->
                                    <div id="uploadPreview" class="d-none align-items-center justify-content-between p-1 bg-white rounded-2 border text-start shadow-sm" style="position: relative; z-index: 10;">
                                        <div class="d-flex align-items-center gap-2 overflow-hidden flex-grow-1" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#modalPreviewFoto" title="Klik untuk melihat foto ukuran penuh">
                                            <div class="position-relative flex-shrink-0">
                                                <img id="imgPreviewThumb" src="" alt="Preview Foto Bidang" class="rounded object-fit-cover border shadow-sm" style="width: 44px; height: 44px; transition: transform 0.15s ease;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
                                                <span class="position-absolute bottom-0 end-0 bg-dark bg-opacity-75 text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 16px; height: 16px; font-size: 9px; pointer-events: none;">
                                                    <i class="bi bi-zoom-in"></i>
                                                </span>
                                            </div>
                                            <div class="overflow-hidden">
                                                <div class="fw-semibold text-dark small text-truncate" id="previewFileName">foto.jpg</div>
                                                <div class="text-muted" style="font-size: 0.72rem;">
                                                    <span id="previewFileSize">0 KB</span> &bull; 
                                                    <span class="text-success fw-medium"><i class="bi bi-check-circle me-1"></i>Siap diupload</span> &bull;
                                                    <span class="text-primary"><i class="bi bi-eye me-1"></i>Lihat foto</span>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 ms-2 flex-shrink-0" id="btnHapusFoto" title="Hapus foto ini">
                                            <i class="bi bi-trash me-1"></i>Hapus
                                        </button>
                                    </div>
                                </div>

                                <!-- Input Keterangan Foto -->
                                <div class="mt-2" id="containerKeteranganFoto">
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted"><i class="bi bi-chat-left-text"></i></span>
                                        <input type="text" name="keterangan_foto" id="keterangan_foto" class="form-control" maxlength="255" placeholder="Keterangan foto bidang (opsional, cth: patok batas utara, kondisi tanah)">
                                    </div>
                                </div>
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

<!-- Modal Preview Foto Bidang -->
<div class="modal fade" id="modalPreviewFoto" tabindex="-1" aria-labelledby="modalPreviewFotoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2 px-3">
                <h5 class="modal-title fs-6 fw-bold text-dark d-flex align-items-center" id="modalPreviewFotoLabel">
                    <i class="bi bi-image text-primary me-2"></i>Preview Foto Bidang
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-2 fw-normal" id="modalPreviewFileName" style="font-size:0.75rem;">foto.jpg</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body text-center p-2 bg-dark">
                <img id="modalPreviewImg" src="" alt="Preview Foto Bidang" class="img-fluid rounded" style="max-height: 75vh; object-fit: contain;">
            </div>
            <div class="modal-footer py-2 px-3 d-flex justify-content-between align-items-center bg-light">
                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>Ukuran: <strong id="modalPreviewFileSize">0 KB</strong>
                </div>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

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

// Penanganan Upload & Preview Foto Bidang
const fotoInput = document.getElementById('foto_bidang');
const dropzone = document.getElementById('dropzoneFoto');
const uploadPrompt = document.getElementById('uploadPrompt');
const uploadPreview = document.getElementById('uploadPreview');
const imgPreviewThumb = document.getElementById('imgPreviewThumb');
const previewFileName = document.getElementById('previewFileName');
const previewFileSize = document.getElementById('previewFileSize');
const btnHapusFoto = document.getElementById('btnHapusFoto');
const keteranganFoto = document.getElementById('keterangan_foto');
const modalPreviewImg = document.getElementById('modalPreviewImg');
const modalPreviewFileName = document.getElementById('modalPreviewFileName');
const modalPreviewFileSize = document.getElementById('modalPreviewFileSize');

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function handleFotoSelected(file) {
    if (!file) return;

    // Validasi tipe file
    if (!file.type.match(/^image\/(jpeg|jpg|png|webp)$/i)) {
        alert('Harap pilih file foto gambar dengan format JPG, JPEG, PNG, atau WEBP.');
        fotoInput.value = '';
        return;
    }

    // Validasi ukuran file (10 MB)
    const maxBytes = 10 * 1024 * 1024;
    if (file.size > maxBytes) {
        alert('Ukuran foto melebihi 10 MB (' + formatBytes(file.size) + '). Harap gunakan foto dengan ukuran maksimal 10 MB.');
        fotoInput.value = '';
        return;
    }

    const formattedSize = formatBytes(file.size);
    previewFileName.textContent = file.name;
    previewFileSize.textContent = formattedSize;
    if (modalPreviewFileName) modalPreviewFileName.textContent = file.name;
    if (modalPreviewFileSize) modalPreviewFileSize.textContent = formattedSize;

    const reader = new FileReader();
    reader.onload = function(e) {
        imgPreviewThumb.src = e.target.result;
        if (modalPreviewImg) {
            modalPreviewImg.src = e.target.result;
        }
        uploadPrompt.classList.add('d-none');
        uploadPreview.classList.remove('d-none');
        uploadPreview.classList.add('d-flex');

        // Fokus ke kolom keterangan foto
        if (keteranganFoto) {
            keteranganFoto.focus();
        }
    };
    reader.readAsDataURL(file);
}

fotoInput?.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        handleFotoSelected(this.files[0]);
    }
});

btnHapusFoto?.addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    fotoInput.value = '';
    imgPreviewThumb.src = '';
    if (modalPreviewImg) {
        modalPreviewImg.src = '';
    }
    uploadPreview.classList.add('d-none');
    uploadPreview.classList.remove('d-flex');
    uploadPrompt.classList.remove('d-none');
    if (keteranganFoto) {
        keteranganFoto.value = '';
    }
});

// Sinkronisasi otomatis saat Modal Preview Foto dibuka
const modalPreviewFoto = document.getElementById('modalPreviewFoto');
modalPreviewFoto?.addEventListener('show.bs.modal', function(e) {
    if (!imgPreviewThumb || !imgPreviewThumb.src || imgPreviewThumb.src === '' || imgPreviewThumb.src === window.location.href) {
        e.preventDefault();
        return;
    }
    const modalImg = document.getElementById('modalPreviewImg');
    const modalName = document.getElementById('modalPreviewFileName');
    const modalSize = document.getElementById('modalPreviewFileSize');
    if (modalImg) modalImg.src = imgPreviewThumb.src;
    if (modalName && previewFileName) modalName.textContent = previewFileName.textContent;
    if (modalSize && previewFileSize) modalSize.textContent = previewFileSize.textContent;
});

// Drag and drop efek visual
['dragenter', 'dragover'].forEach(eventName => {
    dropzone?.addEventListener(eventName, function(e) {
        e.preventDefault();
        dropzone.style.borderColor = 'var(--bs-primary, #0d6efd)';
        dropzone.style.backgroundColor = '#EFF6FF';
    }, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropzone?.addEventListener(eventName, function(e) {
        e.preventDefault();
        dropzone.style.borderColor = '#CBD5E1';
        dropzone.style.backgroundColor = '#F8FAFC';
    }, false);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
