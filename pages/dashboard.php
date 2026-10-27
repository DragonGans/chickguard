<?php
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit;
}

include '../config/koneksi.php';
require_once '../proses/init_tables.php';
require_once '../proses/helpers.php';
chickguard_init_tables($koneksi);

$activePage = 'dashboard';

// User info
$username = $_SESSION['username'] ?? 'admin';

/* ----------------------------------------------------------------------------
 * Pengaturan / batas normal & tanggal masuk ayam (pengaturan_kandang)
 * -------------------------------------------------------------------------- */
$settingQuery = mysqli_query($koneksi, "SELECT * FROM pengaturan_kandang WHERE id = 1 LIMIT 1");
$settings = $settingQuery ? mysqli_fetch_assoc($settingQuery) : [
    'nama_kandang'        => 'Kandang 1 - Utama',
    'tanggal_masuk_ayam'  => date('Y-m-d', strtotime('-18 days')),
    'suhu_min'            => 24.0,
    'suhu_maks'           => 34.0,
    'kelembaban_min'      => 55.0,
    'kelembaban_maks'     => 75.0,
    'cahaya_min'          => 500,
    'level_air_min'       => 50.0,
];

if (empty($settings['tanggal_masuk_ayam'])) {
    $settings['tanggal_masuk_ayam'] = date('Y-m-d', strtotime('-18 days'));
}

// Perhitungan Umur Ayam
$tglMasukTs = strtotime($settings['tanggal_masuk_ayam']);
$umurAyamHari = max(0, (int) round((strtotime('today') - $tglMasukTs) / 86400));

if ($umurAyamHari <= 7) {
    $faseAyam = 'Starter';
    $faseBadgeClass = 'bg-info-subtle text-info';
} elseif ($umurAyamHari <= 21) {
    $faseAyam = 'Grower';
    $faseBadgeClass = 'bg-primary-subtle text-primary';
} else {
    $faseAyam = 'Finisher';
    $faseBadgeClass = 'bg-success-subtle text-success';
}

$mingguKe = intdiv($umurAyamHari, 7) + 1;
$tglMasukFormatted = date('d M Y', $tglMasukTs);

$topbarTitle = 'Selamat Datang, ' . $username . '!';
$topbarSubtitle = 'Pantau kondisi ' . htmlspecialchars($settings['nama_kandang']) . ' • Umur Ayam: ' . $umurAyamHari . ' Hari (' . $faseAyam . ') secara real-time.';

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
    'lampu' => 0,
    'motor_pakan' => 1,
];

/* ----------------------------------------------------------------------------
 * Jadwal pakan widget (maks 2 jadwal aktif)
 * -------------------------------------------------------------------------- */
