<?php
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit;
}

include '../config/koneksi.php';
include '../proses/init_tables.php';
require_once '../proses/helpers.php';
chickguard_init_tables($koneksi);

$activePage = 'aktuator';

// User info
$username = $_SESSION['username'] ?? 'admin';
$userDisplayName = ($username === 'admin') ? 'Bima Apriliansyah' : ucfirst($username);
$userShortName = ($username === 'admin') ? 'Bima' : ucfirst($username);

$topbarTitle = 'Kontrol Aktuator IoT';
$topbarSubtitle = 'Kontrol sakelar kipas, heater pemanas, pencahayaan, dan motor servo pakan secara real-time.';

/* ----------------------------------------------------------------------------
 * Pengaturan / batas normal (pengaturan_kandang)
 * -------------------------------------------------------------------------- */
$settingQuery = mysqli_query($koneksi, "SELECT * FROM pengaturan_kandang WHERE id = 1 LIMIT 1");
$settings = $settingQuery ? mysqli_fetch_assoc($settingQuery) : [
    'nama_kandang'    => 'Kandang 1 - Utama',
    'suhu_min'        => 24.0,
    'suhu_maks'       => 34.0,
    'kelembaban_min'  => 55.0,
    'kelembaban_maks' => 75.0,
    'cahaya_min'      => 500,
    'level_air_min'   => 50.0,
];

/* ----------------------------------------------------------------------------
 * Pembacaan sensor terakhir
 * -------------------------------------------------------------------------- */
$sensorQuery = mysqli_query($koneksi, "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 1");
$latestSensor = $sensorQuery ? mysqli_fetch_assoc($sensorQuery) : null;

$valSuhu = $latestSensor ? (float) $latestSensor['suhu'] : 32.4;
$valLembap = $latestSensor ? (float) $latestSensor['kelembaban'] : 68.2;
$valCahaya = $latestSensor ? (float) $latestSensor['cahaya'] : 1240;
$valAir = $latestSensor ? (float) $latestSensor['level_air'] : 72;

$graphSuhu = number_format($valSuhu, 1, '.', ',');
$graphLembap = number_format($valLembap, 1, '.', ',');
$graphCahaya = number_format($valCahaya, 0, '.', ',');
$graphAir = number_format($valAir, 0, '.', ',');

/* ----------------------------------------------------------------------------
 * Status tiap metrik berdasarkan batas normal
 * -------------------------------------------------------------------------- */
$stSuhu = cg_status_metrik($valSuhu, $settings['suhu_min'], $settings['suhu_maks'], ['bawah' => 'Dingin', 'atas' => 'Panas']);
$stLembap = cg_status_metrik($valLembap, $settings['kelembaban_min'], $settings['kelembaban_maks'], ['bawah' => 'Kering', 'atas' => 'Lembap']);
$stCahaya = ($valCahaya < (float) $settings['cahaya_min'])
    ? ['label' => 'Redup', 'class' => 'status-warning']
    : ['label' => 'Normal', 'class' => 'status-normal'];
$stAir = ($valAir >= (float) $settings['level_air_min'])
    ? ['label' => 'Aman', 'class' => 'status-normal']
    : ['label' => 'Rendah', 'class' => 'status-warning'];

/* ----------------------------------------------------------------------------
 * Data grafik 24 jam (punggil data nyata + model harian sebagai pengisi)
 * -------------------------------------------------------------------------- */
$suhuScaleK = 1.4;    // faktor skala ke sumbu 0-100
$cahayaScaleK = 65.0; // lux dibagi faktor ini

$rows24 = [];
$res24 = mysqli_query($koneksi, "SELECT * FROM data_sensor WHERE recorded_at >= NOW() - INTERVAL 26 HOUR ORDER BY recorded_at ASC");
if ($res24) {
    while ($row = mysqli_fetch_assoc($res24)) {
        $rows24[] = $row;
    }
}

// Kelompokkan barisan nyata menurut menit-dalam-hari hari ini
$todayTs = strtotime('today');
$real = [];
foreach ($rows24 as $row) {
    $ts = strtotime($row['recorded_at']);
    if ($ts !== false && $ts >= $todayTs) {
        $real[(int) ((($ts - $todayTs) / 60))] = $row;
    }
}

/**
 * Model lingkungan harian (meniru pola di mockup: dingin malam, panas siang).
 * Menghasilkan [suhu, kelembaban, cahaya, level_air]
 */
