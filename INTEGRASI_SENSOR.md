# INTEGRASI_SENSOR.md — Rencana Penyambungan Sensor Fisik (IoT)

> Dokumen analisis & rencana kerja untuk menghubungkan sensor/aktuator fisik (ESP32/NodeMCU)
> ke proyek ChickGuard. Baca ini sebelum mulai coding integrasi hardware.
> Untuk konteks arsitektur umum proyek, lihat `AI_CONTEXT.md`.

---

## 1. Kesimpulan Analisis

**Tidak butuh Node-RED — ESP32 bisa langsung konek ke proyek PHP ini.**

Node-RED (+ broker MQTT) baru layak dipakai kalau nanti:
- Protokol bukan HTTP (MQTT, Modbus, LoRa)
- Banyak perangkat / multi-kandang dengan logika antar-perangkat
- Server web tidak satu jaringan dengan alat (butuh broker publik)

Untuk skala 1 kandang dengan ESP32 di WiFi yang sama dengan Laragon → **HTTP langsung ke PHP** paling simpel.

### Perbandingan

| Aspek | Langsung PHP (✅ dipilih) | Node-RED |
|---|---|---|
| Setup | Tambah 2 endpoint | Mosquitto + Node-RED + flow |
| Latensi | Real-time | Real-time (via MQTT) |
| Skalabilitas | 1–beberapa perangkat | Banyak perangkat/protokol |
| Titik rusak | Apache + MySQL saja | + 2 servis ekstra |

---

## 2. Kondisi Proyek Saat Ini (yang relevan untuk integrasi)

- Sensor tersimpan di tabel **`data_sensor`** (`suhu`, `kelembaban`, `cahaya`, `level_air`, `recorded_at`).
- Perintah aktuator dibaca dari tabel **`actuator_status`** — **singleton, selalu `WHERE id = 1`**
  (kolom: `kipas`, `lampu`, `motor_pakan`, semua TINYINT 0/1).
- Dashboard (`pages/dashboard.php`) polling `proses/ambil_data_dashboard.php?simulate=1` tiap **30 detik**
  → data sensor saat ini **hasil simulasi** (`cg_simulasikan_sensor()`, interval minimal 600 detik,
  konstanta `INTERVAL_SIMULASI` di `proses/ambil_data_dashboard.php`).
- ⚠️ **BLOCKER**: semua endpoint di `proses/` diproteksi `$_SESSION['login']` (balas 401 JSON).
  ESP32 tidak bisa login via browser → **harus dibuat endpoint khusus hardware dengan auth API key**,
  bukan session.

---

## 3. Rencana Implementasi (TODO)

### 3.1 Endpoint ingest sensor — `proses/kirim_data_sensor.php` (BARU)

- Metode: `POST` JSON dari ESP32.
- Auth: **API key** (bukan session). Key disimpan di file config terpisah, misal
  `config/api_key.php` → `define('CG_API_KEY', '...');` (jangan hardcode di endpoint,
  dan jangan commit key produksi).
- Validasi:
  - API key cocok (header `X-Api-Key` ATAU field `api_key` di body) → kalau gagal: `401` + `{success:false}`.
  - Whitelist + cast field: `suhu`, `kelembaban`, `cahaya`, `level_air` (float), semua wajib ada / punya default aman.
  - Batas wajar (misal suhu 0–60, kelembaban 0–100) → tolak data ngawur dari sensor rusak.
- Aksi: `INSERT INTO data_sensor (suhu, kelembaban, cahaya, level_air, recorded_at) VALUES (?, ?, ?, ?, NOW())`
  pakai prepared statement (ikuti pola §3.4 di `AI_CONTEXT.md`).
- Response: `{success:true, message:...}` — pola JSON standar proyek (key `success` + `message` bahasa Indonesia).
- Opsional: insert ke `notifikasi` kalau nilai melewati threshold `pengaturan_kandang` (pakai `waktu_notifikasi()`).

### 3.2 Endpoint polling aktuator — `proses/ambil_status_aktuator.php` (BARU)

- Metode: `GET` + API key (query param `?api_key=...` agar gampang dari ESP32, atau header).
- Auth: API key yang sama → gagal: `401`.
- Aksi: `SELECT kipas, lampu, motor_pakan FROM actuator_status WHERE id = 1`.
- Response:
  ```json
  { "success": true, "actuators": { "kipas": 0, "lampu": 1, "motor_pakan": 0 } }
  ```
