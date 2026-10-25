<?php
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit;
}

include '../config/koneksi.php';
include 'init_tables.php';
chickguard_init_tables($koneksi);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function redirect_pakan_minum(string $status): void
{
    header("Location: ../pages/pakan_minum.php?status=" . urlencode($status));
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        redirect_pakan_minum('invalid');
    }

    $stmt = mysqli_prepare($koneksi, "DELETE FROM jadwal_pakan_minum WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    redirect_pakan_minum('deleted');
}

if ($action === 'delete_selected') {
    $selectedSchedules = $_POST['selected_schedules'] ?? [];
    if (!is_array($selectedSchedules) || count($selectedSchedules) === 0) {
        redirect_pakan_minum('none_selected');
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $selectedSchedules), static function ($id) {
        return $id > 0;
    })));

    if (count($ids) === 0) {
        redirect_pakan_minum('none_selected');
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = mysqli_prepare($koneksi, "DELETE FROM jadwal_pakan_minum WHERE id IN ($placeholders)");
    mysqli_stmt_bind_param($stmt, $types, ...$ids);
    mysqli_stmt_execute($stmt);
    redirect_pakan_minum('deleted');
}

$id = (int) ($_POST['id'] ?? 0);
$jenis = 'Pakan';
$waktu = $_POST['waktu'] ?? '';
$jumlah = (float) ($_POST['jumlah'] ?? 0);
$catatan = trim($_POST['catatan'] ?? '');
$hariInput = $_POST['hari'] ?? [];
$hari = in_array('Semua Hari', $hariInput, true) ? 'Semua Hari' : implode(', ', $hariInput);

if ($waktu === '' || $hari === '') {
    redirect_pakan_minum('invalid');
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

if ($action === 'update' && $id > 0) {
    $stmt = mysqli_prepare($koneksi, "UPDATE jadwal_pakan_minum SET jenis=?, waktu=?, jumlah=?, hari=?, catatan=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "ssdssi", $jenis, $waktu, $jumlah, $hari, $catatan, $id);
    mysqli_stmt_execute($stmt);
    redirect_pakan_minum('updated');
}

$stmt = mysqli_prepare($koneksi, "INSERT INTO jadwal_pakan_minum (jenis, waktu, jumlah, hari, catatan) VALUES (?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "ssdss", $jenis, $waktu, $jumlah, $hari, $catatan);
mysqli_stmt_execute($stmt);
redirect_pakan_minum('created');
?>
