<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['login'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi login telah berakhir. Silakan login kembali.']);
    exit;
}

require_once '../config/koneksi.php';
require_once 'init_tables.php';
chickguard_init_tables($koneksi);

$raw = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = trim($raw['action'] ?? 'profil');
$currentUsername = $_SESSION['username'] ?? 'admin';

// Ambil data user yang sedang login
$stmt = mysqli_prepare($koneksi, "SELECT * FROM users WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $currentUsername);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$currentUser = mysqli_fetch_assoc($res);

if (!$currentUser) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan dalam sistem.']);
    exit;
}

$userId = (int) $currentUser['id'];

if ($action === 'profil') {
    $username = trim($raw['username'] ?? '');
    $email = trim($raw['email'] ?? '');

    if ($username === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username wajib diisi.']);
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username hanya boleh huruf, angka, dan underscore (3-30 karakter).']);
        exit;
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Format alamat email tidak valid.']);
        exit;
    }

    // Cek duplikasi username (jika diubah)
    if ($username !== $currentUser['username']) {
        $cStmt = mysqli_prepare($koneksi, "SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($cStmt, "si", $username, $userId);
        mysqli_stmt_execute($cStmt);
        $cRes = mysqli_stmt_get_result($cStmt);
        if (mysqli_num_rows($cRes) > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username sudah digunakan oleh akun lain.']);
            exit;
        }
    }

    // Cek duplikasi email (jika diubah)
    if ($email !== $currentUser['email']) {
        $cStmt = mysqli_prepare($koneksi, "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($cStmt, "si", $email, $userId);
        mysqli_stmt_execute($cStmt);
        $cRes = mysqli_stmt_get_result($cStmt);
        if (mysqli_num_rows($cRes) > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Alamat email sudah digunakan oleh akun lain.']);
            exit;
        }
    }

    // Update profil akun (hanya Username dan Email)
    $uStmt = mysqli_prepare($koneksi, "UPDATE users SET username = ?, email = ? WHERE id = ?");
    mysqli_stmt_bind_param($uStmt, "ssi", $username, $email, $userId);
    $ok = mysqli_stmt_execute($uStmt);

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui profil: ' . mysqli_error($koneksi)]);
        exit;
    }

    // Perbarui session
    $_SESSION['username'] = $username;

    echo json_encode([
        'success' => true,
        'message' => 'Pengaturan user (Username & Email) berhasil diperbarui!',
        'user' => [
            'username' => $username,
            'email' => $email,
        ],
    ]);
    exit;

} elseif ($action === 'password') {
    $passLama = (string) ($raw['password_lama'] ?? '');
    $passBaru = (string) ($raw['password_baru'] ?? '');
    $passKonf = (string) ($raw['konfirmasi_password'] ?? '');

    if ($passLama === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password saat ini wajib diisi.']);
        exit;
    }

    if (!password_verify($passLama, $currentUser['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password saat ini tidak sesuai.']);
        exit;
    }

    if (strlen($passBaru) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password baru minimal harus 6 karakter.']);
        exit;
    }

    if ($passBaru !== $passKonf) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Konfirmasi password baru tidak cocok.']);
        exit;
    }

    $hashBaru = password_hash($passBaru, PASSWORD_DEFAULT);
    $uStmt = mysqli_prepare($koneksi, "UPDATE users SET password = ? WHERE id = ?");
    mysqli_stmt_bind_param($uStmt, "si", $hashBaru, $userId);
    $ok = mysqli_stmt_execute($uStmt);

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui password: ' . mysqli_error($koneksi)]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Password berhasil diubah! Gunakan password baru Anda pada login berikutnya.',
    ]);
    exit;

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
    exit;
}