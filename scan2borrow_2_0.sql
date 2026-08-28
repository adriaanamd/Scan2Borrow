-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 28, 2026 at 07:27 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `scan2borrow_2.0`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 05:10:56'),
(2, NULL, 'book_borrow', 'Book: Introduction to Algorithms, Code: S2B-20260624-5484D2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 05:11:36'),
(3, NULL, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260624-9F9860', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 05:12:27'),
(4, NULL, 'book_return', 'Book: It Ends with Us, Code: S2B-20260624-9F9860', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 05:17:11'),
(5, NULL, 'book_borrow', 'Book: Project Hail Mary, Code: S2B-20260624-DA55B3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 05:20:27'),
(6, NULL, 'book_return_bulk', 'Transaction: S2B-20260622-525DD1, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 05:28:59'),
(7, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-DA55B3, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 05:29:27'),
(8, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-5484D2, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 05:29:45'),
(9, NULL, 'book_borrow', 'Book: Global Times Living History: Mga Kontemporaneong Isyu, Code: S2B-20260624-24A7A0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:04:18'),
(10, NULL, 'book_borrow', 'Book: Introduction to Algorithms, Code: S2B-20260624-944111', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:05:51'),
(11, NULL, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260624-67A54D', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:13:52'),
(12, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-24A7A0, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:14:10'),
(13, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-944111, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:14:27'),
(14, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-67A54D, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:14:42'),
(15, NULL, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260624-24E719', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:15:43'),
(16, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-24E719, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:18:55'),
(17, NULL, 'book_borrow', 'Book: Global Times Living History: Mga Kontemporaneong Isyu, Code: S2B-20260624-4A4829', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:19:14'),
(18, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-4A4829, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:19:36'),
(19, NULL, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260624-ECED01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:20:28'),
(20, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-ECED01, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:25:44'),
(21, NULL, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260624-11FAFE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:30:32'),
(22, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-11FAFE, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:41:08'),
(23, NULL, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260624-497311', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:41:35'),
(24, NULL, 'book_borrow', 'Book: Global Times Living History: Mga Kontemporaneong Isyu, Code: S2B-20260624-EE30AD', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:41:50'),
(25, NULL, 'book_borrow', 'Book: The Alchemist, Code: S2B-20260624-747A6E', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:42:42'),
(26, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-497311, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:43:09'),
(27, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-EE30AD, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:43:24'),
(28, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-747A6E, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:43:40'),
(29, NULL, 'book_borrow', 'Book: Global Times Living History: Mga Kontemporaneong Isyu, Code: S2B-20260624-CEDE68', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:48:49'),
(30, NULL, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260624-4E1F2C', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:53:29'),
(31, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-CEDE68, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:57:20'),
(32, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-4E1F2C, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 06:57:41'),
(33, NULL, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260624-EB440C', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 07:02:23'),
(34, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 09:08:26'),
(35, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 09:10:56'),
(36, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 09:39:51'),
(37, NULL, 'login_success', 'Barcode: 230419', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 09:40:46'),
(38, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 09:43:09'),
(39, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 10:41:38'),
(40, 1, 'book_archive', 'IDs: 7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 10:41:50'),
(41, 1, 'book_restore', 'IDs: 7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 10:41:57'),
(42, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 10:55:03'),
(43, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 16:44:33'),
(44, 1, 'disable_borrowing', 'User ID: 17, New Status: inactive', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 16:45:17'),
(45, NULL, 'book_borrow', 'Book: Introduction to Algorithms, Code: S2B-20260625-BA039D', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 16:45:52'),
(46, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 16:49:58'),
(47, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-24 17:14:56'),
(48, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 09:36:58'),
(49, NULL, 'book_borrow', 'Book: Global Times Living History: Mga Kontemporaneong Isyu, Code: S2B-20260626-10B7FD, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 09:53:16'),
(50, NULL, 'book_return_bulk', 'Transaction: S2B-20260624-EB440C, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 09:55:35'),
(51, NULL, 'book_borrow', 'Book: Global Times Living History: Mga Kontemporaneong Isyu, Code: S2B-20260626-7E4E77, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 09:55:45'),
(52, NULL, 'book_borrow', 'Book: Global Times Living History: Mga Kontemporaneong Isyu, Code: S2B-20260626-E3991B, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 09:59:52'),
(53, NULL, 'book_borrow', 'Book: Global Times Living History: Mga Kontemporaneong Isyu, Code: S2B-20260626-BDCF56, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:00:41'),
(54, NULL, 'book_borrow', 'Book: Global Times Living History: Mga Kontemporaneong Isyu, Code: S2B-20260626-3BC59F, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:01:28'),
(55, NULL, 'book_borrow', 'Book: Global Times Living History: Mga Kontemporaneong Isyu, Code: S2B-20260626-438666, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:03:25'),
(56, 1, 'borrow_reject', 'Rejected borrowing ID: 20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:03:52'),
(57, 1, 'borrow_reject', 'Rejected borrowing ID: 21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:03:55'),
(58, 1, 'borrow_reject', 'Rejected borrowing ID: 22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:03:58'),
(59, 1, 'borrow_reject', 'Rejected borrowing ID: 23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:04:01'),
(60, 1, 'borrow_reject', 'Rejected borrowing ID: 24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:04:04'),
(61, 1, 'borrow_reject', 'Rejected borrowing ID: 25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:04:07'),
(62, 1, 'borrow_reject', 'Rejected borrowing ID: 25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:04:15'),
(63, NULL, 'book_borrow', 'Book: Global Times Living History: Mga Kontemporaneong Isyu, Code: S2B-20260626-8E6E18, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:04:22'),
(64, 1, 'borrow_reject', 'Rejected borrowing ID: 25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:04:30'),
(65, 1, 'borrow_approve', 'Approved borrowing ID: 26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:04:34'),
(66, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-BDCF56, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:09:38'),
(67, NULL, 'book_return_bulk', 'Transaction: S2B-20260625-BA039D, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:09:57'),
(68, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-10B7FD, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:10:16'),
(69, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-7E4E77, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:10:31'),
(70, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-E3991B, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:10:49'),
(71, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-3BC59F, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:11:07'),
(72, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-438666, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:11:21'),
(73, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-8E6E18, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:11:36'),
(74, NULL, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260626-06FB09, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:14:16'),
(75, 1, 'borrow_approve', 'Approved borrowing ID: 27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 10:14:44'),
(76, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-06FB09, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:14:04'),
(77, NULL, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260626-BEC7AB, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:14:50'),
(78, 1, 'borrow_approve', 'Approved borrowing ID: 28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:15:04'),
(79, 1, 'borrow_approve', 'Approved borrowing ID: 28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:15:38'),
(80, NULL, 'book_borrow', 'Book: Global Times Living History: Mga Kontemporaneong Isyu, Code: S2B-20260626-294ED7, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:18:55'),
(81, 1, 'borrow_approve', 'Approved borrowing ID: 29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:22:45'),
(82, 1, 'borrow_approve', 'Approved borrowing ID: 29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:23:34'),
(83, 1, 'borrow_approve', 'Approved borrowing ID: 29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:24:20'),
(84, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-BEC7AB, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:26:03'),
(85, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-294ED7, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:26:27'),
(86, NULL, 'book_borrow', 'Book: Project Hail Mary, Code: S2B-20260626-F34763, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:26:54'),
(87, 1, 'borrow_approve', 'Approved borrowing ID: 30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:27:14'),
(88, NULL, 'book_borrow', 'Book: The Alchemist, Code: S2B-20260626-685153, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:30:57'),
(89, 1, 'borrow_approve', 'Approved borrowing ID: 31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:31:07'),
(90, 1, 'borrow_approve', 'Approved borrowing ID: 31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:31:21'),
(91, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-F34763, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:32:05'),
(92, NULL, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260626-44FBC5, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:32:41'),
(93, 1, 'borrow_approve', 'Approved borrowing ID: 32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:32:51'),
(94, NULL, 'book_borrow', 'Book: Global Times Living History: Mga Kontemporaneong Isyu, Code: S2B-20260626-0E4B5C, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:33:19'),
(95, 1, 'borrow_approve', 'Approved borrowing ID: 33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:33:26'),
(96, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-685153, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:42:35'),
(97, NULL, 'book_borrow', 'Book: Introduction to Algorithms, Code: S2B-20260626-D13FE3, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:44:38'),
(98, 1, 'borrow_approve', 'Approved borrowing ID: 34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:45:02'),
(99, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-44FBC5, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:50:11'),
(100, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-0E4B5C, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:50:34'),
(101, NULL, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260626-156AAC, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:50:48'),
(102, 1, 'borrow_approve', 'Approved borrowing ID: 35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:50:55'),
(103, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-D13FE3, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:54:24'),
(104, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-156AAC, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:54:42'),
(105, NULL, 'book_borrow', 'Book: The Psychology of Money, Code: S2B-20260626-0B51D8, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:56:17'),
(106, 1, 'borrow_approve', 'Approved borrowing ID: 36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:56:29'),
(107, NULL, 'book_borrow', 'Book: Atomic Habits, Code: S2B-20260626-F25637, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 13:59:59'),
(108, 1, 'borrow_approve', 'Approved borrowing ID: 37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 14:00:05'),
(109, NULL, 'book_return_bulk', 'Transaction: S2B-20260626-0B51D8, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 14:00:42'),
(110, NULL, 'book_borrow', 'Book: Verity, Code: S2B-20260626-EE4228, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 14:01:19'),
(111, 1, 'borrow_approve', 'Approved borrowing ID: 38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 14:01:30'),
(112, NULL, 'book_borrow', 'Book: The Midnight Library, Code: S2B-20260626-6FDF97, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 14:01:59'),
(113, 1, 'borrow_approve', 'Approved borrowing ID: 39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 14:02:25'),
(114, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 06:29:05'),
(115, 21, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260627-8CAFDC, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 07:13:36'),
(116, 21, 'book_return_teacher', 'Transaction: S2B-20260627-8CAFDC, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 07:32:35'),
(117, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 08:08:07'),
(118, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 08:26:25'),
(119, 1, 'book_create', 'Book ID: 162, Title: Test', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 08:34:21'),
(120, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 08:41:25'),
(121, 1, 'book_create', 'Book ID: 163, Title: Testing', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 08:42:12'),
(122, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 08:49:13'),
(123, 1, 'book_archive', 'IDs: 163', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 08:49:20'),
(124, 1, 'book_archive', 'IDs: 162', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 08:49:24'),
(125, 1, 'book_create', 'Book ID: 164, Title: Test 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 08:49:49'),
(126, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 08:57:27'),
(127, 1, 'book_archive', 'IDs: 164', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 08:57:36'),
(128, 1, 'book_create', 'Book ID: 165, Title: Test 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 08:58:50'),
(129, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 09:15:30'),
(130, 1, 'book_archive', 'IDs: 165', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 09:15:36'),
(131, 1, 'book_create', 'Book ID: 166, Title: Test 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 09:30:17'),
(132, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 09:37:16'),
(133, 1, 'book_update', 'Book ID: 166, Title: Test 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 09:37:37'),
(134, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 09:42:59'),
(135, 1, 'book_update', 'Book ID: 166, Title: Test 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 09:57:21'),
(136, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 11:46:45'),
(137, 1, 'book_update', 'Book ID: 7, Title: The Psychology of Money', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:20:11'),
(138, 1, 'book_update', 'Book ID: 7, Title: The Psychology of Money', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:20:54'),
(139, 1, 'book_update', 'Book ID: 7, Title: The Psychology of Money', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:21:21'),
(140, 1, 'book_update', 'Book ID: 7, Title: The Psychology of Money', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:21:30'),
(141, 1, 'book_update', 'Book ID: 7, Title: The Psychology of Money', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:28:59'),
(142, 1, 'book_update', 'Book ID: 7, Title: The Psychology of Money', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:29:21'),
(143, 1, 'book_update', 'Book ID: 7, Title: The Psychology of Money', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:39:13'),
(144, 1, 'book_update', 'Book ID: 8, Title: Atomic Habits', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:41:10'),
(145, 1, 'book_update', 'Book ID: 9, Title: The Midnight Library', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:41:27'),
(146, 1, 'book_update', 'Book ID: 10, Title: Project Hail Mary', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:41:38'),
(147, 1, 'book_update', 'Book ID: 11, Title: The Alchemist', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:41:54'),
(148, 1, 'book_update', 'Book ID: 12, Title: It Ends with Us', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:42:05'),
(149, 1, 'book_update', 'Book ID: 13, Title: Verity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:42:15'),
(150, 1, 'book_update', 'Book ID: 14, Title: Fourth Wing', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:44:23'),
(151, 1, 'book_update', 'Book ID: 15, Title: Iron Flame', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:44:34'),
(152, 1, 'book_update', 'Book ID: 16, Title: The House in the Cerulean Sea', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 12:47:21'),
(153, 1, 'book_update', 'Book ID: 25, Title: Research Methods in Education', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 13:12:12'),
(154, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 13:47:21'),
(155, 1, 'book_update', 'Book ID: 12, Title: It Ends with Us', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 13:47:45'),
(156, 1, 'book_update', 'Book ID: 35, Title: 1984', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 13:48:39'),
(157, 1, 'book_update', 'Book ID: 33, Title: Algebra and Trigonometry', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 13:49:09'),
(158, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-27 14:02:09'),
(159, 23, 'book_borrow', 'Book: Clean Code, Code: S2B-20260629-DE4F65, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 01:26:01'),
(160, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 01:26:33'),
(161, 1, 'borrow_approve', 'Approved borrowing ID: 41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 01:28:21'),
(162, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 01:52:17'),
(163, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-04 14:39:09'),
(164, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-05 08:00:31'),
(165, 23, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260805-CBAFF0, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-05 08:01:07'),
(166, 23, 'book_borrow', 'Book: Introduction to Algorithms, Code: S2B-20260805-D39B78, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-05 08:04:14'),
(167, 1, 'borrow_approve', 'Approved borrowing ID: 43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-05 08:04:48'),
(168, 1, 'borrow_approve', 'Approved borrowing ID: 42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-05 08:04:52'),
(169, 23, 'book_return_bulk', 'Transaction: S2B-20260805-D39B78, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-05 08:09:22'),
(170, 23, 'book_return_bulk', 'Transaction: S2B-20260805-CBAFF0, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-05 08:09:39'),
(171, 23, 'book_return_bulk', 'Transaction: S2B-20260629-DE4F65, Books: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-05 08:09:55'),
(172, 23, 'book_borrow', 'Book: Introduction to Algorithms, Code: S2B-20260805-3C4CD7, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-05 08:16:23'),
(173, 23, 'book_borrow', 'Book: The Alchemist, Code: S2B-20260805-90131A, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-05 08:16:44'),
(174, 1, 'login_success', 'Barcode: ADMIN001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-21 04:38:11'),
(175, 1, 'borrow_approve', 'Approved borrowing ID: 45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-21 04:47:00'),
(176, 1, 'borrow_approve', 'Approved borrowing ID: 44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-21 04:47:05'),
(177, 25, 'book_borrow', 'Book: It Ends with Us, Code: S2B-20260821-77FF7B, Status: Pending, Approval: pending', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-21 04:55:43');

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `barcode` varchar(50) NOT NULL,
  `accession_no` varchar(50) DEFAULT NULL,
  `isbn` varchar(30) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `author` varchar(150) DEFAULT NULL,
  `publisher` varchar(150) DEFAULT NULL,
  `category_name` varchar(100) NOT NULL,
  `cover_file` varchar(255) DEFAULT NULL,
  `floor_no` varchar(20) DEFAULT NULL,
  `section_name` varchar(80) DEFAULT NULL,
  `shelf_no` varchar(20) DEFAULT NULL,
  `row_no` varchar(20) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('Available','Borrowed','Reserved') NOT NULL DEFAULT 'Available',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `barcode`, `accession_no`, `isbn`, `title`, `author`, `publisher`, `category_name`, `cover_file`, `floor_no`, `section_name`, `shelf_no`, `row_no`, `due_date`, `return_date`, `status`, `deleted_at`, `created_at`, `description`) VALUES
(1, 'BK-0001', NULL, '9780262033848', 'Introduction to Algorithms', 'Cormen et al.', NULL, 'Computer Science', NULL, '2', 'IT Section', 'A1', '1', NULL, NULL, 'Borrowed', NULL, '2026-06-21 07:48:26', NULL),
(2, 'BK-0002', NULL, '9780132350884', 'Clean Code', 'Robert C. Martin', NULL, 'Computer Science', NULL, '2', 'IT Section', 'A1', '2', NULL, NULL, 'Available', NULL, '2026-06-21 07:48:26', NULL),
(3, 'BK-0003', NULL, '9780596007126', 'Head First Design Patterns', 'Freeman & Robson', NULL, 'Computer Science', NULL, '2', 'IT Section', 'A2', '1', NULL, NULL, 'Available', NULL, '2026-06-21 07:48:26', NULL),
(4, 'BK-0004', NULL, '9780743273565', 'The Great Gatsby', 'F. Scott Fitzgerald', NULL, 'Literature', NULL, '1', 'Fiction Section', 'B3', '2', NULL, NULL, 'Available', NULL, '2026-06-21 07:48:26', NULL),
(5, 'BK-0005', NULL, '9780061120084', 'To Kill a Mockingbird', 'Harper Lee', NULL, 'Literature', NULL, '1', 'Fiction Section', 'B3', '3', NULL, NULL, 'Available', NULL, '2026-06-21 07:48:26', NULL),
(6, '615', NULL, '9786210021042', 'Global Times Living History: Mga Kontemporaneong Isyu', 'Diana Lyn R. Sarenas', 'Sibs Publishing House', 'Education', NULL, '2', '5', 'B7', '3', '2026-06-23', '2026-06-24', 'Available', NULL, '2026-06-21 09:42:04', NULL),
(7, 'BK-2001', NULL, '9780593419064', 'The Psychology of Money', 'Morgan Housel', 'Harriman House', 'Non-Fiction', '', '2', 'General Section', 'C1', '1', '2026-06-27', '2026-06-29', 'Available', NULL, '2026-06-24 05:03:31', ''),
(8, 'BK-2002', NULL, '9780593082090', 'Atomic Habits', 'James Clear', 'Avery', 'Non-Fiction', '', '2', 'General Section', 'C1', '2', NULL, NULL, 'Borrowed', NULL, '2026-06-24 05:03:31', ''),
(9, 'BK-2003', NULL, '9780735211292', 'The Midnight Library', 'Matt Haig', 'Viking', 'Non-Fiction', '', '1', 'Fiction Section', 'D1', '1', NULL, NULL, 'Borrowed', NULL, '2026-06-24 05:03:31', ''),
(10, 'BK-2004', NULL, '9780593156608', 'Project Hail Mary', 'Andy Weir', 'Ballantine Books', 'Non-Fiction', '', '2', 'Science Section', 'E1', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', ''),
(11, 'BK-2005', NULL, '9780593329191', 'The Alchemist', 'Paulo Coelho', 'HarperOne', 'Non-Fiction', '', '1', 'Fiction Section', 'D1', '2', NULL, NULL, 'Borrowed', NULL, '2026-06-24 05:03:31', ''),
(12, 'BK-2006', NULL, '9780593336051', 'It Ends with Us', 'Colleen Hoover', 'Atria Books', 'Non-Fiction', 'uploads/photos/BK-2006-1782568065-6f6bd980.jpg', '1', 'Fiction Section', 'D2', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', ''),
(13, 'BK-2007', NULL, '9780593336052', 'Verity', 'Colleen Hoover', 'Grand Central Publishing', 'Non-Fiction', '', '1', 'Fiction Section', 'D2', '2', NULL, NULL, 'Borrowed', NULL, '2026-06-24 05:03:31', ''),
(14, 'BK-2008', NULL, '9780593530454', 'Fourth Wing', 'Rebecca Yarros', 'Red Tower Books', 'Non-Fiction', '', '2', 'Fantasy Section', 'F1', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', ''),
(15, 'BK-2009', NULL, '9780593530455', 'Iron Flame', 'Rebecca Yarros', 'Red Tower Books', 'Non-Fiction', '', '2', 'Fantasy Section', 'F1', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', ''),
(16, 'BK-2010', NULL, '9780593594166', 'The House in the Cerulean Sea', 'TJ Klune', 'Tor Books', 'Non-Fiction', '', '2', 'Fantasy Section', 'F2', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', ''),
(17, 'BK-3001', NULL, '9781234567890', 'National Geographic - Wonders of the World', 'Various Authors', 'National Geographic Society', 'Magazine Pocket Books', NULL, '1', 'Magazine Section', 'M1', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(18, 'BK-3002', NULL, '9781234567891', 'Time Magazine - Greatest Discoveries', 'Various Authors', 'Time Inc.', 'Magazine Pocket Books', NULL, '1', 'Magazine Section', 'M1', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(19, 'BK-3003', NULL, '9781234567892', 'Reader\'s Digest - Best Stories', 'Various Authors', 'Reader\'s Digest', 'Magazine Pocket Books', NULL, '1', 'Magazine Section', 'M2', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(20, 'BK-3004', NULL, '9781234567893', 'Scientific American - Mind & Brain', 'Various Authors', 'Scientific American', 'Magazine Pocket Books', NULL, '2', 'Science Section', 'S2', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(21, 'BK-3005', NULL, '9781234567894', 'Popular Science - Future Tech', 'Various Authors', 'Popular Science', 'Magazine Pocket Books', NULL, '2', 'Science Section', 'S2', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(22, 'BK-3006', NULL, '9781234567895', 'Forbes - Business Leaders', 'Various Authors', 'Forbes Media', 'Magazine Pocket Books', NULL, '2', 'Business Section', 'B1', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(23, 'BK-3007', NULL, '9781234567896', 'Sports Illustrated - Greatest Moments', 'Various Authors', 'Sports Illustrated', 'Magazine Pocket Books', NULL, '3', 'Sports Section', 'SP1', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(24, 'BK-3008', NULL, '9781234567897', 'Vogue - Fashion Through the Decades', 'Various Authors', 'Condé Nast', 'Magazine Pocket Books', NULL, '1', 'Magazine Section', 'M3', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(25, 'BK-4001', NULL, '9780262033848', 'Research Methods in Education', 'Louis Cohen', 'Routledge', 'Research', '', '3', 'Reference Section', 'R1', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', ''),
(26, 'BK-4002', NULL, '9781452257872', 'Doing Qualitative Research', 'David Silverman', 'SAGE Publications', 'Research Books', NULL, '3', 'Reference Section', 'R1', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(27, 'BK-4003', NULL, '9781412972121', 'Survey Research Methods', 'Floyd J. Fowler', 'SAGE Publications', 'Research Books', NULL, '3', 'Reference Section', 'R2', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(28, 'BK-4004', NULL, '9780198754830', 'Statistical Methods for Research', 'Robert G. D. Steel', 'Oxford University Press', 'Research Books', NULL, '3', 'Reference Section', 'R2', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(29, 'BK-4005', NULL, '9788132214103', 'Case Study Research and Applications', 'Robert K. Yin', 'SAGE Publications', 'Research Books', NULL, '3', 'Reference Section', 'R3', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(30, 'BK-4006', NULL, '9780262534786', 'The Craft of Research', 'Wayne C. Booth', 'University of Chicago Press', 'Research Books', NULL, '3', 'Reference Section', 'R3', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(31, 'BK-4007', NULL, '9781446269078', 'How to Write a Thesis', 'Rowena Murray', 'SAGE Publications', 'Research Books', NULL, '3', 'Reference Section', 'R4', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(32, 'BK-4008', NULL, '9780134685991', 'Data Science from Scratch', 'Joel Grus', 'O\'Reilly Media', 'Research Books', NULL, '3', 'Reference Section', 'R4', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(33, 'BK-5001', NULL, '9780133186126', 'Algebra and Trigonometry', 'Robert F. Blitzer', 'Pearson', 'Mathematics', 'uploads/photos/BK-5001-1782568149-5441e29e.webp', '1', 'Math Section', 'HS1', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', ''),
(34, 'BK-5002', NULL, '9780545582997', 'The Great Gatsby', 'F. Scott Fitzgerald', 'Scholastic', 'High School', NULL, '1', 'English Section', 'HS2', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(35, 'BK-5003', NULL, '9780451524935', '1984', 'George Orwell', 'Signet Classic', 'Non-Fiction', 'uploads/photos/BK-5003-1782568119-23548661.webp', '1', 'English Section', 'HS2', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', ''),
(36, 'BK-5004', NULL, '9780060850524', 'To Kill a Mockingbird', 'Harper Lee', 'HarperCollins', 'High School', NULL, '1', 'English Section', 'HS3', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(37, 'BK-5005', NULL, '9780140283297', 'Of Mice and Men', 'John Steinbeck', 'Penguin Books', 'High School', NULL, '1', 'English Section', 'HS3', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(38, 'BK-5006', NULL, '9780547243653', 'The Giver', 'Lois Lowry', 'Houghton Mifflin', 'High School', NULL, '1', 'English Section', 'HS4', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(39, 'BK-5007', NULL, '9780439023528', 'The Hunger Games', 'Suzanne Collins', 'Scholastic', 'High School', NULL, '1', 'English Section', 'HS4', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(40, 'BK-5008', NULL, '9780547928227', 'The Hobbit', 'J.R.R. Tolkien', 'Houghton Mifflin', 'High School', NULL, '1', 'English Section', 'HS5', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(41, 'BK-5009', NULL, '9780439554930', 'Harry Potter and the Sorcerer\'s Stone', 'J.K. Rowling', 'Scholastic', 'High School', NULL, '1', 'English Section', 'HS5', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(42, 'BK-5010', NULL, '9780316769488', 'The Catcher in the Rye', 'J.D. Salinger', 'Little, Brown', 'High School', NULL, '1', 'English Section', 'HS6', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(43, 'BK-5011', NULL, '9780062315009', 'The Fault in Our Stars', 'John Green', 'Dutton Books', 'High School', NULL, '1', 'English Section', 'HS6', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(44, 'BK-5012', NULL, '9780142407332', 'The Outsiders', 'S.E. Hinton', 'Penguin Books', 'High School', NULL, '1', 'English Section', 'HS7', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(45, 'BK-6001', NULL, '9780134292380', 'Calculus: Early Transcendentals', 'James Stewart', 'Cengage Learning', 'Senior High School', NULL, '1', 'Math Section', 'SHS1', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(46, 'BK-6002', NULL, '9781118475004', 'Physics for Scientists and Engineers', 'Raymond A. Serway', 'Cengage Learning', 'Senior High School', NULL, '2', 'Science Section', 'SHS2', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(47, 'BK-6003', NULL, '9780134416532', 'Chemistry: The Central Science', 'Theodore E. Brown', 'Pearson', 'Senior High School', NULL, '2', 'Science Section', 'SHS2', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(48, 'BK-6004', NULL, '9781285415652', 'Biology', 'Peter J. Raven', 'Cengage Learning', 'Senior High School', NULL, '2', 'Science Section', 'SHS3', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(49, 'BK-6005', NULL, '9780134292381', 'Statistics for Business and Economics', 'James T. McClave', 'Pearson', 'Senior High School', NULL, '1', 'Math Section', 'SHS1', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(50, 'BK-6006', NULL, '9780134256901', 'Discrete Mathematics and Its Applications', 'Kenneth H. Rosen', 'McGraw-Hill', 'Senior High School', NULL, '1', 'Math Section', 'SHS3', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(51, 'BK-6007', NULL, '9780134444320', 'Fundamentals of Database Systems', 'Ramez Elmasri', 'Pearson', 'Senior High School', NULL, '2', 'Computer Section', 'SHS4', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(52, 'BK-6008', NULL, '9780134685992', 'Introduction to Algorithms', 'Thomas H. Cormen', 'MIT Press', 'Senior High School', NULL, '2', 'Computer Section', 'SHS4', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(53, 'BK-6009', NULL, '9780134685993', 'Computer Systems: A Programmer\'s Perspective', 'Randal E. Bryant', 'Prentice Hall', 'Senior High School', NULL, '2', 'Computer Section', 'SHS5', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(54, 'BK-6010', NULL, '9780134685994', 'Clean Code: A Handbook of Agile Software Craftsmanship', 'Robert C. Martin', 'Prentice Hall', 'Senior High School', NULL, '2', 'Computer Section', 'SHS5', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(55, 'BK-6011', NULL, '9780134685995', 'The Pragmatic Programmer', 'David Thomas', 'Addison-Wesley', 'Senior High School', NULL, '2', 'Computer Section', 'SHS6', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(56, 'BK-6012', NULL, '9780134685996', 'Design Patterns: Elements of Reusable Object-Oriented Software', 'Erich Gamma', 'Addison-Wesley', 'Senior High School', NULL, '2', 'Computer Section', 'SHS6', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(57, 'BK-6013', NULL, '9780134685997', 'Introduction to Java Programming', 'Y. Daniel Liang', 'Pearson', 'Senior High School', NULL, '2', 'Computer Section', 'SHS7', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(58, 'BK-6014', NULL, '9780134685998', 'Python Crash Course', 'Eric Matthes', 'No Starch Press', 'Senior High School', NULL, '2', 'Computer Section', 'SHS7', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(59, 'BK-6015', NULL, '9780134685999', 'Web Design with HTML, CSS, JavaScript', 'Jon Duckett', 'Wiley', 'Senior High School', NULL, '2', 'Computer Section', 'SHS8', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(60, 'BK-6016', NULL, '9780134686000', 'Digital Logic and Computer Design', 'M. Morris Mano', 'Pearson', 'Senior High School', NULL, '2', 'Computer Section', 'SHS8', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(61, 'BK-6017', NULL, '9780134686001', 'Networking Essentials', 'Behrouz A. Forouzan', 'McGraw-Hill', 'Senior High School', NULL, '2', 'Computer Section', 'SHS9', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(62, 'BK-6018', NULL, '9780134686002', 'Database Management Systems', 'Raghu Ramakrishnan', 'McGraw-Hill', 'Senior High School', NULL, '2', 'Computer Section', 'SHS9', '2', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL),
(63, 'BK-6019', NULL, '9780134686003', 'Software Engineering: A Practitioner\'s Approach', 'Roger S. Pressman', 'McGraw-Hill', 'Senior High School', NULL, '2', 'Computer Section', 'SHS10', '1', NULL, NULL, 'Available', NULL, '2026-06-24 05:03:31', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `book_keywords`
--

CREATE TABLE `book_keywords` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `keyword_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_keywords`
--

INSERT INTO `book_keywords` (`id`, `book_id`, `keyword_id`, `created_at`) VALUES
(35, 20, 2, '2026-06-24 05:03:31'),
(39, 26, 51, '2026-06-24 05:03:31'),
(40, 26, 50, '2026-06-24 05:03:31'),
(41, 26, 47, '2026-06-24 05:03:31'),
(42, 27, 53, '2026-06-24 05:03:31'),
(43, 27, 47, '2026-06-24 05:03:31'),
(44, 27, 52, '2026-06-24 05:03:31'),
(45, 28, 54, '2026-06-24 05:03:31'),
(46, 28, 53, '2026-06-24 05:03:31'),
(47, 28, 55, '2026-06-24 05:03:31'),
(48, 29, 56, '2026-06-24 05:03:31'),
(49, 29, 49, '2026-06-24 05:03:31'),
(50, 29, 50, '2026-06-24 05:03:31'),
(51, 30, 58, '2026-06-24 05:03:31'),
(52, 30, 47, '2026-06-24 05:03:31'),
(53, 30, 57, '2026-06-24 05:03:31'),
(54, 31, 58, '2026-06-24 05:03:31'),
(55, 31, 59, '2026-06-24 05:03:31'),
(56, 31, 57, '2026-06-24 05:03:31'),
(57, 32, 54, '2026-06-24 05:03:31'),
(58, 32, 60, '2026-06-24 05:03:31'),
(59, 32, 61, '2026-06-24 05:03:31'),
(60, 34, 8, '2026-06-24 05:03:31'),
(62, 36, 8, '2026-06-24 05:03:31'),
(63, 37, 8, '2026-06-24 05:03:31'),
(64, 39, 8, '2026-06-24 05:03:31'),
(65, 40, 19, '2026-06-24 05:03:31'),
(66, 41, 19, '2026-06-24 05:03:31'),
(67, 42, 8, '2026-06-24 05:03:31'),
(68, 43, 8, '2026-06-24 05:03:31'),
(69, 43, 15, '2026-06-24 05:03:31'),
(71, 44, 8, '2026-06-24 05:03:31'),
(82, 17, 145, '2026-06-24 05:04:54'),
(83, 17, 146, '2026-06-24 05:04:54'),
(84, 17, 147, '2026-06-24 05:04:54'),
(85, 17, 148, '2026-06-24 05:04:54'),
(89, 18, 149, '2026-06-24 05:04:54'),
(90, 18, 150, '2026-06-24 05:04:54'),
(91, 18, 154, '2026-06-24 05:04:54'),
(92, 19, 153, '2026-06-24 05:04:54'),
(93, 19, 152, '2026-06-24 05:04:54'),
(94, 19, 151, '2026-06-24 05:04:54'),
(95, 20, 155, '2026-06-24 05:04:54'),
(96, 20, 156, '2026-06-24 05:04:54'),
(97, 20, 154, '2026-06-24 05:04:54'),
(98, 21, 158, '2026-06-24 05:04:54'),
(99, 21, 159, '2026-06-24 05:04:54'),
(100, 21, 157, '2026-06-24 05:04:54'),
(101, 22, 160, '2026-06-24 05:04:54'),
(102, 22, 161, '2026-06-24 05:04:54'),
(103, 22, 162, '2026-06-24 05:04:54'),
(104, 23, 164, '2026-06-24 05:04:54'),
(105, 23, 150, '2026-06-24 05:04:54'),
(106, 23, 163, '2026-06-24 05:04:54'),
(107, 24, 167, '2026-06-24 05:04:54'),
(108, 24, 165, '2026-06-24 05:04:54'),
(109, 24, 166, '2026-06-24 05:04:54'),
(121, 34, 187, '2026-06-24 05:04:54'),
(122, 34, 152, '2026-06-24 05:04:54'),
(127, 36, 152, '2026-06-24 05:04:54'),
(128, 36, 189, '2026-06-24 05:04:54'),
(130, 37, 190, '2026-06-24 05:04:54'),
(131, 37, 152, '2026-06-24 05:04:54'),
(133, 38, 188, '2026-06-24 05:04:54'),
(134, 38, 152, '2026-06-24 05:04:54'),
(135, 38, 191, '2026-06-24 05:04:54'),
(136, 39, 188, '2026-06-24 05:04:54'),
(137, 39, 191, '2026-06-24 05:04:54'),
(139, 40, 192, '2026-06-24 05:04:54'),
(140, 40, 152, '2026-06-24 05:04:54'),
(142, 41, 193, '2026-06-24 05:04:54'),
(143, 41, 191, '2026-06-24 05:04:54'),
(145, 42, 194, '2026-06-24 05:04:54'),
(146, 42, 152, '2026-06-24 05:04:54'),
(148, 43, 191, '2026-06-24 05:04:54'),
(149, 44, 194, '2026-06-24 05:04:54'),
(150, 44, 152, '2026-06-24 05:04:54'),
(152, 45, 199, '2026-06-24 05:04:54'),
(153, 45, 198, '2026-06-24 05:04:54'),
(154, 45, 186, '2026-06-24 05:04:54'),
(155, 46, 201, '2026-06-24 05:04:54'),
(156, 46, 200, '2026-06-24 05:04:54'),
(157, 46, 154, '2026-06-24 05:04:54'),
(158, 47, 202, '2026-06-24 05:04:54'),
(159, 47, 203, '2026-06-24 05:04:54'),
(160, 47, 154, '2026-06-24 05:04:54'),
(202, 48, 204, '2026-06-24 05:08:28'),
(203, 48, 205, '2026-06-24 05:08:28'),
(204, 48, 206, '2026-06-24 05:08:28'),
(205, 49, 160, '2026-06-24 05:08:28'),
(206, 49, 207, '2026-06-24 05:08:28'),
(207, 49, 55, '2026-06-24 05:08:28'),
(208, 50, 209, '2026-06-24 05:08:28'),
(209, 50, 208, '2026-06-24 05:08:28'),
(210, 50, 210, '2026-06-24 05:08:28'),
(211, 51, 209, '2026-06-24 05:08:28'),
(212, 51, 211, '2026-06-24 05:08:28'),
(213, 51, 61, '2026-06-24 05:08:28'),
(214, 52, 212, '2026-06-24 05:08:28'),
(215, 52, 209, '2026-06-24 05:08:28'),
(216, 52, 61, '2026-06-24 05:08:28'),
(217, 53, 214, '2026-06-24 05:08:28'),
(218, 53, 213, '2026-06-24 05:08:28'),
(219, 53, 61, '2026-06-24 05:08:28'),
(220, 54, 216, '2026-06-24 05:08:28'),
(221, 54, 61, '2026-06-24 05:08:28'),
(222, 54, 215, '2026-06-24 05:08:28'),
(223, 55, 218, '2026-06-24 05:08:28'),
(224, 55, 61, '2026-06-24 05:08:28'),
(225, 55, 217, '2026-06-24 05:08:28'),
(226, 56, 219, '2026-06-24 05:08:28'),
(227, 56, 220, '2026-06-24 05:08:28'),
(228, 56, 215, '2026-06-24 05:08:28'),
(229, 57, 209, '2026-06-24 05:08:28'),
(230, 57, 221, '2026-06-24 05:08:28'),
(231, 57, 61, '2026-06-24 05:08:28'),
(232, 58, 223, '2026-06-24 05:08:28'),
(233, 58, 61, '2026-06-24 05:08:28'),
(234, 58, 222, '2026-06-24 05:08:28'),
(235, 59, 226, '2026-06-24 05:08:28'),
(236, 59, 225, '2026-06-24 05:08:28'),
(237, 59, 227, '2026-06-24 05:08:28'),
(238, 59, 224, '2026-06-24 05:08:28'),
(242, 60, 229, '2026-06-24 05:08:28'),
(243, 60, 228, '2026-06-24 05:08:28'),
(244, 60, 230, '2026-06-24 05:08:28'),
(245, 61, 232, '2026-06-24 05:08:28'),
(246, 61, 231, '2026-06-24 05:08:28'),
(247, 61, 157, '2026-06-24 05:08:28'),
(248, 62, 211, '2026-06-24 05:08:28'),
(249, 62, 233, '2026-06-24 05:08:28'),
(250, 62, 234, '2026-06-24 05:08:28'),
(251, 63, 236, '2026-06-24 05:08:28'),
(252, 63, 49, '2026-06-24 05:08:28'),
(253, 63, 235, '2026-06-24 05:08:28');

-- --------------------------------------------------------

--
-- Table structure for table `book_views`
--

CREATE TABLE `book_views` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `borrowing`
--

CREATE TABLE `borrowing` (
  `id` int(11) NOT NULL,
  `transaction_code` varchar(40) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved' COMMENT 'For approval workflow: pending=awaiting staff approval, approved=can borrow, rejected=denied',
  `borrow_date` datetime NOT NULL,
  `due_date` date NOT NULL,
  `return_date` datetime DEFAULT NULL,
  `status` enum('Pending','Borrowed','Returned','Overdue') NOT NULL DEFAULT 'Borrowed',
  `fine_amount` decimal(8,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the borrow request was submitted',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT 'When staff approved/rejected the request',
  `approved_by` int(11) DEFAULT NULL COMMENT 'Staff member who approved/rejected'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowing`
--

INSERT INTO `borrowing` (`id`, `transaction_code`, `user_id`, `book_id`, `processed_by`, `approval_status`, `borrow_date`, `due_date`, `return_date`, `status`, `fine_amount`, `created_at`, `requested_at`, `approved_at`, `approved_by`) VALUES
(40, 'S2B-20260627-8CAFDC', 21, 12, NULL, 'pending', '2026-06-27 15:13:36', '2026-07-27', '2026-06-27 15:32:35', 'Returned', 0.00, '2026-06-27 07:13:36', '2026-06-27 07:13:36', NULL, NULL),
(41, 'S2B-20260629-DE4F65', 23, 2, NULL, 'approved', '2026-06-29 09:26:01', '2026-07-06', '2026-08-05 16:09:55', 'Returned', 145.00, '2026-06-29 01:26:01', '2026-06-29 01:26:01', '2026-06-29 01:28:21', 1),
(42, 'S2B-20260805-CBAFF0', 23, 12, NULL, 'approved', '2026-08-05 16:01:07', '2026-08-12', '2026-08-05 16:09:39', 'Returned', 0.00, '2026-08-05 08:01:07', '2026-08-05 08:01:07', '2026-08-05 08:04:52', 1),
(43, 'S2B-20260805-D39B78', 23, 1, NULL, 'approved', '2026-08-05 16:04:14', '2026-08-12', '2026-08-05 16:09:22', 'Returned', 0.00, '2026-08-05 08:04:14', '2026-08-05 08:04:14', '2026-08-05 08:04:48', 1),
(44, 'S2B-20260805-3C4CD7', 23, 1, NULL, 'approved', '2026-08-05 16:16:23', '2026-08-12', NULL, 'Overdue', 40.00, '2026-08-05 08:16:23', '2026-08-05 08:16:23', '2026-08-21 04:47:05', 1),
(45, 'S2B-20260805-90131A', 23, 11, NULL, 'approved', '2026-08-05 16:16:44', '2026-08-12', NULL, 'Overdue', 40.00, '2026-08-05 08:16:44', '2026-08-05 08:16:44', '2026-08-21 04:47:00', 1),
(46, 'S2B-20260821-77FF7B', 25, 12, NULL, 'pending', '2026-08-21 12:55:43', '2026-08-28', NULL, 'Pending', 0.00, '2026-08-21 04:55:43', '2026-08-21 04:55:43', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `keywords`
--

CREATE TABLE `keywords` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `keywords`
--

INSERT INTO `keywords` (`id`, `name`, `created_at`) VALUES
(1, 'finance', '2026-06-24 05:03:31'),
(2, 'psychology', '2026-06-24 05:03:31'),
(3, 'money', '2026-06-24 05:03:31'),
(4, 'investing', '2026-06-24 05:03:31'),
(5, 'habits', '2026-06-24 05:03:31'),
(6, 'self-improvement', '2026-06-24 05:03:31'),
(7, 'productivity', '2026-06-24 05:03:31'),
(8, 'fiction', '2026-06-24 05:03:31'),
(9, 'philosophy', '2026-06-24 05:03:31'),
(10, 'life choices', '2026-06-24 05:03:31'),
(11, 'science fiction', '2026-06-24 05:03:31'),
(12, 'space', '2026-06-24 05:03:31'),
(13, 'survival', '2026-06-24 05:03:31'),
(14, 'dreams', '2026-06-24 05:03:31'),
(15, 'romance', '2026-06-24 05:03:31'),
(16, 'relationships', '2026-06-24 05:03:31'),
(17, 'thriller', '2026-06-24 05:03:31'),
(18, 'mystery', '2026-06-24 05:03:31'),
(19, 'fantasy', '2026-06-24 05:03:31'),
(20, 'dragons', '2026-06-24 05:03:31'),
(21, 'lgbtq', '2026-06-24 05:03:31'),
(22, 'magical', '2026-06-24 05:03:31'),
(47, 'research', '2026-06-24 05:03:31'),
(48, 'education', '2026-06-24 05:03:31'),
(49, 'methodology', '2026-06-24 05:03:31'),
(50, 'qualitative', '2026-06-24 05:03:31'),
(51, 'methods', '2026-06-24 05:03:31'),
(52, 'survey', '2026-06-24 05:03:31'),
(53, 'quantitative', '2026-06-24 05:03:31'),
(54, 'analysis', '2026-06-24 05:03:31'),
(55, 'statistics', '2026-06-24 05:03:31'),
(56, 'case study', '2026-06-24 05:03:31'),
(57, 'writing', '2026-06-24 05:03:31'),
(58, 'academic', '2026-06-24 05:03:31'),
(59, 'thesis', '2026-06-24 05:03:31'),
(60, 'data science', '2026-06-24 05:03:31'),
(61, 'programming', '2026-06-24 05:03:31'),
(145, 'geography', '2026-06-24 05:04:54'),
(146, 'nature', '2026-06-24 05:04:54'),
(147, 'photography', '2026-06-24 05:04:54'),
(148, 'travel', '2026-06-24 05:04:54'),
(149, 'discoveries', '2026-06-24 05:04:54'),
(150, 'history', '2026-06-24 05:04:54'),
(151, 'stories', '2026-06-24 05:04:54'),
(152, 'literature', '2026-06-24 05:04:54'),
(153, 'general', '2026-06-24 05:04:54'),
(154, 'science', '2026-06-24 05:04:54'),
(155, 'brain', '2026-06-24 05:04:54'),
(156, 'health', '2026-06-24 05:04:54'),
(157, 'technology', '2026-06-24 05:04:54'),
(158, 'future', '2026-06-24 05:04:54'),
(159, 'innovation', '2026-06-24 05:04:54'),
(160, 'business', '2026-06-24 05:04:54'),
(161, 'leadership', '2026-06-24 05:04:54'),
(162, 'success', '2026-06-24 05:04:54'),
(163, 'sports', '2026-06-24 05:04:54'),
(164, 'athletes', '2026-06-24 05:04:54'),
(165, 'fashion', '2026-06-24 05:04:54'),
(166, 'style', '2026-06-24 05:04:54'),
(167, 'culture', '2026-06-24 05:04:54'),
(184, 'algebra', '2026-06-24 05:04:54'),
(185, 'trigonometry', '2026-06-24 05:04:54'),
(186, 'mathematics', '2026-06-24 05:04:54'),
(187, 'american', '2026-06-24 05:04:54'),
(188, 'dystopia', '2026-06-24 05:04:54'),
(189, 'racism', '2026-06-24 05:04:54'),
(190, 'friendship', '2026-06-24 05:04:54'),
(191, 'young adult', '2026-06-24 05:04:54'),
(192, 'adventure', '2026-06-24 05:04:54'),
(193, 'magic', '2026-06-24 05:04:54'),
(194, 'coming of age', '2026-06-24 05:04:54'),
(198, 'calculus', '2026-06-24 05:04:54'),
(199, 'advanced math', '2026-06-24 05:04:54'),
(200, 'physics', '2026-06-24 05:04:54'),
(201, 'engineering', '2026-06-24 05:04:54'),
(202, 'chemistry', '2026-06-24 05:04:54'),
(203, 'laboratory', '2026-06-24 05:04:54'),
(204, 'biology', '2026-06-24 05:04:54'),
(205, 'life science', '2026-06-24 05:04:54'),
(206, 'organisms', '2026-06-24 05:04:54'),
(207, 'economics', '2026-06-24 05:04:54'),
(208, 'discrete math', '2026-06-24 05:04:54'),
(209, 'computer science', '2026-06-24 05:04:54'),
(210, 'logic', '2026-06-24 05:04:54'),
(211, 'database', '2026-06-24 05:04:54'),
(212, 'algorithms', '2026-06-24 05:04:54'),
(213, 'computer systems', '2026-06-24 05:04:54'),
(214, 'architecture', '2026-06-24 05:04:54'),
(215, 'software', '2026-06-24 05:04:54'),
(216, 'best practices', '2026-06-24 05:04:54'),
(217, 'software development', '2026-06-24 05:04:54'),
(218, 'career', '2026-06-24 05:04:54'),
(219, 'design patterns', '2026-06-24 05:04:54'),
(220, 'object-oriented', '2026-06-24 05:04:54'),
(221, 'java', '2026-06-24 05:04:54'),
(222, 'python', '2026-06-24 05:04:54'),
(223, 'beginner', '2026-06-24 05:04:54'),
(224, 'web design', '2026-06-24 05:04:54'),
(225, 'html', '2026-06-24 05:04:54'),
(226, 'css', '2026-06-24 05:04:54'),
(227, 'javascript', '2026-06-24 05:04:54'),
(228, 'digital logic', '2026-06-24 05:04:54'),
(229, 'computer design', '2026-06-24 05:04:54'),
(230, 'hardware', '2026-06-24 05:04:54'),
(231, 'networking', '2026-06-24 05:04:54'),
(232, 'computers', '2026-06-24 05:04:54'),
(233, 'management systems', '2026-06-24 05:04:54'),
(234, 'sql', '2026-06-24 05:04:54'),
(235, 'software engineering', '2026-06-24 05:04:54'),
(236, 'development', '2026-06-24 05:04:54'),
(237, 'artificial intelligence', '2026-06-24 05:04:54'),
(238, 'ai', '2026-06-24 05:04:54'),
(239, 'machine learning', '2026-06-24 05:04:54'),
(321, 'magazine', '2026-06-27 08:42:12');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Staff member who receives the notification',
  `type` enum('borrow_request','overdue_alert','return_alert') NOT NULL DEFAULT 'borrow_request',
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL COMMENT 'ID of related borrowing request',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `related_id`, `is_read`, `created_at`) VALUES
(26, 1, '', 'New Book Borrowed', 'Student <strong>Jeniña Margaret Vargas</strong> has borrowed a book:<br><br><strong>Book:</strong> Verity<br><strong>Borrowed:</strong> Jun 26, 2026<br><strong>Due Date:</strong> Jul 03, 2026<br><br>The book has been successfully borrowed and is now marked as \'Borrowed\'.', 38, 0, '2026-06-26 14:01:30'),
(27, 1, 'borrow_request', 'New Borrow Request', 'Student <strong>Jeniña Margaret Vargas</strong> (ID: 230419) has requested to borrow:<br><br><strong>Book:</strong> The Midnight Library<br><strong>Transaction Code:</strong> S2B-20260626-6FDF97<br><br>Please review and approve/reject this request in the staff dashboard.', 39, 1, '2026-06-26 14:01:59'),
(28, 1, '', 'New Book Borrowed', 'Student <strong>Jeniña Margaret Vargas</strong> has borrowed a book:<br><br><strong>Book:</strong> The Midnight Library<br><strong>Borrowed:</strong> Jun 26, 2026<br><strong>Due Date:</strong> Jul 03, 2026<br><br>The book has been successfully borrowed and is now marked as \'Borrowed\'.', 39, 0, '2026-06-26 14:02:25'),
(29, 1, 'borrow_request', 'New Borrow Request', 'Student <strong>Alexandre Hidalgo</strong> (ID: 112200) has requested to borrow:<br><br><strong>Book:</strong> It Ends with Us<br><strong>Transaction Code:</strong> S2B-20260627-8CAFDC<br><br>Please review and approve/reject this request in the staff dashboard.', 40, 0, '2026-06-27 07:13:36'),
(30, 1, 'borrow_request', 'New Borrow Request', 'Student <strong>Mark Urbano</strong> (ID: 230214) has requested to borrow:<br><br><strong>Book:</strong> Clean Code<br><strong>Transaction Code:</strong> S2B-20260629-DE4F65<br><br>Please review and approve/reject this request in the staff dashboard.', 41, 1, '2026-06-29 01:26:01'),
(31, 1, '', 'New Book Borrowed', 'Student <strong>Mark Urbano</strong> has borrowed a book:<br><br><strong>Book:</strong> Clean Code<br><strong>Borrowed:</strong> Jun 29, 2026<br><strong>Due Date:</strong> Jul 06, 2026<br><br>The book has been successfully borrowed and is now marked as \'Borrowed\'.', 41, 0, '2026-06-29 01:28:21'),
(32, 1, 'borrow_request', 'New Borrow Request', 'Student <strong>Mark Urbano</strong> (ID: 230214) has requested to borrow:<br><br><strong>Book:</strong> It Ends with Us<br><strong>Transaction Code:</strong> S2B-20260805-CBAFF0<br><br>Please review and approve/reject this request in the staff dashboard.', 42, 1, '2026-08-05 08:01:07'),
(33, 1, 'borrow_request', 'New Borrow Request', 'Student <strong>Mark Urbano</strong> (ID: 230214) has requested to borrow:<br><br><strong>Book:</strong> Introduction to Algorithms<br><strong>Transaction Code:</strong> S2B-20260805-D39B78<br><br>Please review and approve/reject this request in the staff dashboard.', 43, 1, '2026-08-05 08:04:14'),
(34, 1, '', 'New Book Borrowed', 'Student <strong>Mark Urbano</strong> has borrowed a book:<br><br><strong>Book:</strong> Introduction to Algorithms<br><strong>Borrowed:</strong> Aug 05, 2026<br><strong>Due Date:</strong> Aug 12, 2026<br><br>The book has been successfully borrowed and is now marked as \'Borrowed\'.', 43, 0, '2026-08-05 08:04:48'),
(35, 1, '', 'New Book Borrowed', 'Student <strong>Mark Urbano</strong> has borrowed a book:<br><br><strong>Book:</strong> It Ends with Us<br><strong>Borrowed:</strong> Aug 05, 2026<br><strong>Due Date:</strong> Aug 12, 2026<br><br>The book has been successfully borrowed and is now marked as \'Borrowed\'.', 42, 0, '2026-08-05 08:04:52'),
(36, 1, 'borrow_request', 'New Borrow Request', 'Student <strong>Mark Urbano</strong> (ID: 230214) has requested to borrow:<br><br><strong>Book:</strong> Introduction to Algorithms<br><strong>Transaction Code:</strong> S2B-20260805-3C4CD7<br><br>Please review and approve/reject this request in the staff dashboard.', 44, 1, '2026-08-05 08:16:23'),
(37, 1, 'borrow_request', 'New Borrow Request', 'Student <strong>Mark Urbano</strong> (ID: 230214) has requested to borrow:<br><br><strong>Book:</strong> The Alchemist<br><strong>Transaction Code:</strong> S2B-20260805-90131A<br><br>Please review and approve/reject this request in the staff dashboard.', 45, 1, '2026-08-05 08:16:44'),
(38, 1, 'borrow_request', 'New Guest Borrow Request', 'Guest <strong>Claire Isabella Regulus Abad</strong> (VIS-2026-000001) requested to borrow <strong>Project Hail Mary</strong> (Accession: BK-2004). Review the verification photo and approve or reject the request.', 0, 0, '2026-08-08 09:50:45'),
(39, 1, '', 'New Book Borrowed', 'Student <strong>Mark Urbano</strong> has borrowed a book:<br><br><strong>Book:</strong> The Alchemist<br><strong>Borrowed:</strong> Aug 05, 2026<br><strong>Due Date:</strong> Aug 12, 2026<br><br>The book has been successfully borrowed and is now marked as \'Borrowed\'.', 45, 0, '2026-08-21 04:47:00'),
(40, 1, '', 'New Book Borrowed', 'Student <strong>Mark Urbano</strong> has borrowed a book:<br><br><strong>Book:</strong> Introduction to Algorithms<br><strong>Borrowed:</strong> Aug 05, 2026<br><strong>Due Date:</strong> Aug 12, 2026<br><br>The book has been successfully borrowed and is now marked as \'Borrowed\'.', 44, 0, '2026-08-21 04:47:05'),
(41, 1, 'borrow_request', 'New Borrow Request', 'Student <strong>Adalhia Hidalgo</strong> (ID: 230419) has requested to borrow:<br><br><strong>Book:</strong> It Ends with Us<br><strong>Transaction Code:</strong> S2B-20260821-77FF7B<br><br>Please review and approve/reject this request in the staff dashboard.', 46, 0, '2026-08-21 04:55:43');

-- --------------------------------------------------------

--
-- Table structure for table `otp_codes`
--

CREATE TABLE `otp_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'Temporary user ID during registration',
  `barcode` varchar(50) DEFAULT NULL COMMENT 'Temporary barcode during registration',
  `otp_code` varchar(6) NOT NULL,
  `phone_number` varchar(30) NOT NULL,
  `user_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Stores registration data temporarily' CHECK (json_valid(`user_data`)),
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `return_notifications`
--

CREATE TABLE `return_notifications` (
  `id` int(11) NOT NULL,
  `borrowing_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Student who returned',
  `book_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_viewed` tinyint(1) NOT NULL DEFAULT 0,
  `viewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `return_notifications`
--

INSERT INTO `return_notifications` (`id`, `borrowing_id`, `user_id`, `book_id`, `message`, `is_viewed`, `viewed_at`, `created_at`) VALUES
(15, 40, 1, 12, 'Book Successfully Returned\n\nStudent:\nAlexandre Hidalgo\n\nBook:\nIt Ends with Us\n\nReturned:\nJun 27, 2026', 1, '2026-06-27 16:08:14', '2026-06-27 07:32:35'),
(16, 43, 1, 1, 'Book Successfully Returned\n\nStudent:\nMark Urbano\n\nBook:\nIntroduction to Algorithms\n\nReturned:\nAug 05, 2026', 1, '2026-08-21 12:46:36', '2026-08-05 08:09:22'),
(17, 42, 1, 12, 'Book Successfully Returned\n\nStudent:\nMark Urbano\n\nBook:\nIt Ends with Us\n\nReturned:\nAug 05, 2026', 1, '2026-08-21 12:45:34', '2026-08-05 08:09:39'),
(18, 41, 1, 2, 'Book Successfully Returned\n\nStudent:\nMark Urbano\n\nBook:\nClean Code\n\nReturned:\nAug 05, 2026', 1, '2026-08-21 12:45:31', '2026-08-05 08:09:55');

-- --------------------------------------------------------

--
-- Table structure for table `search_history`
--

CREATE TABLE `search_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `search_query` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `search_history`
--

INSERT INTO `search_history` (`id`, `user_id`, `search_query`, `created_at`) VALUES
(16, 21, 'Programming', '2026-06-27 07:57:13'),
(17, 21, 'Programming', '2026-06-27 07:58:11'),
(18, 21, 'Programming', '2026-06-27 07:58:18'),
(19, 21, 'Programming', '2026-06-27 07:58:33'),
(20, 21, 'Programming', '2026-06-27 07:58:38'),
(21, 21, 'Programming', '2026-06-27 07:58:44'),
(22, 21, 'Programming', '2026-06-27 08:07:05'),
(23, 21, 'Test', '2026-06-27 08:35:31'),
(24, 21, 'Test', '2026-06-27 08:39:29'),
(25, 21, 'Test', '2026-06-27 08:41:11'),
(26, 21, 'Test', '2026-06-27 08:41:12'),
(27, 21, 'Testing', '2026-06-27 08:42:31'),
(28, 21, 'Test', '2026-06-27 08:50:12'),
(29, 21, 'test', '2026-06-27 08:59:00'),
(30, 21, 'test', '2026-06-27 09:15:20'),
(31, 21, 'test 5', '2026-06-27 09:30:28'),
(32, 21, 'test 5', '2026-06-27 09:36:05'),
(33, 21, 'test 5', '2026-06-27 09:36:47'),
(34, 21, 'test 5', '2026-06-27 09:37:52'),
(35, 21, 'test', '2026-06-27 09:57:31');

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Student who received the SMS',
  `borrowing_id` int(11) DEFAULT NULL COMMENT 'Related borrowing transaction',
  `type` enum('borrow_confirmation','due_date_reminder','otp_verification') NOT NULL,
  `phone_number` varchar(30) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `barcode` varchar(50) NOT NULL,
  `firstname` varchar(80) NOT NULL,
  `middlename` varchar(80) DEFAULT NULL,
  `lastname` varchar(80) NOT NULL,
  `department` varchar(120) DEFAULT NULL,
  `position` varchar(120) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `contact_no` varchar(30) DEFAULT NULL,
  `role` enum('admin','librarian','student','teacher') NOT NULL DEFAULT 'student',
  `password_hash` varchar(255) DEFAULT NULL,
  `photo` mediumtext DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `borrowing_status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `barcode`, `firstname`, `middlename`, `lastname`, `department`, `position`, `course`, `year_level`, `email`, `contact_no`, `role`, `password_hash`, `photo`, `status`, `borrowing_status`, `failed_attempts`, `locked_until`, `last_login`, `reset_token`, `reset_expires`, `created_at`) VALUES
(1, 'ADMIN001', 'Library', '', 'Administrator', NULL, NULL, NULL, NULL, 'admin@scan2borrow.local', NULL, 'admin', '$2y$10$PO07qZD2aFvEM44Lm1A6zOaYyntI/8ZH2Wq7emzRfdq/7hN4D0xB.', NULL, 'active', 'active', 0, NULL, '2026-08-21 12:38:11', NULL, NULL, '2026-06-21 07:48:26'),
(21, '112200', 'Alexandre', 'Madrigal', 'Hidalgo', 'CABAIT', 'College Teacher', '', '', 'kaleanfreix@gmail.com', '09930797626', 'teacher', NULL, 'uploads/photos/112200-1782566029-c010af2d.jpg', 'active', 'active', 0, NULL, NULL, NULL, NULL, '2026-06-27 07:12:15'),
(22, '230418', 'Claire Isabella', 'Alfonso', 'Abad', '', '', 'Bachelor of Science in Information Technology', '3', 'asdfghjk@gmail.com', '+639930797626', 'student', NULL, NULL, 'active', 'active', 0, NULL, NULL, NULL, NULL, '2026-06-27 07:41:52'),
(23, '230214', 'Mark', 'Sarad', 'Urbano', '', '', 'Bachelor of Science in Information Technology', '3', 'marksarad9@gmail.com', '09301216354', 'student', NULL, NULL, 'active', 'active', 0, NULL, NULL, NULL, NULL, '2026-06-29 01:20:20'),
(24, '112233', 'Mark', 'Sarad', 'Urbano', 'CABAIT', 'College Teacher', '', '', 'marksarad9@gmail.com', '09301216354', 'teacher', NULL, NULL, 'active', 'active', 0, NULL, NULL, NULL, NULL, '2026-06-29 01:39:05'),
(25, '230419', 'Adalhia', '', 'Hidalgo', '', '', 'Bachelor of Science in Information Technology', '4', 'HAHAHAHAH@gmail.com', '09930797626', 'student', NULL, NULL, 'active', 'active', 0, NULL, NULL, NULL, NULL, '2026-08-21 04:54:52');

-- --------------------------------------------------------

--
-- Table structure for table `visitors`
--

CREATE TABLE `visitors` (
  `id` int(11) NOT NULL,
  `visitor_number` varchar(30) DEFAULT NULL,
  `qr_token` char(32) DEFAULT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `gender` varchar(30) NOT NULL,
  `birthdate` date NOT NULL,
  `contact_no` varchar(30) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `house_no` varchar(100) NOT NULL,
  `street` varchar(150) NOT NULL,
  `barangay` varchar(150) NOT NULL,
  `municipality` varchar(150) NOT NULL,
  `province` varchar(150) NOT NULL,
  `purpose` varchar(30) NOT NULL,
  `purpose_other` varchar(255) DEFAULT NULL,
  `id_type` varchar(100) NOT NULL,
  `id_barcode` varchar(255) NOT NULL,
  `photo` mediumtext DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 1,
  `verified_at` datetime NOT NULL,
  `registration_expires_at` date DEFAULT NULL,
  `account_status` enum('Active','Borrowing','Suspended','Expired') NOT NULL DEFAULT 'Active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visitors`
--

INSERT INTO `visitors` (`id`, `visitor_number`, `qr_token`, `firstname`, `middlename`, `lastname`, `suffix`, `gender`, `birthdate`, `contact_no`, `email`, `house_no`, `street`, `barangay`, `municipality`, `province`, `purpose`, `purpose_other`, `id_type`, `id_barcode`, `photo`, `is_verified`, `verified_at`, `registration_expires_at`, `account_status`, `last_login_at`, `created_at`) VALUES
(1, 'VIS-2026-000001', 'f0d7271778dc2c3f57589da915607c42', 'Claire Isabella', 'Regulus', 'Abad', NULL, 'Female', '1976-08-06', '09092927379', 'kaleanfreix@gmail.com', '#884', 'Prk Yakal', 'Brgy. San Teodoro', 'Binalbagan', 'Negros Occidental', 'Research', NULL, 'National ID', '220096910', 'data:image/jpeg;base64,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', 1, '2026-08-04 22:06:10', '2027-08-04', 'Active', '2026-08-21 12:16:53', '2026-08-04 14:06:10');

-- --------------------------------------------------------

--
-- Table structure for table `visitor_borrowing`
--

CREATE TABLE `visitor_borrowing` (
  `id` int(11) NOT NULL,
  `visitor_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `borrow_date` date NOT NULL,
  `due_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `request_status` varchar(30) NOT NULL DEFAULT 'Ready for Release',
  `verification_photo` mediumtext DEFAULT NULL,
  `return_verification_photo` mediumtext DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `return_requested_at` datetime DEFAULT NULL,
  `review_notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visitor_borrowing`
--

INSERT INTO `visitor_borrowing` (`id`, `visitor_id`, `book_id`, `borrow_date`, `due_date`, `return_date`, `request_status`, `verification_photo`, `return_verification_photo`, `requested_at`, `released_at`, `return_requested_at`, `review_notes`, `created_at`) VALUES
(1, 1, 12, '2026-08-08', '2026-08-15', NULL, 'Ready for Release', 'data:image/jpeg;base64,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', NULL, '2026-08-08 17:20:36', NULL, NULL, NULL, '2026-08-08 09:20:36');
INSERT INTO `visitor_borrowing` (`id`, `visitor_id`, `book_id`, `borrow_date`, `due_date`, `return_date`, `request_status`, `verification_photo`, `return_verification_photo`, `requested_at`, `released_at`, `return_requested_at`, `review_notes`, `created_at`) VALUES
(2, 1, 12, '2026-08-08', '2026-08-15', NULL, 'Ready for Release', 'data:image/jpeg;base64,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', NULL, '2026-08-08 17:38:24', NULL, NULL, NULL, '2026-08-08 09:38:24');
INSERT INTO `visitor_borrowing` (`id`, `visitor_id`, `book_id`, `borrow_date`, `due_date`, `return_date`, `request_status`, `verification_photo`, `return_verification_photo`, `requested_at`, `released_at`, `return_requested_at`, `review_notes`, `created_at`) VALUES
(3, 1, 10, '2026-08-08', '2026-08-15', NULL, 'Pending', 'data:image/jpeg;base64,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', NULL, '2026-08-08 17:50:45', NULL, NULL, NULL, '2026-08-08 09:50:45');

-- --------------------------------------------------------

--
-- Table structure for table `visitor_notifications`
--

CREATE TABLE `visitor_notifications` (
  `id` int(11) NOT NULL,
  `visitor_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visitor_notifications`
--

INSERT INTO `visitor_notifications` (`id`, `visitor_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 'Borrow request submitted', 'Your request for It Ends with Us is ready for librarian review and release.', 0, '2026-08-08 09:20:36'),
(2, 1, 'Borrow request submitted', 'Your request for It Ends with Us is ready for librarian review and release.', 0, '2026-08-08 09:38:24'),
(3, 1, 'Borrow request submitted', 'Your request for Project Hail Mary is now pending staff approval.', 0, '2026-08-08 09:50:45');

-- --------------------------------------------------------

--
-- Table structure for table `visitor_security_logs`
--

CREATE TABLE `visitor_security_logs` (
  `id` int(11) NOT NULL,
  `visitor_id` int(11) NOT NULL,
  `activity` varchar(100) NOT NULL,
  `details` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visitor_security_logs`
--

INSERT INTO `visitor_security_logs` (`id`, `visitor_id`, `activity`, `details`, `created_at`) VALUES
(1, 1, 'login', 'Guest signed in using government ID barcode.', '2026-08-04 14:23:05'),
(2, 1, 'contact_number_change', 'Mobile number reverified through SMS OTP.', '2026-08-04 14:24:59'),
(3, 1, 'login', 'Guest signed in using government ID barcode.', '2026-08-05 05:20:22'),
(4, 1, 'logout', 'Visitor checked out of the portal.', '2026-08-05 05:24:10'),
(5, 1, 'login', 'Guest signed in using government ID barcode.', '2026-08-05 07:49:55'),
(6, 1, 'logout', 'Visitor checked out of the portal.', '2026-08-05 07:51:21'),
(7, 1, 'login', 'Guest signed in using government ID barcode.', '2026-08-05 07:54:58'),
(8, 1, 'logout', 'Visitor checked out of the portal.', '2026-08-05 08:00:37'),
(9, 1, 'login', 'Guest signed in using government ID barcode.', '2026-08-08 09:19:29'),
(10, 1, 'borrow_request', 'Submitted request for It Ends with Us', '2026-08-08 09:20:36'),
(11, 1, 'borrow_request', 'Submitted request for It Ends with Us', '2026-08-08 09:38:24'),
(12, 1, 'borrow_request', 'Submitted request for Project Hail Mary (Pending)', '2026-08-08 09:50:45'),
(13, 1, 'login', 'Guest signed in using government ID barcode.', '2026-08-21 04:03:46'),
(14, 1, 'logout', 'Visitor checked out of the portal.', '2026-08-21 04:37:59');

-- --------------------------------------------------------

--
-- Table structure for table `visitor_visit_history`
--

CREATE TABLE `visitor_visit_history` (
  `id` int(11) NOT NULL,
  `visitor_id` int(11) NOT NULL,
  `time_in` datetime NOT NULL,
  `time_out` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visitor_visit_history`
--

INSERT INTO `visitor_visit_history` (`id`, `visitor_id`, `time_in`, `time_out`, `created_at`) VALUES
(1, 1, '2026-08-04 22:23:05', NULL, '2026-08-04 14:23:05'),
(2, 1, '2026-08-05 13:20:22', '2026-08-05 13:24:10', '2026-08-05 05:20:22'),
(3, 1, '2026-08-05 15:49:55', '2026-08-05 15:51:21', '2026-08-05 07:49:55'),
(4, 1, '2026-08-05 15:54:58', '2026-08-05 16:00:37', '2026-08-05 07:54:58'),
(5, 1, '2026-08-08 17:19:29', NULL, '2026-08-08 09:19:29'),
(6, 1, '2026-08-21 12:03:46', '2026-08-21 12:37:59', '2026-08-21 04:03:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_action` (`user_id`,`action`,`created_at`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD UNIQUE KEY `uq_books_accession` (`accession_no`);

--
-- Indexes for table `book_keywords`
--
ALTER TABLE `book_keywords`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_book_keyword` (`book_id`,`keyword_id`),
  ADD KEY `fk_bk_keyword` (`keyword_id`);

--
-- Indexes for table `book_views`
--
ALTER TABLE `book_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bv_book` (`book_id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `borrowing`
--
ALTER TABLE `borrowing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_code` (`transaction_code`),
  ADD KEY `fk_borrow_user` (`user_id`),
  ADD KEY `fk_borrow_book` (`book_id`),
  ADD KEY `fk_borrow_staff` (`processed_by`),
  ADD KEY `fk_approval_staff` (`approved_by`),
  ADD KEY `idx_borrowing_approval` (`approval_status`,`requested_at`);

--
-- Indexes for table `keywords`
--
ALTER TABLE `keywords`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user_unread` (`user_id`,`is_read`,`created_at`);

--
-- Indexes for table `otp_codes`
--
ALTER TABLE `otp_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_otp_user` (`user_id`),
  ADD KEY `idx_otp_code` (`otp_code`,`is_verified`,`is_used`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `return_notifications`
--
ALTER TABLE `return_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_return_notif_borrowing` (`borrowing_id`),
  ADD KEY `fk_return_notif_user` (`user_id`),
  ADD KEY `fk_return_notif_book` (`book_id`),
  ADD KEY `idx_viewed` (`is_viewed`,`created_at`);

--
-- Indexes for table `search_history`
--
ALTER TABLE `search_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_borrowing_type` (`borrowing_id`,`type`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `idx_borrowing_status` (`borrowing_status`),
  ADD KEY `idx_user_borrowing_status` (`borrowing_status`);

--
-- Indexes for table `visitors`
--
ALTER TABLE `visitors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_visitor_id_barcode` (`id_barcode`),
  ADD UNIQUE KEY `uq_visitor_number` (`visitor_number`),
  ADD UNIQUE KEY `uq_visitor_qr_token` (`qr_token`);

--
-- Indexes for table `visitor_borrowing`
--
ALTER TABLE `visitor_borrowing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_visitor_borrowing_book` (`book_id`),
  ADD KEY `idx_visitor_active` (`visitor_id`,`return_date`);

--
-- Indexes for table `visitor_notifications`
--
ALTER TABLE `visitor_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_visitor_notification` (`visitor_id`,`is_read`,`created_at`);

--
-- Indexes for table `visitor_security_logs`
--
ALTER TABLE `visitor_security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_visitor_security` (`visitor_id`,`created_at`);

--
-- Indexes for table `visitor_visit_history`
--
ALTER TABLE `visitor_visit_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_visit_history_visitor` (`visitor_id`,`time_in`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=167;

--
-- AUTO_INCREMENT for table `book_keywords`
--
ALTER TABLE `book_keywords`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=263;

--
-- AUTO_INCREMENT for table `book_views`
--
ALTER TABLE `book_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `borrowing`
--
ALTER TABLE `borrowing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `keywords`
--
ALTER TABLE `keywords`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=322;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `otp_codes`
--
ALTER TABLE `otp_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `return_notifications`
--
ALTER TABLE `return_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `search_history`
--
ALTER TABLE `search_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `visitors`
--
ALTER TABLE `visitors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `visitor_borrowing`
--
ALTER TABLE `visitor_borrowing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `visitor_notifications`
--
ALTER TABLE `visitor_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `visitor_security_logs`
--
ALTER TABLE `visitor_security_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `visitor_visit_history`
--
ALTER TABLE `visitor_visit_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `book_keywords`
--
ALTER TABLE `book_keywords`
  ADD CONSTRAINT `fk_bk_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bk_keyword` FOREIGN KEY (`keyword_id`) REFERENCES `keywords` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `book_views`
--
ALTER TABLE `book_views`
  ADD CONSTRAINT `fk_bv_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `borrowing`
--
ALTER TABLE `borrowing`
  ADD CONSTRAINT `fk_approval_staff` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_borrow_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_borrow_staff` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_borrow_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `otp_codes`
--
ALTER TABLE `otp_codes`
  ADD CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `return_notifications`
--
ALTER TABLE `return_notifications`
  ADD CONSTRAINT `fk_return_notif_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_return_notif_borrowing` FOREIGN KEY (`borrowing_id`) REFERENCES `borrowing` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_return_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `search_history`
--
ALTER TABLE `search_history`
  ADD CONSTRAINT `fk_sh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD CONSTRAINT `fk_sms_borrowing` FOREIGN KEY (`borrowing_id`) REFERENCES `borrowing` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sms_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visitor_borrowing`
--
ALTER TABLE `visitor_borrowing`
  ADD CONSTRAINT `fk_visitor_borrowing_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_visitor_borrowing_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visitor_notifications`
--
ALTER TABLE `visitor_notifications`
  ADD CONSTRAINT `fk_visitor_notification_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visitor_security_logs`
--
ALTER TABLE `visitor_security_logs`
  ADD CONSTRAINT `fk_visitor_security_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visitor_visit_history`
--
ALTER TABLE `visitor_visit_history`
  ADD CONSTRAINT `fk_visit_history_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