$jadwalQuery = mysqli_query($koneksi, "SELECT * FROM jadwal_pakan ORDER BY waktu ASC");
$feedSchedules = [];
if ($jadwalQuery) {
    while ($row = mysqli_fetch_assoc($jadwalQuery)) {
        $feedSchedules[] = $row;
    }
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
 * Status kandang
 * -------------------------------------------------------------------------- */
$statKandang = cg_status_kandang($latestSensor ?: [], $settings);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ChickGuard - Smart Chicken Coop IoT</title>
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
</head>
<body class="dashboard-page page-dashboard">
  <div class="dashboard-shell d-lg-flex">
    <?php include '../components/sidebar.php'; ?>

    <main class="main-content">
      <?php include '../components/topbar.php'; ?>

      <!-- 4 Metric Cards (Top Row) -->
      <section class="content-section" id="sensor-section">
        <div class="row g-3 g-xl-4 mb-4">
          <!-- Suhu -->
          <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card-modern">
              <div class="metric-icon-circle icon-temp">
                <i class="bi bi-thermometer-half"></i>
              </div>
              <div class="metric-content">
                <div class="metric-title-modern">Suhu</div>
                <div class="metric-val-modern"><span id="metricSuhu"><?= $graphSuhu ?></span> <span class="metric-unit">&deg;C</span></div>
                <div class="metric-badge-modern <?= $stSuhu['class'] ?>" id="statusSuhu">
                  <span class="status-dot-sm"></span> <?= $stSuhu['label'] ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Kelembaban -->
          <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card-modern">
              <div class="metric-icon-circle icon-humid">
                <i class="bi bi-droplet-fill"></i>
              </div>
              <div class="metric-content">
                <div class="metric-title-modern">Kelembaban</div>
                <div class="metric-val-modern"><span id="metricLembap"><?= $graphLembap ?></span> <span class="metric-unit">%</span></div>
                <div class="metric-badge-modern <?= $stLembap['class'] ?>" id="statusLembap">
                  <span class="status-dot-sm"></span> <?= $stLembap['label'] ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Cahaya -->
          <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card-modern">
              <div class="metric-icon-circle icon-light">
                <i class="bi bi-sun-fill"></i>
              </div>
              <div class="metric-content">
                <div class="metric-title-modern">Cahaya</div>
                <div class="metric-val-modern"><span id="metricCahaya"><?= $graphCahaya ?></span> <span class="metric-unit">lux</span></div>
                <div class="metric-badge-modern <?= $stCahaya['class'] ?>" id="statusCahaya">
                  <span class="status-dot-sm"></span> <?= $stCahaya['label'] ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Level Air -->
          <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card-modern">
              <div class="metric-icon-circle icon-water">
                <i class="bi bi-water"></i>
              </div>
              <div class="metric-content">
                <div class="metric-title-modern">Level Air</div>
                <div class="metric-val-modern"><span id="metricAir"><?= $graphAir ?></span> <span class="metric-unit">%</span></div>
                <div class="metric-badge-modern <?= $stAir['class'] ?>" id="statusAir">
                  <span class="status-dot-sm"></span> <?= $stAir['label'] ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Middle Section: Chart on Left, Actuators + Schedule on Right -->
        <div class="row g-3 g-xl-4 mb-4">
          <!-- Left Column (Grafik Sensor) -->
          <div class="col-12 col-xl-8">
            <div class="dashboard-panel chart-panel h-100 d-flex flex-column">
              <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="panel-title d-flex align-items-center gap-2">
                  <i class="bi bi-graph-up-arrow text-secondary"></i>
                  <span>Grafik Tren Sensor (24 Jam Terakhir)</span>
                </div>
                <div class="sensor-tabs-wrap">
                  <button type="button" class="sensor-tab-btn active" data-view="all">Semua</button>
                  <button type="button" class="sensor-tab-btn" data-view="suhu">Suhu</button>
                  <button type="button" class="sensor-tab-btn" data-view="lembap">Kelembaban</button>
                  <button type="button" class="sensor-tab-btn" data-view="cahaya">Cahaya</button>
                  <button type="button" class="sensor-tab-btn" data-view="air">Air</button>
                </div>
              </div>
              <div class="chart-container-modern mt-3 flex-grow-1">
                <canvas id="sensor24hChart"></canvas>
              </div>
            </div>
          </div>

          <!-- Right Column (Status Kandang + Kontrol Aktuator) -->
          <div class="col-12 col-xl-4" id="pengaturan-section">
            <div class="d-flex flex-column gap-3 gap-xl-4 h-100">
              <!-- Status Kandang -->
              <div class="dashboard-panel status-panel">
                <div class="panel-header d-flex justify-content-between align-items-center mb-3">
                  <div class="panel-title d-flex align-items-center gap-2">
                    <i class="bi bi-house-door-fill text-secondary"></i>
                    <span>Status Kandang</span>
                  </div>
                  <a href="pengaturan.php" class="panel-gear-btn text-decoration-none" title="Pengaturan Sistem & Akun">
                    <i class="bi bi-gear"></i>
                  </a>
                </div>

                <div class="status-block-wrapper d-flex flex-column gap-3">
                  <!-- Umur Ayam -->
                  <div class="status-subblock">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <div class="subblock-heading">Umur Ayam</div>
                      <span class="badge <?= $faseBadgeClass ?> rounded-pill px-2.5 py-1 fw-semibold small">Fase <?= $faseAyam ?></span>
                    </div>
                    <div class="status-alert-box d-flex align-items-center justify-content-between" style="background:#f0fdf4;border:1px solid #dcfce7;box-shadow:0 3px 10px rgba(16, 185, 129, 0.08);">
                      <div class="d-flex align-items-center gap-2">
                        <div style="width:36px;height:36px;border-radius:10px;background:#dcfce7;color:#166534;display:grid;place-items:center;font-size:1.1rem;flex-shrink:0;">
                          <i class="bi bi-calendar-check-fill"></i>
                        </div>
                        <div>
                          <div class="fw-bold text-dark fs-6" id="dashUmurText"><?= $umurAyamHari ?> Hari</div>
                          <div class="text-muted" style="font-size:0.72rem;">Masuk: <?= $tglMasukFormatted ?></div>
                        </div>
                      </div>
                      <span class="badge bg-white text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.75rem;">
                        Minggu ke-<?= $mingguKe ?>
                      </span>
                    </div>
                  </div>

                  <div class="status-subblock">
                    <div class="subblock-heading mb-2">Kondisi Kandang</div>
                    <div class="status-alert-box <?= $statKandang['kondisi']['class'] ?> d-flex align-items-start gap-2" id="kondisiKandangBox">
                      <i class="bi <?= $statKandang['kondisi']['class'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> fs-5 <?= $statKandang['kondisi']['class'] === 'success' ? 'text-success' : 'text-warning' ?>" id="kondisiIcon"></i>
                      <span class="status-desc" id="kondisiText"><?= $statKandang['kondisi']['text'] ?></span>
                    </div>
                  </div>

                  <div class="status-subblock">
                    <div class="subblock-heading mb-2">Tips</div>
                    <div class="status-alert-box info d-flex align-items-start gap-2" id="tipsBox">
                      <i class="bi bi-lightbulb-fill text-warning fs-5 flex-shrink-0 mt-1"></i>
                      <span class="tips-desc" id="tipsText"><?= $statKandang['tips'] ?></span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Kontrol Aktuator -->
              <div class="dashboard-panel actuator-panel" id="aktuator-section">
                <div class="panel-header d-flex justify-content-between align-items-center mb-3">
                  <div class="panel-title d-flex align-items-center gap-2">
                    <i class="fa-solid fa-sliders text-secondary"></i>
                    <span>Kontrol Aktuator</span>
                  </div>
                  <a href="aktuator.php" class="panel-link">Lihat Semua</a>
                </div>

                <div class="actuator-list mt-3 d-flex flex-column gap-3">
                  <!-- Kipas -->
                  <div class="actuator-row d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                      <div class="actuator-icon-box box-fan">
                        <i class="fa-solid fa-fan"></i>
                      </div>
                      <div>
                        <div class="actuator-name">Kipas</div>
                        <div class="actuator-state" id="state-kipas"><?= !empty($actuators['kipas']) ? 'Menyala' : 'Mati' ?></div>
                      </div>
                    </div>
                    <label class="switch-ios">
                      <input type="checkbox" id="toggle-kipas" data-actuator="kipas" <?= !empty($actuators['kipas']) ? 'checked' : '' ?>>
                      <span class="slider round"></span>
                    </label>
                  </div>

                  <!-- Lampu -->
                  <div class="actuator-row d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                      <div class="actuator-icon-box box-lamp">
                        <i class="bi bi-lightbulb-fill"></i>
                      </div>
                      <div>
                        <div class="actuator-name">Lampu</div>
                        <div class="actuator-state" id="state-lampu"><?= !empty($actuators['lampu']) ? 'Menyala' : 'Mati' ?></div>
                      </div>
                    </div>
                    <label class="switch-ios">
                      <input type="checkbox" id="toggle-lampu" data-actuator="lampu" <?= !empty($actuators['lampu']) ? 'checked' : '' ?>>
                      <span class="slider round"></span>
                    </label>
                  </div>

                  <!-- Motor Pakan -->
                  <div class="actuator-row d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                      <div class="actuator-icon-box box-feeder">
                        <i class="fa-solid fa-cubes-stacked"></i>
                      </div>
                      <div>
                        <div class="actuator-name">Motor Pakan</div>
                        <div class="actuator-state" id="state-motor_pakan"><?= !empty($actuators['motor_pakan']) ? 'Jadwal Aktif' : 'Mati' ?></div>
                      </div>
                    </div>
                    <label class="switch-ios">
                      <input type="checkbox" id="toggle-motor_pakan" data-actuator="motor_pakan" <?= !empty($actuators['motor_pakan']) ? 'checked' : '' ?>>
                      <span class="slider round"></span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom Row (Jadwal Pakan, Log Data Terbaru, Notifikasi Terbaru) -->
        <div class="row g-3 g-xl-4">
          <!-- 1. Jadwal Pakan (Left Column) -->
          <div class="col-12 col-xl-4" id="jadwal-section">
            <div class="dashboard-panel schedule-panel h-100 d-flex flex-column justify-content-between">
              <div>
                <div class="panel-header d-flex justify-content-between align-items-center mb-3">
                  <div class="panel-title d-flex align-items-center gap-2">
                    <i class="fa-solid fa-calendar-check text-secondary"></i>
                    <span>Jadwal Pakan</span>
                  </div>
                  <a href="pakan.php" class="panel-link">Lihat Semua</a>
                </div>

                <div class="schedule-list mt-3 d-flex flex-column gap-3" id="scheduleListContainer">
                    <?php if (empty($feedSchedules)): ?>
                      <div class="text-center text-muted py-3 small" id="noScheduleNotice">
                        <i class="bi bi-calendar-x d-block fs-3 text-secondary mb-1"></i>
                        Belum ada jadwal pakan
                      </div>
                    <?php else: ?>
                      <?php foreach ($feedSchedules as $index => $sch): ?>
                        <?php
                          $catatan = trim((string) ($sch['catatan'] ?? ''));
                          $pakanLabel = $catatan !== '' ? $catatan : ('Pakan ' . ($index + 1));
                          $pakanTime = date('H:i', strtotime($sch['waktu']));
                          $isActive = !isset($sch['is_active']) || $sch['is_active'] == 1;
                        ?>
                        <div class="schedule-row d-flex align-items-center justify-content-between" data-schedule-id="<?= (int) $sch['id'] ?>">
                          <div>
                            <div class="schedule-label"><?= htmlspecialchars($pakanLabel, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="schedule-time"><?= htmlspecialchars($pakanTime, ENT_QUOTES, 'UTF-8') ?> WIB</div>
                          </div>
                          <label class="switch-ios">
                            <input type="checkbox" class="js-toggle-feed-schedule" data-id="<?= (int) $sch['id'] ?>" <?= $isActive ? 'checked' : '' ?>>
                            <span class="slider round"></span>
                          </label>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>

                <button type="button" class="btn-atur-jadwal mt-3 w-100 js-open-schedule-modal">
                <i class="bi bi-calendar-check"></i> Atur Jadwal
              </button>
            </div>
          </div>

          <!-- 2. Log Data Terbaru (Middle Column) -->
          <div class="col-12 col-xl-5">
            <div class="dashboard-panel log-panel h-100">
              <div class="panel-header d-flex justify-content-between align-items-center mb-3">
                <div class="panel-title d-flex align-items-center gap-2">
                  <i class="bi bi-database-fill text-secondary"></i>
                  <span>Log Data Terbaru</span>
                </div>
                <a href="log_harian.php" class="panel-link">Lihat Semua</a>
              </div>

              <div class="table-responsive">
                <table class="table modern-table align-middle">
                  <thead>
                    <tr>
                      <th class="sortable" data-col="waktu">Waktu <i class="bi bi-arrow-down-up sort-ico"></i></th>
                      <th class="sortable" data-col="suhu">Suhu <i class="bi bi-arrow-down-up sort-ico"></i></th>
                      <th class="sortable" data-col="kelembaban">Kelembaban <i class="bi bi-arrow-down-up sort-ico"></i></th>
                      <th class="sortable" data-col="cahaya">Cahaya <i class="bi bi-arrow-down-up sort-ico"></i></th>
                      <th class="sortable" data-col="level_air">Level Air <i class="bi bi-arrow-down-up sort-ico"></i></th>
                    </tr>
                  </thead>
                  <tbody id="recentLogsTableBody">
                    <?php foreach ($recentLogs as $log): ?>
                      <tr>
                        <td><?= htmlspecialchars(date('H:i', strtotime($log['recorded_at'] ?? 'now')), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format((float) $log['suhu'], 1, '.', ',') ?> &deg;C</td>
                        <td><?= number_format((float) $log['kelembaban'], 1, '.', ',') ?> %</td>
                        <td><?= number_format((float) $log['cahaya'], 0, '.', ',') ?> lux</td>
                        <td><?= number_format((float) $log['level_air'], 0, '.', ',') ?> %</td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- 3. Notifikasi Terbaru (Right Column) -->
          <div class="col-12 col-xl-3">
            <div class="dashboard-panel notif-panel h-100">
              <div class="panel-header d-flex justify-content-between align-items-center mb-3">
                <div class="panel-title d-flex align-items-center gap-2">
                  <i class="bi bi-bell-fill text-secondary"></i>
                  <span>Notifikasi Terbaru</span>
                </div>
                <a href="#" class="panel-link js-toggle-notifications">Lihat Semua</a>
              </div>

              <div class="notification-list d-flex flex-column gap-3" id="notifListContainer">
                <?php foreach (array_slice($notifs, 0, 4) as $nt): ?>
                  <div class="notif-item d-flex gap-2 align-items-start">
                    <span class="notif-dot <?= $nt['tipe'] === 'warning' ? 'bg-warning' : ($nt['tipe'] === 'danger' ? 'bg-danger' : 'bg-success') ?> mt-1 <?= empty($nt['is_read']) ? '' : 'bg-opacity-50' ?>"></span>
                    <div>
                      <div class="notif-title" <?= empty($nt['is_read']) ? '' : 'style="font-weight:500;color:#64748b"' ?>><?= htmlspecialchars($nt['pesan'], ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="notif-time"><?= htmlspecialchars($nt['waktu_label'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <!-- Modal: Atur Jadwal Pakan -->
  <div class="modal fade" id="modalAturJadwal" tabindex="-1" aria-labelledby="modalAturJadwalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
        <div class="modal-header border-0 pb-0 pt-4 px-4 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <div style="width: 48px; height: 48px; border-radius: 14px; background: #e6f9f0; color: #0d9488; display: grid; place-items: center; font-size: 1.35rem;">
              <i class="fa-solid fa-bowl-food"></i>
            </div>
            <div>
              <h5 class="modal-title fw-bold text-dark mb-0" id="modalAturJadwalTitle">Tambah Jadwal Pakan</h5>
              <small class="text-muted" id="modalAturJadwalSubtitle">Atur waktu dan hari operasional pakan otomatis</small>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="formAturJadwal">
          <div class="modal-body px-4 py-3">
            <!-- 1. Nama Jadwal -->
            <div class="mb-3">
              <label for="dashCatatan" class="form-label fw-bold text-dark small d-flex align-items-center gap-1.5 mb-2">
                <i class="bi bi-tag-fill text-success"></i> Nama Jadwal
              </label>
              <input type="text" name="catatan" id="dashCatatan" class="form-control form-control-lg rounded-3 fw-medium text-dark" placeholder="Contoh: Pakan Pagi" required style="border: 1.5px solid #cbd5e1; font-size: 1.05rem; padding: 0.6rem 1rem;">
            </div>

            <!-- 2. Waktu Pemberian (WIB) -->
            <div class="mb-3">
              <label for="dashWaktu" class="form-label fw-bold text-dark small d-flex align-items-center gap-1.5 mb-2">
                <i class="bi bi-clock-fill text-success"></i> Waktu Pemberian (WIB)
              </label>
              <div class="input-group">
                <input type="time" name="waktu" id="dashWaktu" class="form-control form-control-lg rounded-3 fw-bold text-dark" value="08:00" required style="border: 1.5px solid #cbd5e1; font-size: 1.35rem; letter-spacing: 1px; padding: 0.6rem 1rem;">
              </div>
            </div>

            <!-- 3. Pilih Hari Aktif -->
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label fw-bold text-dark small d-flex align-items-center gap-1.5 mb-0">
                  <i class="bi bi-calendar-week-fill text-success"></i> Pilih Hari Aktif
                </label>
                <button type="button" class="btn btn-sm btn-link text-decoration-none text-muted p-0 small" id="dashResetDaysBtn" style="font-size: 0.8rem;">
                  <i class="bi bi-arrow-repeat me-1"></i>Pilih Ulang
                </button>
              </div>

              <div class="schedule-days-container p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                <div class="mb-3">
                  <label class="form-check d-flex align-items-center gap-2 m-0" style="cursor: pointer;">
                    <input class="form-check-input mt-0" type="checkbox" name="hari[]" value="Semua Hari" id="dashAllDays" checked style="width: 1.15rem; height: 1.15rem;">
                    <span class="form-check-label fw-bold text-dark small">Pilih Semua Hari (Setiap Hari)</span>
                  </label>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2 pt-1" id="dashDayButtonsContainer">
                  <?php foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $day): ?>
                    <label class="day-chip-item m-0">
                      <input type="checkbox" name="hari[]" value="<?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?>" class="dash-day-checkbox visually-hidden" checked>
                      <span class="day-chip-badge"><?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer border-0 pt-0 pb-4 px-4 d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold text-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm d-inline-flex align-items-center gap-2" id="btnSimpanJadwal">
              <i class="bi bi-check2"></i>
              <span>Simpan Jadwal</span>
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
      /* ================= Configuration ================= */
      const SETTINGS = <?= json_encode([
          'suhu_min'        => (float) $settings['suhu_min'],
          'suhu_maks'       => (float) $settings['suhu_maks'],
          'kelembaban_min'  => (float) $settings['kelembaban_min'],
          'kelembaban_maks' => (float) $settings['kelembaban_maks'],
          'cahaya_min'      => (float) $settings['cahaya_min'],
          'level_air_min'   => (float) $settings['level_air_min'],
      ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

      const SENSOR_CHART_DATA = {
        labels: <?= json_encode($chartLabels) ?>,
        suhu: <?= json_encode(array_map(fn($v) => round($v / $suhuScaleK, 1), $chartSuhu)) ?>,
        suhu_scaled: <?= json_encode($chartSuhu) ?>,
        kelembaban: <?= json_encode($chartHumid) ?>,
        cahaya: <?= json_encode(array_map(fn($v) => round($v * $cahayaScaleK), $chartLight)) ?>,
        cahaya_scaled: <?= json_encode($chartLight) ?>,
        level_air: <?= json_encode($chartWater) ?>,
      };

      const SUHU_SCALE = <?= json_encode($suhuScaleK) ?>;
      const CAHAYA_SCALE = <?= json_encode($cahayaScaleK) ?>;
      let currentChartTab = "all";

      /* ================= Toast helper ================= */
      const toastEl = document.getElementById("dashToast");
      const toastMsg = document.getElementById("toastMsg");
      const toastIcon = document.getElementById("toastIcon");
      let toastTimer = null;

      function showToast(message, type = "success") {
        if (!toastEl) return;
        toastMsg.textContent = message;
        if (type === "success") {
          toastIcon.className = "bi bi-check-circle-fill text-success fs-5";
        } else if (type === "warning") {
          toastIcon.className = "bi bi-exclamation-triangle-fill text-warning fs-5";
        } else {
          toastIcon.className = "bi bi-info-circle-fill text-info fs-5";
        }
        toastEl.classList.add("show");
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
          toastEl.classList.remove("show");
        }, 3200);
      }

      /* ================= Detailed Sensor Chart ================= */
      const chartCanvas = document.getElementById("sensor24hChart");
      let sensorChart = null;

      function createDatasets(view) {
        if (view === "suhu") {
          return [{
            label: "Suhu (°C)",
            data: SENSOR_CHART_DATA.suhu,
            borderColor: "#eab308",
            backgroundColor: "rgba(234, 179, 8, 0.12)",
            borderWidth: 2.8,
            fill: true,
            tension: 0.35,
            pointRadius: 2.5,
            pointHoverRadius: 6,
          }];
        } else if (view === "lembap") {
          return [{
            label: "Kelembaban (%)",
            data: SENSOR_CHART_DATA.kelembaban,
            borderColor: "#0284c7",
            backgroundColor: "rgba(2, 132, 199, 0.12)",
            borderWidth: 2.8,
            fill: true,
            tension: 0.35,
            pointRadius: 2.5,
            pointHoverRadius: 6,
          }];
        } else if (view === "cahaya") {
          return [{
            label: "Cahaya (lux)",
            data: SENSOR_CHART_DATA.cahaya,
            borderColor: "#10b981",
            backgroundColor: "rgba(16, 185, 129, 0.12)",
            borderWidth: 2.8,
            fill: true,
            tension: 0.35,
            pointRadius: 2.5,
            pointHoverRadius: 6,
          }];
        } else if (view === "air") {
          return [{
            label: "Level Air (%)",
            data: SENSOR_CHART_DATA.level_air,
            borderColor: "#8b5cf6",
            backgroundColor: "rgba(139, 92, 246, 0.12)",
            borderWidth: 2.8,
            fill: true,
            tension: 0.35,
            pointRadius: 2.5,
            pointHoverRadius: 6,
          }];
        }

        // View "all"
        return [
          {
            label: "Suhu (°C)",
            data: SENSOR_CHART_DATA.suhu_scaled,
            borderColor: "#eab308",
            backgroundColor: "#eab308",
            borderWidth: 2.2,
            pointRadius: 1.8,
            tension: 0.35,
          },
          {
            label: "Kelembaban (%)",
            data: SENSOR_CHART_DATA.kelembaban,
            borderColor: "#0284c7",
            backgroundColor: "#0284c7",
            borderWidth: 2.2,
            pointRadius: 1.8,
            tension: 0.35,
          },
          {
            label: "Cahaya (lux)",
            data: SENSOR_CHART_DATA.cahaya_scaled,
            borderColor: "#10b981",
            backgroundColor: "#10b981",
            borderWidth: 2.2,
            pointRadius: 1.8,
            tension: 0.35,
          },
          {
            label: "Level Air (%)",
            data: SENSOR_CHART_DATA.level_air,
            borderColor: "#8b5cf6",
            backgroundColor: "#8b5cf6",
            borderWidth: 2.2,
            pointRadius: 1.8,
            tension: 0.35,
          }
        ];
      }

      if (chartCanvas) {
        sensorChart = new Chart(chartCanvas, {
          type: "line",
          data: {
            labels: SENSOR_CHART_DATA.labels,
            datasets: createDatasets("all")
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: "index", intersect: false },
            plugins: {
              legend: {
                display: true,
                position: "top",
                labels: {
                  boxWidth: 12,
                  font: { family: "Poppins", size: 11 },
                  color: "#64748b"
                }
              },
              tooltip: {
                backgroundColor: "rgba(15, 23, 42, 0.9)",
                padding: 10,
                cornerRadius: 10,
                titleFont: { family: "Poppins", size: 12 },
                bodyFont: { family: "Poppins", size: 11 },
                callbacks: {
                  label: function(ctx) {
                    const l = ctx.dataset.label || "";
                    if (l.includes("Cahaya")) {
                      const raw = SENSOR_CHART_DATA.cahaya[ctx.dataIndex];
                      return "Cahaya: " + raw + " lux";
                    }
                    if (currentChartTab === "all" && l.includes("Suhu")) {
                      const raw = SENSOR_CHART_DATA.suhu[ctx.dataIndex];
                      return "Suhu: " + raw + " °C";
                    }
                    return l + ": " + ctx.parsed.y;
                  }
                }
              }
            },
            scales: {
              x: {
                grid: { color: "#f1f5f9" },
                ticks: {
                  font: { family: "Poppins", size: 10.5 },
                  color: "#64748b",
                  maxRotation: 0,
                  autoSkip: false,
                  callback: function(val) {
                    const raw = this.getLabelForValue(val);
                    const parts = raw.split(":");
                    const hour = parseInt(parts[0], 10);
                    const minute = parts[1];
                    if (minute !== "00") return "";
                    if (hour % 4 === 0 || hour === 0) return raw;
                    return "";
                  }
                }
              },
              y: {
                grid: { color: "#f1f5f9" },
                ticks: { font: { family: "Poppins", size: 10.5 }, color: "#64748b" }
              }
            }
          }
        });
      }

      // Tab switcher
      document.querySelectorAll(".sensor-tab-btn").forEach(btn => {
        btn.addEventListener("click", () => {
          document.querySelectorAll(".sensor-tab-btn").forEach(b => b.classList.remove("active"));
          btn.classList.add("active");
          const view = btn.getAttribute("data-view");
          currentChartTab = view;
          if (sensorChart) {
            sensorChart.data.datasets = createDatasets(view);
            sensorChart.options.plugins.legend.display = (view === "all");
            sensorChart.update();
          }
        });
      });

      /* ================= Log Data Terbaru: sorting + render ================= */
      const logsTbody = document.getElementById("recentLogsTableBody");
      let sortState = null; // {col, dir}

      function cellSortValue(row, col) {
        const cells = row.querySelectorAll("td");
        const order = ["waktu", "suhu", "kelembaban", "cahaya", "level_air"];
        const idx = order.indexOf(col);
        const text = cells[idx] ? cells[idx].textContent.trim() : "";
        if (col === "waktu") {
          const [h, m] = text.split(":").map(Number);
          return (h || 0) * 60 + (m || 0);
        }
        const num = parseFloat(text.replace(",", "."));
        return isNaN(num) ? 0 : num;
      }

      function applyTableSort() {
        if (!logsTbody || !sortState) return;
        const rows = Array.from(logsTbody.querySelectorAll("tr"));
        const col = sortState.col;
        const dir = sortState.dir === "asc" ? 1 : -1;
        rows.sort((a, b) => (cellSortValue(a, col) - cellSortValue(b, col)) * dir);
        rows.forEach(r => logsTbody.appendChild(r));

        document.querySelectorAll(".modern-table th.sortable").forEach(th => {
          const active = th.dataset.col === col;
          th.classList.toggle("sort-active", active);
          const ico = th.querySelector(".sort-ico");
          if (ico) ico.className = active
            ? (sortState.dir === "asc" ? "bi bi-arrow-up sort-ico text-primary" : "bi bi-arrow-down sort-ico text-primary")
            : "bi bi-arrow-down-up sort-ico";
        });
      }

      document.querySelectorAll(".modern-table th.sortable").forEach(th => {
        th.addEventListener("click", () => {
          const col = th.dataset.col;
          if (sortState && sortState.col === col) {
            sortState.dir = sortState.dir === "asc" ? "desc" : "asc";
          } else {
            sortState = { col, dir: "desc" };
          }
          applyTableSort();
        });
      });

      function renderLogs(logs) {
        if (!logsTbody || !Array.isArray(logs)) return;
        logsTbody.innerHTML = logs.map(log => `
          <tr>
            <td>${log.waktu}</td>
            <td>${Number(log.suhu).toFixed(1)} &deg;C</td>
            <td>${Number(log.kelembaban).toFixed(1)} %</td>
            <td>${Math.round(Number(log.cahaya))} lux</td>
            <td>${Math.round(Number(log.level_air))} %</td>
          </tr>`).join("");
        applyTableSort();
      }

      /* ================= Notifikasi ================= */
      const notifListContainer = document.getElementById("notifListContainer");
      const modalNotifList = document.getElementById("modalNotifList");
      const notifDot = document.getElementById("topbarNotifDot");

      function dotClass(tipe, isRead) {
        let cls = tipe === "warning" ? "bg-warning" : (tipe === "danger" ? "bg-danger" : "bg-success");
        if (isRead == 1) cls += " bg-opacity-50";
        return cls;
      }

      function renderNotifs(notifs) {
        if (!Array.isArray(notifs)) return;
        if (notifListContainer) {
          notifListContainer.innerHTML = notifs.slice(0, 4).map(n => `
            <div class="notif-item d-flex gap-2 align-items-start">
              <span class="notif-dot ${dotClass(n.tipe, n.is_read)} mt-1"></span>
              <div>
                <div class="notif-title" style="${n.is_read == 1 ? 'font-weight:500;color:#64748b' : ''}">${escapeHtml(n.pesan)}</div>
                <div class="notif-time">${escapeHtml(n.waktu_label)}</div>
              </div>
            </div>`).join("");
        }
        if (modalNotifList) {
          modalNotifList.innerHTML = notifs.map(n => `
            <div class="list-group-item px-0 py-2 border-light d-flex gap-3 align-items-center">
              <span class="notif-dot ${dotClass(n.tipe, n.is_read)}"></span>
              <div class="flex-grow-1">
                <div class="fw-semibold text-dark fs-6">${escapeHtml(n.pesan)}</div>
                <div class="text-muted small">${escapeHtml(n.waktu_label)}</div>
              </div>
              ${n.is_read == 1 ? "" : '<span class="badge bg-success-subtle text-success rounded-pill">Baru</span>'}
            </div>`).join("");
        }
      }

      function setUnread(count) {
        if (notifDot) notifDot.hidden = !(count > 0);
      }

      function escapeHtml(str) {
        return String(str ?? "")
          .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
      }



      /* ================= Status Kandang ================= */
      function updateStatusKandang(st) {
        if (!st || !st.kondisi) return;
        const box = document.getElementById("kondisiKandangBox");
        const icon = document.getElementById("kondisiIcon");
        const text = document.getElementById("kondisiText");
        const tips = document.getElementById("tipsText");
        if (box) box.className = `status-alert-box ${st.kondisi.class} d-flex align-items-start gap-2`;
        if (icon) {
          if (st.kondisi.class === "success") {
            icon.className = "bi bi-check-circle-fill fs-5 text-success";
          } else if (st.kondisi.class === "danger") {
            icon.className = "bi bi-x-octagon-fill fs-5 text-danger";
          } else {
            icon.className = "bi bi-exclamation-triangle-fill fs-5 text-warning";
          }
        }
        if (text) text.innerHTML = st.kondisi.text;
        if (tips) tips.textContent = st.tips;
      }

      /* ================= Metrik & status ================= */
      function metricStatus(val, min, max, labelLow, labelHigh) {
        if (val < min) return { label: labelLow, cls: "status-warning" };
        if (val > max) return { label: labelHigh, cls: "status-danger" };
        return { label: "Normal", cls: "status-normal" };
      }

      function metricStatusCahaya(val) {
        return val < SETTINGS.cahaya_min ? { label: "Redup", cls: "status-warning" } : { label: "Normal", cls: "status-normal" };
      }

      function metricStatusAir(val) {
        return val < SETTINGS.level_air_min ? { label: "Rendah", cls: "status-warning" } : { label: "Aman", cls: "status-normal" };
      }

      function setMetric(valueId, statusId, value, status) {
        const valEl = document.getElementById(valueId);
        const stEl = document.getElementById(statusId);
        if (valEl) valEl.textContent = value;
        if (stEl) {
          stEl.className = "metric-badge-modern " + status.cls;
          stEl.innerHTML = '<span class="status-dot-sm"></span> ' + status.label;
        }
      }

      function updateMetrics(sensor) {
        if (!sensor) return;
        setMetric("metricSuhu", "statusSuhu", Number(sensor.suhu).toFixed(1),
          metricStatus(Number(sensor.suhu), SETTINGS.suhu_min, SETTINGS.suhu_maks, "Dingin", "Panas"));
        setMetric("metricLembap", "statusLembap", Number(sensor.kelembaban).toFixed(1),
          metricStatus(Number(sensor.kelembaban), SETTINGS.kelembaban_min, SETTINGS.kelembaban_maks, "Kering", "Lembap"));
        setMetric("metricCahaya", "statusCahaya", Math.round(Number(sensor.cahaya)), metricStatusCahaya(Number(sensor.cahaya)));
        setMetric("metricAir", "statusAir", Math.round(Number(sensor.level_air)), metricStatusAir(Number(sensor.level_air)));

        // Titik terakhir grafik mengikuti pembacaan terbaru
        if (sensorChart) {
          const s = sensor;
          const len = SENSOR_CHART_DATA.suhu.length;
          if (len > 0) {
            SENSOR_CHART_DATA.suhu[len - 1] = Number(Number(s.suhu).toFixed(1));
            SENSOR_CHART_DATA.suhu_scaled[len - 1] = Number((Number(s.suhu) * SUHU_SCALE).toFixed(1));
            SENSOR_CHART_DATA.kelembaban[len - 1] = Number(Number(s.kelembaban).toFixed(1));
            SENSOR_CHART_DATA.cahaya[len - 1] = Math.round(Number(s.cahaya));
            SENSOR_CHART_DATA.cahaya_scaled[len - 1] = Number((Number(s.cahaya) / CAHAYA_SCALE).toFixed(1));
            SENSOR_CHART_DATA.level_air[len - 1] = Math.round(Number(s.level_air));
            sensorChart.data.datasets = createDatasets(currentChartTab);
            sensorChart.update("none");
          }
        }
      }

      /* ================= Aktuator ================= */
      const actuatorLabels = {
        kipas: ["Mati", "Menyala"],
        lampu: ["Mati", "Menyala"],
        motor_pakan: ["Mati", "Jadwal Aktif"],
      };

      function updateActuatorsUI(actuators) {
        if (!actuators) return;
        Object.keys(actuatorLabels).forEach(key => {
          const state = Number(actuators[key]) ? 1 : 0;
          const toggle = document.getElementById(`toggle-${key}`);
          const label = document.getElementById(`state-${key}`);
          if (toggle && document.activeElement !== toggle) toggle.checked = state === 1;
          if (label) label.textContent = actuatorLabels[key][state];
        });
      }

      /* ================= Polling real-time ================= */
      let lastSensorId = <?= json_encode((int) ($latestSensor['id'] ?? 0)) ?>;
      let isPolling = false;

      async function refreshDashboard(simulate = true) {
        if (isPolling) return;
        isPolling = true;
        try {
          const res = await fetch(`../proses/ambil_data_dashboard.php?simulate=${simulate ? 1 : 0}`, {
            headers: { "Accept": "application/json" }
          });
          if (!res.ok) return;
          const data = await res.json();
          if (!data.success) return;

          updateMetrics(data.sensor);
          updateActuatorsUI(data.actuators);
          renderLogs(data.logs);
          renderNotifs(data.notifications);
          setUnread(data.unread_count);
          updateStatusKandang(data.status_kandang);
          lastSensorId = data.sensor.id || lastSensorId;
        } catch (err) {
          // koneksi gagal: biarkan data terakhir
          console.error("Polling gagal:", err);
        } finally {
          isPolling = false;
        }
      }

      // Polling pertama (menyuntik satu pembacaan jika data sudah lama), lalu tiap 30 detik
      setTimeout(() => refreshDashboard(true), 2500);
      setInterval(() => refreshDashboard(true), 30000);

      /* ================= Aktuator: toggle AJAX ================= */
      document.querySelectorAll("input[data-actuator]").forEach(toggle => {
        toggle.addEventListener("change", async () => {
          const act = toggle.getAttribute("data-actuator");
          const isChecked = toggle.checked;
          const stateEl = document.getElementById(`state-${act}`);

          try {
            const res = await fetch("../proses/toggle_aktuator.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ actuator: act, value: isChecked ? 1 : 0 })
            });
            const data = await res.json();
            if (data.success) {
              if (stateEl) stateEl.textContent = data.status_text;
              showToast(data.message || `${act} diperbarui.`, "success");
              refreshDashboard(false);
            } else {
              toggle.checked = !isChecked;
              showToast("Gagal memperbarui aktuator.", "warning");
            }
          } catch (err) {
            console.error(err);
            toggle.checked = !isChecked;
            showToast("Terjadi kesalahan koneksi.", "warning");
          }
        });
      });

      /* ================= Jadwal pakan ================= */
      document.querySelectorAll(".js-toggle-feed-schedule").forEach(toggle => {
        toggle.addEventListener("change", async () => {
          const id = toggle.getAttribute("data-id");
          const status = toggle.checked ? 1 : 0;
          try {
            const res = await fetch("../proses/toggle_jadwal.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ id: id, status: status })
            });
            const data = await res.json();
            if (data.success) {
              showToast(data.message || "Jadwal diperbarui.", "success");
            } else {
              toggle.checked = !status;
              showToast("Gagal memperbarui status jadwal.", "warning");
            }
          } catch (err) {
            console.error(err);
            toggle.checked = !status;
            showToast("Terjadi kesalahan jaringan.", "warning");
          }
        });
      });

      // Modal: Atur Jadwal Open & Controls
      const modalJadwalEl = document.getElementById("modalAturJadwal");
      const modalJadwal = modalJadwalEl ? new bootstrap.Modal(modalJadwalEl) : null;
      const dashCatatan = document.getElementById("dashCatatan");
      const dashWaktu = document.getElementById("dashWaktu");
      const dashAllDays = document.getElementById("dashAllDays");
      const dashDayCheckboxes = document.querySelectorAll(".dash-day-checkbox");
      const dashResetDaysBtn = document.getElementById("dashResetDaysBtn");

      function clearDashScheduleForm() {
        if (dashCatatan) dashCatatan.value = "";
        if (dashWaktu) dashWaktu.value = "08:00";
        if (dashAllDays) dashAllDays.checked = true;
        dashDayCheckboxes.forEach((cb) => (cb.checked = true));
      }

      if (dashAllDays) {
        dashAllDays.addEventListener("change", () => {
          dashDayCheckboxes.forEach((cb) => {
            cb.checked = dashAllDays.checked;
          });
        });
      }

      dashDayCheckboxes.forEach((cb) => {
        cb.addEventListener("change", () => {
          const allChecked = Array.from(dashDayCheckboxes).every((item) => item.checked);
          if (dashAllDays) {
            dashAllDays.checked = allChecked;
          }
        });
      });

      if (dashResetDaysBtn) {
        dashResetDaysBtn.addEventListener("click", () => {
          if (dashAllDays) dashAllDays.checked = true;
          dashDayCheckboxes.forEach((cb) => (cb.checked = true));
        });
      }

      document.querySelectorAll(".js-open-schedule-modal").forEach(btn => {
        btn.addEventListener("click", () => {
          clearDashScheduleForm();
          if (modalJadwal) modalJadwal.show();
        });
      });

      // Submit Form Atur Jadwal
      const formAturJadwal = document.getElementById("formAturJadwal");
      if (formAturJadwal) {
        formAturJadwal.addEventListener("submit", async (e) => {
          e.preventDefault();
          const formData = new FormData(formAturJadwal);
          const submitBtn = document.getElementById("btnSimpanJadwal");
          if (submitBtn) submitBtn.disabled = true;

          try {
            const res = await fetch("../proses/tambah_jadwal_cepat.php", {
              method: "POST",
              body: formData
            });
            const data = await res.json();
            if (data.success) {
              if (modalJadwal) modalJadwal.hide();
              showToast("Jadwal pakan berhasil ditambahkan!", "success");
              const container = document.getElementById("scheduleListContainer");
              if (container) {
                const noNotice = document.getElementById("noScheduleNotice");
                if (noNotice) noNotice.remove();

                const newRow = document.createElement("div");
                newRow.className = "schedule-row d-flex align-items-center justify-content-between";
                newRow.setAttribute("data-schedule-id", data.id);
                newRow.innerHTML = `
                  <div>
                    <div class="schedule-label">${escapeHtml(data.catatan || formData.get("catatan") || "Pakan")}</div>
                    <div class="schedule-time">${data.waktu} WIB</div>
                  </div>
                  <label class="switch-ios">
                    <input type="checkbox" class="js-toggle-feed-schedule" data-id="${data.id}" checked>
                    <span class="slider round"></span>
                  </label>
                `;
                container.appendChild(newRow);
                newRow.querySelector(".js-toggle-feed-schedule").addEventListener("change", (evt) => {
                  const t = evt.target;
                  fetch("../proses/toggle_jadwal.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ id: t.getAttribute("data-id"), status: t.checked ? 1 : 0 })
                  }).catch(() => {});
                });
              }
            } else {
              showToast(data.message || "Gagal menyimpan jadwal.", "warning");
            }
          } catch (err) {
            console.error(err);
            showToast("Terjadi kendala saat mengirim jadwal.", "warning");
          } finally {
            if (submitBtn) submitBtn.disabled = false;
          }
        });
      }

      /* ================= Pengaturan Batas Normal ================= */
      const modalPengaturanEl = document.getElementById("modalPengaturan");
      const modalPengaturan = modalPengaturanEl ? new bootstrap.Modal(modalPengaturanEl) : null;

      document.querySelectorAll(".js-open-pengaturan").forEach(btn => {
        btn.addEventListener("click", () => {
          if (modalPengaturan) modalPengaturan.show();
        });
      });

      // Sidebar "Pengaturan" → scroll + buka modal
      if (window.location.hash === "#pengaturan-section") {
        setTimeout(() => {
          const target = document.getElementById("pengaturan-section");
          if (target) target.scrollIntoView({ behavior: "smooth", block: "center" });
          if (modalPengaturan) modalPengaturan.show();
        }, 400);
      }

      const formPengaturan = document.getElementById("formPengaturan");
      if (formPengaturan) {
        formPengaturan.addEventListener("submit", async (e) => {
          e.preventDefault();
          const values = {};
          formPengaturan.querySelectorAll("input").forEach(input => {
            values[input.name] = input.value;
          });
          try {
            const res = await fetch("../proses/simpan_pengaturan.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(values)
            });
            const data = await res.json();
            if (data.success) {
              if (modalPengaturan) modalPengaturan.hide();
              showToast(data.message || "Pengaturan disimpan. Memuat ulang...", "success");
              setTimeout(() => window.location.reload(), 900);
            } else {
              showToast(data.message || "Gagal menyimpan pengaturan.", "warning");
            }
          } catch (err) {
            console.error(err);
            showToast("Terjadi kendala saat menyimpan.", "warning");
          }
        });
      }
    });
  </script>
</body>
</html>