- ESP32 polling tiap ±5 detik, set pin relay sesuai nilai.
- **Jangan** pakai endpoint `toggle_aktuator.php` dari ESP32 — itu endpoint kontrol dari sisi web (session-based).
  Arah datanya: web menulis `actuator_status`, ESP32 hanya membacanya.

### 3.3 Matikan simulasi setelah sensor terpasang

- `pages/dashboard.php`: ganti pemanggilan `ambil_data_dashboard.php?simulate=1` → tanpa `simulate`
  (sekitar baris `fetch(\`../proses/ambil_data_dashboard.php?simulate=${simulate ? 1 : 0}\`...)`
  dan `setInterval(() => refreshDashboard(true), 30000)` — ubah argumennya jadi `false`).
- Alternatif lebih rapi: buat flag di `config/` (misal `CG_MODE_SIMULASI = true/false`)
  supaya tidak perlu edit halaman bolak-balik saat development ↔ produksi.
- Fungsi `cg_simulasikan_sensor()` dan `cg_model()` **jangan dihapus** — masih berguna untuk
  demo/testing tanpa hardware.

### 3.4 Contoh sketch ESP32 (referensi cepat)

```cpp
// Kiriman data sensor tiap 10 detik + polling aktuator tiap 5 detik
// WiFi.begin("SSID", "password");
//
// POST sensor:
//   http.begin("http://chickguard.test/proses/kirim_data_sensor.php");
//   http.addHeader("Content-Type", "application/json");
//   http.addHeader("X-Api-Key", API_KEY);
//   http.POST("{\"suhu\":30.1,\"kelembaban\":65,\"cahaya\":1200,\"level_air\":80}");
//
// GET aktuator:
//   http.begin("http://chickguard.test/proses/ambil_status_aktuator.php");
//   http.addHeader("X-Api-Key", API_KEY);
//   http.GET();  // parse JSON -> digitalWrite(pin relay)
```

Sensor yang dibutuhkan sesuai kolom DB: DHT22 (suhu+kelembaban), BH1750/LDR (cahaya, lux),
sensor level air (float switch / ultrasonik), relay module (kipas, lampu), servo/motor (dispenser pakan).

Catatan jaringan: `http://chickguard.test` hanya resolve di PC yang menjalankan Laragon.
Dari ESP32 gunakan **IP LAN PC** (misal `http://192.168.x.x/chickguard/proses/...`) — cek dengan `ipconfig`.

### 3.5 Update dokumentasi setelah selesai

- `AI_CONTEXT.md` §7 gotchas: tambah catatan endpoint hardware (API key, bukan session).
- `README.md` bagian "Catatan Pengujian & Endpoint API / AJAX": daftarkan 2 endpoint baru.
- `sitemap.xml` tidak perlu (endpoint bukan halaman).

---

## 4. Checklist Eksekusi

- [ ] Buat `config/api_key.php` (define `CG_API_KEY`) — tambah ke `.gitignore` jika key produksi
- [ ] Buat `proses/kirim_data_sensor.php` (POST, API key, validasi range, prepared statement, JSON response)
- [ ] Buat `proses/ambil_status_aktuator.php` (GET, API key, baca `actuator_status` id=1, JSON response)
- [ ] Tambahkan flag `CG_MODE_SIMULASI` di config; dashboard baca flag itu untuk `?simulate=`
- [ ] Tes pakai curl/Postman (belum perlu hardware):
  - `curl -X POST http://localhost/chickguard/proses/kirim_data_sensor.php -H "Content-Type: application/json" -H "X-Api-Key: <key>" -d '{"suhu":31.2,"kelembaban":66,"cahaya":900,"level_air":78}'`
  - `curl "http://localhost/chickguard/proses/ambil_status_aktuator.php?api_key=<key>"`
  - Cek baris baru muncul di `data_sensor` & dashboard menampilkannya
- [ ] Flash ESP32, pastikan relay mengikuti toggle di halaman Aktuator
- [ ] Sketch ESP32 mengikuti pin mapping §5.1 (GPIO26/27/18/4/34/35/21/22)
- [ ] Rakit daya sesuai topologi 2 adaptor §5.2 (GND menyatu, servo langsung dari step-down, fuse per adaptor)
- [ ] Update README.md + AI_CONTEXT.md

---

## 5. Konfigurasi Hardware (Skematik Revisi, Nov 2026)

