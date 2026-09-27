# SITRACK — Sistem Monitoring dan Tracking Berkas Pradaftar (ATR/BPN)

Aplikasi web untuk memantau perjalanan berkas pendaftaran tanah dari Loket → Seksi 1 (Survei & Pemetaan) → Seksi 2 (Penetapan Hak & Pendaftaran), lengkap dengan jejak audit (siapa mengembalikan/meneruskan, kapan, dan catatannya).

**Stack:** PHP Native 8+ (PDO, prepared statements) · MySQL · Bootstrap 5 · Bootstrap Icons · Chart.js

Sudah diuji end-to-end (lint `php -l` pada seluruh file + simulasi alur penuh di server MySQL/PHP sungguhan): login, input berkas, teruskan/kembalikan di Seksi 1 (Survei & Pemetaan) & Seksi 2 (Penetapan Hak & Pendaftaran), konfirmasi penerimaan Loket, riwayat, manajemen user, dan proteksi akses per-role — semua berjalan tanpa error.

---

## 1. Fitur yang Diimplementasikan

### Sesuai spesifikasi
- 4 role dengan sidebar menu dinamis: **Admin, Loket, Seksi 1 (Survei & Pemetaan), Seksi 2 (Penetapan Hak & Pendaftaran)**.
- Alur kerja penuh: input di Loket → periksa Seksi 1 (Survei & Pemetaan) → proses Seksi 2 (Penetapan Hak & Pendaftaran) → kembali ke Loket, dengan opsi "kembalikan" di setiap tahap yang **wajib** disertai catatan.
- Setiap aksi kembalikan/teruskan/selesai dicatat di `log_pergerakan` beserta **identitas user** yang melakukannya — pihak penerima langsung tahu siapa yang mengembalikan dan alasannya.
- Menu **"Riwayat Berkas"** tersedia di semua role, menampilkan berkas berstatus *Selesai* atau *Ditolak/Dikembalikan*, dengan tombol **Detail** menuju halaman riwayat/log pergerakan.
- Query SQL lengkap: `users`, `berkas`, `log_pergerakan` (lihat `database.sql`).
- Struktur folder modular persis seperti diminta (`/config`, `/includes`, `/auth`, `/admin`, `/loket`, `/seksi1`, `/seksi2`).
- Login berbasis session, setiap halaman dilindungi per-role (`requireRole()`).

### Fitur tambahan yang kami rasa penting (agar sistem terasa "production-ready")
1. **Keamanan lebih kuat dari spesifikasi dasar:**
   - Password di-hash dengan `password_hash()` (bcrypt) — bukan plaintext.
   - Semua query pakai PDO **prepared statement** (`PDO::ATTR_EMULATE_PREPARES = false`) → aman dari SQL Injection.
   - **CSRF token** di setiap form POST.
   - Rate-limit sederhana percobaan login, `session_regenerate_id()` setelah login berhasil, cookie session `HttpOnly`.
2. **Konfirmasi penerimaan di Loket** — saat berkas *selesai* atau *ditolak ke Loket*, Loket harus klik "Terima" (bukan otomatis dianggap sampai), sehingga ada jejak kapan berkas benar-benar diserahkan kembali ke pemohon.
3. **Nomor pendaftaran otomatis** dengan format `REG-YYYYMMDD-0001`, digenerate transaksional agar tidak bentrok.
4. **Timeline visual** (`detail.php`) — jejak pergerakan berkas ditampilkan sebagai linimasa, bukan tabel log mentah, sehingga mudah dibaca siapa pun.
5. **Dashboard Admin** dengan kartu statistik + grafik distribusi status (Chart.js) + aktivitas terbaru — bukan sekadar tabel.
6. **Notifikasi badge** di sidebar (jumlah tugas tertunda per role) agar petugas tahu ada pekerjaan menunggu tanpa harus membuka halaman.
7. **Pencarian, filter status, dan pagination** di semua halaman Riwayat (termasuk Admin yang bisa melihat riwayat lintas-unit).
8. **Manajemen User lengkap**: tambah/edit/nonaktifkan/hapus, dengan validasi username unik dan proteksi agar admin tidak bisa menghapus/menonaktifkan akunnya sendiri; penghapusan user yang masih punya riwayat log akan ditolak secara aman (disarankan nonaktifkan saja).
9. **UI modern & responsif** — sidebar collapsible di mobile, dark navy + gold theme (terinspirasi identitas kedinasan ATR/BPN), badge status berwarna, empty-state yang informatif, bukan template Bootstrap generik.
10. **Data contoh (dummy data)** di `database.sql` supaya sistem bisa langsung dicoba di setiap role tanpa input manual dulu.

