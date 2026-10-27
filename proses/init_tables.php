<?php
function chickguard_init_tables(mysqli $koneksi): void
{
    ensure_users_email_column($koneksi);
    ensure_users_nama_lengkap_column($koneksi);

    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (token),
        CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
    )");

    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS jadwal_pakan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        waktu TIME NOT NULL,
        jumlah DECIMAL(8,2) NOT NULL DEFAULT 2.50,
        hari VARCHAR(120) NOT NULL DEFAULT 'Semua Hari',
        catatan VARCHAR(255) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    mysqli_query($koneksi, "DROP TABLE IF EXISTS jadwal_pakan_minum");
    mysqli_query($koneksi, "DROP TABLE IF EXISTS jadwal_pencahayaan");

    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS log_harian (
        id INT AUTO_INCREMENT PRIMARY KEY,
        waktu TIME NOT NULL,
        suhu DECIMAL(5,2) NOT NULL,
        kelembaban DECIMAL(5,2) NOT NULL,
        pakan DECIMAL(8,2) DEFAULT NULL,
        minum DECIMAL(8,2) DEFAULT NULL,
        lampu ENUM('Hidup','Mati') NOT NULL DEFAULT 'Mati',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS data_sensor (
        id INT AUTO_INCREMENT PRIMARY KEY,
        suhu DECIMAL(5,2) NOT NULL,
        kelembaban DECIMAL(5,2) NOT NULL,
        cahaya DECIMAL(8,2) NOT NULL DEFAULT 1240,
        level_air DECIMAL(5,2) NOT NULL DEFAULT 72,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS actuator_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kipas TINYINT(1) NOT NULL DEFAULT 0,
        heater TINYINT(1) NOT NULL DEFAULT 0,
        lampu TINYINT(1) NOT NULL DEFAULT 0,
        motor_pakan TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS log_pemberian_pakan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        waktu DATETIME NOT NULL,
        metode VARCHAR(50) NOT NULL DEFAULT 'Otomatis',
        status VARCHAR(50) NOT NULL DEFAULT 'Berhasil',
        keterangan VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS notifikasi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pesan VARCHAR(255) NOT NULL,
        tipe VARCHAR(20) NOT NULL DEFAULT 'success',
        waktu_label VARCHAR(50) NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS pengaturan_kandang (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_kandang VARCHAR(100) NOT NULL DEFAULT 'Kandang 1 - Utama',
        suhu_min DECIMAL(5,2) NOT NULL DEFAULT 24.0,
        suhu_maks DECIMAL(5,2) NOT NULL DEFAULT 34.0,
        kelembaban_min DECIMAL(5,2) NOT NULL DEFAULT 55.0,
        kelembaban_maks DECIMAL(5,2) NOT NULL DEFAULT 75.0,
        cahaya_min DECIMAL(8,2) NOT NULL DEFAULT 500,
        level_air_min DECIMAL(5,2) NOT NULL DEFAULT 50.0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    ensure_log_harian_extra_columns($koneksi);
    seed_actuator_status($koneksi);
    seed_data_sensor($koneksi);
    seed_log_pemberian_pakan($koneksi);
    seed_notifikasi($koneksi);
    seed_pengaturan_kandang($koneksi);
    seed_jadwal_pakan($koneksi);
    seed_log_harian($koneksi);
    sinkronkan_data_log_harian($koneksi);
    tambah_log_harian_otomatis($koneksi);
}

function ensure_log_harian_extra_columns(mysqli $koneksi): void
{
    $check = mysqli_query($koneksi, "SHOW COLUMNS FROM log_harian LIKE 'cahaya'");
    if ($check && mysqli_num_rows($check) === 0) {
        mysqli_query($koneksi, "ALTER TABLE log_harian ADD cahaya DECIMAL(8,2) DEFAULT 1240 AFTER kelembaban");
    }

    $check = mysqli_query($koneksi, "SHOW COLUMNS FROM log_harian LIKE 'level_air'");
    if ($check && mysqli_num_rows($check) === 0) {
        mysqli_query($koneksi, "ALTER TABLE log_harian ADD level_air DECIMAL(5,2) DEFAULT 72 AFTER cahaya");
    }



    $checkGarbage = mysqli_query($koneksi, "SHOW COLUMNS FROM log_harian LIKE 'Kolom 11'");
    if ($checkGarbage && mysqli_num_rows($checkGarbage) > 0) {
        mysqli_query($koneksi, "ALTER TABLE log_harian DROP COLUMN `Kolom 11`");
    }
    $checkChecked = mysqli_query($koneksi, "SHOW COLUMNS FROM log_harian LIKE 'is_checked'");
    if ($checkChecked && mysqli_num_rows($checkChecked) > 0) {
        mysqli_query($koneksi, "ALTER TABLE log_harian DROP COLUMN is_checked");
    }
}

function seed_actuator_status(mysqli $koneksi): void
{
    $result = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM actuator_status");
    $row = mysqli_fetch_assoc($result);
    if ((int) ($row['total'] ?? 0) === 0) {
        mysqli_query($koneksi, "INSERT INTO actuator_status (id, kipas, heater, lampu, motor_pakan) VALUES (1, 0, 0, 0, 1)");
    }
}

function seed_data_sensor(mysqli $koneksi): void
{
    $result = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM data_sensor");
    $row = mysqli_fetch_assoc($result);
    if ((int) ($row['total'] ?? 0) === 0) {
        $now = date('Y-m-d');
        $samples = [
            ['15:00:00', 32.6, 68.9, 1260, 73],
            ['15:10:00', 32.3, 67.1, 1210, 72],
            ['15:20:00', 31.9, 66.5, 1120, 71],
            ['15:30:00', 32.1, 67.8, 1180, 72],
            ['15:40:00', 32.4, 68.2, 1240, 72],
        ];
        $stmt = mysqli_prepare($koneksi, "INSERT INTO data_sensor (suhu, kelembaban, cahaya, level_air, recorded_at) VALUES (?, ?, ?, ?, ?)");
        foreach ($samples as $s) {
            $ts = $now . ' ' . $s[0];
            mysqli_stmt_bind_param($stmt, "dddds", $s[1], $s[2], $s[3], $s[4], $ts);
            mysqli_stmt_execute($stmt);
        }
    }
}

function seed_notifikasi(mysqli $koneksi): void
{
    $result = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM notifikasi");
    $row = mysqli_fetch_assoc($result);
    if ((int) ($row['total'] ?? 0) === 0) {
        $notifs = [
            ['Pakan diberikan otomatis', 'success', '16:00 - 10 Okt 2026'],
            ['Level air mulai rendah (72%)', 'warning', '14:32 - 10 Okt 2026'],
            ['Suhu kembali normal', 'success', '12:15 - 10 Okt 2026'],
            ['ESP32 terhubung', 'success', '08:07 - 10 Okt 2026'],
        ];
        $stmt = mysqli_prepare($koneksi, "INSERT INTO notifikasi (pesan, tipe, waktu_label) VALUES (?, ?, ?)");
        foreach ($notifs as $n) {
            mysqli_stmt_bind_param($stmt, "sss", $n[0], $n[1], $n[2]);
            mysqli_stmt_execute($stmt);
        }
    }
}

function seed_pengaturan_kandang(mysqli $koneksi): void
{
    $result = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM pengaturan_kandang");
    $row = mysqli_fetch_assoc($result);
    if ((int) ($row['total'] ?? 0) === 0) {
        mysqli_query($koneksi, "INSERT INTO pengaturan_kandang (id, nama_kandang, suhu_min, suhu_maks, kelembaban_min, kelembaban_maks, cahaya_min, level_air_min) VALUES (1, 'Kandang 1 - Utama', 24.0, 34.0, 55.0, 75.0, 500, 50.0)");
    }
}

function ensure_users_email_column(mysqli $koneksi): void
{
    $result = mysqli_query($koneksi, "SHOW COLUMNS FROM users LIKE 'email'");
    if ($result && mysqli_num_rows($result) === 0) {
        mysqli_query($koneksi, "ALTER TABLE users ADD email VARCHAR(100) NULL UNIQUE AFTER username");
    }

    mysqli_query($koneksi, "UPDATE users SET email='admin@chickguard.local' WHERE username='admin' AND (email IS NULL OR email='')");
}

function ensure_users_nama_lengkap_column(mysqli $koneksi): void
{
    $result = mysqli_query($koneksi, "SHOW COLUMNS FROM users LIKE 'nama_lengkap'");
    if ($result && mysqli_num_rows($result) === 0) {
        mysqli_query($koneksi, "ALTER TABLE users ADD nama_lengkap VARCHAR(100) DEFAULT 'Bima Apriliansyah' AFTER username");
    }
}

function seed_jadwal_pakan(mysqli $koneksi): void
{
    $result = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM jadwal_pakan");
    $row = mysqli_fetch_assoc($result);
    if ((int) ($row['total'] ?? 0) > 0) {
        return;
    }

    $data = [
        ['06:00:00', 3.0, 'Semua Hari', 'Pakan Pagi Rutin'],
        ['12:00:00', 2.0, 'Semua Hari', 'Pakan Siang Tambahan'],
        ['16:00:00', 2.5, 'Semua Hari', 'Pakan Sore Rutin'],
    ];

    $stmt = mysqli_prepare($koneksi, "INSERT INTO jadwal_pakan (waktu, jumlah, hari, catatan, is_active) VALUES (?, ?, ?, ?, 1)");
    foreach ($data as $item) {
        [$waktu, $jumlah, $hari, $catatan] = $item;
        mysqli_stmt_bind_param($stmt, "sdss", $waktu, $jumlah, $hari, $catatan);
        mysqli_stmt_execute($stmt);
    }
}

function seed_log_harian(mysqli $koneksi): void
{
    $result = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM log_harian");
    $row = mysqli_fetch_assoc($result);
    if ((int) ($row['total'] ?? 0) > 0) {
        return;
    }

    $data = [
        ['00:00:00', 26.0, 65.0, 0, 75.0, 'Mati'],
        ['01:00:00', 25.5, 67.0, 0, 75.0, 'Mati'],
        ['02:00:00', 25.0, 68.0, 0, 74.5, 'Mati'],
        ['03:00:00', 24.8, 69.0, 0, 74.0, 'Mati'],
        ['04:00:00', 24.5, 70.0, 0, 74.0, 'Mati'],
        ['05:00:00', 24.8, 71.0, 50, 73.5, 'Mati'],
        ['06:00:00', 25.5, 70.0, 350, 85.0, 'Hidup'],
        ['07:00:00', 26.5, 68.0, 750, 84.0, 'Hidup'],
        ['08:00:00', 27.8, 66.0, 1100, 82.5, 'Hidup'],
        ['09:00:00', 28.5, 64.0, 1350, 80.0, 'Hidup'],
        ['10:00:00', 29.8, 63.0, 1500, 78.5, 'Hidup'],
        ['11:00:00', 30.5, 61.0, 1650, 76.0, 'Hidup'],
        ['12:00:00', 31.2, 60.0, 1750, 74.5, 'Hidup'],
        ['13:00:00', 31.8, 59.0, 1700, 73.0, 'Hidup'],
        ['14:00:00', 31.5, 58.0, 1600, 72.0, 'Hidup'],
        ['15:00:00', 30.8, 60.0, 1400, 71.0, 'Hidup'],
        ['16:00:00', 29.5, 62.0, 1150, 80.0, 'Hidup'],
        ['17:00:00', 28.5, 64.0, 650, 78.5, 'Hidup'],
        ['18:00:00', 27.5, 66.0, 150, 77.0, 'Hidup'],
        ['19:00:00', 27.0, 67.0, 0, 76.0, 'Hidup'],
        ['20:00:00', 26.5, 68.0, 0, 75.5, 'Mati'],
        ['21:00:00', 26.0, 69.0, 0, 75.0, 'Mati'],
        ['22:00:00', 25.5, 70.0, 0, 75.0, 'Mati'],
        ['23:00:00', 25.0, 71.0, 0, 74.5, 'Mati'],
    ];

    $stmt = mysqli_prepare($koneksi, "INSERT INTO log_harian (waktu, suhu, kelembaban, cahaya, level_air, lampu) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($data as $item) {
        [$waktu, $suhu, $kelembaban, $cahaya, $level_air, $lampu] = $item;
        mysqli_stmt_bind_param($stmt, "sdddds", $waktu, $suhu, $kelembaban, $cahaya, $level_air, $lampu);
        mysqli_stmt_execute($stmt);
    }
}

function hari_indonesia(?string $tanggal = null): string
{
    $timestamp = $tanggal ? strtotime($tanggal) : time();
    $hari = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
    ];

    return $hari[date('l', $timestamp)] ?? 'Senin';
}

function jadwal_berlaku_pada_hari(string $jadwalHari, string $hari): bool
{
    if ($jadwalHari === 'Semua Hari') {
        return true;
    }

    $daftarHari = array_map('trim', explode(',', $jadwalHari));
    return in_array($hari, $daftarHari, true);
}

function ringkasan_pakan_minum_harian(mysqli $koneksi, string $hari): array
{
    $result = mysqli_query($koneksi, "SELECT jumlah, hari FROM jadwal_pakan WHERE is_active = 1");
    $ringkasan = ['pakan' => 0.0, 'minum' => 0.0];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if (!jadwal_berlaku_pada_hari((string) $row['hari'], $hari)) {
                continue;
            }
            $ringkasan['pakan'] += (float) $row['jumlah'];
        }
    }

    return $ringkasan;
}