Skematik resmi: **"ChickGuard - Skematik (Revisi)"** — lampu 5V (via relay CH2), hanya kipas 12V
(via relay CH1). Komponen: DHT22, LDR + 10k, water sensor, OLED 0.96" (I2C), relay 2 channel,
servo MG996R.

### 5.1 Pin Mapping ESP32 ↔ Proyek

| Field proyek (tabel DB) | Hardware | Pin ESP32 | Catatan |
|---|---|---|---|
| `actuator_status.kipas` | Relay 2ch CH1 (IN1) → Kipas 12V via NO1/COM1 | GPIO26 | Relay umumnya active-LOW → boot HIGH |
| `actuator_status.lampu` | Relay 2ch CH2 (IN2) → Lampu 5V via NO2/COM2 | GPIO27 | Sama seperti kipas |
| `actuator_status.motor_pakan` | Servo MG996R (SIG) | GPIO18 | Nilai 1 = "jalankan 1 siklus dispensing" (timed), bukan on terus |
| `actuator_status.heater` | — | — | **SUDAH DIHAPUS dari proyek (Nov 2026)** — pemanasan kandang cukup dari lampu; jangan buat lagi field/kolom heater |
| `data_sensor.suhu` + `kelembaban` | DHT22 (DATA) | GPIO4 | Modul 3-pin = pull-up onboard; sensor 4-pin polos butuh 4.7–10k eksternal |
| `data_sensor.cahaya` | LDR + divider 10k (OUT) | GPIO34 (ADC1, input-only) | Kalibrasi ADC → lux di sketch; cek orientasi divider |
| `data_sensor.level_air` | Water sensor (S) | GPIO35 (ADC1, input-only) | Kalibrasi ADC → %; cek modul butuh 3V3 atau 5V |
| (display lokal, bukan DB) | OLED 0.96" I2C | SDA GPIO21, SCL GPIO22 | Address default 0x3C |

GPIO34/35 sudah benar: keduanya ADC1 (ADC2 bentrok dengan WiFi di ESP32).

### 5.2 Topologi Daya (rencana 2 adaptor 12V)

- **Adaptor A (12V)** → fuse 3A → relay COM1 → Kipas 12V (NO1); negatif kipas → GND bersama.
- **Adaptor B (12V)** → fuse → step-down 12V→5V (min 5A) → jalur 5V:
  relay VCC, relay COM2 (lampu 5V via NO2), ESP32 VIN, relay/sensor logic.
- **Servo MG996R V+**: tarik LANGSUNG dari terminal output step-down (star wiring),
  JANGAN daisy-chain lewat pin VIN ESP32 (stall ~2.5A bisa bikin brownout).
- **GND WAJIB menyatu**: negatif adaptor A, negatif adaptor B, GND step-down, GND ESP32,
  GND relay, GND semua sensor, GND servo → satu titik ground bersama.
- Budget arus worst case jalur 5V ≈ 4–4.5A (servo stall 2.5A + lampu ~1A + ESP32 ~0.5A +
  coil relay ~0.15A) → step-down 5A mepet; pakai 6–8A jika lampu besar.

### 5.3 Catatan Teknis & Keamanan Hardware

1. Dua adaptor terpisah = bagus (surge/startup kipas tidak mengganggu logic), asal GND menyatu.
2. Fuse terpisah di tiap jalur adaptor (skematik lama fuse-nya hanya satu sebelum percabangan).
3. Relay active-LOW: inisialisasi GPIO26/27 = HIGH di `setup()` agar aktuator tidak nyala saat boot.
4. Dioda flyback 1N4007 paralel terminal kipas (beban induktif) untuk melindungi kontak relay.
5. Konvensi `motor_pakan`: web menulis 1 → sketch jalankan siklus servo → web/auto-mode reset ke 0.
6. `heater` sudah dihapus total dari proyek (kolom DB, endpoint, UI, CSS) — pemanasan kandang
   diambil alih lampu; mode auto di `toggle_aktuator.php` hanya menghitung kipas & lampu.
7. Kalibrasi sensor analog (LDR, water sensor) dilakukan di sketch sebelum POST ke endpoint ingest.

---

*Dibuat Nov 2026 dari hasil analisis arsitektur ChickGuard. Status: RENCANA — endpoint belum
diimplementasikan; skematik & pin mapping (§5) sudah final dari pengguna.*
