# AI_CONTEXT.md — Panduan Konteks untuk AI / Developer Lanjutan

> File ini adalah "handoff document" untuk AI atau developer yang melanjutkan proyek **ChickGuard**.
> Baca file ini dulu sebelum mengubah kode. Untuk dokumentasi publik (instalasi, fitur, screenshot), lihat `README.md`.

---

## 1. Ringkasan Proyek

**ChickGuard** adalah aplikasi web PHP native untuk monitoring & otomasi kandang ayam berbasis IoT.

- **Stack**: PHP 7.4/8.x prosedural (TANPA framework, TANPA composer), MySQLi (prepared statements), MySQL/MariaDB, Bootstrap 5.3, Chart.js v4, vanilla JS (Fetch API).
- **Lokasi**: `C:\laragon\www\chickguard` (Laragon, akses via `http://chickguard.test/` atau `http://localhost/chickguard/`).
- **Bahasa kode**: Variabel, fungsi, nama tabel, kolom DB, komentar, dan pesan UI semuanya berbahasa **Indonesia**. Pertahankan konvensi ini.
- **Kredensial dev**: `admin` / `admin123` (tabel `users`). Ada bypass login di `login_auto.php` (khusus lokal!).

---

## 2. Struktur Folder & Peran Tiap Direktori

```
chickguard/
├── assets/
│   ├── css/            # 1 file CSS per modul halaman (dashboard.css, pakan.css, dst.)
│   │                   # + style.css (global), sidebar.css, topbar.css, login.css
│   ├── images/         # backgrounds/, branding/ (logo), docs/ (screenshot README)
│   └── vendor/         # Library LOKAL (jangan ganti ke CDN): bootstrap,
│                       # bootstrap-icons, fontawesome, chartjs, poppins
├── components/         # Partial HTML yang di-include semua halaman:
│   ├── sidebar.php     #   navigasi kiri + modal konfirmasi logout
│   └── topbar.php      #   header atas, notifikasi, profil user
├── config/
│   └── koneksi.php     # $koneksi = mysqli_connect("localhost","root","","chickguard")
├── database/
│   └── chickguard.sql  # Dump skema awal (tapi lihat §6: init_tables.php lebih otoritatif)
├── pages/              # Halaman utama (butuh login), di-serve langsung dari browser
│   ├── dashboard.php       # metrik real-time, grafik 24 jam, polling AJAX 30 detik
│   ├── aktuator.php        # kontrol kipas/heater/lampu/motor_pakan + polling
│   ├── pakan.php           # CRUD jadwal pakan + log pemberian pakan
│   ├── log_harian.php      # riwayat data sensor (search, paginasi, bulk delete)
│   ├── pencahayaan.php     # konfigurasi lampu/pencahayaan
│   └── pengaturan.php      # threshold kandang + profil admin & ganti password
├── proses/             # Endpoint backend (AJAX JSON atau form handler + redirect)
│   ├── helpers.php             # fungsi bersama: waktu_notifikasi(), cg_status_metrik(), cg_status_kandang()
│   ├── init_tables.php         # auto-migration + seeder (dipanggil SETIAP halaman load)
│   ├── ambil_data_dashboard.php # GET JSON: sensor+aktuator+notifikasi+status; ?simulate=1 push data simulasi
│   ├── toggle_aktuator.php     # POST JSON: actuator=kipas|heater|lampu|motor_pakan|all|auto, value=0|1
│   ├── toggle_jadwal.php       # POST JSON: aktif/nonaktif jadwal pakan
│   ├── tambah_jadwal_cepat.php # POST JSON: quick-add jadwal (06:00/12:00/16:00)
│   ├── proses_pakan.php        # form handler CRUD jadwal (?action=delete, delete_selected, update, create)
│   ├── filter_log_harian.php   # di-INCLUDE oleh pages/log_harian.php (bukan endpoint!)
│   ├── proses_log_harian.php   # aksi hapus log sensor
│   ├── simpan_pengaturan.php   # POST JSON: threshold kandang
│   ├── simpan_pengaturan_user.php # POST JSON: profil admin & password
│   ├── notifikasi_tandai.php   # tandai notifikasi dibaca
│   ├── proses_login.php / logout.php / proses_lupa_password.php / proses_reset_password.php
│   └── init & seed lainnya
├── index.php           # Entry point: redirect ke pages/dashboard.php atau login.php
├── login.php           # Form login (+ link lupa password)
├── login_auto.php      # ⚠️ BYPASS LOGIN untuk dev lokal — jangan di-deploy ke produksi
├── reset_password.php  # Form password baru via token (?token=...)
├── tambah_admin.php    # Skrip seeder admin manual
└── sitemap.xml
```