function status_lampu_harian(mysqli $koneksi, string $hari): string
{
    return 'Mati';
}

function sinkronkan_data_log_harian(mysqli $koneksi): void
{
    $result = mysqli_query($koneksi, "SELECT id, created_at FROM log_harian");
    $stmt = mysqli_prepare($koneksi, "UPDATE log_harian SET pakan=?, minum=?, lampu=? WHERE id=?");

    while ($row = mysqli_fetch_assoc($result)) {
        $hari = hari_indonesia($row['created_at'] ?? null);
        $ringkasan = ringkasan_pakan_minum_harian($koneksi, $hari);
        $lampu = status_lampu_harian($koneksi, $hari);
        $id = (int) $row['id'];

        mysqli_stmt_bind_param($stmt, "ddsi", $ringkasan['pakan'], $ringkasan['minum'], $lampu, $id);
        mysqli_stmt_execute($stmt);
    }
}

function tambah_log_harian_otomatis(mysqli $koneksi): void
{
    $result = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM log_harian WHERE DATE(created_at) = CURDATE()");
    $row = mysqli_fetch_assoc($result);
    if ((int) ($row['total'] ?? 0) > 0) {
        return;
    }

    $waktu = date('H:i:s');
    $suhu = mt_rand(250, 320) / 10;
    $kelembaban = mt_rand(580, 750) / 10;
    $hari = hari_indonesia();
    $ringkasan = ringkasan_pakan_minum_harian($koneksi, $hari);
    $pakan = $ringkasan['pakan'];
    $minum = $ringkasan['minum'];
    $lampu = status_lampu_harian($koneksi, $hari);

    $stmt = mysqli_prepare($koneksi, "INSERT INTO log_harian (waktu, suhu, kelembaban, pakan, minum, lampu) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sdddds", $waktu, $suhu, $kelembaban, $pakan, $minum, $lampu);
    mysqli_stmt_execute($stmt);
}

