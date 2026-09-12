-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 05, 2026 at 10:26 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ojt_monitoring`
--

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `supervisor_name` varchar(150) DEFAULT NULL,
  `reference_signature` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `company_name`, `address`, `contact_person`, `supervisor_name`, `reference_signature`, `contact_number`, `email`, `is_active`, `created_by`, `created_at`) VALUES
(1, 'Tourism Adventure Corp.', 'Angeles City', 'Juan Dela Cruz', 'Juan Dela Cruz', 'signatures/company_1_signature_1788159226_4667b0f0.jpg', '09123456789', 'tourismcorp@gmail.com', 1, 10, '2026-08-08 17:33:13'),
(2, 'JGH Accounting', 'Angeles City', 'Carlos Yulo', 'Carlos Yulo', 'signatures/company_2_signature_1787910976_361ea9d2.jpg', '09123456789', 'jghacc@gmail.com', 1, 10, '2026-08-08 18:39:32');

-- --------------------------------------------------------

--
-- Table structure for table `company_moa`
--

CREATE TABLE `company_moa` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `moa_file` varchar(255) NOT NULL,
  `effective_date` date NOT NULL,
  `expiration_date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_moa`
--

INSERT INTO `company_moa` (`id`, `company_id`, `moa_file`, `effective_date`, `expiration_date`, `remarks`, `created_by`, `created_at`) VALUES
(1, 1, 'uploads/moa/moa_company_1_1786181649.png', '2026-08-01', '2026-12-30', '', 10, '2026-08-08 17:34:09'),
(2, 2, 'uploads/moa/moa_company_2_1786185676.png', '2026-08-03', '2026-10-31', '', 10, '2026-08-08 18:41:16');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `coordinator_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`id`, `student_id`, `coordinator_id`, `created_at`, `updated_at`) VALUES
(1, 5, 10, '2026-08-06 10:16:58', '2026-08-08 10:28:51'),
(2, 6, 10, '2026-08-06 13:19:05', '2026-08-06 13:19:14'),
(3, 7, 10, '2026-08-06 13:49:19', '2026-08-11 08:05:51'),
(4, 8, 10, '2026-08-08 08:15:20', '2026-08-19 07:20:16'),
(5, 9, 10, '2026-08-09 02:29:53', '2026-08-09 02:29:53');

-- --------------------------------------------------------

--
-- Table structure for table `document_requirements`
--

CREATE TABLE `document_requirements` (
  `id` int(11) NOT NULL,
  `doc_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unlock_after_completion` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_requirements`
--

INSERT INTO `document_requirements` (`id`, `doc_name`, `description`, `is_required`, `created_by`, `created_at`, `unlock_after_completion`) VALUES
(4, 'Resume / CV', 'Updated curriculum vitae', 1, 1, '2026-06-11 08:48:26', 0),
(9, 'Practicum Training Agreements', 'Signed practicum training agreement', 1, 7, '2026-06-27 12:50:24', 0),
(10, 'Endorsement Letter', 'Official letter endorsement of student for practicum training', 1, 7, '2026-06-27 12:51:48', 0),
(11, 'Reply Form', 'Signed reply form from the company', 1, 7, '2026-06-27 12:52:22', 0),
(12, 'PhilHealth MDR Vaccination Card', 'Valid PhilHealth Member Data Record and valid vaccination card', 1, 7, '2026-06-27 12:53:43', 0),
(13, 'Practicum Evaluation', 'Signed and final practicum evaluation', 1, 10, '2026-08-06 07:31:37', 1),
(15, 'Practicum Report', 'Final and finish practicum report', 1, 10, '2026-08-06 07:50:59', 1),
(16, 'Exit Interview', 'Upload your Exit Interview', 1, 10, '2026-08-06 07:51:20', 1);

-- --------------------------------------------------------

--
-- Table structure for table `document_submissions`
--

CREATE TABLE `document_submissions` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `requirement_id` int(11) NOT NULL,
  `file_name` varchar(255) DEFAULT '',
  `file_path` varchar(500) DEFAULT '',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_submissions`
--

INSERT INTO `document_submissions` (`id`, `student_id`, `requirement_id`, `file_name`, `file_path`, `status`, `remarks`, `submitted_at`, `reviewed_at`, `reviewed_by`) VALUES
(3, 5, 4, 'resume.png', 'documents/doc_5_req4_1784770214.png', 'rejected', 'Provide the actual photo.', '2026-07-23 01:30:14', '2026-07-23 01:40:51', 10),
(4, 5, 4, 'resume.png', 'documents/doc_5_req4_1784770859.png', 'rejected', '.', '2026-07-23 01:40:59', '2026-07-27 09:13:06', 10),
(5, 5, 4, 'resume.png', 'documents/doc_5_req4_1784771656.png', 'rejected', '.', '2026-07-23 01:54:16', '2026-07-27 09:13:00', 10),
(6, 5, 4, 'resume.png', 'documents/doc_5_req4_1785374036.png', 'approved', 'Pass the other document needed', '2026-07-30 01:13:57', '2026-07-30 01:26:06', 10),
(7, 5, 9, 'resume.png', 'documents/doc_5_req9_1785375544.png', 'rejected', '.', '2026-07-30 01:39:04', '2026-07-30 01:41:57', 10),
(8, 6, 4, 'resume.png', 'documents/doc_6_req4_1785888494.png', 'approved', 'Good.', '2026-08-05 00:08:14', '2026-08-05 00:08:31', 10),
(9, 8, 4, 'resume.png', 'documents/doc_8_req4_1787123663.png', 'approved', '', '2026-08-19 07:14:23', '2026-08-19 07:14:30', 10);

-- --------------------------------------------------------

--
-- Table structure for table `hour_resets`
--

CREATE TABLE `hour_resets` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `reset_by` int(11) NOT NULL,
  `reset_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `seen` tinyint(1) DEFAULT 0,
  `deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `conversation_id`, `sender_id`, `message`, `attachment`, `seen`, `deleted`, `created_at`) VALUES