---

## 2. Struktur Folder

```
/bpn-tracking
  /config/database.php        Koneksi PDO
  /includes/                  functions.php, header.php, sidebar.php, footer.php
  /assets/css/style.css       Tema visual
  /assets/js/script.js        Sidebar toggle, validasi form, dsb.
  /auth/                      login.php, proses_login.php, logout.php
  /admin/                     index.php, users.php, users_proses.php, riwayat.php
  /loket/                     index.php, tambah.php, simpan.php, kirim.php, hapus.php, terima.php, riwayat.php
  /seksi1/                    index.php, proses.php, riwayat.php
  /seksi2/                    index.php, proses.php, riwayat.php
  detail.php                  Halaman detail & timeline (dipakai semua role)
  index.php                   Redirect ke login/dashboard
  database.sql                Skema + data awal
```

## 3. Alur Status Berkas (`status_posisi`)

```
[Loket input] (status: loket, draft belum dikirim)
     │
     ▼ (Loket konfirmasi kirim / batch checklist)
[Seksi 1 (Survei & Pemetaan — Pool Tim)] 
     ├─► (Lengkap) ──► [Seksi 2 (Penetapan Hak & Pendaftaran — Pool Tim)]
     │                        ├─► (Selesai) ──► [Loket Terima & Serahkan ke Pemohon]
     │                        └─► (Perlu Perbaikan) ──► [Seksi 1 (Survei & Pemetaan)]
     └─► (Tidak Lengkap) ──► [Loket Terima & Hubungi Pemohon]
```

> **Catatan Sistem Pool & Sub-Bagian Seksi 2**: Berkas ditujukan ke unit/seksi secara kolektif (bukan user spesifik), sehingga seluruh petugas di seksi tersebut dapat melihat dan memproses berkas yang masuk di antrean mereka. Khusus pada Seksi 2 (Penetapan Hak & Pendaftaran), pengguna dibagi ke dalam 3 sub-bagian (**Pendaftaran**, **Peralihan**, dan **Penetapan**) yang bertujuan untuk melacak secara transparan siapa petugas dan bagian mana yang menolak/mengembalikan berkas.

`selesai` dan `ditolak_ke_loket` adalah status akhir yang muncul di semua halaman **Riwayat Berkas**.

## 4. Instalasi (XAMPP / Laragon)

1. Salin folder `bpn-tracking` ke dalam `htdocs` (XAMPP) atau `www` (Laragon).
2. Buat database dengan mengimpor `database.sql` lewat phpMyAdmin, atau via terminal:
   ```
   mysql -u root -p < database.sql
   ```
3. Sesuaikan kredensial di `config/database.php` jika perlu (`DB_HOST`, `DB_USER`, `DB_PASS`).
4. Jika nama folder Anda **bukan** `bpn-tracking`, ubah baris berikut di `includes/functions.php`:
   ```php
   function baseUrl(string $path = ''): string {
       return '/bpn-tracking/' . ltrim($path, '/'); // <- sesuaikan
   }
   ```
5. Akses `http://localhost/bpn-tracking/` di browser.

## 5. Akun Demo

| Username   | Password   | Role &amp; Bagian                                        |
|------------|------------|----------------------------------------------------------|
| admin      | bpn12345   | Admin (Administrator)                                   |
| loket1     | bpn12345   | Loket (Petugas Loket)                                   |
| loket2     | bpn12345   | Loket (Petugas Loket)                                   |
| seksi1_1   | bpn12345   | Seksi 1 (Survei & Pemetaan)                             |
| seksi2_1   | bpn12345   | Seksi 2 (Penetapan Hak & Pendaftaran &bull; Penetapan)   |
| seksi2_2   | bpn12345   | Seksi 2 (Penetapan Hak & Pendaftaran &bull; Peralihan)   |
| seksi2_3   | bpn12345   | Seksi 2 (Penetapan Hak & Pendaftaran &bull; Pendaftaran) |

> ⚠️ **Ganti seluruh password ini dan hapus data contoh (`TRUNCATE berkas, log_pergerakan;` lalu `ALTER TABLE ... AUTO_INCREMENT = 1;`) sebelum digunakan di lingkungan produksi.**

## 6. Catatan Pengembangan Lanjutan (opsional)

- Tambahkan notifikasi email/WhatsApp saat berkas berpindah status.
- Tambahkan fitur upload lampiran (scan dokumen) per berkas.
- Tambahkan halaman "lacak status" publik untuk pemohon (input nomor pendaftaran, tanpa login).
- Ekspor riwayat ke Excel/PDF untuk laporan bulanan.
