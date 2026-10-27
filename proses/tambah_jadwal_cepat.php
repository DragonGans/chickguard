<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['login'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/koneksi.php';

$waktu = trim($_POST['waktu'] ?? '');
$catatan = trim($_POST['catatan'] ?? '');
$jumlah = (float) ($_POST['jumlah'] ?? 2.5);
if ($jumlah <= 0) {
    $jumlah = 2.5;
}

$hariInput = $_POST['hari'] ?? ['Semua Hari'];
if (is_array($hariInput)) {
    $hari = in_array('Semua Hari', $hariInput, true) ? 'Semua Hari' : implode(', ', $hariInput);
} else {
    $hari = trim((string) $hariInput);
}
if ($hari === '') {
    $hari = 'Semua Hari';
}

if ($waktu === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Waktu wajib diisi']);
    exit;
}

if ($catatan === '') {
    $hour = (int) substr($waktu, 0, 2);
    if ($hour < 11) {
        $catatan = 'Pakan Pagi';
    } elseif ($hour < 15) {
        $catatan = 'Pakan Siang';
    } else {
        $catatan = 'Pakan Sore';
    }
}

// Ensure HH:MM:SS
if (strlen($waktu) === 5) {
    $waktu .= ':00';
}

$stmt = mysqli_prepare($koneksi, "INSERT INTO jadwal_pakan (waktu, jumlah, hari, catatan, is_active) VALUES (?, ?, ?, ?, 1)");
mysqli_stmt_bind_param($stmt, "sdss", $waktu, $jumlah, $hari, $catatan);
$success = mysqli_stmt_execute($stmt);

if ($success) {
    $newId = mysqli_insert_id($koneksi);
    echo json_encode([
        'success' => true,
        'id' => $newId,
        'catatan' => $catatan,
        'waktu' => substr($waktu, 0, 5),
        'hari' => $hari,
        'message' => 'Jadwal berhasil ditambahkan',
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ke database']);
}