(1, 1, 11, 'Hi Sir', NULL, 1, 0, '2026-08-06 11:31:18'),
(2, 1, 10, 'hello', NULL, 1, 0, '2026-08-06 13:06:20'),
(3, 1, 10, 'Hi', NULL, 1, 0, '2026-08-06 13:06:47'),
(4, 1, 10, 'Hello', NULL, 1, 0, '2026-08-06 13:07:21'),
(5, 1, 11, 'Hi Sir', NULL, 1, 0, '2026-08-06 13:07:45'),
(6, 1, 10, 'Hello', NULL, 1, 0, '2026-08-06 13:13:43'),
(7, 1, 11, 'Hi', NULL, 1, 0, '2026-08-06 13:13:56'),
(8, 1, 10, 'How are you?', NULL, 1, 0, '2026-08-06 13:16:22'),
(9, 1, 11, 'I\'m fine sir', NULL, 1, 0, '2026-08-06 13:16:49'),
(10, 1, 10, '', '20260806151727_f1b75d48f57b585c.png', 1, 0, '2026-08-06 13:17:27'),
(11, 2, 12, 'Goodevening Sir', NULL, 1, 0, '2026-08-06 13:19:14'),
(12, 3, 13, 'Hello Sir', NULL, 1, 0, '2026-08-06 13:52:18'),
(13, 3, 10, 'Hi', NULL, 1, 0, '2026-08-06 13:52:30'),
(14, 3, 10, 'Hello Marko, just want to follow up your document\'s submission before we tag you as deployed.', NULL, 1, 0, '2026-08-07 07:08:06'),
(15, 3, 13, 'Noted on this sir. Thanks much', NULL, 1, 0, '2026-08-07 07:08:34'),
(16, 4, 14, 'Hi sir', NULL, 1, 0, '2026-08-08 08:15:57'),
(17, 1, 11, '', '20260808122851_1f06526eeecfc2d7.jpg', 1, 0, '2026-08-08 10:28:51'),
(18, 3, 13, '.', NULL, 1, 0, '2026-08-09 04:13:04'),
(19, 3, 13, '', '20260811100432_d499eb40f66db29b.docx', 1, 0, '2026-08-11 08:04:32'),
(20, 3, 13, '', '20260811100551_75321e29d9d28c0c.png', 1, 0, '2026-08-11 08:05:51'),
(21, 4, 14, 'Hello Sir', NULL, 1, 0, '2026-08-19 07:20:16');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` enum('warning','info','success','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(6, 11, '❌ Document Rejected', 'Your document \"Medical Certificate\" was rejected. Reason: This is not a valid Medical Certificate.', 'error', 1, '2026-06-17 01:03:36'),
(7, 11, '✅ Document Approved', 'Your document \"Medical Certificate\" has been approved.', 'success', 1, '2026-06-17 09:32:31'),
(8, 11, '✅ Document Approved', 'Your document \"Medical Certificate\" has been approved.', 'success', 1, '2026-06-17 09:32:44'),
(9, 11, 'Documents', 'You must submit your documents on time', 'warning', 1, '2026-06-17 09:33:14'),
(10, 11, '⚠ Hour Deficit — Week 1', 'You rendered 36 hrs this week but the target is 40 hrs. Deficit: 4.0 hrs.', 'warning', 1, '2026-06-17 09:35:04'),
(11, 11, 'Weekly Log Approved', 'Your Week 1 log (36.00 hrs) has been approved.', 'success', 1, '2026-06-17 09:36:05'),
(12, 11, '⚠ Hour Deficit Noted', 'Week 1: You are 4.0 hrs short of your weekly target.', 'warning', 1, '2026-06-17 09:36:05'),
(13, 11, 'Deployment Date Set', 'Your deployment date has been set to July 01, 2026. Deadline for documents: June 22, 2026.', 'info', 1, '2026-06-19 09:05:16'),
(14, 12, 'Documents', 'Please pass your remaining required documents on time.', 'warning', 1, '2026-06-22 12:42:53'),
(15, 12, 'Documents', 'Please pass the remaining documents that you need to submit.', 'warning', 1, '2026-06-22 12:54:31'),
(16, 11, '⚠ Hour Deficit — Week 2', 'You rendered 25 hrs this week but the target is 40 hrs. Deficit: 15.0 hrs.', 'warning', 1, '2026-06-23 03:54:21'),
(17, 11, 'Weekly Log Rejected', 'Your Week 2 log was rejected. Reason: Invalid DTR', 'error', 1, '2026-06-23 03:55:26'),
(18, 12, 'Weekly Log Approved', 'Your Week 1 log (41.00 hrs) has been approved.', 'success', 1, '2026-06-27 13:10:35'),
(19, 11, 'Deployment Date Set', 'Your deployment date has been set to July 01, 2026. Deadline for documents: June 22, 2026.', 'info', 1, '2026-06-29 07:24:55'),
(20, 12, 'Deployment Date Set', 'Your deployment date has been set to July 13, 2026. Deadline for documents: July 06, 2026.', 'info', 1, '2026-06-30 00:26:48'),
(21, 12, 'Deployment Date Set', 'Your deployment date has been set to July 13, 2026. Deadline for documents: July 06, 2026.', 'info', 1, '2026-06-30 00:27:15'),
(22, 11, 'Passing of Documents', 'Start of passing your documents in on July 30,2026', 'info', 1, '2026-07-22 23:48:53'),
(23, 12, 'Passing of Documents', 'Start of passing your documents in on July 30,2026', 'info', 1, '2026-07-22 23:48:53'),
(24, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: Blurred. Please upload other clear photo to see the document more clearer.', 'error', 1, '2026-07-23 00:06:04'),
(25, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: Blurred. Please upload other clear photo to see the document more clearer.', 'error', 1, '2026-07-23 00:07:29'),
(26, 11, '⚠ Hour Deficit — Week 4', 'You rendered 35 hrs this week but the target is 40 hrs. Deficit: 5.0 hrs.', 'warning', 1, '2026-07-23 00:56:59'),
(27, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: Provide the actual photo.', 'error', 1, '2026-07-23 01:40:51'),
(28, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: .', 'error', 1, '2026-07-27 09:12:52'),
(29, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: .', 'error', 1, '2026-07-27 09:13:00'),
(30, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: .', 'error', 1, '2026-07-27 09:13:06'),
(31, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: Upload a raw photo and clear one.', 'error', 1, '2026-07-27 09:20:59'),
(32, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: .', 'error', 1, '2026-07-27 09:28:14'),
(33, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: Upload a raw photo not scanned.', 'error', 1, '2026-07-27 09:28:55'),
(34, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: .', 'error', 1, '2026-07-27 09:30:58'),
(35, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: Upload a raw photo', 'error', 1, '2026-07-27 09:35:37'),
(36, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: Upload a raw photo not scanned and edited', 'error', 1, '2026-07-27 09:36:23'),
(37, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: upload a raw photo not scanned', 'error', 1, '2026-07-27 09:39:36'),
(38, 11, '❌ Document Rejected', 'Your document \"Practicum Training Agreements\" was rejected. Reason: Upload jpg only', 'error', 1, '2026-07-27 09:40:45'),
(39, 11, '❌ Document Rejected', 'Your document \"Practicum Training Agreements\" was rejected. Reason: upload jpg only', 'error', 1, '2026-07-27 09:48:14'),
(40, 11, '❌ Document Rejected', 'Your document \"Practicum Training Agreements\" was rejected. Reason: this is a resume', 'error', 1, '2026-07-27 09:48:45'),
(41, 11, '❌ Document Rejected', 'Your document \"Practicum Training Agreements\" was rejected. Reason: this is a resume', 'error', 1, '2026-07-27 09:48:59'),
(42, 11, '❌ Document Rejected', 'Your document \"Practicum Training Agreements\" was rejected. Reason: This is a resume', 'error', 1, '2026-07-27 09:49:44'),
(43, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: wrong', 'error', 1, '2026-07-30 01:10:30'),
(44, 11, '❌ Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: wrong', 'error', 1, '2026-07-30 01:13:38'),
(45, 11, '✅ Document Approved', 'Your document \"Resume / CV\" has been approved.', 'success', 1, '2026-07-30 01:26:06'),
(46, 11, '❌ Document Rejected', 'Your document \"Practicum Training Agreements\" was rejected. Reason: .', 'error', 1, '2026-07-30 01:34:49'),
(47, 11, '❌ Document Rejected', 'Your document \"Practicum Training Agreements\" was rejected. Reason: .', 'error', 1, '2026-07-30 01:36:33'),
(48, 11, '❌ Document Rejected', 'Your document \"Practicum Training Agreements\" was rejected. Reason: .', 'error', 1, '2026-07-30 01:37:23'),
(49, 11, '❌ Document Rejected', 'Your document \"Practicum Training Agreements\" was rejected. Reason: ,', 'error', 1, '2026-07-30 01:38:00'),
(50, 11, '❌ Document Rejected', 'Your document \"Practicum Training Agreements\" was rejected. Reason: .', 'error', 1, '2026-07-30 01:38:17'),
(51, 11, '❌ Document Rejected', 'Your document \"Practicum Training Agreements\" was rejected. Reason: .', 'error', 1, '2026-07-30 01:38:46'),
(52, 11, '❌ Document Rejected', 'Your document \"Practicum Training Agreements\" was rejected. Reason: .', 'error', 1, '2026-07-30 01:41:57'),
(53, 11, 'Passing of Documents', 'Pass your documents before the deadline to prevent delay on your ojt', 'warning', 1, '2026-07-30 01:57:56'),
(54, 12, 'Passing of Documents', 'Pass your documents before the deadline to prevent delay on your ojt', 'warning', 1, '2026-07-30 01:57:56'),
(55, 11, 'Deployment Date Set', 'Your deployment date has been set to July 01, 2026. Deadline for documents: June 22, 2026.', 'info', 1, '2026-07-30 02:38:39'),
(56, 11, 'OJT Hours Reset', 'Your rendered OJT hours were reset to 0. Reason: Violation of company rules', 'warning', 1, '2026-07-30 03:16:03'),
(57, 11, 'Deployment Date Set', 'Your deployment date has been set to July 01, 2026. Deadline for documents: June 22, 2026.', 'info', 1, '2026-07-30 03:18:26'),
(58, 12, 'Deployment Date Set', 'Your deployment date has been set to July 13, 2026. Deadline for documents: July 06, 2026.', 'info', 1, '2026-07-30 03:18:42'),
(59, 12, '✅ Document Approved', 'Your document \"Resume / CV\" has been approved.', 'success', 1, '2026-08-05 00:08:31'),
(60, 13, 'Weekly Log Approved', 'Your Week 1 log (41.00 hrs) has been approved.', 'success', 1, '2026-08-06 07:05:23'),
(61, 13, 'Weekly Log Rejected', 'Your Week 2 log was rejected. Reason: Not a valid photp', 'error', 1, '2026-08-06 07:06:18'),
(62, 11, 'Weekly Log Rejected', 'Your Week 5 log was rejected. Reason: .', 'error', 1, '2026-08-06 07:34:20'),
(63, 11, 'Weekly Log Rejected', 'Your Week 4 log was rejected. Reason: .', 'error', 1, '2026-08-06 07:34:23'),
(64, 11, 'Weekly Log Rejected', 'Your Week 3 log was rejected. Reason: .', 'error', 1, '2026-08-06 07:34:25'),
(65, 11, 'Weekly Log Approved', 'Your Week 2 log (120.00 hrs) has been approved.', 'success', 1, '2026-08-06 07:34:27'),
(66, 11, 'Weekly Log Approved', 'Your Week 1 log (120.00 hrs) has been approved.', 'success', 1, '2026-08-06 07:34:29'),
(67, 11, 'Weekly Log Approved', 'Your Week 8 log (120.00 hrs) has been approved.', 'success', 1, '2026-08-06 08:17:12'),
(68, 11, 'Weekly Log Approved', 'Your Week 7 log (120.00 hrs) has been approved.', 'success', 1, '2026-08-06 08:17:15'),
(69, 11, 'Weekly Log Rejected', 'Your Week 6 log was rejected. Reason: .', 'error', 1, '2026-08-06 08:17:17'),
(70, 13, 'Weekly Log Rejected', 'Your Week 3 log was rejected. Reason: Invalid photo', 'error', 1, '2026-08-06 13:51:45'),
(71, 13, 'Documents', '.', 'info', 1, '2026-08-07 11:45:02'),
(72, 11, 'Deployment Date Set', 'Your deployment date has been set to August 10, 2026. Deadline for documents: August 03, 2026.', 'info', 1, '2026-08-08 10:11:50'),
(73, 13, 'DTR', 'Please submit your weekly DTR on time and upload a Raw Photo only', 'warning', 1, '2026-08-17 02:03:30'),
(74, 13, 'DTR', '.', 'info', 1, '2026-08-17 03:15:08'),
(75, 13, 'DTR', '.', 'info', 1, '2026-08-17 03:22:28'),
(76, 13, 'DTR', '.', 'info', 1, '2026-08-17 03:22:48'),
(77, 13, 'DTR', '.', 'info', 1, '2026-08-17 03:25:44'),
(78, 13, 'DTR', 'w', 'info', 1, '2026-08-17 03:25:55'),
(79, 13, 'DTR', 'Wrong', 'error', 1, '2026-08-17 03:26:26'),
(80, 13, 'Weekly Log Approved', 'Your Week 4 log (40.0 hrs) has been approved.', 'success', 1, '2026-08-17 03:29:03'),
(81, 13, 'Deployment Date Set', 'Your deployment date has been set to January 10, 2027. Deadline for documents: December 20, 2026.', 'info', 1, '2026-08-19 06:55:20'),
(82, 13, 'OJT Hours Reset', 'Your rendered OJT hours were reset to 0. Reason: Violation of company rules.', 'warning', 1, '2026-08-19 06:57:48'),
(83, 14, 'Weekly Log Rejected', 'Your Week 1 log was rejected. Reason: DTR is scanned not raw photo', 'error', 1, '2026-08-19 07:08:16'),
(84, 14, 'Weekly Log Approved', 'Your Week 2 log (40.0 hrs) has been approved.', 'success', 1, '2026-08-19 07:10:18'),
(85, 14, 'Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: Wrong', 'error', 1, '2026-08-19 07:12:51'),
(86, 14, 'Document Rejected', 'Your document \"Resume / CV\" was rejected. Reason: wrong', 'error', 1, '2026-08-19 07:13:47'),
(87, 14, 'Document Approved', 'Your document \"Resume / CV\" has been approved.', 'success', 1, '2026-08-19 07:14:30'),
(88, 14, 'DTR', 'Submit your weekly dtr', 'warning', 1, '2026-08-19 07:22:11'),
(89, 13, 'Weekly Log Rejected', 'Your Week 1 log was rejected. Reason: .', 'error', 1, '2026-08-28 09:57:54'),
(90, 13, 'Deployment Date Set', 'Your deployment date has been set to January 10, 2027. Deadline for documents: December 20, 2026.', 'info', 1, '2026-08-28 10:05:41'),
(91, 13, 'Weekly Log Rejected', 'Your Week 1 log was rejected. Reason: The signature is not valid', 'error', 1, '2026-08-28 10:20:57'),
(92, 13, 'Weekly Log Rejected', 'Your Week 1 log was rejected. Reason: .', 'error', 1, '2026-08-28 10:21:51'),
(93, 13, 'Weekly Log Rejected', 'Your Week 1 log was rejected. Reason: .', 'error', 1, '2026-08-28 10:37:35'),
(94, 13, 'Weekly Log Approved', 'Your Week 1 log (41.0 hrs) has been approved.', 'success', 1, '2026-08-28 10:38:17'),
(95, 13, 'Weekly Log Approved', 'Your Week 2 log (41.0 hrs) has been approved.', 'success', 1, '2026-08-28 10:41:04'),
(96, 13, 'Weekly Log Rejected', 'Your Week 3 log was rejected. Reason: .', 'error', 1, '2026-08-30 07:59:47'),
(97, 13, 'Weekly Log Rejected', 'Your Week 3 log was rejected. Reason: .', 'error', 1, '2026-08-30 08:06:01'),
(98, 13, 'Weekly Log Approved', 'Your Week 3 log (41.0 hrs) has been approved.', 'success', 1, '2026-08-30 08:07:48'),
(99, 14, 'Weekly Log Rejected', 'Your Week 1 log was rejected. Reason: .', 'error', 1, '2026-08-31 06:58:40'),
(100, 14, 'Weekly Log Rejected', 'Your Week 1 log was rejected. Reason: ,', 'error', 1, '2026-08-31 07:00:32'),
(101, 14, 'Weekly Log Rejected', 'Your Week 1 log was rejected. Reason: .', 'error', 1, '2026-08-31 07:02:14'),
(102, 14, 'Weekly Log Rejected', 'Your Week 1 log was rejected. Reason: .', 'error', 0, '2026-08-31 07:03:10');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'total_required_hours', '600', '2026-06-17 09:22:06'),
(2, 'weekly_required_hours', '40', '2026-06-11 08:48:27'),
(3, 'system_name', 'OJT Monitoring System', '2026-06-11 08:48:27'),
(4, 'school_name', 'City College of Angeles', '2026-06-19 09:07:31'),
(31, 'academic_year', '2027-2028', '2026-08-19 07:23:27'),
(32, 'semester', 'Second Semester', '2026-08-07 07:09:28'),
(35, 'settings_updated_by', '10', '2026-08-06 15:02:25'),
(36, 'settings_updated_at', '2026-08-19 15:23:27', '2026-08-19 07:23:27');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_number` varchar(20) NOT NULL,
  `course` varchar(100) DEFAULT '',
  `section` varchar(20) DEFAULT '',
  `company_name` varchar(150) DEFAULT '',
  `company_id` int(11) DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `coordinator_id` int(11) DEFAULT NULL,
  `required_hours` decimal(10,2) NOT NULL DEFAULT 600.00,
  `weekly_required_hours` decimal(10,2) NOT NULL DEFAULT 40.00,
  `deployment_date` date DEFAULT NULL,
  `pre_deployment_deadline` date DEFAULT NULL,
  `status` enum('pending','deployed','completed') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `student_number`, `course`, `section`, `company_name`, `company_id`, `supervisor_id`, `coordinator_id`, `required_hours`, `weekly_required_hours`, `deployment_date`, `pre_deployment_deadline`, `status`) VALUES
