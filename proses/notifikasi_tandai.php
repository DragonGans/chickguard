<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['login'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/koneksi.php';

$raw = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Tandai satu ID, atau semua(=true)
$semua = !empty($raw['semua']);
$id = isset($raw['id']) ? (int) $raw['id'] : 0;

if ($semua) {
    $stmt = mysqli_prepare($koneksi, "UPDATE notifikasi SET is_read = 1 WHERE is_read = 0");
    mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
} elseif ($id > 0) {
    $stmt = mysqli_prepare($koneksi, "UPDATE notifikasi SET is_read = 1 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
    exit;
}

$cQuery = mysqli_query($koneksi, "SELECT COUNT(*) AS unread FROM notifikasi WHERE is_read = 0");
$cRow = $cQuery ? mysqli_fetch_assoc($cQuery) : null;
$unread = (int) ($cRow['unread'] ?? 0);

echo json_encode([
    'success' => true,
    'affected' => max(0, (int) $affected),
    'unread_count' => $unread,
    'message' => 'Notifikasi ditandai telah dibaca',
]);
