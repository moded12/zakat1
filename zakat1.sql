-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 12, 2026 at 07:05 AM
-- Server version: 10.5.29-MariaDB
-- PHP Version: 8.4.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `zakat1`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) DEFAULT 'مدير النظام',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `full_name`, `created_at`) VALUES
(3, 'admin@admin', '$2y$10$uMBu.ncO.q0WP/6iAWCEiu0VSvf4qCfq6A0XQbv711aPfmYEuZgwu', 'مدير النظام', '2026-03-07 12:38:14'),
(4, 'admin', '$2y$10$yTLjIlT87pACmYIFdpdrh.zyDpCU7bnRxBV.jgyYbn67VpX1x8V6e', 'مدير النظام', '2026-03-07 17:15:45');

-- --------------------------------------------------------

--
-- Table structure for table `attachments`
--

CREATE TABLE `attachments` (
  `id` int(10) UNSIGNED NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(10) UNSIGNED NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `beneficiary_distributions`
--

CREATE TABLE `beneficiary_distributions` (
  `id` int(11) NOT NULL,
  `beneficiary_type` enum('poor_families','orphans','sponsorships') NOT NULL,
  `distribution_date` date NOT NULL,
  `category` enum('نقد','مواد عينية','منظفات','ملابس','أخرى') NOT NULL,
  `title` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `beneficiary_distributions`
--

INSERT INTO `beneficiary_distributions` (`id`, `beneficiary_type`, `distribution_date`, `category`, `title`, `notes`, `created_by`, `created_at`) VALUES
(1, 'orphans', '2026-03-11', 'نقد', 'اختبار', 'تجربة', 4, '2026-03-09 13:50:21'),
(2, 'orphans', '2026-03-09', 'مواد عينية', 'طرد تجريبي', 'تجريب أيتام', 4, '2026-03-09 13:52:03'),
(3, 'orphans', '2026-03-09', 'نقد', 'تجربة 1', '', 4, '2026-03-09 13:52:28'),
(4, 'orphans', '2026-03-09', 'نقد', 'توزيع نقد تجربة 11', 'توزيعه نقدية 11 تجريبية', 4, '2026-03-09 14:50:58'),
(5, 'orphans', '2026-03-09', 'نقد', 'توزيع نقد تجربة 11', 'توزيعه نقدية 11 تجريبية', 4, '2026-03-09 14:53:24'),
(6, 'sponsorships', '2026-03-09', 'نقد', 'xxxx', 'تجريبة xxxx', 4, '2026-03-09 15:10:50'),
(7, 'orphans', '2026-03-10', 'نقد', '2222', '', 4, '2026-03-10 08:29:23'),
(8, '', '2026-03-10', 'نقد', 'رواتب الأسر 1', '', 4, '2026-03-10 15:28:52'),
(9, 'orphans', '2026-03-10', 'نقد', '8888', '', 4, '2026-03-10 19:01:14'),
(10, 'orphans', '2026-03-10', 'مواد عينية', '9999', '', 4, '2026-03-10 19:01:36'),
(11, 'orphans', '2026-03-10', 'نقد', '9999', '', 4, '2026-03-10 19:02:59'),
(12, 'orphans', '2026-03-10', 'نقد', '101010', '', 4, '2026-03-10 21:10:36');

-- --------------------------------------------------------

--
-- Table structure for table `beneficiary_distribution_items`
--

CREATE TABLE `beneficiary_distribution_items` (
  `id` int(11) NOT NULL,
  `distribution_id` int(11) NOT NULL,
  `beneficiary_id` int(11) NOT NULL,
  `cash_amount` decimal(10,2) DEFAULT NULL,
  `details_text` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `beneficiary_distribution_items`
--

INSERT INTO `beneficiary_distribution_items` (`id`, `distribution_id`, `beneficiary_id`, `cash_amount`, `details_text`, `notes`, `created_at`) VALUES
(1, 2, 10, NULL, 'طرد', 'طرد', '2026-03-09 13:52:03'),
(2, 2, 11, NULL, 'طرد', 'طرد', '2026-03-09 13:52:03'),
(3, 3, 10, 50.00, NULL, '', '2026-03-09 13:52:28'),
(4, 3, 11, 40.00, NULL, '', '2026-03-09 13:52:28'),
(5, 4, 10, 50.00, NULL, '', '2026-03-09 14:50:58'),
(6, 4, 11, 50.00, NULL, '', '2026-03-09 14:50:58'),
(7, 4, 12, 20.00, NULL, '', '2026-03-09 14:50:58'),
(8, 4, 13, 90.00, NULL, '', '2026-03-09 14:50:58'),
(9, 4, 14, 10.00, NULL, '', '2026-03-09 14:50:58'),
(10, 5, 10, 50.00, NULL, '', '2026-03-09 14:53:24'),
(11, 5, 11, 50.00, NULL, '', '2026-03-09 14:53:24'),
(12, 5, 12, 20.00, NULL, '', '2026-03-09 14:53:24'),
(13, 5, 13, 90.00, NULL, '', '2026-03-09 14:53:24'),
(14, 5, 14, 10.00, NULL, '', '2026-03-09 14:53:24'),
(15, 6, 8, 40.00, NULL, '55', '2026-03-09 15:10:50'),
(16, 6, 9, 12.00, NULL, '55', '2026-03-09 15:10:50'),
(17, 6, 10, 56.00, NULL, '55', '2026-03-09 15:10:50'),
(18, 7, 32, 0.01, NULL, '', '2026-03-10 08:29:23'),
(19, 8, 1, 20.00, NULL, '', '2026-03-10 15:28:52'),
(20, 8, 2, 20.00, NULL, '', '2026-03-10 15:28:52'),
(21, 8, 3, 20.00, NULL, '', '2026-03-10 15:28:52'),
(22, 8, 4, 20.00, NULL, '', '2026-03-10 15:28:52'),
(23, 8, 5, 20.00, NULL, '', '2026-03-10 15:28:52'),
(24, 8, 6, 20.00, NULL, '', '2026-03-10 15:28:52'),
(25, 8, 7, 20.00, NULL, '', '2026-03-10 15:28:52'),
(26, 9, 32, 20.00, NULL, '', '2026-03-10 19:01:14'),
(27, 9, 33, 20.00, NULL, '', '2026-03-10 19:01:14'),
(28, 9, 34, 20.00, NULL, '', '2026-03-10 19:01:14'),
(29, 9, 35, 20.00, NULL, '', '2026-03-10 19:01:14'),
(30, 9, 36, 20.00, NULL, '', '2026-03-10 19:01:14'),
(31, 9, 37, 20.00, NULL, '', '2026-03-10 19:01:14'),
(32, 9, 38, 20.00, NULL, '', '2026-03-10 19:01:14'),
(33, 9, 39, 20.00, NULL, '', '2026-03-10 19:01:14'),
(34, 9, 40, 20.00, NULL, '', '2026-03-10 19:01:14'),
(35, 9, 41, 20.00, NULL, '', '2026-03-10 19:01:14'),
(36, 9, 42, 20.00, NULL, '', '2026-03-10 19:01:14'),
(37, 9, 43, 20.00, NULL, '', '2026-03-10 19:01:14'),
(38, 9, 44, 20.00, NULL, '', '2026-03-10 19:01:14'),
(39, 10, 32, NULL, 'طرد', '', '2026-03-10 19:01:36'),
(40, 10, 33, NULL, 'طرد', '', '2026-03-10 19:01:36'),
(41, 10, 34, NULL, 'طرد', '', '2026-03-10 19:01:36'),
(42, 10, 35, NULL, 'طرد', '', '2026-03-10 19:01:36'),
(43, 10, 36, NULL, 'طرد', '', '2026-03-10 19:01:36'),
(44, 10, 37, NULL, 'طرد', '', '2026-03-10 19:01:36'),
(45, 10, 38, NULL, 'طرد', '', '2026-03-10 19:01:36'),
(46, 10, 39, NULL, 'طرد', '', '2026-03-10 19:01:36'),
(47, 10, 40, NULL, 'طرد', '', '2026-03-10 19:01:36'),
(48, 10, 41, NULL, 'طرد', '', '2026-03-10 19:01:36'),
(49, 10, 42, NULL, 'طرد', '', '2026-03-10 19:01:36'),
(50, 10, 43, NULL, 'طرد', '', '2026-03-10 19:01:36'),
(51, 10, 44, NULL, 'طرد', '', '2026-03-10 19:01:36'),
(52, 11, 32, 20.00, NULL, '', '2026-03-10 19:02:59'),
(53, 11, 33, 20.00, NULL, '', '2026-03-10 19:02:59'),
(54, 11, 34, 20.00, NULL, '', '2026-03-10 19:02:59'),
(55, 11, 35, 20.00, NULL, '', '2026-03-10 19:02:59'),
(56, 11, 36, 20.00, NULL, '', '2026-03-10 19:02:59'),
(57, 11, 37, 20.00, NULL, '', '2026-03-10 19:02:59'),
(58, 11, 38, 20.00, NULL, '', '2026-03-10 19:02:59'),
(59, 11, 39, 20.00, NULL, '', '2026-03-10 19:02:59'),
(60, 11, 40, 20.00, NULL, '', '2026-03-10 19:02:59'),
(61, 11, 41, 20.00, NULL, '', '2026-03-10 19:02:59'),
(62, 11, 42, 20.00, NULL, '', '2026-03-10 19:02:59'),
(63, 11, 43, 20.00, NULL, '', '2026-03-10 19:02:59'),
(64, 11, 44, 20.00, NULL, '', '2026-03-10 19:02:59'),
(65, 12, 34, 20.00, NULL, '', '2026-03-10 21:10:36'),
(66, 12, 35, 20.00, NULL, '', '2026-03-10 21:10:36');

-- --------------------------------------------------------

--
-- Table structure for table `distributions`
--

CREATE TABLE `distributions` (
  `id` int(10) UNSIGNED NOT NULL,
  `assistance_type` varchar(255) NOT NULL,
  `beneficiary_name` varchar(255) NOT NULL,
  `category_name` varchar(100) DEFAULT NULL,
  `distribution_date` date DEFAULT NULL,
  `quantity_or_amount` varchar(100) DEFAULT NULL,
  `delivery_status` varchar(50) DEFAULT 'تم التسليم',
  `responsible_person` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `distribution_sheets`
--

CREATE TABLE `distribution_sheets` (
  `id` int(11) NOT NULL,
  `sheet_number` varchar(50) NOT NULL,
  `source_type` varchar(50) NOT NULL,
  `distribution_type` varchar(50) NOT NULL,
  `distribution_date` date NOT NULL,
  `total_records` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `distribution_sheets`
--

INSERT INTO `distribution_sheets` (`id`, `sheet_number`, `source_type`, `distribution_type`, `distribution_date`, `total_records`, `notes`, `created_by`, `created_at`) VALUES
(1, 'DS-20260308-0001', 'sponsorships', 'نقداً', '2026-03-08', 3, '', 4, '2026-03-08 21:06:25'),
(2, 'DS-20260308-0002', 'sponsorships', 'نقداً', '2026-03-08', 3, '', 4, '2026-03-08 21:07:52'),
(3, 'DS-20260308-0003', 'poor_families', 'نقداً', '2026-03-08', 5, '', 4, '2026-03-08 21:08:09'),
(4, 'DS-20260308-0004', 'poor_families', 'نقداً', '2026-03-08', 5, '', 4, '2026-03-08 21:10:37'),
(5, 'DS-20260308-0005', 'orphans', 'نقداً', '2026-03-08', 2, '', 4, '2026-03-08 21:12:29'),
(6, 'DS-20260308-0006', 'poor_families', 'نقداً', '2026-03-08', 5, '', 4, '2026-03-08 21:13:47'),
(7, 'DS-20260308-0007', 'poor_families', 'نقداً', '2026-03-08', 5, '', 4, '2026-03-08 21:16:31'),
(8, 'DS-20260308-0008', 'poor_families', 'نقداً', '2026-03-08', 6, '', 4, '2026-03-08 21:22:48'),
(9, 'DS-20260309-0001', 'poor_families', 'نقداً', '2026-03-09', 76, '', 4, '2026-03-09 09:13:26');

-- --------------------------------------------------------

--
-- Table structure for table `distribution_sheet_items`
--

CREATE TABLE `distribution_sheet_items` (
  `id` int(11) NOT NULL,
  `sheet_id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `record_label` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `distribution_sheet_items`
--

INSERT INTO `distribution_sheet_items` (`id`, `sheet_id`, `record_id`, `record_label`, `created_at`) VALUES
(1, 1, 8, 'مجدولين عبد الرحمن حمد الفرج', '2026-03-08 21:06:25'),
(2, 1, 9, 'باسمة سليمان محمود درويش', '2026-03-08 21:06:25'),
(3, 1, 10, 'رجاء محمد حمدان ابن حمد', '2026-03-08 21:06:25'),
(4, 2, 8, 'مجدولين عبد الرحمن حمد الفرج', '2026-03-08 21:07:52'),
(5, 2, 9, 'باسمة سليمان محمود درويش', '2026-03-08 21:07:52'),
(6, 2, 10, 'رجاء محمد حمدان ابن حمد', '2026-03-08 21:07:52'),
(7, 3, 161, 'حسن محمد حسن براهمة', '2026-03-08 21:08:09'),
(8, 3, 162, 'أية خميس ابراهيم العمصي', '2026-03-08 21:08:09'),
(9, 3, 163, 'تغريد عايد نبيه الكعابنة', '2026-03-08 21:08:09'),
(10, 3, 164, 'حرب محمد علي الراموني', '2026-03-08 21:08:09'),
(11, 3, 165, 'خليل محمد لطفي ابو رومي', '2026-03-08 21:08:09'),
(12, 4, 161, 'حسن محمد حسن براهمة', '2026-03-08 21:10:37'),
(13, 4, 162, 'أية خميس ابراهيم العمصي', '2026-03-08 21:10:37'),
(14, 4, 163, 'تغريد عايد نبيه الكعابنة', '2026-03-08 21:10:37'),
(15, 4, 164, 'حرب محمد علي الراموني', '2026-03-08 21:10:37'),
(16, 4, 165, 'خليل محمد لطفي ابو رومي', '2026-03-08 21:10:37'),
(17, 5, 10, 'محمد محمود  اخلاوي المصري', '2026-03-08 21:12:29'),
(18, 5, 11, 'سعاد جابر سال صبيح', '2026-03-08 21:12:29'),
(19, 6, 161, 'حسن محمد حسن براهمة', '2026-03-08 21:13:47'),
(20, 6, 162, 'أية خميس ابراهيم العمصي', '2026-03-08 21:13:47'),
(21, 6, 163, 'تغريد عايد نبيه الكعابنة', '2026-03-08 21:13:47'),
(22, 6, 164, 'حرب محمد علي الراموني', '2026-03-08 21:13:47'),
(23, 6, 165, 'خليل محمد لطفي ابو رومي', '2026-03-08 21:13:47'),
(24, 7, 161, 'حسن محمد حسن براهمة', '2026-03-08 21:16:31'),
(25, 7, 162, 'أية خميس ابراهيم العمصي', '2026-03-08 21:16:31'),
(26, 7, 163, 'تغريد عايد نبيه الكعابنة', '2026-03-08 21:16:31'),
(27, 7, 164, 'حرب محمد علي الراموني', '2026-03-08 21:16:31'),
(28, 7, 165, 'خليل محمد لطفي ابو رومي', '2026-03-08 21:16:31'),
(29, 8, 161, 'حسن محمد حسن براهمة', '2026-03-08 21:22:48'),
(30, 8, 162, 'أية خميس ابراهيم العمصي', '2026-03-08 21:22:48'),
(31, 8, 163, 'تغريد عايد نبيه الكعابنة', '2026-03-08 21:22:48'),
(32, 8, 164, 'حرب محمد علي الراموني', '2026-03-08 21:22:48'),
(33, 8, 165, 'خليل محمد لطفي ابو رومي', '2026-03-08 21:22:48'),
(34, 8, 167, 'خليل محمد لطفي ابو رومي', '2026-03-08 21:22:48'),
(35, 9, 161, 'حسن محمد حسن براهمة', '2026-03-09 09:13:26'),
(36, 9, 162, 'أية خميس ابراهيم العمصي', '2026-03-09 09:13:26'),
(37, 9, 163, 'تغريد عايد نبيه الكعابنة', '2026-03-09 09:13:26'),
(38, 9, 164, 'حرب محمد علي الراموني', '2026-03-09 09:13:26'),
(39, 9, 165, 'خليل محمد لطفي ابو رومي', '2026-03-09 09:13:26'),
(40, 9, 167, 'خليل محمد لطفي ابو رومي', '2026-03-09 09:13:26'),
(41, 9, 168, 'حسن محمد حسن براهمة', '2026-03-09 09:13:26'),
(42, 9, 169, 'أية خميس ابراهيم العمصي', '2026-03-09 09:13:26'),
(43, 9, 170, 'تغريد عايد نبيه الكعابنة', '2026-03-09 09:13:26'),
(44, 9, 171, 'حرب محمد علي الراموني', '2026-03-09 09:13:26'),
(45, 9, 172, 'خليل محمد لطفي ابو رومي', '2026-03-09 09:13:26'),
(46, 9, 173, 'عيسى محمد احمد الشواكري', '2026-03-09 09:13:26'),
(47, 9, 174, 'فاطمة عبد الهادي محمود سلام', '2026-03-09 09:13:26'),
(48, 9, 175, 'محمد حسين سالم أبو عمرة', '2026-03-09 09:13:26'),
(49, 9, 176, 'يوسف محمد يوسف حمدان', '2026-03-09 09:13:26'),
(50, 9, 177, 'رائد محمد حسن ابو راشد', '2026-03-09 09:13:26'),
(51, 9, 178, 'سامي محمد عبدالله أبو خوصة', '2026-03-09 09:13:26'),
(52, 9, 179, 'سعدية محمد عطية عبد النبي', '2026-03-09 09:13:26'),
(53, 9, 180, 'سلمى محمود محمد شاهين', '2026-03-09 09:13:26'),
(54, 9, 182, 'سماح محمد جير أبو الخل', '2026-03-09 09:13:26'),
(55, 9, 183, 'ايمان اسماعيل موسى ابو جزر', '2026-03-09 09:13:26'),
(56, 9, 184, 'بسمة صالح موسى أبو عشيبة', '2026-03-09 09:13:26'),
(57, 9, 185, 'جهاد محمد مسلم دلدوم', '2026-03-09 09:13:26'),
(58, 9, 186, 'حاتم خليل مسعود حطاب', '2026-03-09 09:13:26'),
(59, 9, 187, 'سعاد صالح موسى ابو عشيبة', '2026-03-09 09:13:26'),
(60, 9, 188, 'كمال  عطي احمد العطي', '2026-03-09 09:13:26'),
(61, 9, 189, 'محمد عمر محمود علي', '2026-03-09 09:13:26'),
(62, 9, 190, 'موسى  يوسف محمد براهمة', '2026-03-09 09:13:26'),
(63, 9, 191, 'منى شحادة عبد المجيد الترابين', '2026-03-09 09:13:26'),
(64, 9, 192, 'هيجر محمود حسن حمدان', '2026-03-09 09:13:26'),
(65, 9, 193, 'سندس هاني  ابراهيم  سدودي', '2026-03-09 09:13:26'),
(66, 9, 194, 'شادي عادل محمد احمد', '2026-03-09 09:13:26'),
(67, 9, 195, 'فاطمة جابر احمد العجوري', '2026-03-09 09:13:26'),
(68, 9, 196, 'فيصل ذياب جبر الثوابتة', '2026-03-09 09:13:26'),
(69, 9, 197, 'ماجد صبحي محمد الرياحي', '2026-03-09 09:13:26'),
(70, 9, 198, 'نجوى خالد محمد غبن', '2026-03-09 09:13:26'),
(71, 9, 199, 'بكر سالم احمد فياض', '2026-03-09 09:13:26'),
(72, 9, 200, 'فاتن محمد عبد القادر العجارمة', '2026-03-09 09:13:26'),
(73, 9, 201, 'احلام احمد محمد عويضه', '2026-03-09 09:13:26'),
(74, 9, 202, 'وحيد محمد عبد الحميد ابو محيسن', '2026-03-09 09:13:26'),
(75, 9, 203, 'اسلام بسام احمد زيغان', '2026-03-09 09:13:26'),
(76, 9, 204, 'ياسمين يعقوب منصور الحويطي', '2026-03-09 09:13:26'),
(77, 9, 205, 'حسن يوسف سالم ابو عيادة', '2026-03-09 09:13:26'),
(78, 9, 206, 'الهام محمد عليان العروقي', '2026-03-09 09:13:26'),
(79, 9, 207, 'محمد كامل مسلم أبو عاذرة', '2026-03-09 09:13:26'),
(80, 9, 208, 'خلود محمد عبد العزيز ضمرة', '2026-03-09 09:13:26'),
(81, 9, 209, 'سحر احمد احمد بن علي', '2026-03-09 09:13:26'),
(82, 9, 210, 'جهاد احمد  بشير مطر', '2026-03-09 09:13:26'),
(83, 9, 211, 'رائد جمال اسماعيل ابو عون', '2026-03-09 09:13:26'),
(84, 9, 212, 'عبد الله حسين محمد أبو سليم', '2026-03-09 09:13:26'),
(85, 9, 213, 'عمر حسن اسماعيل الشنباري', '2026-03-09 09:13:26'),
(86, 9, 214, 'اعتدال احمد قاسم فريج', '2026-03-09 09:13:26'),
(87, 9, 215, 'أمينة اسعد خليل أبو خليفة', '2026-03-09 09:13:26'),
(88, 9, 216, 'احمد محمد عبد الكريم السرابطه', '2026-03-09 09:13:26'),
(89, 9, 217, 'هاني عبد الرحيم حسن ابو هلال', '2026-03-09 09:13:26'),
(90, 9, 218, 'هناء ماهر فؤاد محمود', '2026-03-09 09:13:26'),
(91, 9, 219, 'وليد سليمان محمود درويش', '2026-03-09 09:13:26'),
(92, 9, 220, 'يوسف حسن احمد ابو شوارب', '2026-03-09 09:13:26'),
(93, 9, 221, 'عيد محمود يوسف عقلة', '2026-03-09 09:13:26'),
(94, 9, 222, 'كفاية محمد سليمان إسماعيل', '2026-03-09 09:13:26'),
(95, 9, 223, 'نسرين عبد الله خميس البصيلي', '2026-03-09 09:13:26'),
(96, 9, 224, 'محمد سليمان محمد عطايا', '2026-03-09 09:13:26'),
(97, 9, 225, 'مهند ناصر سالم البحيري', '2026-03-09 09:13:26'),
(98, 9, 226, 'حسام محمد عطية أبو زيد', '2026-03-09 09:13:26'),
(99, 9, 227, 'هانم صابر علام علام / ابو كايد', '2026-03-09 09:13:26'),
(100, 9, 228, 'بسام علي اسماعيل شاهين', '2026-03-09 09:13:26'),
(101, 9, 229, 'فتحية خليل ابراهيم حماد', '2026-03-09 09:13:26'),
(102, 9, 230, 'حنان جابر سالم صبح', '2026-03-09 09:13:26'),
(103, 9, 232, 'محمود ابراهيم محمد العمري', '2026-03-09 09:13:26'),
(104, 9, 233, 'ابتسام سلامه صباح الترابين', '2026-03-09 09:13:26'),
(105, 9, 234, 'سهى محمد احمد الشباكي', '2026-03-09 09:13:26'),
(106, 9, 235, 'نعمة عزمي محمد الصليبي', '2026-03-09 09:13:26'),
(107, 9, 236, 'اياد محمود محمد سلطان', '2026-03-09 09:13:26'),
(108, 9, 237, 'ولاء محمود محمد أبو توبة', '2026-03-09 09:13:26'),
(109, 9, 238, 'هيام يونس سعيد المصري', '2026-03-09 09:13:26'),
(110, 9, 239, 'احمد عبد الهادي محمد عابد', '2026-03-09 09:13:26');

-- --------------------------------------------------------

--
-- Table structure for table `family_salaries`
--

CREATE TABLE `family_salaries` (
  `id` int(11) NOT NULL,
  `salary_number` varchar(50) NOT NULL,
  `beneficiary_name` varchar(255) NOT NULL,
  `beneficiary_id_number` varchar(50) DEFAULT NULL,
  `beneficiary_phone` varchar(50) DEFAULT NULL,
  `salary_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `family_salaries`
--

INSERT INTO `family_salaries` (`id`, `salary_number`, `beneficiary_name`, `beneficiary_id_number`, `beneficiary_phone`, `salary_amount`, `notes`, `created_at`) VALUES
(1, '1', 'محمد راتب أسرة', '52524555', '0798854215', 50.00, NULL, '2026-03-10 15:23:43'),
(2, '2', 'وعد عامر مصطفى البرقاوي', '780161339', '9932069350', 0.00, NULL, '2026-03-10 15:27:20'),
(3, '3', 'جهاد جمال مسلم المغايضة', 'ج غ T413942', '786074028', 0.00, NULL, '2026-03-10 15:27:20'),
(4, '4', 'مرزوق جميل خليل أبو الحاج', '9711002610', '795884753', 0.00, NULL, '2026-03-10 15:27:20'),
(5, '5', 'علي احمد علي ابو شارب', '9751014580', '786996747', 0.00, NULL, '2026-03-10 15:27:20'),
(6, '6', 'كفاية خالد محمد ابو حاشي', '2002458430', '788896851', 0.00, NULL, '2026-03-10 15:27:20'),
(7, '7', 'خديجه غازي عبد الحليم خاطر', '9842004046', '786303591', 0.00, NULL, '2026-03-10 15:27:20');

-- --------------------------------------------------------

--
-- Table structure for table `orphans`
--

CREATE TABLE `orphans` (
  `id` int(10) UNSIGNED NOT NULL,
  `file_number` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `id_number` varchar(100) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `mother_name` varchar(255) DEFAULT NULL,
  `guardian_name` varchar(255) DEFAULT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `education_status` varchar(255) DEFAULT NULL,
  `health_status` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orphans`
--

INSERT INTO `orphans` (`id`, `file_number`, `name`, `id_number`, `birth_date`, `gender`, `mother_name`, `guardian_name`, `contact_info`, `address`, `education_status`, `health_status`, `notes`, `created_at`) VALUES
(32, '1', 'محمد محمود  اخلاوي المصري', '9902030502', NULL, '', '', '', '780389280', '', '', '', '', '2026-03-09 18:18:36'),
(33, '2', 'سعاد جابر سال صبيح', '2001203607', NULL, '', '', '', '796937576', '', '', '', '', '2026-03-09 18:18:36'),
(34, '3', 'ربيعة سلامة محمد أبو غليون', '9842060625', NULL, '', '', '', '789776500', '', '', '', '', '2026-03-09 18:18:36'),
(35, '4', 'مجدولين عبد الرحمن حمد الفرج', '97720000433', NULL, '', '', '', '772068495', '', '', '', '', '2026-03-09 18:18:36'),
(36, '5', 'باسمة سليمان محمود درويش', '5000017935', NULL, '', '', '', '796706577', '', '', '', '', '2026-03-09 18:18:36'),
(37, '6', 'رجاء محمد حمدان ابن حمد', '5000054117', NULL, '', '', '', '782642110', '', '', '', '', '2026-03-09 18:18:36'),
(38, '7', 'ولاء حسين عزات الحسنات', '9912011534', NULL, '', '', '', '786899211', '', '', '', '', '2026-03-09 18:18:36'),
(39, '8', 'مها محمد رشيد ابو داوود', '9792043933', NULL, '', '', '', '786705784', '', '', '', '', '2026-03-09 18:18:36'),
(40, '9', 'عايدة عبد العزيز محمود المصري', '9862012055', NULL, '', '', '', '781119687', '', '', '', '', '2026-03-09 18:18:36'),
(41, '10', 'عزيزة عبد الكريم محمد إبراهيم', '9652021325', NULL, '', '', '', '786174628', '', '', '', '', '2026-03-09 18:18:36'),
(42, '11', 'سجى خليل محمد أبو محيسن', '5000004125', NULL, '', '', '', '782712229', '', '', '', '', '2026-03-09 18:18:36'),
(43, '12', 'سناء عبد عطية السويعدي', 'ج عراقي/ 17937443', NULL, '', '', '', '792948772', '', '', '', '', '2026-03-09 18:18:36'),
(44, '13', 'لورانس عامر مصطفى البرقاوي', '780161339', NULL, '', '', '', '9932069350', '', '', '', '', '2026-03-09 18:18:36');

-- --------------------------------------------------------

--
-- Table structure for table `orphans_backup_before_cleanup`
--

CREATE TABLE `orphans_backup_before_cleanup` (
  `id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `file_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_info` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `education_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `health_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `orphans_backup_before_cleanup`
--

INSERT INTO `orphans_backup_before_cleanup` (`id`, `file_number`, `name`, `id_number`, `birth_date`, `gender`, `mother_name`, `guardian_name`, `contact_info`, `address`, `education_status`, `health_status`, `notes`, `created_at`) VALUES
(6, 'سامي محمد عبدالله أبو خوصة', '5000063100', '786287018', NULL, '', '', '', '', '', '', '', '', '2026-03-08 19:58:25'),
(7, 'سعدية محمد عطية عبد النبي', '245212 غ', '790716326', NULL, '', '', '', '', '', '', '', '', '2026-03-08 19:58:25'),
(8, 'سلمى محمود محمد شاهين', '5000041566', '780073570', NULL, '', '', '', '', '', '', '', '', '2026-03-08 19:58:25'),
(9, 'ابراهيم صبحي ابراهيم ابو تيم', '5000032091', '789558807', NULL, '', '', '', '', '', '', '', '', '2026-03-08 19:58:25');

-- --------------------------------------------------------

--
-- Table structure for table `poor_families`
--

CREATE TABLE `poor_families` (
  `id` int(10) UNSIGNED NOT NULL,
  `file_number` varchar(100) NOT NULL,
  `head_name` varchar(255) NOT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `members_count` int(11) DEFAULT 0,
  `mobile` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `work_status` varchar(150) DEFAULT NULL,
  `income_amount` decimal(12,2) DEFAULT 0.00,
  `need_type` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `poor_families`
--

INSERT INTO `poor_families` (`id`, `file_number`, `head_name`, `id_number`, `members_count`, `mobile`, `address`, `work_status`, `income_amount`, `need_type`, `notes`, `created_at`) VALUES
(240, '1', 'حسن محمد حسن براهمة', '9921068298', 0, '781755129', '', '', 0.00, '', '', '2026-03-09 18:18:11'),
(241, '2', 'أية خميس ابراهيم العمصي', '9882016225', 0, '796177382', '', '', 0.00, '', '', '2026-03-09 18:18:11'),
(242, '3', 'تغريد عايد نبيه الكعابنة', '9782015146', 0, '787250938', '', '', 0.00, '', '', '2026-03-09 18:18:11'),
(243, '4', 'حرب محمد علي الراموني', '9671002043', 0, '780418171', '', '', 0.00, '', '', '2026-03-09 18:18:11'),
(244, '5', 'خليل محمد لطفي ابو رومي', '9751014585', 0, '780306847', '', '', 0.00, '', '', '2026-03-09 18:18:11'),
(245, '6', 'عيسى محمد احمد الشواكري', '9691012630', 0, '789199796', '', '', 0.00, '', '', '2026-03-09 18:18:11'),
(246, '7', 'فاطمة عبد الهادي محمود سلام', '9842041461', 0, '786715750', '', '', 0.00, '', '', '2026-03-09 18:18:11'),
(247, '8', 'محمد حسين سالم أبو عمرة', '9622015966', 0, '781767149', '', '', 0.00, '', '', '2026-03-09 18:18:11'),
(248, '9', 'يوسف محمد يوسف حمدان', '5000030418', 0, '785416020', '', '', 0.00, '', '', '2026-03-09 18:18:11'),
(249, '10', 'رائد محمد حسن ابو راشد', '234073', 0, '795640584', '', '', 0.00, '', '', '2026-03-09 18:18:11');

-- --------------------------------------------------------

--
-- Table structure for table `poor_families_backup_before_cleanup`
--

CREATE TABLE `poor_families_backup_before_cleanup` (
  `id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `file_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `head_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `members_count` int(11) DEFAULT 0,
  `mobile` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_status` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `income_amount` decimal(12,2) DEFAULT 0.00,
  `need_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `poor_families_backup_before_cleanup`
--

INSERT INTO `poor_families_backup_before_cleanup` (`id`, `file_number`, `head_name`, `id_number`, `members_count`, `mobile`, `address`, `work_status`, `income_amount`, `need_type`, `notes`, `created_at`) VALUES
(151, 'حسن محمد حسن براهمة', '9921068298', '781755129', 0, '', '', '', 0.00, '', '', '2026-03-08 19:57:34'),
(152, 'أية خميس ابراهيم العمصي', '9882016225', '796177382', 0, '', '', '', 0.00, '', '', '2026-03-08 19:57:34'),
(153, 'تغريد عايد نبيه الكعابنة', '9782015146', '787250938', 0, '', '', '', 0.00, '', '', '2026-03-08 19:57:34'),
(154, 'حرب محمد علي الراموني', '9671002043', '780418171', 0, '', '', '', 0.00, '', '', '2026-03-08 19:57:34'),
(155, 'خليل محمد لطفي ابو رومي', '9751014585', '780306847', 0, '', '', '', 0.00, '', '', '2026-03-08 19:57:34'),
(156, 'عيسى محمد احمد الشواكري', '9691012630', '789199796', 0, '', '', '', 0.00, '', '', '2026-03-08 19:57:34'),
(157, 'فاطمة عبد الهادي محمود سلام', '9842041461', '786715750', 0, '', '', '', 0.00, '', '', '2026-03-08 19:57:34'),
(158, 'محمد حسين سالم أبو عمرة', '9622015966', '781767149', 0, '', '', '', 0.00, '', '', '2026-03-08 19:57:34'),
(159, 'يوسف محمد يوسف حمدان', '5000030418', '785416020', 0, '', '', '', 0.00, '', '', '2026-03-08 19:57:34'),
(160, 'رائد محمد حسن ابو راشد', '234073', '795640584', 0, '', '', '', 0.00, '', '', '2026-03-08 19:57:34');

-- --------------------------------------------------------

--
-- Table structure for table `poor_families_backup_before_fix`
--

CREATE TABLE `poor_families_backup_before_fix` (
  `id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `file_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `head_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `members_count` int(11) DEFAULT 0,
  `mobile` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_status` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `income_amount` decimal(12,2) DEFAULT 0.00,
  `need_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `poor_families_backup_before_fix`
--

INSERT INTO `poor_families_backup_before_fix` (`id`, `file_number`, `head_name`, `id_number`, `members_count`, `mobile`, `address`, `work_status`, `income_amount`, `need_type`, `notes`, `created_at`) VALUES
(141, 'حسن محمد حسن براهمة', '9921068298', '781755129', 0, '', '', '', 0.00, '', '', '2026-03-08 18:23:29'),
(142, 'أية خميس ابراهيم العمصي', '9882016225', '796177382', 0, '', '', '', 0.00, '', '', '2026-03-08 18:23:29'),
(143, 'تغريد عايد نبيه الكعابنة', '9782015146', '787250938', 0, '', '', '', 0.00, '', '', '2026-03-08 18:23:29'),
(144, 'حرب محمد علي الراموني', '9671002043', '780418171', 0, '', '', '', 0.00, '', '', '2026-03-08 18:23:29'),
(145, 'خليل محمد لطفي ابو رومي', '9751014585', '780306847', 0, '', '', '', 0.00, '', '', '2026-03-08 18:23:29'),
(146, 'عيسى محمد احمد الشواكري', '9691012630', '789199796', 0, '', '', '', 0.00, '', '', '2026-03-08 18:23:29'),
(147, 'فاطمة عبد الهادي محمود سلام', '9842041461', '786715750', 0, '', '', '', 0.00, '', '', '2026-03-08 18:23:29'),
(148, 'محمد حسين سالم أبو عمرة', '9622015966', '781767149', 0, '', '', '', 0.00, '', '', '2026-03-08 18:23:29'),
(149, 'يوسف محمد يوسف حمدان', '5000030418', '785416020', 0, '', '', '', 0.00, '', '', '2026-03-08 18:23:29'),
(150, 'رائد محمد حسن ابو راشد', '234073', '795640584', 0, '', '', '', 0.00, '', '', '2026-03-08 18:23:29');

-- --------------------------------------------------------

--
-- Table structure for table `sponsorships`
--

CREATE TABLE `sponsorships` (
  `id` int(10) UNSIGNED NOT NULL,
  `sponsorship_number` varchar(100) NOT NULL,
  `orphan_name` varchar(255) NOT NULL,
  `beneficiary_id_number` varchar(100) DEFAULT NULL,
  `beneficiary_phone` varchar(50) DEFAULT NULL,
  `sponsor_name` varchar(255) NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'نشطة',
  `payment_method` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sponsorships`
--

INSERT INTO `sponsorships` (`id`, `sponsorship_number`, `orphan_name`, `beneficiary_id_number`, `beneficiary_phone`, `sponsor_name`, `amount`, `start_date`, `end_date`, `status`, `payment_method`, `notes`, `created_at`) VALUES
(31, '1', 'فاطمة علي احمد ابو زيد', '2000273461', '781554696', '', 0.00, NULL, NULL, 'معلقة', '', '', '2026-03-09 18:20:35'),
(32, '2', 'دعاء يوسف مصطفى نصار', '2001822713', '781562548', '', 0.00, NULL, NULL, 'معلقة', '', '', '2026-03-09 18:20:35'),
(33, '3', 'ريم علي محمد الكرد', '5000015109 غزة', '791423567', '', 0.00, NULL, NULL, 'معلقة', '', '', '2026-03-09 18:20:35'),
(34, '4', 'دينا وحيد عبد الله الخطيب', '9842020150', '789843238', '', 0.00, NULL, NULL, 'معلقة', '', '', '2026-03-09 18:20:35'),
(35, '5', 'شفاء حماد علي الحطاب', '9842028216', '789479595', '', 0.00, NULL, NULL, 'معلقة', '', '', '2026-03-09 18:20:35'),
(36, '6', 'بنان يوسف محمود ابو فنونة', '9902023225', '785253838', '', 0.00, NULL, NULL, 'معلقة', '', '', '2026-03-09 18:20:35'),
(37, '7', 'رائدة محمد علي ابو هنية', '9742012550', '787591530', '', 0.00, NULL, NULL, 'معلقة', '', '', '2026-03-09 18:20:35'),
(38, '8', 'أيمان خالد محمود ابو العرج', '9922006765', '787185810', '', 0.00, NULL, NULL, 'معلقة', '', '', '2026-03-09 18:20:35'),
(39, '9', 'باسمة اسماعيل ابراهيم عشيش', '9732018695', '782928987', '', 0.00, NULL, NULL, 'معلقة', '', '', '2026-03-09 18:20:35'),
(40, '10', 'شفه يوسف ابراهيم السدودي', '5000002087 غزة', '785183620', '', 0.00, NULL, NULL, 'معلقة', '', '', '2026-03-09 18:20:35'),
(41, '11', 'رقية احمد محمود العرباتي', '9802013316', '785206474', '', 0.00, NULL, NULL, 'معلقة', '', '', '2026-03-09 18:20:35');

-- --------------------------------------------------------

--
-- Table structure for table `sponsorships_backup_before_cleanup`
--

CREATE TABLE `sponsorships_backup_before_cleanup` (
  `id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sponsorship_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `orphan_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `beneficiary_id_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `beneficiary_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sponsor_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'نشطة',
  `payment_method` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `sponsorships_backup_before_cleanup`
--

INSERT INTO `sponsorships_backup_before_cleanup` (`id`, `sponsorship_number`, `orphan_name`, `beneficiary_id_number`, `beneficiary_phone`, `sponsor_name`, `amount`, `start_date`, `end_date`, `status`, `payment_method`, `notes`, `created_at`) VALUES
(5, 'ايمان اسماعيل موسى ابو جزر', '2000152248', '787230150', '', '', 0.00, NULL, NULL, 'معلقة', '', '', '2026-03-08 19:58:41'),
(6, 'بسمة صالح موسى أبو عشيبة', '9742021097', '7 95101535', '', '', 0.00, NULL, NULL, 'معلقة', '', '', '2026-03-08 19:58:41'),
(7, 'جهاد محمد مسلم دلدوم', '5000019112', '798012692', '', '', 0.00, NULL, NULL, 'معلقة', '', '', '2026-03-08 19:58:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_admin_username` (`username`);

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `beneficiary_distributions`
--
ALTER TABLE `beneficiary_distributions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_beneficiary_distributions_type_date` (`beneficiary_type`,`distribution_date`),
  ADD KEY `idx_beneficiary_distributions_created_by` (`created_by`);

--
-- Indexes for table `beneficiary_distribution_items`
--
ALTER TABLE `beneficiary_distribution_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_beneficiary_distribution_items_distribution_id` (`distribution_id`),
  ADD KEY `idx_beneficiary_distribution_items_beneficiary_id` (`beneficiary_id`);

--
-- Indexes for table `distributions`
--
ALTER TABLE `distributions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_distribution_date` (`distribution_date`),
  ADD KEY `idx_beneficiary_name` (`beneficiary_name`);

--
-- Indexes for table `distribution_sheets`
--
ALTER TABLE `distribution_sheets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sheet_number` (`sheet_number`);

--
-- Indexes for table `distribution_sheet_items`
--
ALTER TABLE `distribution_sheet_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sheet_items_sheet` (`sheet_id`);

--
-- Indexes for table `family_salaries`
--
ALTER TABLE `family_salaries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_family_salaries_salary_number` (`salary_number`),
  ADD KEY `idx_family_salaries_id_number` (`beneficiary_id_number`),
  ADD KEY `idx_family_salaries_phone` (`beneficiary_phone`);

--
-- Indexes for table `orphans`
--
ALTER TABLE `orphans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orphans_file_number` (`file_number`),
  ADD KEY `idx_orphans_name` (`name`);

--
-- Indexes for table `poor_families`
--
ALTER TABLE `poor_families`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pf_file_number` (`file_number`),
  ADD KEY `idx_pf_head_name` (`head_name`);

--
-- Indexes for table `sponsorships`
--
ALTER TABLE `sponsorships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sponsorship_number` (`sponsorship_number`),
  ADD KEY `idx_sponsor_name` (`sponsor_name`),
  ADD KEY `idx_orphan_name` (`orphan_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `attachments`
--
ALTER TABLE `attachments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `beneficiary_distributions`
--
ALTER TABLE `beneficiary_distributions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `beneficiary_distribution_items`
--
ALTER TABLE `beneficiary_distribution_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `distributions`
--
ALTER TABLE `distributions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `distribution_sheets`
--
ALTER TABLE `distribution_sheets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `distribution_sheet_items`
--
ALTER TABLE `distribution_sheet_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `family_salaries`
--
ALTER TABLE `family_salaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `orphans`
--
ALTER TABLE `orphans`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `poor_families`
--
ALTER TABLE `poor_families`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=250;

--
-- AUTO_INCREMENT for table `sponsorships`
--
ALTER TABLE `sponsorships`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `beneficiary_distribution_items`
--
ALTER TABLE `beneficiary_distribution_items`
  ADD CONSTRAINT `fk_beneficiary_distribution_items_distribution` FOREIGN KEY (`distribution_id`) REFERENCES `beneficiary_distributions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `distribution_sheet_items`
--
ALTER TABLE `distribution_sheet_items`
  ADD CONSTRAINT `fk_sheet_items_sheet` FOREIGN KEY (`sheet_id`) REFERENCES `distribution_sheets` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