(5, 11, '22-0222', 'BSTM', 'T-401', 'Tourism Adventure Corp.', 1, NULL, 10, 600.00, 40.00, '2026-08-10', '2026-08-03', 'pending'),
(6, 12, '22-0221', 'BSA', 'A-402', 'JGH Accounting', 2, NULL, 10, 600.00, 40.00, NULL, NULL, 'pending'),
(7, 13, '22-0223', 'BSA', 'A-402', 'JGH Accounting', 2, NULL, 10, 600.00, 40.00, '2027-01-10', '2026-12-20', 'pending'),
(8, 14, '22-2222', 'BSTM', 'T-403', 'Tourism Adventure Corp.', 1, NULL, 10, 600.00, 40.00, NULL, NULL, 'pending'),
(9, 15, '22-0123', 'BSA', 'A-401', '', NULL, NULL, 10, 600.00, 40.00, NULL, NULL, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','coordinator','student') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `full_name`, `email`, `created_at`) VALUES
(7, 'admin', '$2y$10$t0bzRstKNP4x1GlXOGhO2uTATjY0e.kakxhsSCmc.nbnV7Y8aCKGO', 'admin', 'System Administrator', 'admin@gmail.com', '2026-06-11 09:43:29'),
(10, '01234', '$2y$10$pqXUAMrEJr/0hO6AxlGO6u9e9JovSPw7sKX6YZ2c2fm6RzSvWoh8.', 'coordinator', 'Juan Miller', 'juanmiller@edu.ph', '2026-06-17 00:02:26'),
(11, '22_0222', '$2y$10$7uFUbLbjkirR4dsNtKEjBut1XmApGh4mnM6SYKq9amtogqUumcAIy', 'student', 'Leonora Teresa', 'leonorateresa22-0222@edu.ph', '2026-06-17 00:02:53'),
(12, '22_0221', '$2y$10$QghhYGgY1moN5SmUM0/h1uaWhy2Y0MzeikT0FLahk4hZSENuwtEZi', 'student', 'Maria Contesa', 'mcontesa22-0221@edu.ph', '2026-06-22 01:50:13'),
(13, 'mpolo22-0223@edu.ph', '$2y$10$homB8Yczn5dEXnvLaX6qZeT2xKxk6aWpbKlatnqiynkm2Jfmx9UrG', 'student', 'Marko Polo', 'Mpolo22-0223@edu.ph', '2026-08-06 06:51:51'),
(14, 'gfreek22-2222@edu.ph', '$2y$10$5TiHaQN3ldb6/D9NWRaPcuE/uLbnNqlKaFYJF94Xe8zZ4qv3zHf5W', 'student', 'Gon Freeks', 'gfreeks22-2222@edu.ph', '2026-08-07 11:48:46'),
(15, 'rtempest22-0123@edu.ph', '$2y$10$bpTP2YPREVCBMHkpoan0AuoOEzNdOvaoGZq/zZHXUy3KuBRJawUF.', 'student', 'Rimuru Tempest', 'rtempest22-0123@edu.ph', '2026-08-09 02:29:13');

-- --------------------------------------------------------

--
-- Table structure for table `weekly_logs`
--

CREATE TABLE `weekly_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `week_number` int(11) NOT NULL,
  `week_start` date NOT NULL,
  `week_end` date NOT NULL,
  `rendered_hours` decimal(5,2) DEFAULT 0.00,
  `dtr_photo` varchar(500) DEFAULT '',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `coordinator_remarks` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `signature_x` decimal(8,6) DEFAULT NULL,
  `signature_y` decimal(8,6) DEFAULT NULL,
  `signature_width` decimal(8,6) DEFAULT NULL,
  `signature_height` decimal(8,6) DEFAULT NULL,
  `signature_similarity` decimal(5,2) DEFAULT NULL,
  `signature_result` varchar(100) DEFAULT NULL,
  `signature_checked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `weekly_logs`
--

INSERT INTO `weekly_logs` (`id`, `student_id`, `week_number`, `week_start`, `week_end`, `rendered_hours`, `dtr_photo`, `status`, `coordinator_remarks`, `submitted_at`, `reviewed_at`, `reviewed_by`, `signature_x`, `signature_y`, `signature_width`, `signature_height`, `signature_similarity`, `signature_result`, `signature_checked_at`) VALUES
(12, 6, 1, '2026-06-22', '2026-06-26', 41.00, 'dtr/dtr_6_wk1_1782565790.png', 'approved', 'Very Good!', '2026-06-27 13:09:50', '2026-06-27 13:10:35', 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 5, 1, '2026-08-03', '2026-08-07', 120.00, 'dtr/dtr_5_wk1_1786001567.png', 'approved', '.', '2026-08-06 07:32:47', '2026-08-06 07:34:29', 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 5, 2, '2026-08-03', '2026-08-07', 120.00, 'dtr/dtr_5_wk2_1786001587.png', 'approved', '.', '2026-08-06 07:33:07', '2026-08-06 07:34:27', 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 5, 3, '2026-08-03', '2026-08-07', 120.00, 'dtr/dtr_5_wk3_1786001596.png', 'rejected', '.', '2026-08-06 07:33:16', '2026-08-06 07:34:25', 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 5, 4, '2026-08-03', '2026-08-07', 120.00, 'dtr/dtr_5_wk4_1786001607.png', 'rejected', '.', '2026-08-06 07:33:27', '2026-08-06 07:34:23', 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 5, 5, '2026-08-03', '2026-08-07', 120.00, 'dtr/dtr_5_wk5_1786001622.png', 'rejected', '.', '2026-08-06 07:33:42', '2026-08-06 07:34:20', 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(24, 5, 6, '2026-08-03', '2026-08-07', 120.00, 'dtr/dtr_5_wk6_1786003905.png', 'rejected', '.', '2026-08-06 08:11:45', '2026-08-06 08:17:17', 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(25, 5, 7, '2026-08-03', '2026-08-07', 120.00, 'dtr/dtr_5_wk7_1786003914.png', 'approved', '.', '2026-08-06 08:11:54', '2026-08-06 08:17:15', 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(26, 5, 8, '2026-08-03', '2026-08-07', 120.00, 'dtr/dtr_5_wk8_1786003925.png', 'approved', '.', '2026-08-06 08:12:05', '2026-08-06 08:17:12', 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, 8, 1, '2026-08-10', '2026-08-14', 40.00, 'dtr/dtr_8_wk1_1788159769_f366fd.jpg', 'rejected', '.', '2026-08-31 07:02:51', '2026-08-31 07:03:10', 10, 0.031621, 0.132806, 0.823715, 0.670356, 88.29, 'High Similarity', '2026-08-31 15:02:51'),
(30, 8, 2, '2026-08-17', '2026-08-21', 40.00, 'dtr/dtr_8_wk2_1787123408.png', 'approved', 'Good.', '2026-08-19 07:10:08', '2026-08-19 07:10:18', 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(31, 7, 1, '2026-08-24', '2026-08-28', 41.00, 'dtr/dtr_7_wk1_1787913476_bbef49.jpg', 'approved', '.', '2026-08-28 10:38:00', '2026-08-28 10:38:17', 10, 0.039526, 0.187615, 0.960474, 0.763109, 86.99, 'High Similarity', '2026-08-28 18:38:00'),
(32, 7, 2, '2026-08-24', '2026-08-28', 41.00, 'dtr/dtr_7_wk2_1787913654_4ccaf8.png', 'approved', ',', '2026-08-28 10:40:57', '2026-08-28 10:41:04', 10, 0.487582, 0.709153, 0.500414, 0.193024, 25.89, 'Needs Manual Verification', '2026-08-28 18:40:57'),
(33, 7, 3, '2026-08-24', '2026-08-28', 41.00, 'dtr/dtr_7_wk3_1788077195_4db902.jpg', 'approved', 'okay', '2026-08-30 08:06:38', '2026-08-30 08:07:48', 10, 0.031621, 0.153887, 0.888538, 0.638735, 81.60, 'High Similarity', '2026-08-30 16:06:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_companies_created_by` (`created_by`);

--
-- Indexes for table `company_moa`
--
ALTER TABLE `company_moa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_company_moa_company` (`company_id`),
  ADD KEY `fk_company_moa_created_by` (`created_by`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_chat` (`student_id`,`coordinator_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `coordinator_id` (`coordinator_id`);

--
-- Indexes for table `document_requirements`
--
ALTER TABLE `document_requirements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `document_submissions`
--
ALTER TABLE `document_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `requirement_id` (`requirement_id`);

--
-- Indexes for table `hour_resets`
--
ALTER TABLE `hour_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversation_id` (`conversation_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `coordinator_id` (`coordinator_id`),
  ADD KEY `fk_students_company` (`company_id`),
  ADD KEY `fk_student_supervisor` (`supervisor_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `weekly_logs`
--
ALTER TABLE `weekly_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `company_moa`
--
ALTER TABLE `company_moa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `document_requirements`
--
ALTER TABLE `document_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `document_submissions`
--
ALTER TABLE `document_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `hour_resets`
--
ALTER TABLE `hour_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=166;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `weekly_logs`
--
ALTER TABLE `weekly_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `fk_companies_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `company_moa`
--
ALTER TABLE `company_moa`
  ADD CONSTRAINT `fk_company_moa_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_company_moa_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_submissions`
--
ALTER TABLE `document_submissions`
  ADD CONSTRAINT `document_submissions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_submissions_ibfk_2` FOREIGN KEY (`requirement_id`) REFERENCES `document_requirements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `weekly_logs`
--
ALTER TABLE `weekly_logs`
  ADD CONSTRAINT `weekly_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