---

## 3. Arsitektur & Pola yang WAJIB Diikuti

### 3.1 Pola Halaman (`pages/*.php`)

Setiap halaman mengikuti template yang sama persis:

```php
<?php
session_start();

if (!isset($_SESSION['login'])) {        // guard autentikasi
    header("Location: ../login.php");
    exit;
}

include '../config/koneksi.php';
require_once '../proses/init_tables.php';
require_once '../proses/helpers.php';
chickguard_init_tables($koneksi);         // self-healing migration tiap load

$activePage = 'dashboard';                // dikontrak oleh components/sidebar.php
$topbarTitle = 'Selamat Datang, ...';     // dikontrak oleh components/topbar.php
$topbarSubtitle = '...';

// ... query data via mysqli, siapkan variabel untuk view ...
?>
<!DOCTYPE html>
<html lang="id">
...
<?php include '../components/sidebar.php'; ?>
<?php include '../components/topbar.php'; ?>
... HTML + <script> vanilla JS di bagian bawah ...
```

**Kontrak komponen** (variabel yang dibaca `components/`):
- `sidebar.php`: `$activePage` (string key: `dashboard|aktuator|pakan|log_harian|pengaturan`), plus `$menuItems` didefinisikan di dalamnya.
- `topbar.php`: `$topbarTitle`, `$topbarSubtitle`, opsional `$notifs`, `$unreadCount` (kalau belum diset, topbar query sendiri dari DB, dengan fallback data dummy). `$username`/`$role` diambil dari `$_SESSION`.

### 3.2 Pola Endpoint AJAX JSON (`proses/*.php` yang dipanggil via fetch)

```php
<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['login'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/koneksi.php';
require_once __DIR__ . '/helpers.php';

// Baca input: JSON body ATAU $_POST (dua-duanya didukung)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

// ... logika dengan prepared statements ...
echo json_encode(['success' => true, ...payload...]);
```

- Response selalu punya key `success` (bool) dan `message` (string, bahasa Indonesia).
- Error: `http_response_code(400/401/500)` + JSON `{success:false, message:...}`.

### 3.3 Pola Form Handler Klasik (PRG pattern)

Contoh: `proses/proses_pakan.php` — cek sesi → proses `$_POST`/`$_GET['action']` → `header("Location: ../pages/pakan.php?status=created|updated|deleted|invalid")` → `exit`. Halaman tujuan membaca `$_GET['status']` untuk menampilkan alert.

### 3.4 Database Access

