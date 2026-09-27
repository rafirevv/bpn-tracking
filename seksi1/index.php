<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['seksi_1']);

$overdueList = getOverdueBerkasUser($conn, $_SESSION['user_id'], 'seksi_1');
$warningList = getWarningBerkasUser($conn, $_SESSION['user_id'], 'seksi_1');

$list = $conn->query("
    SELECT b.*, u.nama_lengkap AS pemegang_nama,
           (b.status_posisi = 'ditolak_ke_seksi1') AS is_perlu_perbaikan,
           (b.status_posisi != 'ditolak_ke_seksi1' AND (SELECT lp.status_sebelum 
                FROM log_pergerakan lp 
                WHERE lp.id_berkas = b.id AND lp.status_sesudah = b.status_posisi 
                ORDER BY lp.id DESC LIMIT 1) = 'ditolak_ke_loket'
           ) AS is_revisi
    FROM berkas b
    LEFT JOIN users u ON u.id = b.petugas_tujuan_id
    WHERE b.status_posisi IN ('seksi_1','ditolak_ke_seksi1')
    ORDER BY (b.deadline_at IS NOT NULL AND b.deadline_at < NOW()) DESC, 
             (b.deadline_at IS NOT NULL AND b.deadline_at >= NOW() AND b.deadline_at <= DATE_ADD(NOW(), INTERVAL 1 DAY)) DESC,
             FIELD(b.status_posisi,'ditolak_ke_seksi1','seksi_1'), 
             b.updated_at ASC
")->fetchAll();

$totalAntrean         = count($list);
$totalPerluPerbaikan  = 0;
$totalRevisi          = 0;
$totalWaspada         = count($warningList);
$totalKritis          = count($overdueList);

foreach ($list as $b) {
    if (!empty($b['is_perlu_perbaikan'])) {
        $totalPerluPerbaikan++;
    } elseif (!empty($b['is_revisi'])) {
        $totalRevisi++;
    }
}

$pageTitle = 'Antrean Seksi 1 — Survei & Pemetaan';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Antrean &mdash; Seksi 1 (Survei &amp; Pemetaan)</h1>
        <p>Lakukan pemeriksaan berkas, pengukuran, dan pemetaan. Teruskan ke Seksi 2 (Penetapan Hak &amp; Pendaftaran), teruskan ke Loket, atau kembalikan ke Loket jika tidak lengkap.</p>
    </div>
</div>

<style>
.stat-card-tab {
    cursor: pointer;
}
</style>

<!-- Stat Cards Seksi 1 (5 Kartu) -->
<div class="row g-3 mb-4" id="statCardRow">
    <div class="col-6 col-md-4 col-xl">
        <div class="stat-card stat-card-tab active-tab" style="--stat-color:#2563EB" data-filter="semua" tabindex="0" role="button" aria-pressed="true" title="Tampilkan semua berkas antrean">
            <div class="stat-icon"><i class="bi bi-inbox-fill"></i></div>
            <div>
                <div class="stat-value"><?= $totalAntrean ?></div>
                <div class="stat-label">Total Antrean</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="stat-card stat-card-tab <?= $totalPerluPerbaikan > 0 ? 'border-danger' : '' ?>" style="--stat-color:#C0392B" data-filter="perbaikan" tabindex="0" role="button" title="Berkas yang ditolak/dikembalikan ke Seksi 1 untuk diperbaiki">
            <div class="stat-icon"><i class="bi bi-arrow-return-left"></i></div>
            <div>
                <div class="stat-value <?= $totalPerluPerbaikan > 0 ? 'text-danger' : '' ?>"><?= $totalPerluPerbaikan ?></div>
                <div class="stat-label">Perlu Perbaikan</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="stat-card stat-card-tab <?= $totalRevisi > 0 ? 'border-danger' : '' ?>" style="--stat-color:#DC3545" data-filter="revisi" tabindex="0" role="button" title="Berkas setelah ditolak dan dikembalikan lagi untuk diproses ulang">
            <div class="stat-icon"><i class="bi bi-arrow-repeat"></i></div>
            <div>
                <div class="stat-value text-danger"><?= $totalRevisi ?></div>
                <div class="stat-label">Berkas Revisi</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="stat-card stat-card-tab <?= $totalWaspada > 0 ? 'border-warning' : '' ?>" style="--stat-color:#D97706" data-filter="waspada" tabindex="0" role="button" title="Berkas dengan sisa waktu pengerjaan kurang dari 24 jam">
            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-value text-warning-emphasis"><?= $totalWaspada ?></div>
                <div class="stat-label">Waspada</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-xl">
        <div class="stat-card stat-card-tab <?= $totalKritis > 0 ? 'border-danger' : '' ?>" style="--stat-color:#DC3545" data-filter="kritis" tabindex="0" role="button" title="Berkas yang telah melewati batas waktu pengerjaan 2 hari">
            <div class="stat-icon"><i class="bi bi-exclamation-octagon"></i></div>
            <div>
                <div class="stat-value text-danger"><?= $totalKritis ?></div>
                <div class="stat-label">Kritis</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">
            <i class="bi bi-inbox me-1 text-primary"></i> Daftar Berkas dalam Antrean
            <span class="badge text-bg-light border text-muted ms-1" id="filterBadge" style="display:none;"></span>
        </span>
        <div class="d-flex flex-wrap gap-1">
            <?php if (!empty($overdueList)): ?>
                <span class="badge text-bg-danger"><i class="bi bi-exclamation-octagon-fill me-1"></i><?= count($overdueList) ?> Kritis</span>
            <?php endif; ?>
            <?php if (!empty($warningList)): ?>
                <span class="badge text-bg-warning text-dark"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= count($warningList) ?> Waspada</span>
            <?php endif; ?>
            <?php if (empty($overdueList) && empty($warningList)): ?>
                <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Semua Tepat Waktu</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-scroll-hint">
        <i class="bi bi-arrows-expand-vertical" style="transform: rotate(90deg);"></i> Geser tabel ke samping untuk melihat kolom selengkapnya
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0" id="tableAntrean">
            <thead>
                <tr>
                    <th>No. Berkas</th>
                    <th>Nama Pemohon</th>
                    <th>Jenis Layanan</th>
                    <th>Status</th>
                    <th>Penerima / Posisi</th>
                    <th>Batas Waktu</th>
                    <th class="text-nowrap">Masuk Sejak</th>
                    <th class="text-end text-nowrap" style="min-width: 175px; width: 175px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($list)): ?>
                    <tr><td colspan="8"><div class="empty-state"><i class="bi bi-check2-circle"></i>Tidak ada berkas dalam antrean. Kerja bagus!</div></td></tr>
                <?php endif; ?>
                <?php foreach ($list as $b): 
                    $sla = hitungSla($b['deadline_at'], $b['updated_at']);
                    $isPerluPerbaikan = !empty($b['is_perlu_perbaikan']);
                    $isRevisi = !empty($b['is_revisi']);
                ?>
                <tr class="berkas-row <?= $sla['is_overdue'] ? 'table-danger' : (!empty($sla['is_warning']) ? 'table-warning' : '') ?>"
                    data-perbaikan="<?= $isPerluPerbaikan ? '1' : '0' ?>"
                    data-revisi="<?= $isRevisi ? '1' : '0' ?>"
                    data-warning="<?= !empty($sla['is_warning']) ? '1' : '0' ?>"
                    data-overdue="<?= !empty($sla['is_overdue']) ? '1' : '0' ?>">
                    <td class="mono fw-semibold text-nowrap">
                        <a href="<?= baseUrl('detail.php?id=' . (int) $b['id']) ?>" class="text-decoration-none">
                            <?= e($b['nomor_pendaftaran']) ?>
                        </a>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= e($b['nama_pemohon']) ?></div>
                        <?php if (!empty($b['sertifikat_desa'])): ?>
                            <div class="text-muted small"><i class="bi bi-award me-1"></i><?= e($b['sertifikat_desa']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= e($b['jenis_layanan']) ?></td>
                    <td class="text-nowrap">
                        <?= statusBadge($b['status_posisi']) ?>
                        <?php if ($isPerluPerbaikan): ?>
                            <span class="badge text-bg-light text-muted border d-block mt-1">
                                <i class="bi bi-arrow-return-left me-1 text-danger"></i>Perlu Perbaikan
                            </span>
                        <?php elseif ($isRevisi): ?>
                            <span class="badge text-bg-light text-muted border d-block mt-1">
                                <i class="bi bi-arrow-repeat me-1 text-danger"></i>Berkas Revisi
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="small fw-semibold text-dark">
                            <i class="bi bi-people me-1 text-primary"></i>
                            <?= e(pemegangBerkasLabel($b['pemegang_nama'], $b['status_posisi'])) ?>
                        </div>
                    </td>
                    <td class="text-nowrap">
                        <?= slaBadge($b['deadline_at'], $b['status_posisi'], false, $b['sisa_sla_detik'] ?? null) ?>
                    </td>
                    <td class="small text-muted text-nowrap"><?= formatTanggal($b['updated_at']) ?></td>
                    <td class="text-end text-nowrap" style="width: 175px;">
                        <div class="action-btns justify-content-end">
                            <a href="<?= baseUrl('detail.php?id=' . (int) $b['id']) ?>" class="btn btn-detail" title="Lihat Detail &amp; Riwayat Berkas">
                                <i class="bi bi-eye"></i>Detail
                            </a>
                            <a href="proses.php?id=<?= (int) $b['id'] ?>" class="btn btn-proses" title="Proses Berkas">
                                <i class="bi bi-arrow-right-circle"></i>Proses
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr id="emptyFilterRow" class="d-none">
                    <td colspan="8">
                        <div class="empty-state py-4">
                            <i class="bi bi-funnel"></i>
                            Tidak ada berkas dalam kategori ini.
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.stat-card-tab');
    const rows = document.querySelectorAll('.berkas-row');
    const emptyRow = document.getElementById('emptyFilterRow');
    const filterBadge = document.getElementById('filterBadge');

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            tabs.forEach(t => {
                t.classList.remove('active-tab', 'active');
                t.setAttribute('aria-pressed', 'false');
            });
            this.classList.add('active-tab', 'active');
            this.setAttribute('aria-pressed', 'true');

            const filter = this.dataset.filter;
            let visibleCount = 0;

            rows.forEach(row => {
                let show = false;
                if (filter === 'semua') {
                    show = true;
                } else if (filter === 'perbaikan' && row.dataset.perbaikan === '1') {
                    show = true;
                } else if (filter === 'revisi' && row.dataset.revisi === '1') {
                    show = true;
                } else if (filter === 'waspada' && row.dataset.warning === '1') {
                    show = true;
                } else if (filter === 'kritis' && row.dataset.overdue === '1') {
                    show = true;
                }

                if (show) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (emptyRow) {
                emptyRow.classList.toggle('d-none', visibleCount > 0);
            }

            if (filterBadge) {
                const labelText = this.querySelector('.stat-label')?.textContent || '';
                if (filter === 'semua') {
                    filterBadge.style.display = 'none';
                } else {
                    filterBadge.textContent = 'Filter: ' + labelText + ' (' + visibleCount + ')';
                    filterBadge.style.display = 'inline-block';
                }
            }
        });

        tab.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
