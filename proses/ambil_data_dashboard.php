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

/**
 * Simulasi push data sensor ala ESP32.
 * Membuat 1 baris data_sensor baru bila data terakhir sudah lebih lama
 * dari INTERVAL_SIMULASI detik (agar refresh halaman beruntun tidak membanjiri DB).
 */
const INTERVAL_SIMULASI = 600; // 10 menit

function cg_simulasikan_sensor(mysqli $koneksi): ?array
{
    $lastQuery = mysqli_query($koneksi, "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 1");
    $last = $lastQuery ? mysqli_fetch_assoc($lastQuery) : null;

    if ($last) {
        $umur = time() - strtotime($last['recorded_at']);
        if ($umur < INTERVAL_SIMULASI) {
            return null; // data masih segar
        }
    }

    $jam = (float) date('G') + ((int) date('i')) / 60;

    // Model lingkungan ideal per jam (mirip pola di grafik 24 jam)
    $idealSuhu = 24.8 + 8 * exp(-pow($jam - 13.5, 2) / 22);
    $idealLembap = max(50.0, min(85.0, 80 - ($idealSuhu - 24.8) * 1.6));
    if ($jam >= 24 || $jam < 5) {
        $idealCahaya = mt_rand(0, 12);
    } else {
        $idealCahaya = max(0, (int) (1800 * exp(-pow($jam - 12.5, 2) / 30)));
    }

    if ($last) {
        $suhu = max(20.0, min(40.0, (float) $last['suhu'] + (mt_rand(-30, 30) / 100)));
        $lembap = max(40.0, min(90.0, (float) $last['kelembaban'] + (mt_rand(-15, 15) / 10)));
        $cahaya = max(0.0, (float) $last['cahaya'] + (mt_rand(-150, 150)));
        // Dekatkan ke nilai ideal agar menarik ke target
        $suhu += ($idealSuhu - $suhu) * 0.15;
        $lembap += ($idealLembap - $lembap) * 0.15;
        $cahaya += ($idealCahaya - $cahaya) * 0.35;
    } else {
        $suhu = $idealSuhu;
        $lembap = $idealLembap;
        $cahaya = $idealCahaya;
    }

    // Level air menurun perlahan, dan terisi ulang saat jadwal pakan (06:00 & 16:00)
    $air = isset($last['level_air']) ? (float) $last['level_air'] : 85.0;
    $air -= mt_rand(5, 18) / 10;
    $menit = (int) date('G') * 60 + (int) date('i');
    if (abs($menit - 6 * 60) <= 5 || abs($menit - 16 * 60) <= 5) {
        $air += mt_rand(60, 120) / 10; // refill
    }
    $air = max(15.0, min(96.0, $air));

    $stmt = mysqli_prepare(
        $koneksi,
        "INSERT INTO data_sensor (suhu, kelembaban, cahaya, level_air, recorded_at) VALUES (?, ?, ?, ?, NOW())"
    );
    mysqli_stmt_bind_param($stmt, "dddd", $suhu, $lembap, $cahaya, $air);
    mysqli_stmt_execute($stmt);

    $fresh = mysqli_query($koneksi, "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 1");

    return $fresh ? mysqli_fetch_assoc($fresh) : null;
}

if (isset($_GET['simulate'])) {
    cg_simulasikan_sensor($koneksi);
}

// --- Pengaturan / batas normal ---------------------------------------------
$settingQuery = mysqli_query($koneksi, "SELECT * FROM pengaturan_kandang WHERE id = 1 LIMIT 1");
$settings = $settingQuery ? mysqli_fetch_assoc($settingQuery) : [
    'nama_kandang' => 'Kandang 1 - Utama',
    'suhu_min' => 24.0,
    'suhu_maks' => 34.0,
    'kelembaban_min' => 55.0,
    'kelembaban_maks' => 75.0,
    'cahaya_min' => 500,
    'level_air_min' => 50.0,
];

// --- Sensor terbaru ----------------------------------------------------------
$sensorQuery = mysqli_query($koneksi, "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 1");
$latestSensor = $sensorQuery ? mysqli_fetch_assoc($sensorQuery) : null;

if (!$latestSensor) {
    $latestSensor = [
        'id' => 0,
        'suhu' => 32.4,
        'kelembaban' => 68.2,
        'cahaya' => 1240,
        'level_air' => 72,
        'recorded_at' => date('Y-m-d H:i:s'),
    ];
}

// --- Aktuator ----------------------------------------------------------------
$actQuery = mysqli_query($koneksi, "SELECT * FROM actuator_status WHERE id = 1 LIMIT 1");
$actuators = $actQuery ? mysqli_fetch_assoc($actQuery) : [
    'kipas' => 0,
    'heater' => 0,
    'lampu' => 0,
    'motor_pakan' => 1,
];

// --- Log terbaru (5) ----------------------------------------------------------
$logsQuery = mysqli_query($koneksi, "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 5");
$recentLogs = [];
if ($logsQuery) {
    while ($row = mysqli_fetch_assoc($logsQuery)) {
        $recentLogs[] = [
            'id' => (int) $row['id'],
            'waktu' => date('H:i', strtotime($row['recorded_at'] ?? 'now')),
            'suhu' => (float) $row['suhu'],
            'kelembaban' => (float) $row['kelembaban'],
            'cahaya' => (float) $row['cahaya'],
            'level_air' => (float) $row['level_air'],
        ];
    }
}

// --- Notifikasi ---------------------------------------------------------------
$notifQuery = mysqli_query($koneksi, "SELECT * FROM notifikasi ORDER BY id DESC LIMIT 8");
$notifs = [];
if ($notifQuery) {
    while ($row = mysqli_fetch_assoc($notifQuery)) {
        $notifs[] = [
            'id' => (int) $row['id'],
            'pesan' => $row['pesan'],
            'tipe' => $row['tipe'],
            'waktu_label' => $row['waktu_label'],
        ];
    }
}

$unreadQuery = mysqli_query($koneksi, "SELECT COUNT(*) AS unread FROM notifikasi WHERE is_read = 0");
$unreadRow = $unreadQuery ? mysqli_fetch_assoc($unreadQuery) : null;
$unreadCount = (int) ($unreadRow['unread'] ?? 0);

// --- Status kandang -----------------------------------------------------------
$statusKandang = cg_status_kandang($latestSensor, $settings);

echo json_encode([
    'success' => true,
    'sensor' => [
        'id' => (int) ($latestSensor['id'] ?? 0),
        'suhu' => (float) $latestSensor['suhu'],
        'kelembaban' => (float) $latestSensor['kelembaban'],
        'cahaya' => (float) $latestSensor['cahaya'],
        'level_air' => (float) $latestSensor['level_air'],
        'recorded_at' => $latestSensor['recorded_at'],
    ],
    'actuators' => $actuators,
    'logs' => $recentLogs,
    'notifications' => $notifs,
    'unread_count' => $unreadCount,
    'status_kandang' => $statusKandang,
]);
