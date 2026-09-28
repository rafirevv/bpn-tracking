<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);

$users = $conn->query("SELECT id, username, password_plain, nama_lengkap, role, sub_bagian, is_active, created_at FROM users ORDER BY FIELD(role,'admin','loket','seksi_1','seksi_2'), nama_lengkap ASC")->fetchAll();

$pageTitle = 'Manajemen User';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-head">
    <div>
        <h1>Manajemen User</h1>
        <p>Kelola akun untuk Admin, Loket, Seksi 1 (Survei &amp; Pemetaan), dan Seksi 2 (Penetapan Hak &amp; Pendaftaran).</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" id="btnToggleAllPasswords" onclick="toggleAllPasswords()">
            <i class="bi bi-eye me-1" id="iconToggleAll"></i> <span id="textToggleAll">Lihat Semua Sandi</span>
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUser" onclick="openAddUser()">
            <i class="bi bi-person-plus me-1"></i> Tambah User
        </button>
    </div>
</div>

<div class="card">
    <div class="table-scroll-hint">
        <i class="bi bi-arrows-expand-vertical" style="transform: rotate(90deg);"></i> Geser tabel ke samping untuk melihat kolom selengkapnya
    </div>
    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-nowrap">Nama Lengkap</th>
                    <th class="text-nowrap">Username</th>
                    <th class="text-nowrap">Kata Sandi</th>
                    <th class="text-nowrap">Role &amp; Bagian</th>
                    <th class="text-nowrap">Status</th>
                    <th class="text-nowrap">Dibuat</th>
                    <th class="text-end text-nowrap" style="min-width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i>Belum ada data user.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="fw-semibold text-nowrap"><?= e($u['nama_lengkap']) ?></td>
                    <td class="mono text-nowrap"><?= e($u['username']) ?></td>
                    <td class="text-nowrap">
                        <?php if (!empty($u['password_plain'])): ?>
                            <div class="d-inline-flex align-items-center gap-1 bg-light px-2 py-1 rounded border user-pwd-box" style="font-size: 0.85rem;">
                                <span class="user-pwd-text font-monospace" 
                                      id="pwd-text-<?= $u['id'] ?>" 
                                      data-password="<?= e($u['password_plain']) ?>" 
                                      data-masked="true">••••••••</span>
                                <button type="button" 
                                        class="btn btn-link btn-sm p-0 text-secondary ms-1" 
                                        id="pwd-btn-<?= $u['id'] ?>" 
                                        onclick="toggleUserPassword(<?= $u['id'] ?>)" 
                                        title="Tampilkan / Sembunyikan Kata Sandi">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button type="button" 
                                        class="btn btn-link btn-sm p-0 text-secondary ms-1" 
                                        onclick="copyUserPassword(<?= htmlspecialchars(json_encode($u['password_plain']), ENT_QUOTES, 'UTF-8') ?>, this)" 
                                        title="Salin Kata Sandi">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <span class="badge text-bg-secondary" title="Sandi di-hash lama, belum tersimpan teks asli"><i class="bi bi-lock me-1"></i>Terenkripsi</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <span class="info-chip"><?= e(roleLabel($u['role'])) ?></span>
                        <?php if (!empty($u['sub_bagian'])): ?>
                            <span class="badge text-bg-primary ms-1"><i class="bi bi-diagram-3 me-1"></i>Bagian <?= e($u['sub_bagian']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <?php if ($u['is_active']): ?>
                            <span class="badge text-bg-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted text-nowrap"><?= formatTanggal($u['created_at']) ?></td>
                    <td class="text-end text-nowrap">
                        <div class="d-inline-flex align-items-center justify-content-end gap-1">
                            <button class="btn btn-sm btn-outline-primary"
                                onclick='openEditUser(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                title="Edit User">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form action="users_proses.php" method="POST" class="d-inline-flex m-0 p-0">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                    <i class="bi bi-power"></i>
                                </button>
                            </form>
                            <form action="users_proses.php" method="POST" class="d-inline-flex m-0 p-0"
                                  data-confirm="Hapus user '<?= e($u['nama_lengkap']) ?>'? Tindakan ini permanen.">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus User">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                            <?php else: ?>
                                <span class="badge text-bg-light text-muted border px-2 py-1 ms-1">Anda</span>
                            <?php endif; ?>
                        </div>
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
                            <option value="loket">Loket</option>
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
                        <div class="input-group">
                            <input type="password" name="password" id="formPassword" class="form-control" placeholder="Minimal 6 karakter">
                            <button class="btn btn-outline-secondary" type="button" id="btnToggleModalPassword" onclick="toggleModalPasswordInput()" title="Lihat/Sembunyikan Sandi">
                                <i class="bi bi-eye" id="iconModalPassword"></i>
                            </button>
                        </div>
                        <div class="form-text" id="formPasswordHint">
                            <span>Kosongkan jika tidak ingin mengubah kata sandi.</span>
                            <div id="currentPasswordContainer" class="mt-1 small" style="display:none;">
                                Sandi saat ini: <strong class="font-monospace bg-light px-2 py-0.5 rounded border text-dark" id="currentPasswordDisplay"></strong>
                            </div>
                        </div>
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

function toggleUserPassword(id) {
    const textEl = document.getElementById('pwd-text-' + id);
    const btnEl = document.getElementById('pwd-btn-' + id);
    if (!textEl) return;
    const isMasked = textEl.getAttribute('data-masked') === 'true';
    if (isMasked) {
        textEl.textContent = textEl.getAttribute('data-password');
        textEl.setAttribute('data-masked', 'false');
        if (btnEl) btnEl.innerHTML = '<i class="bi bi-eye-slash text-primary"></i>';
    } else {
        textEl.textContent = '••••••••';
        textEl.setAttribute('data-masked', 'true');
        if (btnEl) btnEl.innerHTML = '<i class="bi bi-eye"></i>';
    }
}

let allPasswordsVisible = false;
function toggleAllPasswords() {
    allPasswordsVisible = !allPasswordsVisible;
    const items = document.querySelectorAll('.user-pwd-text');
    items.forEach(el => {
        const id = el.id.replace('pwd-text-', '');
        const btn = document.getElementById('pwd-btn-' + id);
        if (allPasswordsVisible) {
            el.textContent = el.getAttribute('data-password');
            el.setAttribute('data-masked', 'false');
            if (btn) btn.innerHTML = '<i class="bi bi-eye-slash text-primary"></i>';
        } else {
            el.textContent = '••••••••';
            el.setAttribute('data-masked', 'true');
            if (btn) btn.innerHTML = '<i class="bi bi-eye"></i>';
        }
    });

    const icon = document.getElementById('iconToggleAll');
    const text = document.getElementById('textToggleAll');
    if (icon && text) {
        if (allPasswordsVisible) {
            icon.className = 'bi bi-eye-slash me-1';
            text.textContent = 'Sembunyikan Semua Sandi';
        } else {
            icon.className = 'bi bi-eye me-1';
            text.textContent = 'Lihat Semua Sandi';
        }
    }
}

function copyUserPassword(pwd, btn) {
    if (!pwd) return;
    navigator.clipboard.writeText(pwd).then(() => {
        const origIcon = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
        setTimeout(() => {
            btn.innerHTML = origIcon;
        }, 1500);
    }).catch(err => {
        console.error('Gagal menyalin:', err);
    });
}

function toggleModalPasswordInput() {
    const input = document.getElementById('formPassword');
    const icon = document.getElementById('iconModalPassword');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash text-primary';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function openAddUser() {
    document.getElementById('modalUserTitle').textContent = 'Tambah User';
    document.getElementById('formAction').value = 'create';
    document.getElementById('formUserId').value = '';
    document.getElementById('formNama').value = '';
    document.getElementById('formUsername').value = '';
    document.getElementById('formRole').value = 'loket';
    document.getElementById('formSubBagian').value = '';
    toggleSubBagian();
    const pwdInput = document.getElementById('formPassword');
    pwdInput.value = '';
    pwdInput.type = 'password';
    pwdInput.required = true;
    document.getElementById('iconModalPassword').className = 'bi bi-eye';
    document.getElementById('formPasswordHint').style.display = 'none';
    document.getElementById('currentPasswordContainer').style.display = 'none';
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
    const pwdInput = document.getElementById('formPassword');
    pwdInput.value = '';
    pwdInput.type = 'password';
    pwdInput.required = false;
    document.getElementById('iconModalPassword').className = 'bi bi-eye';
    document.getElementById('formPasswordHint').style.display = 'block';

    const currentPwdDisplay = document.getElementById('currentPasswordDisplay');
    const currentPwdContainer = document.getElementById('currentPasswordContainer');
    if (u.password_plain) {
        currentPwdDisplay.textContent = u.password_plain;
        currentPwdContainer.style.display = 'block';
    } else {
        currentPwdContainer.style.display = 'none';
    }

    new bootstrap.Modal(document.getElementById('modalUser')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
