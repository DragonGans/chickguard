<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['login'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/koneksi.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

$id = isset($data['id']) ? (int) $data['id'] : 0;
$status = isset($data['status']) ? (int) $data['status'] : 1;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$stmt = mysqli_prepare($koneksi, "UPDATE jadwal_pakan SET is_active = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, "ii", $status, $id);
$success = mysqli_stmt_execute($stmt);

echo json_encode([
    'success' => $success,
    'id' => $id,
    'status' => $status,
    'message' => $status ? 'Jadwal diaktifkan' : 'Jadwal dinonaktifkan',
]);
