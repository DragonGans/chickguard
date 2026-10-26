-- phpMyAdmin SQL Dump
-- version 6.0.0-dev+20261025.2907d1fa8c
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 26, 2026 at 08:49 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `chickguard`
--

-- --------------------------------------------------------

--
-- Table structure for table `actuator_status`
--
CREATE TABLE `actuator_status` (
  `id` int NOT NULL,
  `kipas` tinyint(1) NOT NULL DEFAULT '0',
  `heater` tinyint(1) NOT NULL DEFAULT '0',
  `lampu` tinyint(1) NOT NULL DEFAULT '0',
  `motor_pakan` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `actuator_status`
--

INSERT INTO `actuator_status` (`id`, `kipas`, `heater`, `lampu`, `motor_pakan`, `updated_at`) VALUES
(1, 0, 0, 0, 0, '2026-10-26 07:27:08');

-- --------------------------------------------------------

--
-- Table structure for table `data_sensor`
--
CREATE TABLE `data_sensor` (
  `id` int NOT NULL,
  `suhu` decimal(5,2) NOT NULL,
  `kelembaban` decimal(5,2) NOT NULL,
  `cahaya` decimal(8,2) NOT NULL DEFAULT '1240.00',
  `level_air` decimal(5,2) NOT NULL DEFAULT '72.00',
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `data_sensor`
--

INSERT INTO `data_sensor` (`id`, `suhu`, `kelembaban`, `cahaya`, `level_air`, `recorded_at`) VALUES
(1, 32.60, 68.90, 1260.00, 73.00, '2026-10-10 08:00:00'),
(2, 32.30, 67.10, 1210.00, 72.00, '2026-10-10 08:10:00'),
(3, 31.90, 66.50, 1120.00, 71.00, '2026-10-10 08:20:00'),
(4, 32.10, 67.80, 1180.00, 72.00, '2026-10-10 08:30:00'),
(5, 32.40, 68.20, 1240.00, 72.00, '2026-10-10 08:40:00'),
(6, 31.72, 68.38, 1361.15, 70.80, '2026-10-22 07:56:34'),
(7, 31.35, 69.11, 1411.75, 69.90, '2026-10-25 08:45:46'),
(8, 30.29, 70.23, 951.34, 68.30, '2026-10-26 01:33:29');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_pakan_minum`
--
CREATE TABLE `jadwal_pakan_minum` (
  `id` int NOT NULL,
  `jenis` enum('Pakan','Minum') COLLATE utf8mb4_general_ci NOT NULL,
  `waktu` time NOT NULL,
  `jumlah` decimal(8,2) NOT NULL,
  `hari` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `catatan` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jadwal_pakan_minum`
--

INSERT INTO `jadwal_pakan_minum` (`id`, `jenis`, `waktu`, `jumlah`, `hari`, `catatan`, `is_active`, `created_at`) VALUES
(101, 'Pakan', '06:00:00', 2.50, 'Semua Hari', 'Pakan 1', 1, '2026-10-22 07:46:48'),
(102, 'Pakan', '16:00:00', 2.50, 'Semua Hari', 'Pakan 2', 1, '2026-10-22 07:46:48');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_pencahayaan`
--
CREATE TABLE `jadwal_pencahayaan` (
  `id` int NOT NULL,
  `jenis` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `waktu` time NOT NULL,
  `durasi` decimal(8,2) NOT NULL,
  `hari` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `catatan` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jadwal_pencahayaan`
--

INSERT INTO `jadwal_pencahayaan` (`id`, `jenis`, `waktu`, `durasi`, `hari`, `catatan`, `created_at`) VALUES
(5, 'Lampu Utama', '19:00:00', 2.80, 'Sabtu, Minggu', 'Pencahayaan malam untuk akhir pekan', '2026-05-07 07:05:59'),
(9, 'aaaaaaa', '12:32:00', 12.00, 'Selasa, Sabtu', '212121212', '2026-05-17 02:24:32'),
(10, 'aaaaaaa', '08:00:00', 32.00, 'Semua Hari', '123', '2026-05-18 01:39:07'),
(11, '12121212', '12:00:00', 12.00, 'Semua Hari', '', '2026-06-03 05:53:18');

-- --------------------------------------------------------

--
-- Table structure for table `log_harian`
--
CREATE TABLE `log_harian` (
  `id` int NOT NULL,
  `waktu` time NOT NULL,
  `suhu` decimal(5,2) NOT NULL,
  `kelembaban` decimal(5,2) NOT NULL,
  `cahaya` decimal(8,2) DEFAULT '1240.00',
  `level_air` decimal(5,2) DEFAULT '72.00',
  `pakan` decimal(8,2) UNSIGNED DEFAULT NULL,
  `minum` decimal(8,2) UNSIGNED DEFAULT NULL,
  `lampu` enum('Hidup','Mati') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Mati',
  `is_checked` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Kolom 11` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `log_harian`
--

INSERT INTO `log_harian` (`id`, `waktu`, `suhu`, `kelembaban`, `cahaya`, `level_air`, `pakan`, `minum`, `lampu`, `is_checked`, `created_at`, `Kolom 11`) VALUES
(121, '05:00:00', 24.00, 71.00, 270.00, 72.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(122, '06:00:00', 25.00, 70.00, 201.00, 76.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(123, '07:00:00', 26.00, 68.00, 805.00, 72.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(124, '08:00:00', 27.00, 66.00, 788.00, 69.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(125, '09:00:00', 28.00, 64.00, 1156.00, 77.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(126, '10:00:00', 29.00, 63.00, 1452.00, 78.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(127, '11:00:00', 30.00, 61.00, 1417.00, 84.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(128, '12:00:00', 30.00, 60.00, 1832.00, 83.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(129, '13:00:00', 31.00, 59.00, 1380.00, 83.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(130, '14:00:00', 31.00, 58.00, 1784.00, 70.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(131, '15:00:00', 30.00, 60.00, 1035.00, 76.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(132, '16:00:00', 29.00, 62.00, 823.00, 75.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(133, '17:00:00', 28.00, 64.00, 1119.00, 72.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(134, '18:00:00', 27.00, 66.00, 342.00, 74.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(135, '19:00:00', 27.00, 67.00, 156.00, 83.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(136, '20:00:00', 26.00, 68.00, 0.00, 75.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(137, '21:00:00', 26.00, 69.00, 0.00, 72.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(138, '22:00:00', 25.00, 70.00, 0.00, 81.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(139, '23:00:00', 25.00, 71.00, 0.00, 84.00, 5.00, 0.00, 'Hidup', 0, '2026-05-19 03:01:53', NULL),
(140, '06:43:47', 28.70, 61.60, 378.00, 86.00, 5.00, 0.00, 'Hidup', 0, '2026-06-02 04:43:47', NULL),
(141, '06:16:33', 26.80, 73.80, 323.00, 72.00, 5.00, 0.00, 'Hidup', 0, '2026-06-03 04:16:33', NULL),
(142, '13:19:56', 31.00, 73.20, 1373.00, 69.00, 5.00, 0.00, 'Hidup', 0, '2026-09-04 11:19:56', NULL),
(143, '01:08:07', 31.20, 62.20, 0.00, 70.00, 5.00, 0.00, 'Hidup', 0, '2026-09-28 23:08:07', NULL),
(144, '07:03:31', 29.80, 60.00, 1038.00, 69.00, 5.00, 0.00, 'Hidup', 0, '2026-10-22 05:03:31', NULL),
(145, '09:45:29', 27.10, 73.90, 1071.00, 81.00, 5.00, 0.00, 'Hidup', 0, '2026-10-25 08:45:29', NULL),
(146, '01:33:26', 29.50, 73.50, 1240.00, 72.00, 5.00, 0.00, 'Hidup', 0, '2026-10-26 01:33:26', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `log_pemberian_pakan`
--
CREATE TABLE `log_pemberian_pakan` (
  `id` int NOT NULL,
  `waktu` datetime NOT NULL,
  `metode` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Otomatis',
  `status` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Berhasil',
  `keterangan` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `log_pemberian_pakan`
--

INSERT INTO `log_pemberian_pakan` (`id`, `waktu`, `metode`, `status`, `keterangan`, `created_at`) VALUES
(1, '2026-10-25 16:00:00', 'Otomatis', 'Berhasil', 'Pemberian pakan sore sesuai jadwal', '2026-10-25 13:04:19'),
(2, '2026-10-25 12:00:00', 'Otomatis', 'Berhasil', 'Pemberian pakan siang sesuai jadwal', '2026-10-25 13:04:19'),
(3, '2026-10-25 08:30:00', 'Manual', 'Berhasil', 'Pemberian pakan manual via dispenser', '2026-10-25 13:04:19'),
(4, '2026-10-25 06:00:00', 'Otomatis', 'Berhasil', 'Pemberian pakan pagi sesuai jadwal', '2026-10-25 13:04:19'),
(5, '2026-10-24 16:00:00', 'Otomatis', 'Berhasil', 'Pemberian pakan sore sesuai jadwal', '2026-10-25 13:04:19'),
(6, '2026-10-24 12:00:00', 'Otomatis', 'Berhasil', 'Pemberian pakan siang sesuai jadwal', '2026-10-25 13:04:19'),
(7, '2026-10-24 06:00:00', 'Otomatis', 'Berhasil', 'Pemberian pakan pagi sesuai jadwal', '2026-10-25 13:04:19'),
(8, '2026-10-25 20:26:31', 'Manual', 'Berhasil', 'Pemberian pakan manual via dispenser', '2026-10-25 13:26:31'),
(9, '2026-10-26 14:21:33', 'Manual', 'Berhasil', 'Pemberian pakan manual via dispenser', '2026-10-26 07:21:33');

-- --------------------------------------------------------

--
-- Table structure for table `notifikasi`
--
CREATE TABLE `notifikasi` (
  `id` int NOT NULL,
  `pesan` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `tipe` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'success',
  `waktu_label` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifikasi`
--

INSERT INTO `notifikasi` (`id`, `pesan`, `tipe`, `waktu_label`, `is_read`, `created_at`) VALUES
(1, 'ESP32 terhubung', 'success', '08:07 - 10 Okt 2026', 1, '2026-10-22 07:56:48'),
(2, 'Suhu kembali normal', 'success', '12:15 - 10 Okt 2026', 1, '2026-10-22 07:56:48'),
(3, 'Level air mulai rendah (72%)', 'warning', '14:32 - 10 Okt 2026', 1, '2026-10-22 07:56:48'),
(4, 'Pakan diberikan otomatis', 'success', '16:00 - 10 Okt 2026', 1, '2026-10-22 07:56:48'),
(5, 'Kipas diubah ke Menyala', 'info', '09:46 - 25 Okt 2026', 1, '2026-10-25 08:46:29'),
(6, 'Kipas diubah ke Mati', 'info', '09:46 - 25 Okt 2026', 1, '2026-10-25 08:46:32'),
(7, 'Motor Pakan diubah ke Jadwal Aktif', 'info', '10:15 - 25 Okt 2026', 1, '2026-10-25 09:15:08'),
(8, 'Motor Pakan diubah ke Jadwal Aktif', 'info', '10:15 - 25 Okt 2026', 1, '2026-10-25 09:15:11'),
(9, 'Motor Pakan diubah ke Mati', 'info', '11:36 - 25 Okt 2026', 1, '2026-10-25 10:36:20'),
(10, 'Motor Pakan diubah ke Jadwal Aktif', 'info', '11:36 - 25 Okt 2026', 1, '2026-10-25 10:36:20'),
(11, 'Lampu diubah ke Menyala', 'info', '11:36 - 25 Okt 2026', 1, '2026-10-25 10:36:24'),
(12, 'Lampu diubah ke Mati', 'info', '11:36 - 25 Okt 2026', 1, '2026-10-25 10:36:25'),
(13, 'Lampu diubah ke Menyala', 'info', '11:36 - 25 Okt 2026', 1, '2026-10-25 10:36:25'),
(14, 'Lampu diubah ke Mati', 'info', '11:36 - 25 Okt 2026', 1, '2026-10-25 10:36:26'),
(15, 'Lampu diubah ke Menyala', 'info', '11:36 - 25 Okt 2026', 1, '2026-10-25 10:36:26'),
(16, 'Lampu diubah ke Mati', 'info', '11:36 - 25 Okt 2026', 1, '2026-10-25 10:36:27'),
(17, 'Lampu diubah ke Menyala', 'info', '11:36 - 25 Okt 2026', 1, '2026-10-25 10:36:27'),
(18, 'Lampu diubah ke Mati', 'info', '11:36 - 25 Okt 2026', 1, '2026-10-25 10:36:27'),
(19, 'Lampu diubah ke Menyala', 'info', '11:36 - 25 Okt 2026', 1, '2026-10-25 10:36:27'),
(20, 'Lampu diubah ke Mati', 'info', '11:36 - 25 Okt 2026', 1, '2026-10-25 10:36:27'),
(21, 'Lampu diubah ke Menyala', 'info', '11:36 - 25 Okt 2026', 1, '2026-10-25 10:36:27'),
(22, 'Lampu diubah ke Mati', 'info', '11:36 - 25 Okt 2026', 1, '2026-10-25 10:36:28'),
(23, 'Motor Pakan diubah ke Jadwal Aktif', 'info', '12:29 - 25 Okt 2026', 1, '2026-10-25 11:29:19'),
(24, 'Motor Pakan diubah ke Jadwal Aktif', 'info', '12:29 - 25 Okt 2026', 1, '2026-10-25 11:29:22'),
(25, 'Semua aktuator dinyalakan secara manual', 'info', '13:01 - 25 Okt 2026', 1, '2026-10-25 12:01:36'),
(26, 'Semua aktuator dinyalakan secara manual', 'info', '13:01 - 25 Okt 2026', 1, '2026-10-25 12:01:36'),
(27, 'Semua aktuator dinyalakan secara manual', 'info', '13:01 - 25 Okt 2026', 1, '2026-10-25 12:01:38'),
(28, 'Aktuator disinkronkan kembali ke mode otomasi mikroklimat', 'info', '13:01 - 25 Okt 2026', 1, '2026-10-25 12:01:40'),
(29, 'Aktuator disinkronkan kembali ke mode otomasi mikroklimat', 'info', '13:01 - 25 Okt 2026', 1, '2026-10-25 12:01:41'),
(30, 'Kipas Ventilasi diubah ke Mati', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:45'),
(31, 'Kipas Ventilasi diubah ke Mati', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:47'),
(32, 'Heater Pemanas diubah ke Mati', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:48'),
(33, 'Lampu Penerangan diubah ke Mati', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:49'),
(34, 'Motor Pakan diubah ke Mati', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:50'),
(35, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:53'),
(36, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:54'),
(37, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:54'),
(38, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:54'),
(39, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:55'),
(40, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:55'),
(41, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:55'),
(42, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:55'),
(43, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:55'),
(44, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:56'),
(45, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:56'),
(46, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:56'),
(47, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:56'),
(48, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:56'),
(49, 'Semua aktuator dinyalakan secara manual', 'info', '13:02 - 25 Okt 2026', 1, '2026-10-25 12:02:56'),
(50, 'Semua aktuator dinyalakan secara manual', 'info', '13:03 - 25 Okt 2026', 1, '2026-10-25 12:03:00'),
(51, 'Kipas Ventilasi diubah ke Menyala', 'info', '13:03 - 25 Okt 2026', 1, '2026-10-25 12:03:02'),
(52, 'Heater Pemanas diubah ke Mati', 'info', '13:03 - 25 Okt 2026', 1, '2026-10-25 12:03:02'),
(53, 'Kipas Ventilasi diubah ke Menyala', 'info', '13:03 - 25 Okt 2026', 1, '2026-10-25 12:03:04'),
(54, 'Heater Pemanas diubah ke Mati', 'info', '13:03 - 25 Okt 2026', 1, '2026-10-25 12:03:04'),
(55, 'Semua aktuator berhasil dimatikan', 'info', '13:03 - 25 Okt 2026', 1, '2026-10-25 12:03:06'),
(56, 'Heater Pemanas diubah ke Mati', 'info', '13:03 - 25 Okt 2026', 1, '2026-10-25 12:03:09'),
(57, 'Kipas Ventilasi diubah ke Menyala', 'info', '13:03 - 25 Okt 2026', 1, '2026-10-25 12:03:09'),
(58, 'Heater Pemanas diubah ke Menyala', 'info', '13:04 - 25 Okt 2026', 1, '2026-10-25 12:04:44'),
(59, 'Heater Pemanas diubah ke Mati', 'info', '13:04 - 25 Okt 2026', 1, '2026-10-25 12:04:45'),
(60, 'Motor Pakan diubah ke Menyala', 'info', '13:04 - 25 Okt 2026', 1, '2026-10-25 12:04:50'),
(61, 'Motor Pakan diubah ke Mati', 'info', '13:04 - 25 Okt 2026', 1, '2026-10-25 12:04:51'),
(62, 'Lampu Penerangan diubah ke Menyala', 'info', '13:04 - 25 Okt 2026', 1, '2026-10-25 12:04:52'),
(63, 'Lampu Penerangan diubah ke Mati', 'info', '13:04 - 25 Okt 2026', 1, '2026-10-25 12:04:52'),
(64, 'Motor Pakan diubah ke Menyala', 'info', '13:58 - 25 Okt 2026', 1, '2026-10-25 12:58:27'),
(65, 'Semua aktuator berhasil dimatikan', 'info', '14:01 - 25 Okt 2026', 1, '2026-10-25 13:01:02'),
(66, 'Kipas Ventilasi diubah ke Menyala', 'info', '14:01 - 25 Okt 2026', 1, '2026-10-25 13:01:26'),
(67, 'Kipas Ventilasi diubah ke Mati', 'info', '14:01 - 25 Okt 2026', 1, '2026-10-25 13:01:31'),
(68, 'Heater Pemanas diubah ke Menyala', 'info', '14:01 - 25 Okt 2026', 1, '2026-10-25 13:01:32'),
(69, 'Heater Pemanas diubah ke Mati', 'info', '14:01 - 25 Okt 2026', 1, '2026-10-25 13:01:34'),
(70, 'Heater Pemanas diubah ke Menyala', 'info', '14:01 - 25 Okt 2026', 0, '2026-10-25 13:01:44'),
(71, 'Motor Pakan diubah ke Menyala', 'info', '14:26 - 25 Okt 2026', 0, '2026-10-25 13:26:31'),
(72, 'Motor Pakan diubah ke Menyala', 'info', '07:21 - 26 Okt 2026', 0, '2026-10-26 07:21:33'),
(73, 'Semua aktuator berhasil dimatikan', 'info', '07:27 - 26 Okt 2026', 0, '2026-10-26 07:27:08'),
(74, 'Semua aktuator berhasil dimatikan', 'info', '07:27 - 26 Okt 2026', 0, '2026-10-26 07:27:10'),
(75, 'Semua aktuator berhasil dimatikan', 'info', '07:27 - 26 Okt 2026', 0, '2026-10-26 07:27:11'),
(76, 'Semua aktuator berhasil dimatikan', 'info', '07:27 - 26 Okt 2026', 0, '2026-10-26 07:27:13'),
(77, 'Semua aktuator berhasil dimatikan', 'info', '07:27 - 26 Okt 2026', 0, '2026-10-26 07:27:14'),
(78, 'Semua aktuator berhasil dimatikan', 'info', '07:27 - 26 Okt 2026', 0, '2026-10-26 07:27:15');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--
CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `used_at`, `created_at`) VALUES
(2, 1, 'b33e9f512b1ee6d265cc6476ac1b1666a488e193b1fefcd34de9164a2ffefab3', '2026-10-25 21:48:37', '2026-10-25 20:49:04', '2026-10-25 13:48:37');

-- --------------------------------------------------------

--
-- Table structure for table `pengaturan_kandang`
--
CREATE TABLE `pengaturan_kandang` (
  `id` int NOT NULL,
  `nama_kandang` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Kandang 1 - Utama',
  `tanggal_masuk_ayam` date DEFAULT '2026-10-07',
  `suhu_min` decimal(5,2) NOT NULL DEFAULT '24.00',
  `suhu_maks` decimal(5,2) NOT NULL DEFAULT '34.00',
  `kelembaban_min` decimal(5,2) NOT NULL DEFAULT '55.00',
  `kelembaban_maks` decimal(5,2) NOT NULL DEFAULT '75.00',
  `cahaya_min` decimal(8,2) NOT NULL DEFAULT '500.00',
  `level_air_min` decimal(5,2) NOT NULL DEFAULT '50.00',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengaturan_kandang`
--

INSERT INTO `pengaturan_kandang` (`id`, `nama_kandang`, `tanggal_masuk_ayam`, `suhu_min`, `suhu_maks`, `kelembaban_min`, `kelembaban_maks`, `cahaya_min`, `level_air_min`, `updated_at`) VALUES
(1, 'Kandang 1 - Utama', '2026-10-07', 24.00, 34.00, 55.00, 75.00, 500.00, 50.00, '2026-10-22 06:59:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--
CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `nama_lengkap` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'Bima Apriliansyah',
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `nama_lengkap`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'admin', 'Bima Apriliansyah', 'admin@chickguard.local', '$2y$10$7Q03uVl0J4Rw06lE8Nmpb.DOT4OtKyluZjYcCdVJs28JP.NjGn2ee', 'admin', '2026-05-05 12:36:45');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `actuator_status`
--
ALTER TABLE `actuator_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `data_sensor`
--
ALTER TABLE `data_sensor`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jadwal_pakan_minum`
--
ALTER TABLE `jadwal_pakan_minum`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jadwal_pencahayaan`
--
ALTER TABLE `jadwal_pencahayaan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `log_harian`
--
ALTER TABLE `log_harian`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `log_pemberian_pakan`
--
ALTER TABLE `log_pemberian_pakan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `token_2` (`token`);

--
-- Indexes for table `pengaturan_kandang`
--
ALTER TABLE `pengaturan_kandang`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `actuator_status`
--
ALTER TABLE `actuator_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `data_sensor`
--
ALTER TABLE `data_sensor`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `jadwal_pakan_minum`
--
ALTER TABLE `jadwal_pakan_minum`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `jadwal_pencahayaan`
--
ALTER TABLE `jadwal_pencahayaan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `log_harian`
--
ALTER TABLE `log_harian`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT for table `log_pemberian_pakan`
--
ALTER TABLE `log_pemberian_pakan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pengaturan_kandang`
--
ALTER TABLE `pengaturan_kandang`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
