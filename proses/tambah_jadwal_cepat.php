<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['login'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/koneksi.php';

$jenis = trim($_POST['jenis'] ?? 'Pakan');
$waktu = trim($_POST['waktu'] ?? '');
$jumlah = (float) ($_POST['jumlah'] ?? 2.5);
$hari = trim($_POST['hari'] ?? 'Semua Hari');
$catatan = trim($_POST['catatan'] ?? 'Pemberian pakan terjadwal');

if ($waktu === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Waktu wajib diisi']);
    exit;
}

// Ensure HH:MM:SS
if (strlen($waktu) === 5) {
    $waktu .= ':00';
}

$stmt = mysqli_prepare($koneksi, "INSERT INTO jadwal_pakan_minum (jenis, waktu, jumlah, hari, catatan, is_active) VALUES (?, ?, ?, ?, ?, 1)");
mysqli_stmt_bind_param($stmt, "ssdss", $jenis, $waktu, $jumlah, $hari, $catatan);
$success = mysqli_stmt_execute($stmt);

if ($success) {
    $newId = mysqli_insert_id($koneksi);
    echo json_encode([
        'success' => true,
        'id' => $newId,
        'waktu' => substr($waktu, 0, 5),
        'message' => 'Jadwal berhasil ditambahkan',
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ke database']);
}
