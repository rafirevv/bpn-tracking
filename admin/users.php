<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);

$users = $conn->query("SELECT id, username, nama_lengkap, role, sub_bagian, is_active, created_at FROM users ORDER BY FIELD(role,'admin','loket','seksi_1','seksi_2'), nama_lengkap ASC")->fetchAll();

$pageTitle = 'Manajemen User';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Manajemen User</h1>
        <p>Kelola akun untuk Admin, Loket, Seksi 1 (Survei &amp; Pemetaan), dan Seksi 2 (Penetapan Hak &amp; Pendaftaran).</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUser" onclick="openAddUser()">
        <i class="bi bi-person-plus me-1"></i> Tambah User
    </button>
</div>

<div class="card">
    <div class="table-scroll-hint">
        <i class="bi bi-arrows-expand-vertical" style="transform: rotate(90deg);"></i> Geser tabel ke samping untuk melihat kolom selengkapnya
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead>
                <tr>
                    <th>Nama Lengkap</th>
                    <th>Username</th>
                    <th>Role &amp; Bagian</th>
                    <th>Status</th>
                    <th>Dibuat</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="bi bi-people"></i>Belum ada data user.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="fw-semibold"><?= e($u['nama_lengkap']) ?></td>
                    <td class="mono"><?= e($u['username']) ?></td>
                    <td>
                        <span class="info-chip"><?= e(roleLabel($u['role'])) ?></span>
                        <?php if (!empty($u['sub_bagian'])): ?>
                            <span class="badge text-bg-primary ms-1"><i class="bi bi-diagram-3 me-1"></i>Bagian <?= e($u['sub_bagian']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="badge text-bg-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= formatTanggal($u['created_at']) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary"
                            onclick='openEditUser(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <form action="users_proses.php" method="POST" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Aktifkan/Nonaktifkan">
                                <i class="bi bi-power"></i>
                            </button>
                        </form>
                        <form action="users_proses.php" method="POST" class="d-inline"
                              data-confirm="Hapus user '<?= e($u['nama_lengkap']) ?>'? Tindakan ini permanen.">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                        <?php else: ?>
                            <span class="badge text-bg-light text-muted">Anda</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Edit User -->
<div class="modal fade" id="modalUser" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="users_proses.php" method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formUserId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalUserTitle">Tambah User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" id="formNama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" id="formUsername" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" id="formRole" class="form-select" required>
                            <option value="admin">Administrator</option>
                            <option value="loket">Petugas Loket</option>
                            <option value="seksi_1">Seksi 1 (Survei &amp; Pemetaan)</option>
                            <option value="seksi_2">Seksi 2 (Penetapan Hak &amp; Pendaftaran)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="wrapperSubBagian" style="display:none;">
                        <label class="form-label fw-semibold">Bagian (Khusus Seksi 2) <span class="text-danger">*</span></label>
                        <select name="sub_bagian" id="formSubBagian" class="form-select">
                            <option value="">-- Pilih Bagian --</option>
                            <option value="Pendaftaran">Pendaftaran</option>
                            <option value="Peralihan">Peralihan</option>
                            <option value="Penetapan">Penetapan</option>
                        </select>
                        <div class="form-text">Pilih sub-bagian untuk staf Seksi 2 guna tracking berkas (siapa dan bagian mana yang menolak/memproses).</div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label" id="formPasswordLabel">Kata Sandi</label>
                        <input type="password" name="password" id="formPassword" class="form-control" placeholder="Minimal 6 karakter">
                        <div class="form-text" id="formPasswordHint">Kosongkan jika tidak ingin mengubah kata sandi.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const formRole = document.getElementById('formRole');
const wrapperSubBagian = document.getElementById('wrapperSubBagian');
const formSubBagian = document.getElementById('formSubBagian');

function toggleSubBagian() {
    if (formRole.value === 'seksi_2') {
        wrapperSubBagian.style.display = 'block';
        formSubBagian.required = true;
    } else {
        wrapperSubBagian.style.display = 'none';
        formSubBagian.required = false;
        formSubBagian.value = '';
    }
}

formRole.addEventListener('change', toggleSubBagian);

function openAddUser() {
    document.getElementById('modalUserTitle').textContent = 'Tambah User';
    document.getElementById('formAction').value = 'create';
    document.getElementById('formUserId').value = '';
    document.getElementById('formNama').value = '';
    document.getElementById('formUsername').value = '';
    document.getElementById('formRole').value = 'loket';
    document.getElementById('formSubBagian').value = '';
    toggleSubBagian();
    document.getElementById('formPassword').value = '';
    document.getElementById('formPassword').required = true;
    document.getElementById('formPasswordHint').style.display = 'none';
}
function openEditUser(u) {
    document.getElementById('modalUserTitle').textContent = 'Edit User';
    document.getElementById('formAction').value = 'update';
    document.getElementById('formUserId').value = u.id;
    document.getElementById('formNama').value = u.nama_lengkap;
    document.getElementById('formUsername').value = u.username;
    document.getElementById('formRole').value = u.role;
    document.getElementById('formSubBagian').value = u.sub_bagian || '';
    toggleSubBagian();
    document.getElementById('formPassword').value = '';
    document.getElementById('formPassword').required = false;
    document.getElementById('formPasswordHint').style.display = 'block';
    new bootstrap.Modal(document.getElementById('modalUser')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