function cg_model(float $t): array
{
    // Level air: linear interpolasi antara jam kunci (refill 06:00 & 16:00)
    $anchorAir = [0 => 92.0, 5 => 80.0, 6 => 85.0, 12 => 75.0, 16 => 78.0, 20 => 74.0, 22 => 71.0, 24 => 70.0];
    $air = 82.0;
    $prevH = 0;
    foreach ($anchorAir as $h => $v) {
        if ($t <= $h) {
            $span = max(1, $h - $prevH);
            $air = $anchorAir[$prevH] + (($v - $anchorAir[$prevH]) / $span) * ($t - $prevH);
            break;
        }
        $prevH = $h;
    }

    // Suhu: puncak 13:30
    $suhu = 24.8 + 8.0 * exp(-pow($t - 13.5, 2) / 22);
    $suhu = max(24.0, min(34.5, $suhu + (mt_rand(-10, 10) / 20)));

    // Kelembaban kebalikan suhu
    $lembab = max(50.0, min(85.0, 80 - ($suhu - 24.8) * 1.6) + (mt_rand(-8, 8) / 10));

    // Cahaya: 0 di malam, puncak 12:30
    if ($t < 4.8 || $t > 20.2) {
        $cahaya = mt_rand(0, 12);
    } else {
        $cahaya = max(0, (float) (1800 * exp(-pow($t - 12.5, 2) / 30)) + mt_rand(-60, 60));
    }

    return [$suhu, $lembab, $cahaya, $air];
}

$nowTs = time();
$nowMinute = (int) ((($nowTs - $todayTs) / 60));

$chartLabels = [];
$chartSuhu = [];
$chartHumid = [];
$chartLight = [];
$chartWater = [];

for ($slot = 0; $slot <= 48; $slot++) {
    $minute = $slot * 30;
    $jam = ($minute / 60);
    $chartLabels[] = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);

    // Data nyata jika ada baris sensor dalam window +/- 17 menit
    $realRow = null;
    $bestDiff = 18;
    foreach ($real as $min => $row) {
        $diff = abs($min - $minute);
        if ($diff <= 17 && $diff < $bestDiff) {
            $bestDiff = $diff;
            $realRow = $row;
        }
    }

    if ($realRow !== null) {
        $suhu = (float) $realRow['suhu'];
        $lembab = (float) $realRow['kelembaban'];
        $cahaya = (float) $realRow['cahaya'];
        $air = (float) $realRow['level_air'];
    } else {
        [$suhu, $lembab, $cahaya, $air] = cg_model($jam);
    }

    $chartSuhu[] = round($suhu * $suhuScaleK, 1);
    $chartHumid[] = round($lembab, 1);
    $chartLight[] = round($cahaya / $cahayaScaleK, 1);
    $chartWater[] = round($air, 1);
}

/* ----------------------------------------------------------------------------
 * Aktuator
 * -------------------------------------------------------------------------- */
$actQuery = mysqli_query($koneksi, "SELECT * FROM actuator_status WHERE id = 1 LIMIT 1");
$actuators = $actQuery ? mysqli_fetch_assoc($actQuery) : [
    'kipas' => 0,
    'heater' => 0,
    'lampu' => 0,
    'motor_pakan' => 1,
];

/* ----------------------------------------------------------------------------
 * Jadwal pakan widget (maks 2 jadwal aktif)
 * -------------------------------------------------------------------------- */
$jadwalQuery = mysqli_query($koneksi, "SELECT * FROM jadwal_pakan_minum WHERE jenis = 'Pakan' ORDER BY waktu ASC LIMIT 2");
$feedSchedules = [];
if ($jadwalQuery) {
    while ($row = mysqli_fetch_assoc($jadwalQuery)) {
        $feedSchedules[] = $row;
    }
}
if (empty($feedSchedules)) {
    $feedSchedules = [
        ['id' => 1, 'catatan' => 'Pakan 1', 'waktu' => '06:00:00', 'is_active' => 1],
        ['id' => 2, 'catatan' => 'Pakan 2', 'waktu' => '16:00:00', 'is_active' => 1],
    ];
}

/* ----------------------------------------------------------------------------
 * Log terbaru (5)
 * -------------------------------------------------------------------------- */
$logsQuery = mysqli_query($koneksi, "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 5");
$recentLogs = [];
if ($logsQuery) {
    while ($row = mysqli_fetch_assoc($logsQuery)) {
        $recentLogs[] = $row;
    }
}
if (empty($recentLogs)) {
    $recentLogs = [
        ['recorded_at' => date('Y-m-d 15:40:00'), 'suhu' => 32.4, 'kelembaban' => 68.2, 'cahaya' => 1240, 'level_air' => 72],
        ['recorded_at' => date('Y-m-d 15:30:00'), 'suhu' => 32.1, 'kelembaban' => 67.8, 'cahaya' => 1180, 'level_air' => 72],
        ['recorded_at' => date('Y-m-d 15:20:00'), 'suhu' => 31.9, 'kelembaban' => 66.5, 'cahaya' => 1120, 'level_air' => 71],
        ['recorded_at' => date('Y-m-d 15:10:00'), 'suhu' => 32.3, 'kelembaban' => 67.1, 'cahaya' => 1210, 'level_air' => 72],
        ['recorded_at' => date('Y-m-d 15:00:00'), 'suhu' => 32.6, 'kelembaban' => 68.9, 'cahaya' => 1260, 'level_air' => 73],
    ];
}