- **Selalu MySQLi prepared statements** (`mysqli_prepare` + `mysqli_stmt_bind_param` + `mysqli_stmt_execute`) untuk semua query dengan input user. Query statis boleh pakai `mysqli_query` langsung.
- **Fetch**: `mysqli_fetch_assoc()`.
- **Tidak ada ORM / abstraction layer** — query SQL ditulis inline di file endpoint/halaman.
- Escape output HTML dengan `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
- Casting eksplisit: `(int)`, `(float)`, `trim()` untuk input.
- Whitelist untuk kolom dinamis (lihat `toggle_aktuator.php`: `$allowed = ['kipas','heater','lampu','motor_pakan']` sebelum interpolasi ke SQL).

### 3.5 Self-Healing Migration (`proses/init_tables.php`)

- Fungsi utama `chickguard_init_tables($koneksi)` dipanggil **di setiap halaman dan banyak endpoint**.
- Isinya: `CREATE TABLE IF NOT EXISTS` untuk semua tabel, `ensure_*_column()` untuk menambah kolom yang hilang (cek `SHOW COLUMNS`/`information_schema`), dan `seed_*()` untuk mengisi data awal jika tabel kosong.
- Juga ada logika bisnis bersama di sini: `hari_indonesia()`, `jadwal_berlaku_pada_hari()`, `ringkasan_pakan_minum_harian()`, `tambah_log_harian_otomatis()`, `sinkronkan_data_log_harian()`.
- **Penting**: tabel `jadwal_pakan_minum` dan `jadwal_pencahayaan` sengaja di-DROP di init (legacy, sudah tidak dipakai). Jangan hidupkan lagi.
- Kalau menambah tabel/kolom baru: tambahkan ke `init_tables.php` (bukan hanya ke `database/chickguard.sql`), supaya auto-migration tetap konsisten.

### 3.6 Frontend / JavaScript

- **Vanilla JS ES6** inline dalam `<script>` di bawah tiap halaman (tidak ada build step, tidak ada file .js terpisah selain vendor). Pola: IIFE `(() => { ... })()` atau `async function` + `addEventListener`.
- **AJAX**: `fetch("../proses/xxx.php", {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({...})})` lalu cek `res.ok` / `data.success`.
- **Polling**: `setInterval` — dashboard refresh tiap **30 detik** (`ambil_data_dashboard.php?simulate=1`), halaman aktuator juga polling.
- **Grafik**: Chart.js via `assets/vendor/chartjs/chart.umd.min.js`, diinisialisasi inline di dashboard.
- **CSS**: 1 file per modul di `assets/css/`, di-load bersama `style.css` (global) + Bootstrap + vendor icons. Gaya UI: glassmorphism/flat dashboard, font Poppins (lokal).
- **Icons**: campuran Bootstrap Icons (`bi bi-*`) dan Font Awesome (`fa-solid fa-*`) — keduanya dipakai di sidebar; ikuti icon yang sudah ada di `$menuItems` `sidebar.php`.

### 3.7 Simulasi Data IoT

Belum ada perangkat fisik. Dua mekanisme simulasi:
1. `proses/ambil_data_dashboard.php?simulate=1` → `cg_simulasikan_sensor()`: insert baris `data_sensor` baru maksimal tiap **600 detik** (`INTERVAL_SIMULASI`), dengan model lingkungan berbasis jam (suhu puncak ~13:30, cahaya nol saat malam 24:00–05:00, level air refill ±06:00 & 16:00).
2. `cg_model($t)` di `pages/dashboard.php`: model kurva 24 jam untuk mengisi grafik bila data nyata sedikit.

Kalau nanti integrasi ESP32/NodeMCU sungguhan: perangkat POST ke tabel `data_sensor`, dan poll `actuator_status` (baris `id = 1`) via HTTP.

---

## 4. Skema Database (nama DB: `chickguard`)

| Tabel | Catatan penting |
|---|---|
| `users` | id, username, password (hash `password_hash()` BCRYPT / `PASSWORD_DEFAULT`), role, email, nama_lengkap |
| `actuator_status` | **Satu baris saja, `id = 1`**: kipas, heater, lampu, motor_pakan (TINYINT 0/1), updated_at |
| `data_sensor` | suhu, kelembaban, cahaya, level_air, recorded_at |
| `jadwal_pakan` | waktu (TIME), jumlah (DECIMAL kg), hari (string CSV: `"Semua Hari"` atau `"Senin, Rabu"`), catatan, is_active |
| `log_pemberian_pakan` | waktu, metode (`Otomatis`/`Manual`), status (`Berhasil`/...), keterangan |
| `log_harian` | agregasi harian: waktu, suhu, kelembaban, pakan, minum, lampu ENUM('Hidup','Mati') |
| `pengaturan_kandang` | **Satu baris, `id = 1`**: tanggal_masuk_ayam, suhu_min/maks, kelembaban_min/maks, cahaya_min, level_air_min (kolom `nama_kandang` sudah dihapus — ada auto-drop migration di init_tables.php) |
| `notifikasi` | pesan, tipe (`info`/`success`/`warning`), waktu_label (string terformat, BUKAN timestamp), is_read |
| `password_resets` | user_id (FK), token 64 char, expires_at, used_at |

**Konvensi singleton**: `actuator_status` dan `pengaturan_kandang` selalu diakses dengan `WHERE id = 1`. Jangan bikin multi-row tanpa refactor besar.

**Logika domain kunci** (ada di `pages/dashboard.php` & `helpers.php`):
- Umur ayam = selisih hari `today` − `tanggal_masuk_ayam`.
- Fase: **Starter** 0–7 hari, **Grower** 8–21, **Finisher** >21.
- Status metrik: `cg_status_metrik()` → `['label'=>..., 'class'=>'status-normal|status-warning|status-danger']`.
- Kondisi kandang + tips: `cg_status_kandang($sensor, $settings)` → `['kondisi'=>['class'=>'success|warning|danger','text'=>...], 'tips'=>...]`.
- Format waktu notifikasi: `waktu_notifikasi()` → `"14:32 - 10 Okt 2026"` (bulan Indonesia singkat).

---

## 5. Alur Autentikasi

1. `login.php` (form) → POST `proses/proses_login.php` → `password_verify()` → set `$_SESSION['login']=true`, `$_SESSION['username']`, `$_SESSION['role']` → redirect `../index.php`.
2. `index.php` → redirect ke `pages/dashboard.php` (jika login) atau `login.php`.
3. Setiap halaman & endpoint JSON cek `isset($_SESSION['login'])` di baris awal.
4. Lupa password: `proses/proses_lupa_password.php` buat token → `reset_password.php?token=...` → `proses/proses_reset_password.php` verifikasi `expires_at` & `used_at`.
5. Logout: `proses/logout.php` (destroy session), dipicu via modal konfirmasi di `sidebar.php`.
6. **Celah dev yang diketahui**: `login_auto.php` set sesi langsung tanpa kredensial, dan `tambah_admin.php` seeder terbuka. Keduanya HARUS dihapus/diamankan sebelum produksi.

---

## 6. Konvensi Penamaan & Gaya Kode

- **File**: snake_case bahasa Indonesia (`proses_pakan.php`, `log_harian.php`, `toggle_aktuator.php`). Prefix `proses_` = form handler/aksi, `simpan_` = penyimpanan, `toggle_`/`tambah_` = aksi AJAX.
- **Fungsi global**: prefix `cg_` (helpers dashboard) atau `chickguard_`/deskriptif Indonesia (`waktu_notifikasi`, `seed_*`, `ensure_*`).
- **Variabel**: campuran — `$camelCase` untuk hasil query (`$latestSensor`, `$unreadCount`), `$snake_case` untuk data domain (`$umurAyamHari` kadang camel, `$valSuhu`). Umum: camelCase di halaman, snake_case di kolom DB & nama fungsi.
- **Kolom/tabel DB**: snake_case Indonesia (`level_air`, `tanggal_masuk_ayam`, `motor_pakan`).
- **Komentar**: blok `/* --- Judul Bagian --- */` untuk seksi panjang, docblock `/** ... */` berbahasa Indonesia untuk fungsi.
- **Indentasi**: 4 spasi (PHP), 2 spasi (HTML/CSS/JS di beberapa file — ikuti file yang sedang diedit).
- `include` vs `require_once` dipakai tidak konsisten antar halaman (kosmetik); untuk file baru gunakan `require_once`.
- Tidak ada autoloader, namespace, composer, atau framework. **Jangan memperkenalkan dependensi baru** kecuali diminta — proyek ini sengaja PHP native.

---

## 7. Hal yang Perlu Diwaspadai (Gotchas)

1. **`init_tables.php` berjalan di setiap request** — jangan taruh query berat di sana; pola `IF NOT EXISTS` + cek-sebelum-seed sudah benar, pertahankan.
2. **`filter_log_harian.php` bukan endpoint** — dia di-include `pages/log_harian.php` untuk menyiapkan query filter/paginasi. Jangan panggil langsung.
3. **`waktu_label` di notifikasi adalah string terformat**, bukan DATETIME. Pakai `waktu_notifikasi()` saat insert.
4. **Halaman `pencahayaan.php` masih ada** meski tabel `jadwal_pencahayaan` di-drop oleh init — cek dulu sebelum menyentuhnya; data pencahayaan kini bagian dari `actuator_status.lampu`.
5. **`proses_login.php` gagal-login pakai `echo <script>alert(...)</script>`** (pola lama) — beda dengan pola redirect `?status=` di handler lain. Kalau merapikan, seragamkan ke pola PRG.
6. **Interpolasi `$actuator` ke SQL** di `toggle_aktuator.php` aman hanya karena ada whitelist `$allowed` — kalau menambah aktuator baru, tambahkan juga ke whitelist, kolom `actuator_status`, dan map `$actuatorNames`.
7. **Semua path relatif terhadap posisi file** (`../config/koneksi.php` dari `pages/` atau `proses/`). Tidak ada base URL helper.
8. **Tidak ada CSRF protection** saat ini. Endpoint JSON hanya mengandalkan session guard.
9. **Data dummy fallback** ada di banyak tempat (topbar, ambil_data_dashboard, dashboard) — kalau query kosong, kode memakai nilai default hardcoded (mis. suhu 32.4). Ini disengaja agar UI tidak pernah kosong; jangan dihapus tanpa menyediakan fallback lain.
10. **`database/chickguard.sql` bisa tertinggal** dari skema nyata karena `init_tables.php` yang jadi sumber kebenaran runtime.

---

## 8. Resep: Menambah Halaman Baru

1. Buat `pages/nama_halaman.php` — copy struktur §3.1 (session guard, include koneksi/init/helpers, `$activePage`).
2. Buat `assets/css/nama_halaman.css` bila butuh gaya khusus; link di `<head>` bersama style.css, sidebar.css, topbar.css, vendor.
3. Tambahkan entri menu di array `$menuItems` pada `components/sidebar.php` (key = nama file tanpa `.php`).
4. Jika butuh endpoint: buat di `proses/` mengikuti pola §3.2 (JSON) atau §3.3 (PRG).
5. Jika butuh tabel/kolom baru: tambahkan `CREATE TABLE IF NOT EXISTS` / `ensure_*_column()` + `seed_*()` di `proses/init_tables.php`, dan update `database/chickguard.sql`.
6. Update `README.md` (struktur folder + fitur) dan file ini bila arsitektur berubah.

---

## 9. Cara Menjalankan (ringkas)

1. Laragon: **Start All** (Apache + MySQL). Proyek di `C:\laragon\www\chickguard`.
2. Buat DB `chickguard`, import `database/chickguard.sql` (atau biarkan `init_tables.php` membuat semuanya otomatis saat halaman pertama dibuka).
3. Buka `http://chickguard.test/` → login `admin` / `admin123` (atau `login_auto.php` untuk bypass).

---

*Dokumen ini dibuat otomatis dari analisis kode per Nov 2026. Jika kode berubah signifikan, perbarui file ini.*
