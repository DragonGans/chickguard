<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['login'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/koneksi.php';
require_once 'init_tables.php';
chickguard_init_tables($koneksi);

$raw = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$nama = trim($raw['nama_kandang'] ?? 'Kandang 1 - Utama');
$tglMasuk = trim($raw['tanggal_masuk_ayam'] ?? '');
$suhuMin = (float) ($raw['suhu_min'] ?? 24.0);
$suhuMaks = (float) ($raw['suhu_maks'] ?? 34.0);
$lembapMin = (float) ($raw['kelembaban_min'] ?? 55.0);
$lembapMaks = (float) ($raw['kelembaban_maks'] ?? 75.0);
$cahayaMin = (float) ($raw['cahaya_min'] ?? 500.0);
$airMin = (float) ($raw['level_air_min'] ?? 50.0);

if ($nama === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nama kandang wajib diisi']);
    exit;
}

if ($tglMasuk === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglMasuk)) {
    $tglMasuk = date('Y-m-d', strtotime('-18 days'));
}

if ($suhuMin >= $suhuMaks) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Suhu minimum harus lebih kecil dari suhu maksimum']);
    exit;
}

if ($lembapMin >= $lembapMaks) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Kelembaban minimum harus lebih kecil dari maksimum']);
    exit;
}

$cek = mysqli_query($koneksi, "SELECT id FROM pengaturan_kandang WHERE id = 1 LIMIT 1");

if ($cek && mysqli_num_rows($cek) > 0) {
    $stmt = mysqli_prepare(
        $koneksi,
        "UPDATE pengaturan_kandang
         SET nama_kandang = ?, tanggal_masuk_ayam = ?, suhu_min = ?, suhu_maks = ?, kelembaban_min = ?, kelembaban_maks = ?, cahaya_min = ?, level_air_min = ?
         WHERE id = 1"
    );
    mysqli_stmt_bind_param($stmt, "ssdddddd", $nama, $tglMasuk, $suhuMin, $suhuMaks, $lembapMin, $lembapMaks, $cahayaMin, $airMin);
} else {
    $stmt = mysqli_prepare(
        $koneksi,
        "INSERT INTO pengaturan_kandang (id, nama_kandang, tanggal_masuk_ayam, suhu_min, suhu_maks, kelembaban_min, kelembaban_maks, cahaya_min, level_air_min)
         VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "ssdddddd", $nama, $tglMasuk, $suhuMin, $suhuMaks, $lembapMin, $lembapMaks, $cahayaMin, $airMin);
}

$success = mysqli_stmt_execute($stmt);

if (!$success) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pengaturan: ' . mysqli_error($koneksi)]);
    exit;
}

// Hitung umur ayam terbaru
$tglMasukTs = strtotime($tglMasuk);
$umurAyamHari = max(0, (int) round((strtotime('today') - $tglMasukTs) / 86400));

echo json_encode([
    'success' => true,
    'message' => 'Pengaturan kandang & tanggal masuk ayam berhasil disimpan',
    'settings' => [
        'nama_kandang' => $nama,
        'tanggal_masuk_ayam' => $tglMasuk,
        'umur_ayam_hari' => $umurAyamHari,
        'suhu_min' => $suhuMin,
        'suhu_maks' => $suhuMaks,
        'kelembaban_min' => $lembapMin,
        'kelembaban_maks' => $lembapMaks,
        'cahaya_min' => $cahayaMin,
        'level_air_min' => $airMin,
    ],
]);