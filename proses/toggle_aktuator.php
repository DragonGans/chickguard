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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

$actuator = trim($data['actuator'] ?? '');
$value = isset($data['value']) ? (int) $data['value'] : null;

// Batch toggle all
if ($actuator === 'all') {
    $val = ($value === 1) ? 1 : 0;
    $stmt = mysqli_prepare($koneksi, "UPDATE actuator_status SET kipas = ?, heater = ?, lampu = ?, motor_pakan = ? WHERE id = 1");
    mysqli_stmt_bind_param($stmt, "iiii", $val, $val, $val, $val);
    $executed = mysqli_stmt_execute($stmt);

    if ($executed) {
        $actionMsg = $val ? 'Semua aktuator dinyalakan secara manual' : 'Semua aktuator berhasil dimatikan';
        $timeLabel = waktu_notifikasi();
        $notifStmt = mysqli_prepare($koneksi, "INSERT INTO notifikasi (pesan, tipe, waktu_label) VALUES (?, 'info', ?)");
        mysqli_stmt_bind_param($notifStmt, "ss", $actionMsg, $timeLabel);
        mysqli_stmt_execute($notifStmt);

        echo json_encode([
            'success' => true,
            'actuator' => 'all',
            'value' => $val,
            'message' => $actionMsg,
            'actuators' => [
                'kipas' => $val,
                'heater' => $val,
                'lampu' => $val,
                'motor_pakan' => $val,
            ]
        ]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status aktuator.']);
        exit;
    }
}

// Mode otomatis: sinkronkan aktuator dengan batas sensor mikroklimat
if ($actuator === 'auto') {
    $sq = mysqli_query($koneksi, "SELECT * FROM pengaturan_kandang WHERE id = 1 LIMIT 1");
    $settings = $sq ? mysqli_fetch_assoc($sq) : [
        'suhu_min' => 24.0,
        'suhu_maks' => 34.0,
        'cahaya_min' => 500
    ];

    $sqSensor = mysqli_query($koneksi, "SELECT * FROM data_sensor ORDER BY id DESC LIMIT 1");
    $sensor = $sqSensor ? mysqli_fetch_assoc($sqSensor) : null;
    $suhu = $sensor ? (float)$sensor['suhu'] : 30.0;
    $cahaya = $sensor ? (float)$sensor['cahaya'] : 1000;

    $kipas = ($suhu > (float)$settings['suhu_maks']) ? 1 : 0;
    $heater = ($suhu < (float)$settings['suhu_min']) ? 1 : 0;
    $lampu = ($cahaya < (float)$settings['cahaya_min']) ? 1 : 0;
    $motor = 0;

    $stmt = mysqli_prepare($koneksi, "UPDATE actuator_status SET kipas = ?, heater = ?, lampu = ?, motor_pakan = ? WHERE id = 1");
    mysqli_stmt_bind_param($stmt, "iiii", $kipas, $heater, $lampu, $motor);
    $executed = mysqli_stmt_execute($stmt);

    if ($executed) {
        $actionMsg = 'Aktuator disinkronkan kembali ke mode otomasi mikroklimat';
        $timeLabel = waktu_notifikasi();
        $notifStmt = mysqli_prepare($koneksi, "INSERT INTO notifikasi (pesan, tipe, waktu_label) VALUES (?, 'info', ?)");
        mysqli_stmt_bind_param($notifStmt, "ss", $actionMsg, $timeLabel);
        mysqli_stmt_execute($notifStmt);

        echo json_encode([
            'success' => true,
            'actuator' => 'auto',
            'message' => $actionMsg,
            'actuators' => [
                'kipas' => $kipas,
                'heater' => $heater,
                'lampu' => $lampu,
                'motor_pakan' => $motor,
            ]
        ]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyinkronkan mode otomasi.']);
        exit;
    }
}

$allowed = ['kipas', 'heater', 'lampu', 'motor_pakan'];
if (!in_array($actuator, $allowed, true) || ($value !== 0 && $value !== 1)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$stmt = mysqli_prepare($koneksi, "UPDATE actuator_status SET $actuator = ? WHERE id = 1");
mysqli_stmt_bind_param($stmt, "i", $value);
$executed = mysqli_stmt_execute($stmt);

if ($executed) {
    $labels = [
        'kipas' => $value ? 'Menyala' : 'Mati',
        'heater' => $value ? 'Menyala' : 'Mati',
        'lampu' => $value ? 'Menyala' : 'Mati',
        'motor_pakan' => $value ? 'Menyala' : 'Mati',
    ];

    $actuatorNames = [
        'kipas' => 'Kipas Ventilasi',
        'heater' => 'Heater Pemanas',
        'lampu' => 'Lampu Penerangan',
        'motor_pakan' => 'Motor Pakan',
    ];
    if ($actuator === 'motor_pakan' && $value === 1) {
        $feedLogStmt = mysqli_prepare($koneksi, "INSERT INTO log_pemberian_pakan (waktu, metode, status, keterangan) VALUES (NOW(), 'Manual', 'Berhasil', 'Pemberian pakan manual via dispenser')");
        if ($feedLogStmt) {
            mysqli_stmt_execute($feedLogStmt);
        }
    }

    $actionMsg = $actuatorNames[$actuator] . ' diubah ke ' . $labels[$actuator];
    $timeLabel = waktu_notifikasi();
    $notifStmt = mysqli_prepare($koneksi, "INSERT INTO notifikasi (pesan, tipe, waktu_label) VALUES (?, 'info', ?)");
    mysqli_stmt_bind_param($notifStmt, "ss", $actionMsg, $timeLabel);
    mysqli_stmt_execute($notifStmt);

    echo json_encode([
        'success' => true,
        'actuator' => $actuator,
        'value' => $value,
        'status_text' => $labels[$actuator],
        'message' => $actionMsg,
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}