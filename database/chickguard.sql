-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: chickguard
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `chickguard`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `chickguard` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `chickguard`;

--
-- Table structure for table `actuator_status`
--

DROP TABLE IF EXISTS `actuator_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `actuator_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kipas` tinyint(1) NOT NULL DEFAULT '0',
  `heater` tinyint(1) NOT NULL DEFAULT '0',
  `lampu` tinyint(1) NOT NULL DEFAULT '0',
  `motor_pakan` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `actuator_status`
--

LOCK TABLES `actuator_status` WRITE;
/*!40000 ALTER TABLE `actuator_status` DISABLE KEYS */;
INSERT INTO `actuator_status` VALUES (1,0,0,0,0,'2026-10-27 12:28:36');
/*!40000 ALTER TABLE `actuator_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_sensor`
--

DROP TABLE IF EXISTS `data_sensor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_sensor` (
  `id` int NOT NULL AUTO_INCREMENT,
  `suhu` decimal(5,2) NOT NULL,
  `kelembaban` decimal(5,2) NOT NULL,
  `cahaya` decimal(8,2) NOT NULL DEFAULT '1240.00',
  `level_air` decimal(5,2) NOT NULL DEFAULT '72.00',
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_sensor`
--

LOCK TABLES `data_sensor` WRITE;
/*!40000 ALTER TABLE `data_sensor` DISABLE KEYS */;
INSERT INTO `data_sensor` VALUES (1,32.60,68.90,1260.00,73.00,'2026-10-10 08:00:00'),(2,32.30,67.10,1210.00,72.00,'2026-10-10 08:10:00'),(3,31.90,66.50,1120.00,71.00,'2026-10-10 08:20:00'),(4,32.10,67.80,1180.00,72.00,'2026-10-10 08:30:00'),(5,32.40,68.20,1240.00,72.00,'2026-10-10 08:40:00'),(6,31.72,68.38,1361.15,70.80,'2026-10-22 07:56:34'),(7,31.35,69.11,1411.75,69.90,'2026-10-25 08:45:46'),(8,30.29,70.23,951.34,68.30,'2026-10-26 01:33:29'),(9,29.73,71.72,970.42,66.80,'2026-10-26 08:55:25'),(10,29.05,73.11,559.77,66.20,'2026-10-26 23:27:12'),(11,29.33,73.49,996.50,65.20,'2026-10-27 11:28:20');
/*!40000 ALTER TABLE `data_sensor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jadwal_pakan`
--

DROP TABLE IF EXISTS `jadwal_pakan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jadwal_pakan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `waktu` time NOT NULL,
  `jumlah` decimal(8,2) NOT NULL DEFAULT '2.50',
  `hari` varchar(120) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Semua Hari',
  `catatan` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jadwal_pakan`
--

LOCK TABLES `jadwal_pakan` WRITE;
/*!40000 ALTER TABLE `jadwal_pakan` DISABLE KEYS */;
INSERT INTO `jadwal_pakan` VALUES (101,'06:00:00',2.50,'Semua Hari','Pakan 1',1,'2026-10-22 07:46:48'),(102,'16:00:00',2.50,'Semua Hari','Pakan 2',1,'2026-10-22 07:46:48'),(104,'19:28:00',0.00,'Semua Hari','Pakan Sore',1,'2026-10-27 12:27:31'),(105,'08:00:00',0.00,'Semua Hari','Pakan Pagi',1,'2026-10-27 13:08:11');
/*!40000 ALTER TABLE `jadwal_pakan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `log_harian`
--

DROP TABLE IF EXISTS `log_harian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_harian` (
  `id` int NOT NULL AUTO_INCREMENT,
  `waktu` time NOT NULL,
  `suhu` decimal(5,2) NOT NULL,
  `kelembaban` decimal(5,2) NOT NULL,
  `cahaya` decimal(8,2) DEFAULT '1240.00',
  `level_air` decimal(5,2) DEFAULT '72.00',
  `pakan` decimal(8,2) unsigned DEFAULT NULL,
  `minum` decimal(8,2) unsigned DEFAULT NULL,
  `lampu` enum('Hidup','Mati') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Mati',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_harian`
--

LOCK TABLES `log_harian` WRITE;
/*!40000 ALTER TABLE `log_harian` DISABLE KEYS */;
INSERT INTO `log_harian` VALUES (121,'05:00:00',24.00,71.00,270.00,72.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(122,'06:00:00',25.00,70.00,201.00,76.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(123,'07:00:00',26.00,68.00,805.00,72.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(124,'08:00:00',27.00,66.00,788.00,69.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(125,'09:00:00',28.00,64.00,1156.00,77.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(126,'10:00:00',29.00,63.00,1452.00,78.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(127,'11:00:00',30.00,61.00,1417.00,84.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(128,'12:00:00',30.00,60.00,1832.00,83.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(129,'13:00:00',31.00,59.00,1380.00,83.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(130,'14:00:00',31.00,58.00,1784.00,70.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(131,'15:00:00',30.00,60.00,1035.00,76.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(132,'16:00:00',29.00,62.00,823.00,75.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(133,'17:00:00',28.00,64.00,1119.00,72.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(134,'18:00:00',27.00,66.00,342.00,74.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(135,'19:00:00',27.00,67.00,156.00,83.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(136,'20:00:00',26.00,68.00,0.00,75.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(137,'21:00:00',26.00,69.00,0.00,72.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(138,'22:00:00',25.00,70.00,0.00,81.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(139,'23:00:00',25.00,71.00,0.00,84.00,5.00,0.00,'Mati','2026-05-19 03:01:53'),(140,'06:43:47',28.70,61.60,378.00,86.00,5.00,0.00,'Mati','2026-06-02 04:43:47'),(141,'06:16:33',26.80,73.80,323.00,72.00,5.00,0.00,'Mati','2026-06-03 04:16:33'),(142,'13:19:56',31.00,73.20,1373.00,69.00,5.00,0.00,'Mati','2026-09-04 11:19:56'),(143,'01:08:07',31.20,62.20,0.00,70.00,5.00,0.00,'Mati','2026-09-28 23:08:07'),(144,'07:03:31',29.80,60.00,1038.00,69.00,5.00,0.00,'Mati','2026-10-22 05:03:31'),(145,'09:45:29',27.10,73.90,1071.00,81.00,5.00,0.00,'Mati','2026-10-25 08:45:29'),(146,'01:33:26',29.50,73.50,1240.00,72.00,5.00,0.00,'Mati','2026-10-26 01:33:26'),(147,'23:27:08',26.80,68.80,1240.00,72.00,5.00,0.00,'Mati','2026-10-26 23:27:08');
/*!40000 ALTER TABLE `log_harian` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `log_pemberian_pakan`
--

DROP TABLE IF EXISTS `log_pemberian_pakan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_pemberian_pakan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `waktu` datetime NOT NULL,
  `metode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Otomatis',
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Berhasil',
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_pemberian_pakan`
--

LOCK TABLES `log_pemberian_pakan` WRITE;
/*!40000 ALTER TABLE `log_pemberian_pakan` DISABLE KEYS */;
INSERT INTO `log_pemberian_pakan` VALUES (1,'2026-10-25 16:00:00','Otomatis','Berhasil','Pemberian pakan sore sesuai jadwal','2026-10-25 13:04:19'),(2,'2026-10-25 12:00:00','Otomatis','Berhasil','Pemberian pakan siang sesuai jadwal','2026-10-25 13:04:19'),(3,'2026-10-25 08:30:00','Manual','Berhasil','Pemberian pakan manual via dispenser','2026-10-25 13:04:19'),(4,'2026-10-25 06:00:00','Otomatis','Berhasil','Pemberian pakan pagi sesuai jadwal','2026-10-25 13:04:19'),(5,'2026-10-24 16:00:00','Otomatis','Berhasil','Pemberian pakan sore sesuai jadwal','2026-10-25 13:04:19'),(6,'2026-10-24 12:00:00','Otomatis','Berhasil','Pemberian pakan siang sesuai jadwal','2026-10-25 13:04:19'),(7,'2026-10-24 06:00:00','Otomatis','Berhasil','Pemberian pakan pagi sesuai jadwal','2026-10-25 13:04:19'),(8,'2026-10-25 20:26:31','Manual','Berhasil','Pemberian pakan manual via dispenser','2026-10-25 13:26:31'),(9,'2026-10-26 14:21:33','Manual','Berhasil','Pemberian pakan manual via dispenser','2026-10-26 07:21:33'),(10,'2026-10-27 19:26:59','Manual','Berhasil','Pemberian pakan manual via dispenser','2026-10-27 12:26:59');
/*!40000 ALTER TABLE `log_pemberian_pakan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifikasi`
--

DROP TABLE IF EXISTS `notifikasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifikasi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pesan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tipe` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'success',
  `waktu_label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifikasi`
--

LOCK TABLES `notifikasi` WRITE;
/*!40000 ALTER TABLE `notifikasi` DISABLE KEYS */;
INSERT INTO `notifikasi` VALUES (1,'ESP32 terhubung','success','08:07 - 10 Okt 2026',1,'2026-10-22 07:56:48'),(2,'Suhu kembali normal','success','12:15 - 10 Okt 2026',1,'2026-10-22 07:56:48'),(3,'Level air mulai rendah (72%)','warning','14:32 - 10 Okt 2026',1,'2026-10-22 07:56:48'),(4,'Pakan diberikan otomatis','success','16:00 - 10 Okt 2026',1,'2026-10-22 07:56:48'),(5,'Kipas diubah ke Menyala','info','09:46 - 25 Okt 2026',1,'2026-10-25 08:46:29'),(6,'Kipas diubah ke Mati','info','09:46 - 25 Okt 2026',1,'2026-10-25 08:46:32'),(7,'Motor Pakan diubah ke Jadwal Aktif','info','10:15 - 25 Okt 2026',1,'2026-10-25 09:15:08'),(8,'Motor Pakan diubah ke Jadwal Aktif','info','10:15 - 25 Okt 2026',1,'2026-10-25 09:15:11'),(9,'Motor Pakan diubah ke Mati','info','11:36 - 25 Okt 2026',1,'2026-10-25 10:36:20'),(10,'Motor Pakan diubah ke Jadwal Aktif','info','11:36 - 25 Okt 2026',1,'2026-10-25 10:36:20'),(11,'Lampu diubah ke Menyala','info','11:36 - 25 Okt 2026',1,'2026-10-25 10:36:24'),(12,'Lampu diubah ke Mati','info','11:36 - 25 Okt 2026',1,'2026-10-25 10:36:25'),(13,'Lampu diubah ke Menyala','info','11:36 - 25 Okt 2026',1,'2026-10-25 10:36:25'),(14,'Lampu diubah ke Mati','info','11:36 - 25 Okt 2026',1,'2026-10-25 10:36:26'),(15,'Lampu diubah ke Menyala','info','11:36 - 25 Okt 2026',1,'2026-10-25 10:36:26'),(16,'Lampu diubah ke Mati','info','11:36 - 25 Okt 2026',1,'2026-10-25 10:36:27'),(17,'Lampu diubah ke Menyala','info','11:36 - 25 Okt 2026',1,'2026-10-25 10:36:27'),(18,'Lampu diubah ke Mati','info','11:36 - 25 Okt 2026',1,'2026-10-25 10:36:27'),(19,'Lampu diubah ke Menyala','info','11:36 - 25 Okt 2026',1,'2026-10-25 10:36:27'),(20,'Lampu diubah ke Mati','info','11:36 - 25 Okt 2026',1,'2026-10-25 10:36:27'),(21,'Lampu diubah ke Menyala','info','11:36 - 25 Okt 2026',1,'2026-10-25 10:36:27'),(22,'Lampu diubah ke Mati','info','11:36 - 25 Okt 2026',1,'2026-10-25 10:36:28'),(23,'Motor Pakan diubah ke Jadwal Aktif','info','12:29 - 25 Okt 2026',1,'2026-10-25 11:29:19'),(24,'Motor Pakan diubah ke Jadwal Aktif','info','12:29 - 25 Okt 2026',1,'2026-10-25 11:29:22'),(25,'Semua aktuator dinyalakan secara manual','info','13:01 - 25 Okt 2026',1,'2026-10-25 12:01:36'),(26,'Semua aktuator dinyalakan secara manual','info','13:01 - 25 Okt 2026',1,'2026-10-25 12:01:36'),(27,'Semua aktuator dinyalakan secara manual','info','13:01 - 25 Okt 2026',1,'2026-10-25 12:01:38'),(28,'Aktuator disinkronkan kembali ke mode otomasi mikroklimat','info','13:01 - 25 Okt 2026',1,'2026-10-25 12:01:40'),(29,'Aktuator disinkronkan kembali ke mode otomasi mikroklimat','info','13:01 - 25 Okt 2026',1,'2026-10-25 12:01:41'),(30,'Kipas Ventilasi diubah ke Mati','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:45'),(31,'Kipas Ventilasi diubah ke Mati','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:47'),(32,'Heater Pemanas diubah ke Mati','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:48'),(33,'Lampu Penerangan diubah ke Mati','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:49'),(34,'Motor Pakan diubah ke Mati','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:50'),(35,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:53'),(36,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:54'),(37,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:54'),(38,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:54'),(39,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:55'),(40,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:55'),(41,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:55'),(42,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:55'),(43,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:55'),(44,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:56'),(45,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:56'),(46,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:56'),(47,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:56'),(48,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:56'),(49,'Semua aktuator dinyalakan secara manual','info','13:02 - 25 Okt 2026',1,'2026-10-25 12:02:56'),(50,'Semua aktuator dinyalakan secara manual','info','13:03 - 25 Okt 2026',1,'2026-10-25 12:03:00'),(51,'Kipas Ventilasi diubah ke Menyala','info','13:03 - 25 Okt 2026',1,'2026-10-25 12:03:02'),(52,'Heater Pemanas diubah ke Mati','info','13:03 - 25 Okt 2026',1,'2026-10-25 12:03:02'),(53,'Kipas Ventilasi diubah ke Menyala','info','13:03 - 25 Okt 2026',1,'2026-10-25 12:03:04'),(54,'Heater Pemanas diubah ke Mati','info','13:03 - 25 Okt 2026',1,'2026-10-25 12:03:04'),(55,'Semua aktuator berhasil dimatikan','info','13:03 - 25 Okt 2026',1,'2026-10-25 12:03:06'),(56,'Heater Pemanas diubah ke Mati','info','13:03 - 25 Okt 2026',1,'2026-10-25 12:03:09'),(57,'Kipas Ventilasi diubah ke Menyala','info','13:03 - 25 Okt 2026',1,'2026-10-25 12:03:09'),(58,'Heater Pemanas diubah ke Menyala','info','13:04 - 25 Okt 2026',1,'2026-10-25 12:04:44'),(59,'Heater Pemanas diubah ke Mati','info','13:04 - 25 Okt 2026',1,'2026-10-25 12:04:45'),(60,'Motor Pakan diubah ke Menyala','info','13:04 - 25 Okt 2026',1,'2026-10-25 12:04:50'),(61,'Motor Pakan diubah ke Mati','info','13:04 - 25 Okt 2026',1,'2026-10-25 12:04:51'),(62,'Lampu Penerangan diubah ke Menyala','info','13:04 - 25 Okt 2026',1,'2026-10-25 12:04:52'),(63,'Lampu Penerangan diubah ke Mati','info','13:04 - 25 Okt 2026',1,'2026-10-25 12:04:52'),(64,'Motor Pakan diubah ke Menyala','info','13:58 - 25 Okt 2026',1,'2026-10-25 12:58:27'),(65,'Semua aktuator berhasil dimatikan','info','14:01 - 25 Okt 2026',1,'2026-10-25 13:01:02'),(66,'Kipas Ventilasi diubah ke Menyala','info','14:01 - 25 Okt 2026',1,'2026-10-25 13:01:26'),(67,'Kipas Ventilasi diubah ke Mati','info','14:01 - 25 Okt 2026',1,'2026-10-25 13:01:31'),(68,'Heater Pemanas diubah ke Menyala','info','14:01 - 25 Okt 2026',1,'2026-10-25 13:01:32'),(69,'Heater Pemanas diubah ke Mati','info','14:01 - 25 Okt 2026',1,'2026-10-25 13:01:34'),(70,'Heater Pemanas diubah ke Menyala','info','14:01 - 25 Okt 2026',1,'2026-10-25 13:01:44'),(71,'Motor Pakan diubah ke Menyala','info','14:26 - 25 Okt 2026',1,'2026-10-25 13:26:31'),(72,'Motor Pakan diubah ke Menyala','info','07:21 - 26 Okt 2026',1,'2026-10-26 07:21:33'),(73,'Semua aktuator berhasil dimatikan','info','07:27 - 26 Okt 2026',1,'2026-10-26 07:27:08'),(74,'Semua aktuator berhasil dimatikan','info','07:27 - 26 Okt 2026',1,'2026-10-26 07:27:10'),(75,'Semua aktuator berhasil dimatikan','info','07:27 - 26 Okt 2026',1,'2026-10-26 07:27:11'),(76,'Semua aktuator berhasil dimatikan','info','07:27 - 26 Okt 2026',1,'2026-10-26 07:27:13'),(77,'Semua aktuator berhasil dimatikan','info','07:27 - 26 Okt 2026',1,'2026-10-26 07:27:14'),(78,'Semua aktuator berhasil dimatikan','info','07:27 - 26 Okt 2026',1,'2026-10-26 07:27:15'),(79,'Kipas Ventilasi diubah ke Menyala','info','11:52 - 27 Okt 2026',1,'2026-10-27 11:52:12'),(80,'Kipas Ventilasi diubah ke Mati','info','11:52 - 27 Okt 2026',1,'2026-10-27 11:52:13'),(81,'Motor Pakan diubah ke Menyala','info','12:26 - 27 Okt 2026',0,'2026-10-27 12:26:59'),(82,'Kipas Ventilasi diubah ke Mati','info','12:28 - 27 Okt 2026',0,'2026-10-27 12:28:33'),(83,'Lampu Penerangan diubah ke Mati','info','12:28 - 27 Okt 2026',0,'2026-10-27 12:28:35'),(84,'Motor Pakan diubah ke Mati','info','12:28 - 27 Okt 2026',0,'2026-10-27 12:28:36'),(85,'Lampu Penerangan diubah ke Mati','info','12:28 - 27 Okt 2026',0,'2026-10-27 12:28:38'),(86,'Semua aktuator berhasil dimatikan','info','12:28 - 27 Okt 2026',0,'2026-10-27 12:28:39'),(87,'Semua aktuator berhasil dimatikan','info','12:28 - 27 Okt 2026',0,'2026-10-27 12:28:42');
/*!40000 ALTER TABLE `notifikasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `token_2` (`token`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (3,1,'210fb51bfac1101d585b7996f5b865f92aa1bec09268d90e76c9b4aea92c6244','2026-10-26 16:55:04','2026-10-26 15:55:18','2026-10-26 08:55:04');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengaturan_kandang`
--

DROP TABLE IF EXISTS `pengaturan_kandang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengaturan_kandang` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_kandang` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Kandang 1 - Utama',
  `tanggal_masuk_ayam` date DEFAULT '2026-10-07',
  `suhu_min` decimal(5,2) NOT NULL DEFAULT '24.00',
  `suhu_maks` decimal(5,2) NOT NULL DEFAULT '34.00',
  `kelembaban_min` decimal(5,2) NOT NULL DEFAULT '55.00',
  `kelembaban_maks` decimal(5,2) NOT NULL DEFAULT '75.00',
  `cahaya_min` decimal(8,2) NOT NULL DEFAULT '500.00',
  `level_air_min` decimal(5,2) NOT NULL DEFAULT '50.00',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengaturan_kandang`
--

LOCK TABLES `pengaturan_kandang` WRITE;
/*!40000 ALTER TABLE `pengaturan_kandang` DISABLE KEYS */;
INSERT INTO `pengaturan_kandang` VALUES (1,'Kandang 1 - Utama','2026-10-07',24.00,34.00,55.00,75.00,500.00,50.00,'2026-10-22 06:59:29');
/*!40000 ALTER TABLE `pengaturan_kandang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_lengkap` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Bima Apriliansyah',
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','Bima Apriliansyah','admin@chickguard.local','$2y$10$UeUjnUBkCbR9tOokwPCmHeedzMFEq91pbOFo2HjpdvUihlBpMsij.','admin','2026-05-05 12:36:45');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'chickguard'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-27 20:08:29
