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
        INDEX (token)
    )");

    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS jadwal_pakan_minum (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jenis ENUM('Pakan','Minum') NOT NULL,
        waktu TIME NOT NULL,
        jumlah DECIMAL(8,2) NOT NULL,
        hari VARCHAR(120) NOT NULL,
        catatan VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS jadwal_pencahayaan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jenis VARCHAR(50) NOT NULL,
        waktu TIME NOT NULL,
        durasi DECIMAL(8,2) NOT NULL,
        hari VARCHAR(120) NOT NULL,
        catatan VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

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
    seed_notifikasi($koneksi);
    seed_pengaturan_kandang($koneksi);
    seed_jadwal_pakan_minum($koneksi);
    seed_jadwal_pencahayaan($koneksi);
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

    $check = mysqli_query($koneksi, "SHOW COLUMNS FROM jadwal_pakan_minum LIKE 'is_active'");
    if ($check && mysqli_num_rows($check) === 0) {
        mysqli_query($koneksi, "ALTER TABLE jadwal_pakan_minum ADD is_active TINYINT(1) DEFAULT 1 AFTER catatan");
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

function seed_jadwal_pakan_minum(mysqli $koneksi): void
{
    $result = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM jadwal_pakan_minum");
    $row = mysqli_fetch_assoc($result);
    if ((int) ($row['total'] ?? 0) > 0) {
        return;
    }

    $data = [
        ['Pakan', '06:00:00', 3.0, 'Senin, Rabu, Jumat', 'Pakan pagi untuk awal aktivitas'],
        ['Minum', '07:00:00', 2.0, 'Semua Hari', 'Isi air minum setelah pakan pagi'],
        ['Pakan', '12:00:00', 2.0, 'Semua Hari', 'Tambahan pakan siang secukupnya'],
        ['Minum', '13:00:00', 1.5, 'Selasa, Kamis', 'Pengecekan dan isi ulang wadah air'],
        ['Pakan', '19:00:00', 2.8, 'Sabtu, Minggu', 'Pakan malam untuk akhir pekan'],
    ];

    $stmt = mysqli_prepare($koneksi, "INSERT INTO jadwal_pakan_minum (jenis, waktu, jumlah, hari, catatan) VALUES (?, ?, ?, ?, ?)");
    foreach ($data as $item) {
        [$jenis, $waktu, $jumlah, $hari, $catatan] = $item;
        mysqli_stmt_bind_param($stmt, "ssdss", $jenis, $waktu, $jumlah, $hari, $catatan);
        mysqli_stmt_execute($stmt);
    }
}

function seed_jadwal_pencahayaan(mysqli $koneksi): void
{
    $result = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM jadwal_pencahayaan");
    $row = mysqli_fetch_assoc($result);
    if ((int) ($row['total'] ?? 0) > 0) {
        return;
    }

    $data = [
        ['Lampu Utama', '06:00:00', 3.0, 'Senin, Rabu, Jumat', 'Pencahayaan pagi untuk awal aktivitas kandang'],
        ['Lampu Pemanas', '07:00:00', 2.0, 'Semua Hari', 'Menjaga suhu kandang tetap hangat di pagi hari'],
        ['Lampu Utama', '12:00:00', 2.0, 'Semua Hari', 'Pencahayaan siang untuk menjaga visibilitas area'],
        ['Lampu Cadangan', '13:00:00', 1.5, 'Selasa, Kamis', 'Lampu tambahan saat cuaca redup atau mendung'],
        ['Lampu Utama', '19:00:00', 2.8, 'Sabtu, Minggu', 'Pencahayaan malam untuk akhir pekan'],
    ];

    $stmt = mysqli_prepare($koneksi, "INSERT INTO jadwal_pencahayaan (jenis, waktu, durasi, hari, catatan) VALUES (?, ?, ?, ?, ?)");
    foreach ($data as $item) {
        [$jenis, $waktu, $durasi, $hari, $catatan] = $item;
        mysqli_stmt_bind_param($stmt, "ssdss", $jenis, $waktu, $durasi, $hari, $catatan);
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
        ['00:00:00', 26, 65, null, null, 'Mati'],
        ['01:00:00', 25, 67, null, null, 'Mati'],
        ['02:00:00', 25, 68, null, null, 'Mati'],
        ['03:00:00', 24, 69, null, null, 'Mati'],
        ['04:00:00', 24, 70, null, null, 'Mati'],
        ['05:00:00', 24, 71, null, null, 'Mati'],
        ['06:00:00', 25, 70, 2.5, 5.2, 'Hidup'],
        ['07:00:00', 26, 68, null, 3.1, 'Hidup'],
        ['08:00:00', 27, 66, null, 2.8, 'Hidup'],
        ['09:00:00', 28, 64, null, 3.5, 'Hidup'],
        ['10:00:00', 29, 63, null, null, 'Hidup'],
        ['11:00:00', 30, 61, null, null, 'Hidup'],
        ['12:00:00', 30, 60, 2.0, null, 'Hidup'],
        ['13:00:00', 31, 59, null, 1.5, 'Hidup'],
        ['14:00:00', 31, 58, null, null, 'Hidup'],
        ['15:00:00', 30, 60, null, null, 'Hidup'],
        ['16:00:00', 29, 62, null, null, 'Hidup'],
        ['17:00:00', 28, 64, null, null, 'Hidup'],
        ['18:00:00', 27, 66, null, null, 'Hidup'],
        ['19:00:00', 27, 67, 2.8, null, 'Hidup'],
        ['20:00:00', 26, 68, null, null, 'Mati'],
        ['21:00:00', 26, 69, null, null, 'Mati'],
        ['22:00:00', 25, 70, null, null, 'Mati'],
        ['23:00:00', 25, 71, null, null, 'Mati'],
    ];

    $stmt = mysqli_prepare($koneksi, "INSERT INTO log_harian (waktu, suhu, kelembaban, pakan, minum, lampu) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($data as $item) {
        [$waktu, $suhu, $kelembaban, $pakan, $minum, $lampu] = $item;
        mysqli_stmt_bind_param($stmt, "sdddds", $waktu, $suhu, $kelembaban, $pakan, $minum, $lampu);
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
    $result = mysqli_query($koneksi, "SELECT jenis, jumlah, hari FROM jadwal_pakan_minum");
    $ringkasan = ['pakan' => 0.0, 'minum' => 0.0];

    while ($row = mysqli_fetch_assoc($result)) {
        if (!jadwal_berlaku_pada_hari((string) $row['hari'], $hari)) {
            continue;
        }

        if ($row['jenis'] === 'Pakan') {
            $ringkasan['pakan'] += (float) $row['jumlah'];
        } elseif ($row['jenis'] === 'Minum') {
            $ringkasan['minum'] += (float) $row['jumlah'];
        }
    }

    return $ringkasan;
}

function status_lampu_harian(mysqli $koneksi, string $hari): string
{
    $result = mysqli_query($koneksi, "SELECT hari FROM jadwal_pencahayaan");
    while ($row = mysqli_fetch_assoc($result)) {
        if (jadwal_berlaku_pada_hari((string) $row['hari'], $hari)) {
            return 'Hidup';
        }
    }

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
?>