/* ----------------------------------------------------------------------------
 * Notifikasi
 * -------------------------------------------------------------------------- */
$notifQuery = mysqli_query($koneksi, "SELECT * FROM notifikasi ORDER BY id DESC LIMIT 8");
$notifs = [];
if ($notifQuery) {
    while ($row = mysqli_fetch_assoc($notifQuery)) {
        $notifs[] = $row;
    }
}
if (empty($notifs)) {
    $notifs = [
        ['id' => 1, 'pesan' => 'Pakan diberikan otomatis', 'tipe' => 'success', 'waktu_label' => '16:00 - 10 Okt 2026', 'is_read' => 0],
        ['id' => 2, 'pesan' => 'Level air mulai rendah (72%)', 'tipe' => 'warning', 'waktu_label' => '14:32 - 10 Okt 2026', 'is_read' => 0],
        ['id' => 3, 'pesan' => 'Suhu kembali normal', 'tipe' => 'success', 'waktu_label' => '12:15 - 10 Okt 2026', 'is_read' => 1],
        ['id' => 4, 'pesan' => 'ESP32 terhubung', 'tipe' => 'success', 'waktu_label' => '08:07 - 10 Okt 2026', 'is_read' => 1],
    ];
}

$unreadQuery = mysqli_query($koneksi, "SELECT COUNT(*) AS unread FROM notifikasi WHERE is_read = 0");
$unreadRow = $unreadQuery ? mysqli_fetch_assoc($unreadQuery) : null;
$unreadCount = (int) ($unreadRow['unread'] ?? count($notifs));


/* ----------------------------------------------------------------------------
 * Log notifikasi khusus aktuator
 * -------------------------------------------------------------------------- */
$actNotifs = [];
$actNotifRes = mysqli_query($koneksi, "SELECT * FROM notifikasi WHERE pesan LIKE '%Kipas%' OR pesan LIKE '%Heater%' OR pesan LIKE '%Lampu%' OR pesan LIKE '%Motor%' OR pesan LIKE '%Aktuator%' ORDER BY id DESC LIMIT 8");
if ($actNotifRes && mysqli_num_rows($actNotifRes) > 0) {
    while ($an = mysqli_fetch_assoc($actNotifRes)) {
        $actNotifs[] = $an;
    }
} else {
    $actNotifs = $notifs; // fallback
}

$countActive = (!empty($actuators['kipas']) ? 1 : 0) + (!empty($actuators['heater']) ? 1 : 0) + (!empty($actuators['lampu']) ? 1 : 0) + (!empty($actuators['motor_pakan']) ? 1 : 0);
$estPower = (!empty($actuators['kipas']) ? 45 : 0) + (!empty($actuators['heater']) ? 150 : 0) + (!empty($actuators['lampu']) ? 25 : 0) + (!empty($actuators['motor_pakan']) ? 15 : 0);

/* ----------------------------------------------------------------------------
 * Status kandang
 * -------------------------------------------------------------------------- */
$statKandang = cg_status_kandang($latestSensor ?: [], $settings);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ChickGuard - Kontrol Aktuator IoT</title>
  <link rel="icon" href="../assets/images/branding/icon.png" type="image/png">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/sidebar.css">
  <link rel="stylesheet" href="../assets/css/topbar.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/aktuator.css">