function seed_log_pemberian_pakan(mysqli $koneksi): void
{
    $result = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM log_pemberian_pakan");
    $row = mysqli_fetch_assoc($result);
    if ((int) ($row['total'] ?? 0) > 0) {
        return;
    }

    $now = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $samples = [
        [$now . ' 16:00:00', 'Otomatis', 'Berhasil', 'Pemberian pakan sore sesuai jadwal'],
        [$now . ' 12:00:00', 'Otomatis', 'Berhasil', 'Pemberian pakan siang sesuai jadwal'],
        [$now . ' 08:30:00', 'Manual', 'Berhasil', 'Pemberian pakan manual via dispenser'],
        [$now . ' 06:00:00', 'Otomatis', 'Berhasil', 'Pemberian pakan pagi sesuai jadwal'],
        [$yesterday . ' 16:00:00', 'Otomatis', 'Berhasil', 'Pemberian pakan sore sesuai jadwal'],
        [$yesterday . ' 12:00:00', 'Otomatis', 'Berhasil', 'Pemberian pakan siang sesuai jadwal'],
        [$yesterday . ' 06:00:00', 'Otomatis', 'Berhasil', 'Pemberian pakan pagi sesuai jadwal'],
    ];

    $stmt = mysqli_prepare($koneksi, "INSERT INTO log_pemberian_pakan (waktu, metode, status, keterangan) VALUES (?, ?, ?, ?)");
    foreach ($samples as $s) {
        [$waktu, $metode, $status, $keterangan] = $s;
        mysqli_stmt_bind_param($stmt, "ssss", $waktu, $metode, $status, $keterangan);
        mysqli_stmt_execute($stmt);
    }
}
