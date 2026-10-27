<?php
session_start();
$_SESSION['login'] = true;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';
$to = $_GET['to'] ?? 'pages/dashboard.php';
header("Location: $to");
exit;