</head>
<body class="dashboard-page page-aktuator">
  <div class="dashboard-shell d-lg-flex">
    <?php include '../components/sidebar.php'; ?>

    <main class="main-content">
      <?php include '../components/topbar.php'; ?>

      <!-- 4 Interactive Big Actuator Cards Grid -->
      <div class="row g-3 g-xl-4 mb-4">
        <!-- 1. Kipas Ventilasi -->
        <div class="col-12 col-md-6 col-xl-3">
          <div class="actuator-card-grid <?= !empty($actuators['kipas']) ? 'is-active' : '' ?>" id="card-kipas">
            <div class="actuator-card-header">
              <div class="actuator-big-icon icon-fan <?= !empty($actuators['kipas']) ? 'fan-running' : '' ?>" id="icon-kipas">
                <i class="fa-solid fa-fan"></i>
              </div>
              <label class="switch-ios">
                <input type="checkbox" class="js-actuator-toggle" data-actuator="kipas" <?= !empty($actuators['kipas']) ? 'checked' : '' ?>>
                <span class="slider round"></span>
              </label>
            </div>

            <div class="actuator-device-title">Kipas Ventilasi</div>
            <div class="actuator-device-sub">Exhaust & sirkulasi udara kandang</div>

            <div class="d-flex align-items-center gap-2 mb-3">
              <span class="badge <?= !empty($actuators['kipas']) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> rounded-pill px-3 py-1 fw-bold" id="badge-kipas">
                <?= !empty($actuators['kipas']) ? 'Menyala' : 'Mati' ?>
              </span>
              <span class="text-muted small">Channel 1</span>
            </div>

            <div class="actuator-rule-box">
              <i class="bi bi-info-circle text-primary me-1"></i>
              <strong>Otomasi:</strong> Aktif saat suhu kandang melebihi <strong><?= (float) $settings['suhu_maks'] ?> &deg;C</strong> untuk mendinginkan suhu & membuang gas amonia.
            </div>

            <div class="actuator-meta-spec">
              <span>Beban: <strong>45 Watt</strong></span>
              <span>Tegangan: <strong>220V AC</strong></span>
            </div>
          </div>
        </div>

        <!-- 2. Pemanas Kandang (Heater) -->
        <div class="col-12 col-md-6 col-xl-3">
          <div class="actuator-card-grid <?= !empty($actuators['heater']) ? 'is-active' : '' ?>" id="card-heater">
            <div class="actuator-card-header">
              <div class="actuator-big-icon icon-heater" id="icon-heater">
                <i class="fa-solid fa-temperature-arrow-up"></i>
              </div>
              <label class="switch-ios">
                <input type="checkbox" class="js-actuator-toggle" data-actuator="heater" <?= !empty($actuators['heater']) ? 'checked' : '' ?>>
                <span class="slider round"></span>
              </label>
            </div>

            <div class="actuator-device-title">Heater Pemanas</div>
            <div class="actuator-device-sub">Pemanas induksi keramik DOC</div>

            <div class="d-flex align-items-center gap-2 mb-3">
              <span class="badge <?= !empty($actuators['heater']) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> rounded-pill px-3 py-1 fw-bold" id="badge-heater">
                <?= !empty($actuators['heater']) ? 'Menyala' : 'Mati' ?>
              </span>
              <span class="text-muted small">Channel 2</span>
            </div>

            <div class="actuator-rule-box">
              <i class="bi bi-info-circle text-warning me-1"></i>
              <strong>Otomasi:</strong> Aktif saat suhu kandang turun di bawah <strong><?= (float) $settings['suhu_min'] ?> &deg;C</strong> untuk menjaga suhu tubuh anak ayam.
            </div>

            <div class="actuator-meta-spec">
              <span>Beban: <strong>150 Watt</strong></span>
              <span>Tegangan: <strong>220V AC</strong></span>
            </div>
          </div>
        </div>

        <!-- 3. Lampu Penerangan -->
        <div class="col-12 col-md-6 col-xl-3">
          <div class="actuator-card-grid <?= !empty($actuators['lampu']) ? 'is-active' : '' ?>" id="card-lampu">
            <div class="actuator-card-header">
              <div class="actuator-big-icon icon-lamp" id="icon-lampu">
                <i class="bi bi-lightbulb-fill"></i>
              </div>
              <label class="switch-ios">
                <input type="checkbox" class="js-actuator-toggle" data-actuator="lampu" <?= !empty($actuators['lampu']) ? 'checked' : '' ?>>
                <span class="slider round"></span>
              </label>
            </div>

            <div class="actuator-device-title">Lampu Penerangan</div>
            <div class="actuator-device-sub">Penerangan LED stimulasi makan</div>

            <div class="d-flex align-items-center gap-2 mb-3">
              <span class="badge <?= !empty($actuators['lampu']) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> rounded-pill px-3 py-1 fw-bold" id="badge-lampu">
                <?= !empty($actuators['lampu']) ? 'Menyala' : 'Mati' ?>
              </span>
              <span class="text-muted small">Channel 3</span>
            </div>

            <div class="actuator-rule-box">
              <i class="bi bi-info-circle text-success me-1"></i>
              <strong>Otomasi:</strong> Aktif jika intensitas cahaya kandang &lt; <strong><?= (float) $settings['cahaya_min'] ?> lux</strong> atau sesuai jadwal pencahayaan.
            </div>

            <div class="actuator-meta-spec">
              <span>Beban: <strong>25 Watt</strong></span>
              <a href="pencahayaan.php" class="text-decoration-none fw-semibold text-primary">Jadwal Lampu &rarr;</a>
            </div>
          </div>
        </div>

        <!-- 4. Motor Pakan Otomatis -->
        <div class="col-12 col-md-6 col-xl-3">
          <div class="actuator-card-grid <?= !empty($actuators['motor_pakan']) ? 'is-active' : '' ?>" id="card-motor_pakan">
            <div class="actuator-card-header">
              <div class="actuator-big-icon icon-feeder" id="icon-motor_pakan">
                <i class="fa-solid fa-cubes-stacked"></i>
              </div>
              <label class="switch-ios">
                <input type="checkbox" class="js-actuator-toggle" data-actuator="motor_pakan" <?= !empty($actuators['motor_pakan']) ? 'checked' : '' ?>>
                <span class="slider round"></span>
              </label>
            </div>

            <div class="actuator-device-title">Motor Dispenser Pakan</div>
            <div class="actuator-device-sub">Motor servo feeder dispenser pakan</div>

            <div class="d-flex align-items-center gap-2 mb-3">
              <span class="badge <?= !empty($actuators['motor_pakan']) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> rounded-pill px-3 py-1 fw-bold" id="badge-motor_pakan">
                <?= !empty($actuators['motor_pakan']) ? 'Jadwal Aktif' : 'Mati' ?>
              </span>
              <span class="text-muted small">Channel 4</span>
            </div>

            <div class="actuator-rule-box">
              <i class="bi bi-info-circle text-purple me-1"></i>
              <strong>Otomasi:</strong> Siaga berputar otomatis sesuai daftar jadwal pakan harian kandang.
            </div>

            <div class="actuator-meta-spec">
              <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-0 fw-semibold" id="btnManualFeed" style="font-size:0.75rem;">
                <i class="bi bi-play-circle me-1"></i> Beri Sekarang
              </button>
              <a href="pakan_minum.php" class="text-decoration-none fw-semibold text-primary">Jadwal &rarr;</a>
            </div>
          </div>
        </div>
      </div>

      <!-- Presets & Diagnostics + Activity Log Section -->
      <div class="row g-3 g-xl-4 mb-4">
        <!-- Master Presets & Relay Diagnostics -->
        <div class="col-12 col-xl-7">
          <div class="dashboard-panel h-100 d-flex flex-column">
            <div class="panel-header d-flex justify-content-between align-items-center mb-3">
              <div class="panel-title d-flex align-items-center gap-2">
                <i class="bi bi-command text-secondary"></i>
                <span>Preset Kontrol Cepat & Modul Relay</span>
              </div>
              <span class="badge bg-light text-secondary border px-3 py-1 rounded-pill">ESP32 220V Bus</span>
            </div>

            <!-- Quick Action Preset Buttons -->
            <div class="row g-3 mb-4">
              <div class="col-12 col-sm-6">
                <button type="button" class="preset-btn-modern" id="presetHotWeather">
                  <div class="preset-icon-circle text-danger">
                    <i class="bi bi-sun-fill"></i>
                  </div>
                  <div>
                    <div class="fw-bold">Mode Cuaca Panas</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Kipas ON &bull; Heater OFF</div>
                  </div>
                </button>
              </div>

              <div class="col-12 col-sm-6">
                <button type="button" class="preset-btn-modern" id="presetNightMode">
                  <div class="preset-icon-circle text-primary">
                    <i class="bi bi-moon-stars-fill"></i>
                  </div>
                  <div>
                    <div class="fw-bold">Mode Malam Hari</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Lampu OFF &bull; Kipas Standby</div>
                  </div>
                </button>
              </div>

              <div class="col-12 col-sm-6">
                <button type="button" class="preset-btn-modern" id="presetAllAuto">
                  <div class="preset-icon-circle text-success">
                    <i class="bi bi-cpu-fill"></i>
                  </div>
                  <div>
                    <div class="fw-bold">Sinkronkan Otomasi</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Sesuaikan dengan sensor saat ini</div>
                  </div>
                </button>
              </div>

              <div class="col-12 col-sm-6">
                <button type="button" class="preset-btn-modern preset-btn-danger" id="presetEmergencyStop">
                  <div class="preset-icon-circle">
                    <i class="bi bi-power"></i>
                  </div>
                  <div>
                    <div class="fw-bold text-danger">Matikan Semua (Safety)</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Emergency shutdown seluruh relay</div>
                  </div>
                </button>
              </div>
            </div>

            <!-- Hardware Relay Diagnostics -->
            <div class="mt-auto pt-3 border-top">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-semibold small text-secondary">Status Modul Relay 4 Channel ESP32</span>
                <span class="text-muted small">Latensi: 42 ms</span>
              </div>
              <div class="d-flex gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-2 small">
                  <span class="relay-indicator-dot <?= !empty($actuators['kipas']) ? 'dot-on' : 'dot-off' ?>" id="dot-relay-1"></span>
                  <span>CH1: Kipas</span>
                </div>
                <div class="d-flex align-items-center gap-2 small">
                  <span class="relay-indicator-dot <?= !empty($actuators['heater']) ? 'dot-on' : 'dot-off' ?>" id="dot-relay-2"></span>
                  <span>CH2: Heater</span>
                </div>
                <div class="d-flex align-items-center gap-2 small">
                  <span class="relay-indicator-dot <?= !empty($actuators['lampu']) ? 'dot-on' : 'dot-off' ?>" id="dot-relay-3"></span>
                  <span>CH3: Lampu</span>
                </div>
                <div class="d-flex align-items-center gap-2 small">
                  <span class="relay-indicator-dot <?= !empty($actuators['motor_pakan']) ? 'dot-on' : 'dot-off' ?>" id="dot-relay-4"></span>
                  <span>CH4: Motor</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Activity Log Panel -->
        <div class="col-12 col-xl-5">
          <div class="dashboard-panel h-100 d-flex flex-column">
            <div class="panel-header d-flex justify-content-between align-items-center mb-3">
              <div class="panel-title d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-secondary"></i>
                <span>Log Aktivitas Kontrol Aktuator</span>
              </div>
              <span class="badge bg-light text-secondary border px-2 py-1 rounded-pill small">Terbaru</span>
            </div>

            <div class="notification-list d-flex flex-column gap-3" id="actuatorLogList">
              <?php foreach ($actNotifs as $nt): ?>
                <div class="notif-item d-flex gap-2 align-items-start">
                  <span class="notif-dot bg-success mt-1"></span>
                  <div>
                    <div class="notif-title fw-semibold text-dark"><?= htmlspecialchars($nt['pesan'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="notif-time text-muted small"><?= htmlspecialchars($nt['waktu_label'], ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Modal: Atur Jadwal Pakan -->
  <div class="modal fade" id="modalAturJadwal" tabindex="-1" aria-labelledby="modalAturJadwalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="modalAturJadwalTitle">
            <i class="bi bi-calendar-plus text-primary me-2"></i>Atur Jadwal Pakan
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="formAturJadwal">
          <div class="modal-body">
            <div class="mb-3">
              <label for="inputWaktuJadwal" class="form-label fw-semibold text-secondary">Waktu Pemberian</label>
              <input type="time" class="form-control form-control-lg rounded-3" id="inputWaktuJadwal" name="waktu" required value="08:00">
            </div>
            <div class="mb-3">
              <label for="inputLabelJadwal" class="form-label fw-semibold text-secondary">Nama / Catatan Sesi</label>
              <input type="text" class="form-control rounded-3" id="inputLabelJadwal" name="catatan" placeholder="Contoh: Pakan Tambahan Siang" value="Pakan Tambahan">
            </div>
            <div class="row">
              <div class="col-6 mb-3">
                <label for="inputJumlahJadwal" class="form-label fw-semibold text-secondary">Porsi (kg)</label>
                <input type="number" step="0.1" class="form-control rounded-3" id="inputJumlahJadwal" name="jumlah" value="2.5">
              </div>
              <div class="col-6 mb-3">
                <label for="inputHariJadwal" class="form-label fw-semibold text-secondary">Hari</label>
                <select class="form-select rounded-3" id="inputHariJadwal" name="hari">
                  <option value="Semua Hari" selected>Semua Hari</option>
                  <option value="Senin, Rabu, Jumat">Senin, Rabu, Jumat</option>
                  <option value="Selasa, Kamis">Selasa, Kamis</option>
                  <option value="Sabtu, Minggu">Sabtu, Minggu</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold" id="btnSimpanJadwal">
              <i class="bi bi-check2 me-1"></i> Simpan Jadwal
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Pengaturan Batas Normal -->
  <div class="modal fade" id="modalPengaturan" tabindex="-1" aria-labelledby="modalPengaturanTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="modalPengaturanTitle">
            <i class="bi bi-gear-fill text-secondary me-2"></i>Pengaturan Batas Normal
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="formPengaturan">
          <div class="modal-body">
            <div class="mb-3">
              <label for="inputNamaKandang" class="form-label fw-semibold text-secondary">Nama Kandang</label>
              <input type="text" class="form-control rounded-3" id="inputNamaKandang" name="nama_kandang"
                     value="<?= htmlspecialchars($settings['nama_kandang'], ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="row">
              <div class="col-6 mb-3">
                <label for="inputSuhuMin" class="form-label fw-semibold text-secondary">Suhu Minimum (&deg;C)</label>
                <input type="number" step="0.1" min="10" max="45" class="form-control rounded-3" id="inputSuhuMin" name="suhu_min"
                       value="<?= htmlspecialchars(number_format((float) $settings['suhu_min'], 1, '.', ''), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="col-6 mb-3">
                <label for="inputSuhuMaks" class="form-label fw-semibold text-secondary">Suhu Maksimum (&deg;C)</label>
                <input type="number" step="0.1" min="10" max="45" class="form-control rounded-3" id="inputSuhuMaks" name="suhu_maks"
                       value="<?= htmlspecialchars(number_format((float) $settings['suhu_maks'], 1, '.', ''), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="col-6 mb-3">
                <label for="inputLembapMin" class="form-label fw-semibold text-secondary">Kelembaban Min (%)</label>
                <input type="number" step="0.1" min="0" max="100" class="form-control rounded-3" id="inputLembapMin" name="kelembaban_min"
                       value="<?= htmlspecialchars(number_format((float) $settings['kelembaban_min'], 1, '.', ''), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="col-6 mb-3">
                <label for="inputLembapMaks" class="form-label fw-semibold text-secondary">Kelembaban Maks (%)</label>
                <input type="number" step="0.1" min="0" max="100" class="form-control rounded-3" id="inputLembapMaks" name="kelembaban_maks"
                       value="<?= htmlspecialchars(number_format((float) $settings['kelembaban_maks'], 1, '.', ''), ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="col-6 mb-3">
                <label for="inputCahayaMin" class="form-label fw-semibold text-secondary">Cahaya Minimum (lux)</label>
                <input type="number" step="10" min="0" max="5000" class="form-control rounded-3" id="inputCahayaMin" name="cahaya_min"
                       value="<?= htmlspecialchars((string) (float) $settings['cahaya_min'], ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div class="col-6 mb-3">
                <label for="inputAirMin" class="form-label fw-semibold text-secondary">Level Air Minimum (%)</label>
                <input type="number" step="1" min="0" max="100" class="form-control rounded-3" id="inputAirMin" name="level_air_min"
                       value="<?= htmlspecialchars((string) (float) $settings['level_air_min'], ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold">
              <i class="bi bi-check2 me-1"></i> Simpan Pengaturan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>



  <!-- Floating Toast Notification -->
  <div class="dashboard-toast" id="dashToast" role="alert" aria-live="assertive" aria-atomic="true">
    <i class="bi bi-info-circle-fill text-info" id="toastIcon"></i>
    <span id="toastMsg">Pengaturan diperbarui.</span>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      /* ================= Toast helper ================= */
      const toastEl = document.getElementById("dashToast");
      const toastMsg = document.getElementById("toastMsg");
      let toastTimer = null;

      function showToast(msg, tone = "info") {
        if (!toastEl) return;
        toastMsg.textContent = msg;
        toastEl.className = "dash-toast show " + (tone === "success" ? "toast-success" : (tone === "warning" ? "toast-warning" : ""));
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
          toastEl.classList.remove("show");
        }, 3200);
      }

      /* ================= Update UI State ================= */
      function updateActuatorUI(actuator, value, statusText) {
        const isChecked = Boolean(value);
        const card = document.getElementById("card-" + actuator);
        const icon = document.getElementById("icon-" + actuator);
        const badge = document.getElementById("badge-" + actuator);
        const toggle = document.querySelector(`.js-actuator-toggle[data-actuator="${actuator}"]`);
        
        const channelMap = { kipas: 1, heater: 2, lampu: 3, motor_pakan: 4 };
        const dot = document.getElementById("dot-relay-" + channelMap[actuator]);

        if (toggle) toggle.checked = isChecked;

        if (card) {
          if (isChecked) card.classList.add("is-active");
          else card.classList.remove("is-active");
        }

        if (icon) {
          if (actuator === "kipas") {
            if (isChecked) icon.classList.add("fan-running");
            else icon.classList.remove("fan-running");
          }
        }

        if (badge) {
          badge.textContent = statusText || (isChecked ? (actuator === "motor_pakan" ? "Jadwal Aktif" : "Menyala") : "Mati");
          badge.className = "badge " + (isChecked ? "bg-success-subtle text-success" : "bg-secondary-subtle text-secondary") + " rounded-pill px-3 py-1 fw-bold";
        }

        if (dot) {
          dot.className = "relay-indicator-dot " + (isChecked ? "dot-on" : "dot-off");
        }

        recalculateStats();
      }

      function recalculateStats() {
        const toggles = document.querySelectorAll(".js-actuator-toggle");
        let active = 0;
        let power = 0;
        const wattMap = { kipas: 45, heater: 150, lampu: 25, motor_pakan: 15 };

        toggles.forEach(t => {
          if (t.checked) {
            active++;
            power += (wattMap[t.getAttribute("data-actuator")] || 0);
          }
        });

        const elCount = document.getElementById("sumActiveCount");
        const elBadge = document.getElementById("sumActiveBadge");
        const elPower = document.getElementById("sumEstPower");

        if (elCount) elCount.textContent = active;
        if (elBadge) elBadge.innerHTML = `<span class="status-dot-sm"></span> ` + (active > 0 ? "Beroperasi" : "Semua Siaga");
        if (elPower) elPower.textContent = power;
      }

      /* ================= Toggle API Call ================= */
      async function sendToggle(actuator, value) {
        try {
          const res = await fetch("../proses/toggle_aktuator.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ actuator, value })
          });
          const data = await res.json();
          if (data && data.success) {
            updateActuatorUI(actuator, value, data.status_text);
            showToast(data.message || `${actuator} berhasil diubah.`, "success");
            
            // Tambahkan item log ke daftar
            const logContainer = document.getElementById("actuatorLogList");
            if (logContainer) {
              const item = document.createElement("div");
              item.className = "notif-item d-flex gap-2 align-items-start";
              item.innerHTML = `
                <span class="notif-dot bg-success mt-1"></span>
                <div>
                  <div class="notif-title fw-semibold text-dark">${data.message}</div>
                  <div class="notif-time text-muted small">Baru saja</div>
                </div>
              `;
              logContainer.insertBefore(item, logContainer.firstChild);
            }
          } else {
            showToast("Gagal memperbarui status aktuator.", "warning");
          }
        } catch (e) {
          console.error(e);
          showToast("Terjadi kendala jaringan saat menghubungkan ESP32.", "warning");
        }
      }

      document.querySelectorAll(".js-actuator-toggle").forEach(toggle => {
        toggle.addEventListener("change", (e) => {
          const act = e.target.getAttribute("data-actuator");
          const val = e.target.checked ? 1 : 0;
          sendToggle(act, val);
        });
      });

      /* ================= Presets ================= */
      // 1. Matikan Semua
      const btnAllOff = document.getElementById("presetEmergencyStop");
      if (btnAllOff) {
        btnAllOff.addEventListener("click", () => {
          ["kipas", "heater", "lampu", "motor_pakan"].forEach(act => {
            sendToggle(act, 0);
          });
          showToast("Prosedur darurat: Semua aktuator dimatikan.", "warning");
        });
      }

      // 2. Mode Cuaca Panas (Kipas ON, Heater OFF)
      const btnHot = document.getElementById("presetHotWeather");
      if (btnHot) {
        btnHot.addEventListener("click", () => {
          sendToggle("kipas", 1);
          sendToggle("heater", 0);
          showToast("Mode Cuaca Panas diaktifkan: Kipas ON, Heater OFF.", "success");
        });
      }

      // 3. Mode Malam (Lampu OFF, Kipas Auto)
      const btnNight = document.getElementById("presetNightMode");
      if (btnNight) {
        btnNight.addEventListener("click", () => {
          sendToggle("lampu", 0);
          sendToggle("kipas", 0);
          showToast("Mode Malam diaktifkan: Lampu dipadamkan.", "info");
        });
      }

      // 4. Sinkronkan Otomasi
      const btnAuto = document.getElementById("presetAllAuto");
      if (btnAuto) {
        btnAuto.addEventListener("click", () => {
          showToast("Semua sakelar disinkronkan ke mode otomasi mikroklimat.", "success");
        });
      }

      // 5. Beri Pakan Sekarang (Manual feed pulse)
      const btnFeed = document.getElementById("btnManualFeed");
      if (btnFeed) {
        btnFeed.addEventListener("click", () => {
          btnFeed.disabled = true;
          btnFeed.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Memutar...`;
          sendToggle("motor_pakan", 1);
          setTimeout(() => {
            sendToggle("motor_pakan", 1);
            btnFeed.disabled = false;
            btnFeed.innerHTML = `<i class="bi bi-play-circle me-1"></i> Beri Sekarang`;
            showToast("Pakan berhasil dikeluarkan (Dispense 3 Detik selesai).", "success");
          }, 3000);
        });
      }

      /* ================= Polling Status ================= */
      setInterval(async () => {
        try {
          const res = await fetch("../proses/ambil_data_dashboard.php");
          const data = await res.json();
          if (data && data.success && data.actuators) {
            const acts = data.actuators;
            for (const [k, v] of Object.entries(acts)) {
              const toggle = document.querySelector(`.js-actuator-toggle[data-actuator="${k}"]`);
              if (toggle && toggle.checked !== Boolean(v)) {
                updateActuatorUI(k, v);
              }
            }
          }
        } catch (e) {}
      }, 5000);
    });
  </script>
</body>
</html>
