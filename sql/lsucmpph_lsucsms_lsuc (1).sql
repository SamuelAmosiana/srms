-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 07, 2026 at 07:21 AM
-- Server version: 11.4.9-MariaDB-cll-lve-log
-- PHP Version: 8.3.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lsucmpph_lsucsms_lsuc`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_calendars`
--

CREATE TABLE `academic_calendars` (
  `id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `registration_deadline` date DEFAULT NULL,
  `exam_start_date` date DEFAULT NULL,
  `exam_end_date` date DEFAULT NULL,
  `holidays` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_calendars`
--

INSERT INTO `academic_calendars` (`id`, `academic_year`, `semester`, `start_date`, `end_date`, `registration_deadline`, `exam_start_date`, `exam_end_date`, `holidays`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2024/2025', 'Semester 1', '2024-09-01', '2024-12-15', NULL, NULL, NULL, NULL, NULL, 1, '2025-12-22 15:30:32', '2025-12-22 15:30:32'),
(2, '2024/2025', 'Semester 2', '2025-01-15', '2025-05-30', NULL, NULL, NULL, NULL, NULL, 1, '2025-12-22 15:30:32', '2025-12-22 15:30:32'),
(3, '2024/2025', 'Semester 1', '2024-09-01', '2024-12-15', NULL, NULL, NULL, NULL, NULL, 1, '2025-12-22 15:31:11', '2025-12-22 15:31:11'),
(4, '2024/2025', 'Semester 2', '2025-01-15', '2025-05-30', NULL, NULL, NULL, NULL, NULL, 1, '2025-12-22 15:31:11', '2025-12-22 15:31:11');

-- --------------------------------------------------------

--
-- Table structure for table `academic_year_comments`
--

CREATE TABLE `academic_year_comments` (
  `id` int(11) NOT NULL,
  `student_user_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `comment` text NOT NULL,
  `added_by_user_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_year_comments`
--

INSERT INTO `academic_year_comments` (`id`, `student_user_id`, `academic_year`, `comment`, `added_by_user_id`, `added_at`) VALUES
(1, 4, '2024', 'Excellent performance throughout the academic year. Keep up the good work!', 1, '2025-12-22 10:48:04');

-- --------------------------------------------------------

--
-- Table structure for table `accommodation_applications`
--

CREATE TABLE `accommodation_applications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `application_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `room_preference` enum('single','double','triple') DEFAULT 'double',
  `block_preference` varchar(50) DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `special_requests` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','allocated') DEFAULT 'pending',
  `approved_date` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `allocation_details` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accommodation_rooms`
--

CREATE TABLE `accommodation_rooms` (
  `id` int(11) NOT NULL,
  `block_name` varchar(50) NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `room_type` enum('single','double','triple') NOT NULL,
  `floor_number` int(11) DEFAULT NULL,
  `capacity` int(11) NOT NULL,
  `occupied_spots` int(11) DEFAULT 0,
  `status` enum('available','occupied','maintenance') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_profile`
--

CREATE TABLE `admin_profile` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `staff_id` varchar(50) DEFAULT NULL,
  `bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_profile`
--

INSERT INTO `admin_profile` (`user_id`, `full_name`, `staff_id`, `bio`) VALUES
(1, 'John Admin', 'ADM001', NULL),
(9, 'Jane Admin', 'ADM002', NULL),
(42, 'Academics Coordinator', 'ACAD001', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `application_type` enum('undergraduate','short_course') DEFAULT NULL,
  `programme_id` int(11) DEFAULT NULL,
  `intake_id` int(11) DEFAULT NULL,
  `mode_of_learning` enum('online','physical') DEFAULT NULL,
  `documents` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `full_name`, `email`, `phone`, `application_type`, `programme_id`, `intake_id`, `mode_of_learning`, `documents`, `status`, `rejection_reason`, `processed_by`, `updated_at`, `created_at`, `processed_at`) VALUES
(1, 'John Sample Student', 'john.sample@lsc.ac.zm', NULL, 'short_course', 1, 4, NULL, '[{\"name\":\"Transcript.pdf\",\"path\":\"\\/uploads\\/transcript.pdf\"},{\"name\":\"ID_Copy.pdf\",\"path\":\"\\/uploads\\/id_copy.pdf\"}]', 'approved', NULL, NULL, '2025-11-07 13:53:25', '2025-10-13 11:07:59', NULL),
(2, 'sam smith', 'sianamtesamuel@gmail.com', NULL, NULL, NULL, NULL, NULL, '[{\"name\":\"intakes_2025-10-17.csv\",\"path\":\"uploads\\/1760967252_grade12results_intakes_2025-10-17.csv\"},{\"name\":\"student_docket_LSC000001.pdf\",\"path\":\"uploads\\/1760967252_previousschool_student_docket_LSC000001.pdf\"}]', 'approved', NULL, NULL, '2025-10-29 13:28:43', '2025-10-20 13:34:12', NULL),
(3, 'Test Sianamate', 'sianamatesamuel@gmail.com', NULL, NULL, NULL, NULL, NULL, '{\"0\":{\"name\":\"student_docket_LSC000001.pdf\",\"path\":\"uploads\\/1760969019_grade12results_student_docket_LSC000001.pdf\"},\"1\":{\"name\":\"student_docket_LSC000001.pdf\",\"path\":\"uploads\\/1760969019_previousschool_student_docket_LSC000001.pdf\"},\"recommended_by\":\"none\"}', 'approved', NULL, NULL, '2025-10-29 13:28:43', '2025-10-20 14:03:39', NULL),
(4, 'sam smith', 'sianamtesamuel@gmail.com', NULL, NULL, NULL, NULL, NULL, '{\"occupation\":\"\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"agent\"}', 'approved', NULL, NULL, '2025-10-29 13:28:43', '2025-10-27 11:04:35', NULL),
(5, 'Tes1 Test2', 'test@mail.com', NULL, NULL, NULL, NULL, NULL, '{\"0\":{\"name\":\"student_docket_LSC000001 (1).pdf\",\"path\":\"uploads\\/1761572818_grade12results_student_docket_LSC000001 (1).pdf\"},\"1\":{\"name\":\"student_docket_LSC000001 (1).pdf\",\"path\":\"uploads\\/1761572818_previousschool_student_docket_LSC000001 (1).pdf\"},\"recommended_by\":\"none\"}', 'approved', NULL, NULL, '2025-10-29 13:28:43', '2025-10-27 13:46:58', NULL),
(6, 'john Doe', 'doe@example.com', NULL, NULL, NULL, NULL, NULL, '{\"0\":{\"name\":\"intakes_2025-10-17.csv\",\"path\":\"uploads\\/1761574219_grade12results_intakes_2025-10-17.csv\"},\"1\":{\"name\":\"intakes_2025-10-17.csv\",\"path\":\"uploads\\/1761574219_previousschool_intakes_2025-10-17.csv\"},\"recommended_by\":\"none\"}', 'rejected', 'Results are not complete', NULL, '2025-10-29 13:28:43', '2025-10-27 14:10:19', NULL),
(7, 'NATEC', 'sianamtesamuel@gmail.com', '0979667723', NULL, NULL, NULL, NULL, '{\"type\":\"corporate_training\",\"company\":\"NATEC\",\"industry\":\"technology\",\"company_size\":\"1-10\",\"address\":\"chongwe\",\"contact_name\":\"michelo\",\"position\":\"CEO\",\"phone\":\"0979667723\",\"training_type\":\"leadership\",\"participants\":\"1\",\"duration\":\"half-day\",\"location\":\"on-site\",\"budget\":\"under-5000\",\"specific_needs\":\"leadership\",\"timeline\":\"1 week\"}', 'approved', NULL, NULL, '2025-11-07 09:11:28', '2025-10-29 11:45:13', NULL),
(8, 'Applicant 1', 'applicant1@example.com', NULL, 'short_course', 1, 1, NULL, NULL, 'approved', NULL, 20, '2025-11-07 13:53:25', '2025-10-30 08:29:08', NULL),
(9, 'Applicant 2', 'applicant2@example.com', NULL, 'short_course', 1, 1, NULL, NULL, 'approved', NULL, 20, '2025-11-07 13:53:25', '2025-10-30 08:29:08', NULL),
(10, 'Applicant 3', 'applicant3@example.com', NULL, 'short_course', 1, 1, NULL, NULL, 'approved', NULL, 20, '2025-11-07 13:53:25', '2025-10-30 08:29:08', NULL),
(11, 'Applicant 4', 'applicant4@example.com', NULL, 'short_course', 1, 1, NULL, NULL, 'rejected', 'Does not meet requirements', 22, '2025-11-07 13:53:25', '2025-10-30 08:29:08', NULL),
(12, 'Applicant 5', 'applicant5@example.com', NULL, 'short_course', 1, 1, NULL, NULL, 'approved', NULL, 22, '2025-11-07 13:53:25', '2025-10-30 08:29:08', NULL),
(13, 'moses Phiri', 'Moses@example.com', NULL, 'short_course', 6, 1, NULL, '{\"0\":{\"name\":\"lsuc 2026 admissioin advert.png\",\"path\":\"uploads\\/1761835405_grade12results_lsuc 2026 admissioin advert.png\"},\"1\":{\"name\":\"lsuc 2026 admissioin Advert (1).png\",\"path\":\"uploads\\/1761835405_previousschool_lsuc 2026 admissioin Advert (1).png\"},\"recommended_by\":\"\"}', 'approved', NULL, 20, '2025-11-17 12:51:19', '2025-10-30 14:43:25', NULL),
(14, 'Kondwani Banda', 'kondwani@gmail.com', NULL, 'short_course', 25, 1, NULL, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1761904390_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1761904390_previousschool_IT-DETAILS (2)-1.pdf\"},\"recommended_by\":\"none\"}', 'approved', NULL, 20, '2025-11-07 13:53:25', '2025-10-31 09:53:10', NULL),
(15, 'John Mbewe', 'john@gmai.com', NULL, 'undergraduate', 26, 2, NULL, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1761912785_grade12results_IT-DETAILS (2)-1 (1).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1761912785_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"none\"}', 'approved', NULL, 20, '2025-11-27 08:17:37', '2025-10-31 12:13:05', NULL),
(16, 'NOTEC', 'sianamtesamuel@gmail.com', '0979667723', NULL, NULL, NULL, NULL, '{\"type\":\"corporate_training\",\"company\":\"NOTEC\",\"industry\":\"finance\",\"company_size\":\"1-10\",\"address\":\"chongwe\\r\\nSite and service\",\"contact_name\":\"Kay Katongo\",\"position\":\"Secretary\",\"phone\":\"0979667723\",\"training_type\":\"leadership\",\"participants\":\"1\",\"duration\":\"half-day\",\"location\":\"lsuc-campus\",\"budget\":\"under-5000\",\"specific_needs\":\"none\",\"timeline\":\"5 days\"}', 'approved', NULL, 22, '2025-11-07 09:11:28', '2025-10-31 12:15:42', NULL),
(17, 'Anna Mulenga', 'anna@gmail.com', NULL, 'undergraduate', 20, 1, NULL, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1762002503_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1762002503_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"Banda\"}', 'approved', NULL, 20, '2025-11-17 08:14:49', '2025-11-01 13:08:23', NULL),
(18, 'Mwale Phiri', 'mwale@gmial.com', '0979667723', NULL, NULL, NULL, NULL, '{\"phone\":\"0979667723\",\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'pending', NULL, NULL, '2025-11-07 09:11:28', '2025-11-06 08:34:03', NULL),
(19, 'Test User', 'test@example.com', '123-456-7890', NULL, NULL, NULL, NULL, '{\"phone\":\"123-456-7890\",\"occupation\":\"Developer\",\"schedule\":\"weekdays\",\"experience\":\"5 years\",\"goals\":\"Career advancement\"}', 'pending', NULL, NULL, '2025-11-07 09:11:28', '2025-11-06 09:30:38', NULL),
(20, 'Mwale Phiri', 'mwale@gmial.com', '0979667723', NULL, NULL, NULL, NULL, '{\"phone\":\"0979667723\",\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'pending', NULL, NULL, '2025-11-07 09:11:28', '2025-11-06 14:08:11', NULL),
(21, 'Mwale Phiri', 'mwale@gmial.com', '0979667723', NULL, NULL, NULL, NULL, '{\"phone\":\"0979667723\",\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'pending', NULL, NULL, '2025-11-07 09:11:28', '2025-11-07 08:47:04', NULL),
(22, 'Brian Chishimba', 'brianchishimba@gmail.com', '0978654534', NULL, NULL, NULL, NULL, '{\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"none\",\"goals\":\"none\"}', 'pending', NULL, NULL, '2025-11-07 09:14:55', '2025-11-07 09:14:55', NULL),
(23, 'Amosiana Sam', 'sianamatesamuel@gmail.com', '0979667723', 'short_course', 7, 1, NULL, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762513266_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762513266_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', 'approved', NULL, 20, '2025-11-07 13:53:25', '2025-11-07 11:01:06', NULL),
(24, 'Amosiana Sam', 'sianamatesamuel@gmail.com', '0979667723', 'short_course', 7, 1, NULL, '{\"0\":{\"name\":\"lsucmpph.coreftp\",\"path\":\"uploads\\/1762519536_grade12results_lsucmpph.coreftp\"},\"1\":{\"name\":\"Secure lsucmpph.coreftp\",\"path\":\"uploads\\/1762519536_previousschool_Secure lsucmpph.coreftp\"},\"recommended_by\":\"Jones\"}', 'approved', NULL, 20, '2025-11-07 13:53:25', '2025-11-07 12:45:36', NULL),
(25, 'short course', 'test@gmail.com', '0978654534', 'short_course', NULL, NULL, NULL, '{\"occupation\":\"\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'rejected', 'testing', 20, '2025-11-07 14:02:35', '2025-11-07 14:00:24', NULL),
(26, 'long courses', 'test@gmail.com', '0978654534', 'undergraduate', 27, 1, NULL, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762524115_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762524115_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', 'rejected', 'testing', 20, '2025-11-07 14:02:20', '2025-11-07 14:01:55', NULL),
(27, 'long courses', 'sianamatesamuel@gmail.com', '0978654534', 'undergraduate', 12, 1, NULL, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', 'approved', NULL, 20, '2025-11-08 09:09:25', '2025-11-07 14:22:02', NULL),
(28, 'long courses', 'sianamatesamuel@gmail.com', '0978654534', 'undergraduate', 12, 1, NULL, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762592759_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762592759_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', 'approved', NULL, 20, '2025-11-08 09:09:56', '2025-11-08 09:05:59', NULL),
(29, 'long courses', 'sianamatesamuel@gmail.com', '0978654534', 'undergraduate', 12, 1, NULL, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762612330_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762612330_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', 'rejected', 'clearance', 20, '2025-11-27 08:20:18', '2025-11-08 14:32:10', NULL),
(30, 'long courses', 'sianamatesamuel@gmail.com', '0978654534', 'undergraduate', 12, 1, NULL, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762760428_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762760428_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', 'rejected', 'testing', 20, '2025-11-27 08:20:51', '2025-11-10 07:40:28', NULL),
(31, 'long courses', 'sianamatesamuel@gmail.com', '0978654534', 'undergraduate', 12, 1, NULL, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1763025340_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1763025340_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', 'rejected', 'cleaning', 20, '2025-11-27 08:20:33', '2025-11-13 09:15:40', NULL),
(32, 'long courses', 'sianamatesamuel@gmail.com', '0978654534', 'undergraduate', 12, 1, NULL, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1763029150_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1763029150_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', 'rejected', 'Testing', 20, '2025-11-27 08:20:40', '2025-11-13 10:19:10', NULL),
(33, 'long courses', 'sianamatesamuel@gmail.com', '0978654534', 'undergraduate', 12, 1, NULL, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1763101890_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1763101890_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', 'rejected', 'cleaning', 20, '2025-11-27 08:20:25', '2025-11-14 06:31:30', NULL),
(34, 'long courses', 'sianamatesamuel@gmail.com', '0978654534', 'undergraduate', 12, 1, NULL, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1763367295_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1763367295_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', 'approved', NULL, 20, '2025-11-27 08:19:56', '2025-11-17 08:14:55', NULL),
(35, 'long courses', 'sianamatesamuel@gmail.com', '0978654534', 'undergraduate', 12, 1, NULL, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1763383842_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1763383842_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', 'rejected', 'noting', 20, '2025-11-27 08:19:49', '2025-11-17 12:50:42', NULL),
(36, 'sam smith', 'sianamtesamuel@gmail.com', '0979667723', 'undergraduate', 33, 1, NULL, '{\"0\":{\"name\":\"Screenshot (169).png\",\"path\":\"uploads\\/1764079902_grade12results_Screenshot (169).png\"},\"1\":{\"name\":\"Screenshot (169).png\",\"path\":\"uploads\\/1764079902_previousschool_Screenshot (169).png\"},\"recommended_by\":\"Jones\"}', 'approved', NULL, 20, '2025-11-25 14:12:31', '2025-11-25 14:11:42', NULL),
(37, 'sam smith', 'smithamosiana@gmail.com', '0979667723', 'undergraduate', 28, 1, NULL, '{\"0\":{\"name\":\"Screenshot (188).png\",\"path\":\"uploads\\/1764232014_grade12results_Screenshot (188).png\"},\"1\":{\"name\":\"Screenshot (188).png\",\"path\":\"uploads\\/1764232014_previousschool_Screenshot (188).png\"},\"recommended_by\":\"Jones\"}', 'approved', NULL, 20, '2025-11-27 08:27:22', '2025-11-27 08:26:54', NULL),
(38, 'Husted Chola', 'hustedchola114@gmail.com', '0979886013', 'undergraduate', 6, 1, NULL, '{\"0\":{\"name\":\"Screenshot (144).png\",\"path\":\"uploads\\/1764238098_grade12results_Screenshot (144).png\"},\"1\":{\"name\":\"Screenshot (144).png\",\"path\":\"uploads\\/1764238098_previousschool_Screenshot (144).png\"},\"recommended_by\":\"Samuel\"}', 'approved', NULL, 20, '2025-11-27 10:09:37', '2025-11-27 10:08:18', NULL),
(39, 'Husted Chola', 'hustedchola114@gmail.com', '0979886013', 'undergraduate', 6, 1, NULL, '{\"0\":{\"name\":\"Screenshot (144).png\",\"path\":\"uploads\\/1764238966_grade12results_Screenshot (144).png\"},\"1\":{\"name\":\"Screenshot (144).png\",\"path\":\"uploads\\/1764238966_previousschool_Screenshot (144).png\"},\"recommended_by\":\"Samuel\"}', 'approved', NULL, 20, '2025-11-27 10:23:10', '2025-11-27 10:22:46', NULL),
(40, 'Whiteson Chilambwe', 'chilambwewhiteson@gmail.com', '0979886013', 'undergraduate', 19, 1, NULL, '{\"0\":{\"name\":\"Screenshot (144).png\",\"path\":\"uploads\\/1764254859_grade12results_Screenshot (144).png\"},\"1\":{\"name\":\"Screenshot (144).png\",\"path\":\"uploads\\/1764254859_previousschool_Screenshot (144).png\"},\"recommended_by\":\"Samuel\"}', 'approved', NULL, 20, '2025-11-27 14:48:15', '2025-11-27 14:47:39', NULL),
(41, 'peter chikubula', 'peterchikubula@lsuczm.com', '0979886013', 'undergraduate', 15, 1, NULL, '{\"0\":{\"name\":\"Screenshot (144).png\",\"path\":\"uploads\\/1764256865_grade12results_Screenshot (144).png\"},\"1\":{\"name\":\"Screenshot (144).png\",\"path\":\"uploads\\/1764256865_previousschool_Screenshot (144).png\"},\"recommended_by\":\"Samuel\"}', 'approved', NULL, 20, '2025-11-27 15:21:47', '2025-11-27 15:21:05', NULL),
(42, 'Mildred Chisenga', 'mildredchisenga43@gmail.com', '0978654534', 'undergraduate', 21, 1, NULL, '{\"0\":{\"name\":\"transactions_export.csv\",\"path\":\"uploads\\/1764337726_grade12results_transactions_export.csv\"},\"1\":{\"name\":\"transactions_sample (1).csv\",\"path\":\"uploads\\/1764337726_previousschool_transactions_sample (1).csv\"},\"recommended_by\":\"Jones\"}', 'approved', NULL, 20, '2025-11-28 13:52:32', '2025-11-28 13:48:46', NULL),
(44, 'sam smith', 'sianamtesamuel@gmail.com', '0979667723', 'short_course', NULL, NULL, NULL, '{\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'rejected', 'Incorect email', 20, '2026-01-06 09:20:57', '2025-12-04 10:04:42', NULL),
(45, 'sam smith', 'sianamtesamuel@gmail.com', '0979667723', 'short_course', NULL, NULL, NULL, '{\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'rejected', 'Incorect email', 20, '2026-01-06 09:20:51', '2025-12-05 08:13:17', NULL),
(46, 'sam smith', 'sianamtesamuel@gmail.com', '0979667723', 'short_course', NULL, NULL, NULL, '{\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'rejected', 'Incorect email', 20, '2026-01-06 09:20:46', '2025-12-15 06:36:12', NULL),
(47, 'sam smith', 'sianamtesamuel@gmail.com', '0979667723', 'short_course', NULL, NULL, NULL, '{\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'rejected', 'Incorect email', 20, '2026-01-06 09:20:34', '2025-12-18 08:20:09', NULL),
(48, 'sam smith', 'sianamtesamuel@gmail.com', '0979667723', 'short_course', NULL, NULL, NULL, '{\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'approved', NULL, 20, '2026-01-06 09:19:52', '2025-12-18 08:20:26', NULL),
(49, 'sam smith', 'sianamtesamuel@gmail.com', '0979667723', 'short_course', NULL, NULL, NULL, '{\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'rejected', 'no meeting requiremenrts', 4, '2025-12-19 07:36:26', '2025-12-18 08:28:12', '2025-12-19 07:36:26'),
(50, 'sam smith', 'sianamatesamuel@gmail.com', '0979667723', 'undergraduate', 23, 1, 'physical', '{\"0\":{\"name\":\"images.jpeg\",\"path\":\"uploads\\/1767165206_grade12results_images.jpeg\"},\"1\":{\"name\":\"images.jpeg\",\"path\":\"uploads\\/1767165206_previousschool_images.jpeg\"},\"recommended_by\":\"Jones\"}', 'approved', NULL, 20, '2025-12-31 07:25:10', '2025-12-31 07:13:26', NULL),
(51, 'sam smith', 'sianamatesamuel@gmail.com', '0979667723', 'undergraduate', 23, 1, 'physical', '{\"0\":{\"name\":\"images.jpeg\",\"path\":\"uploads\\/1767180050_grade12results_images.jpeg\"},\"1\":{\"name\":\"images.jpeg\",\"path\":\"uploads\\/1767180050_previousschool_images.jpeg\"},\"recommended_by\":\"Jones\"}', 'approved', NULL, 20, '2025-12-31 12:15:55', '2025-12-31 11:20:50', NULL),
(52, 'Test Test', 'sianamatesamuel@gmail.com', '0979667723', 'undergraduate', 27, 1, 'physical', '{\"0\":{\"name\":\"images.jpeg\",\"path\":\"uploads\\/1767181621_grade12results_images.jpeg\"},\"1\":{\"name\":\"images.jpeg\",\"path\":\"uploads\\/1767181621_previousschool_images.jpeg\"},\"recommended_by\":\"none\"}', 'approved', NULL, 20, '2025-12-31 11:47:32', '2025-12-31 11:47:01', NULL),
(53, 'Benson Tembo', 'tliphars@gmail.com', '0966871455', 'undergraduate', 5, 1, 'online', '{\"0\":{\"name\":\"17672561018764316081208148589348.jpg\",\"path\":\"uploads\\/1767256375_grade12results_17672561018764316081208148589348.jpg\"},\"1\":{\"name\":\"17672563207441916994706985031849.jpg\",\"path\":\"uploads\\/1767256375_previousschool_17672563207441916994706985031849.jpg\"},\"recommended_by\":\"\"}', 'approved', NULL, 22, '2026-01-06 09:22:26', '2026-01-01 08:32:55', NULL),
(54, 'Benson Tembo', 'tliphars@gmail.com', '0966871455', 'undergraduate', 5, 1, 'online', '{\"0\":{\"name\":\"17672561018764316081208148589348.jpg\",\"path\":\"uploads\\/1767256887_grade12results_17672561018764316081208148589348.jpg\"},\"1\":{\"name\":\"17672563207441916994706985031849.jpg\",\"path\":\"uploads\\/1767256887_previousschool_17672563207441916994706985031849.jpg\"},\"recommended_by\":\"\"}', 'approved', NULL, 22, '2026-01-05 06:50:21', '2026-01-01 08:41:27', NULL),
(55, 'Test Test', 'sianamatesamuel@gmail.com', '0979667723', 'undergraduate', 27, 1, 'physical', '{\"0\":{\"name\":\"images.jpeg\",\"path\":\"uploads\\/1767354490_grade12results_images.jpeg\"},\"1\":{\"name\":\"images.jpeg\",\"path\":\"uploads\\/1767354490_previousschool_images.jpeg\"},\"recommended_by\":\"none\"}', 'rejected', 'kjyugi', 1, '2026-01-04 07:37:03', '2026-01-02 11:48:10', '2026-01-04 07:37:03'),
(56, 'Reagan Susa', 'Reagansusa036@gmail.com', '0960882869', 'undergraduate', 28, 1, 'physical', '{\"0\":{\"name\":\"Screenshot_20260102-215534_Phoenix.jpg\",\"path\":\"uploads\\/1767384009_grade12results_Screenshot_20260102-215534_Phoenix.jpg\"},\"1\":{\"name\":\"Screenshot_20260102-215534_Phoenix.jpg\",\"path\":\"uploads\\/1767384009_previousschool_Screenshot_20260102-215534_Phoenix.jpg\"},\"recommended_by\":\"\"}', 'approved', NULL, 1, '2026-01-04 07:36:11', '2026-01-02 20:00:09', '2026-01-04 07:36:11'),
(57, 'Chomba Ntambi', 'ntambichomba@gmail.com', '0963556993', 'undergraduate', 24, 1, 'physical', '{\"0\":{\"name\":\"IMG_7738.jpeg\",\"path\":\"uploads\\/1767601344_grade12results_IMG_7738.jpeg\"},\"1\":{\"name\":\"A9B6CC3E-1A1D-466D-AF33-36D12C5F0EBC.jpeg\",\"path\":\"uploads\\/1767601344_previousschool_A9B6CC3E-1A1D-466D-AF33-36D12C5F0EBC.jpeg\"},\"recommended_by\":\"\"}', 'approved', NULL, 20, '2026-01-05 08:23:25', '2026-01-05 08:22:24', NULL),
(58, 'Test Test', 'sianamatesamuel@gmail.com', '0979667723', 'undergraduate', 27, 1, 'physical', '{\"0\":{\"name\":\"images.jpeg\",\"path\":\"uploads\\/1767601738_grade12results_images.jpeg\"},\"1\":{\"name\":\"images.jpeg\",\"path\":\"uploads\\/1767601738_previousschool_images.jpeg\"},\"recommended_by\":\"none\"}', 'approved', NULL, 23, '2026-01-07 07:38:59', '2026-01-05 08:28:58', NULL),
(59, 'Winnie Chenga', 'winniechenga@gmail.com', '0979667723', 'undergraduate', 12, 1, 'physical', '{\"0\":{\"name\":\"images.jpeg\",\"path\":\"uploads\\/1767616982_grade12results_images.jpeg\"},\"1\":{\"name\":\"72ef2d4f999c44729a55ab46fba425d2.webp\",\"path\":\"uploads\\/1767616982_previousschool_72ef2d4f999c44729a55ab46fba425d2.webp\"},\"recommended_by\":\"Banda\"}', 'approved', NULL, 20, '2026-01-05 12:44:16', '2026-01-05 12:43:02', NULL),
(60, 'NATASHA CHISENGA', 'nchisenga303@gmail.com', '+260773719900', 'undergraduate', 12, 1, 'online', '{\"0\":{\"name\":\"AnyScanner_11_04_2024.pdf\",\"path\":\"uploads\\/1767632715_grade12results_AnyScanner_11_04_2024.pdf\"},\"1\":{\"name\":\"AnyScanner_11_04_2024.pdf\",\"path\":\"uploads\\/1767632715_previousschool_AnyScanner_11_04_2024.pdf\"},\"recommended_by\":\"PETER CHIKUBULA JR\"}', 'approved', NULL, 22, '2026-01-06 06:46:55', '2026-01-05 17:05:15', NULL),
(61, 'Hellen Chewe', 'hellenchandachewe24@gmail.com', '0770909546', 'undergraduate', 38, 1, 'physical', '{\"0\":{\"name\":\"IMG-20251119-WA0004.jpg\",\"path\":\"uploads\\/1767684502_grade12results_IMG-20251119-WA0004.jpg\"},\"1\":{\"name\":\"IMG-20251119-WA0004.jpg\",\"path\":\"uploads\\/1767684502_previousschool_IMG-20251119-WA0004.jpg\"},\"recommended_by\":\"Monica\"}', 'approved', NULL, 23, '2026-01-06 08:31:31', '2026-01-06 07:28:22', NULL),
(62, 'Hellen Chewe', 'hellenchandachewe24@gmail.com', '0770909546', 'undergraduate', 38, 1, 'physical', '{\"0\":{\"name\":\"IMG-20251119-WA0004.jpg\",\"path\":\"uploads\\/1767684518_grade12results_IMG-20251119-WA0004.jpg\"},\"1\":{\"name\":\"IMG-20251119-WA0004.jpg\",\"path\":\"uploads\\/1767684518_previousschool_IMG-20251119-WA0004.jpg\"},\"recommended_by\":\"Monica\"}', 'approved', NULL, 23, '2026-01-06 08:31:12', '2026-01-06 07:28:38', NULL),
(63, 'Chrispin Tembo', 'jackchris650@gmail.com', '260978227236', 'undergraduate', 10, 1, 'online', '{\"0\":{\"name\":\"DOCUMENTS .pdf\",\"path\":\"uploads\\/1767686116_grade12results_DOCUMENTS .pdf\"},\"1\":{\"name\":\"WhatsApp Image 2026-01-06 at 09.49.31.jpeg\",\"path\":\"uploads\\/1767686116_previousschool_WhatsApp Image 2026-01-06 at 09.49.31.jpeg\"},\"recommended_by\":\"\"}', 'approved', NULL, 23, '2026-01-06 08:00:59', '2026-01-06 07:55:16', NULL),
(64, 'Enerst Banda', 'juniorenerst01@gmail.com', '0970397477', 'undergraduate', 39, 1, 'online', '{\"0\":{\"name\":\"Screenshot_20251207-083305~2.png\",\"path\":\"uploads\\/1767690125_grade12results_Screenshot_20251207-083305~2.png\"},\"1\":{\"name\":\"Screenshot_20251207-082227~2.png\",\"path\":\"uploads\\/1767690125_previousschool_Screenshot_20251207-082227~2.png\"},\"recommended_by\":\"\"}', 'approved', NULL, 22, '2026-01-06 09:26:53', '2026-01-06 09:02:05', NULL),
(65, 'ENERST BANDA', 'juniorenerst01@gmail.com', '0970397477', 'undergraduate', 39, 2, 'online', '{\"0\":{\"name\":\"Screenshot_20251207-083305~2.png\",\"path\":\"uploads\\/1767691481_grade12results_Screenshot_20251207-083305~2.png\"},\"1\":{\"name\":\"Screenshot_20251207-082227~2.png\",\"path\":\"uploads\\/1767691481_previousschool_Screenshot_20251207-082227~2.png\"},\"recommended_by\":\"\"}', 'approved', NULL, 22, '2026-01-06 09:27:33', '2026-01-06 09:24:41', NULL),
(66, 'ENERST BANDA', 'juniorenerst01@gmail.com', '0970397477', 'undergraduate', 39, 2, 'online', '{\"0\":{\"name\":\"Screenshot_20251207-083305~2.png\",\"path\":\"uploads\\/1767691512_grade12results_Screenshot_20251207-083305~2.png\"},\"1\":{\"name\":\"Screenshot_20251207-082227~2.png\",\"path\":\"uploads\\/1767691512_previousschool_Screenshot_20251207-082227~2.png\"},\"recommended_by\":\"\"}', 'approved', NULL, 22, '2026-01-06 09:27:24', '2026-01-06 09:25:12', NULL),
(67, 'Christopher Jere', 'christopherjere459@gmail.com', '0978888260', 'undergraduate', 6, 1, 'physical', '{\"0\":{\"name\":\"IMG_20230119_114635~2.jpg\",\"path\":\"uploads\\/1767695032_grade12results_IMG_20230119_114635~2.jpg\"},\"1\":{\"name\":\"Screenshot_20260106-122013.png\",\"path\":\"uploads\\/1767695032_previousschool_Screenshot_20260106-122013.png\"},\"recommended_by\":\"\"}', 'approved', NULL, 23, '2026-01-06 13:34:02', '2026-01-06 10:23:52', NULL),
(68, 'Musanda Namoonde', 'musandanamoonde16@gmail.com', '0771409497', 'undergraduate', 9, 1, 'physical', '{\"0\":{\"name\":\"B0C2FA63-10C5-4452-ADE2-F511B017931B.jpeg\",\"path\":\"uploads\\/1767695711_grade12results_B0C2FA63-10C5-4452-ADE2-F511B017931B.jpeg\"},\"1\":{\"name\":\"2945F4EE-DED0-41DA-985F-E9C8682DC1C7.png\",\"path\":\"uploads\\/1767695711_previousschool_2945F4EE-DED0-41DA-985F-E9C8682DC1C7.png\"},\"recommended_by\":\"Ms j kababa\"}', 'approved', NULL, 23, '2026-01-06 13:11:09', '2026-01-06 10:35:11', NULL),
(69, 'Musanda Namoonde', 'musandanamoonde16@gmail.com', '0771409497', 'undergraduate', 9, 1, 'physical', '{\"0\":{\"name\":\"EBF3E7BD-F28A-4F85-B7EC-611AE10F1E51.jpeg\",\"path\":\"uploads\\/1767695903_grade12results_EBF3E7BD-F28A-4F85-B7EC-611AE10F1E51.jpeg\"},\"1\":{\"name\":\"55807516-9A04-4D02-BF38-48900620F010.png\",\"path\":\"uploads\\/1767695903_previousschool_55807516-9A04-4D02-BF38-48900620F010.png\"},\"recommended_by\":\"Ms j kababa\"}', 'approved', NULL, 23, '2026-01-06 12:42:10', '2026-01-06 10:38:23', NULL),
(70, 'Musanda Namoonde', 'musandanamoonde16@gmail.com', '0771409497', 'undergraduate', 9, 1, 'physical', '{\"0\":{\"name\":\"9D69D57B-CC81-493F-85ED-8A35AB98934B.jpeg\",\"path\":\"uploads\\/1767696162_grade12results_9D69D57B-CC81-493F-85ED-8A35AB98934B.jpeg\"},\"1\":{\"name\":\"5302A90B-C18B-45F8-86CA-E7C4AE28C7ED.png\",\"path\":\"uploads\\/1767696162_previousschool_5302A90B-C18B-45F8-86CA-E7C4AE28C7ED.png\"},\"recommended_by\":\"Ms j kababa\"}', 'approved', NULL, 23, '2026-01-06 12:42:05', '2026-01-06 10:42:42', NULL),
(71, 'sam smith', 'sianamatesamuel@gmail.com', '0979667723', 'short_course', 11, NULL, NULL, '{\"occupation\":\"\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'approved', NULL, 20, '2026-01-06 11:23:51', '2026-01-06 11:23:24', NULL),
(72, 'Rabson Mvula', 'rabsonm095@gmail.com', '0777408840', 'undergraduate', 39, 1, 'physical', '{\"0\":{\"name\":\"20260106_122003.jpg\",\"path\":\"uploads\\/1767715014_grade12results_20260106_122003.jpg\"},\"1\":{\"name\":\"20251215_130926.jpg\",\"path\":\"uploads\\/1767715014_previousschool_20251215_130926.jpg\"},\"2\":{\"name\":\"20260106_115600.jpg\",\"path\":\"uploads\\/1767715014_nrc_copy_20260106_115600.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"829728\\/10\\/1\"}', 'approved', NULL, 23, '2026-01-07 07:37:42', '2026-01-06 15:56:54', NULL),
(73, 'peter chikubula', 'peterchikubula@lsuczm.com', '0979886013', 'undergraduate', 12, 1, 'physical', '{\"0\":{\"name\":\"ENTRY REQUIREMENTS.pdf\",\"path\":\"uploads\\/1767771212_grade12results_ENTRY REQUIREMENTS.pdf\"},\"1\":{\"name\":\"Application Form LSC 2025 (1).pdf\",\"path\":\"uploads\\/1767771212_previousschool_Application Form LSC 2025 (1).pdf\"},\"recommended_by\":\"JESSY KABABA\",\"gender\":\"Male\",\"nrc_number\":\"537606\\/10\\/1\"}', 'approved', NULL, 23, '2026-01-07 07:38:41', '2026-01-07 07:33:32', NULL),
(74, 'peter chikubula', 'peterchikubula@lsuczm.com', '0979886013', 'undergraduate', 12, 1, 'physical', '{\"0\":{\"name\":\"ENTRY REQUIREMENTS.pdf\",\"path\":\"uploads\\/1767771473_grade12results_ENTRY REQUIREMENTS.pdf\"},\"1\":{\"name\":\"Application Form LSC 2025 (1).pdf\",\"path\":\"uploads\\/1767771473_previousschool_Application Form LSC 2025 (1).pdf\"},\"recommended_by\":\"JESSY KABABA\",\"gender\":\"Male\",\"nrc_number\":\"537606\\/10\\/1\"}', 'approved', NULL, 23, '2026-01-07 07:38:36', '2026-01-07 07:37:53', NULL),
(75, 'Abraham Zezu', 'abrahamzezu712@gmail.com', '+260970000204', 'undergraduate', 10, 1, 'physical', '{\"0\":{\"name\":\"IMG_20260107_071326_173.jpg\",\"path\":\"uploads\\/1767778416_grade12results_IMG_20260107_071326_173.jpg\"},\"1\":{\"name\":\"Screenshot_20260107-112416.jpg\",\"path\":\"uploads\\/1767778416_previousschool_Screenshot_20260107-112416.jpg\"},\"2\":{\"name\":\"IMG_20240530_122339_036.jpg\",\"path\":\"uploads\\/1767778416_nrc_copy_IMG_20240530_122339_036.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"183442\\/22\\/1\"}', 'approved', NULL, 23, '2026-01-07 11:00:03', '2026-01-07 09:33:36', NULL),
(76, 'Abraham Zezu', 'abrahamzezu712@gmail.com', '+260970000204', 'undergraduate', 10, 1, 'physical', '{\"0\":{\"name\":\"IMG_20260107_071324_391.jpg\",\"path\":\"uploads\\/1767778870_grade12results_IMG_20260107_071324_391.jpg\"},\"1\":{\"name\":\"Screenshot_20260107-112416.jpg\",\"path\":\"uploads\\/1767778870_previousschool_Screenshot_20260107-112416.jpg\"},\"2\":{\"name\":\"IMG_20240530_122339_036.jpg\",\"path\":\"uploads\\/1767778870_nrc_copy_IMG_20240530_122339_036.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"183442\\/22\\/1\"}', 'approved', NULL, 23, '2026-01-07 10:54:10', '2026-01-07 09:41:10', NULL),
(77, 'Abraham Zezu', 'abrahamzezu712@gmail.com', '+260970000204', 'undergraduate', 10, 1, 'physical', '{\"0\":{\"name\":\"IMG_20260107_071326_173.jpg\",\"path\":\"uploads\\/1767779500_grade12results_IMG_20260107_071326_173.jpg\"},\"1\":{\"name\":\"Screenshot_20260107-112416.jpg\",\"path\":\"uploads\\/1767779500_previousschool_Screenshot_20260107-112416.jpg\"},\"2\":{\"name\":\"IMG_20240530_122339_036.jpg\",\"path\":\"uploads\\/1767779500_nrc_copy_IMG_20240530_122339_036.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"183442\\/22\\/1\"}', 'approved', NULL, 23, '2026-01-07 10:54:06', '2026-01-07 09:51:40', NULL),
(78, 'Abraham Zezu', 'abrahamzezu712@gmail.com', '+260970000204', 'undergraduate', 10, 1, 'physical', '{\"0\":{\"name\":\"IMG_20260107_071326_173.jpg\",\"path\":\"uploads\\/1767779587_grade12results_IMG_20260107_071326_173.jpg\"},\"1\":{\"name\":\"Screenshot_20260107-112416.jpg\",\"path\":\"uploads\\/1767779587_previousschool_Screenshot_20260107-112416.jpg\"},\"2\":{\"name\":\"IMG_20240530_122339_036.jpg\",\"path\":\"uploads\\/1767779587_nrc_copy_IMG_20240530_122339_036.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"183442\\/22\\/1\"}', 'approved', NULL, 23, '2026-01-07 10:54:03', '2026-01-07 09:53:07', NULL),
(79, 'Abraham Zezu', 'abrahamzezu712@gmail.com', '+260970000204', 'undergraduate', 10, 1, 'physical', '{\"0\":{\"name\":\"IMG_20260107_071326_173.jpg\",\"path\":\"uploads\\/1767779660_grade12results_IMG_20260107_071326_173.jpg\"},\"1\":{\"name\":\"Screenshot_20260107-112416.jpg\",\"path\":\"uploads\\/1767779660_previousschool_Screenshot_20260107-112416.jpg\"},\"2\":{\"name\":\"IMG_20240530_122339_036.jpg\",\"path\":\"uploads\\/1767779660_nrc_copy_IMG_20240530_122339_036.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"183442\\/22\\/1\"}', 'approved', NULL, 23, '2026-01-07 10:53:58', '2026-01-07 09:54:20', NULL),
(80, 'Abraham Zezu', 'abrahamzezu712@gmail.com', '+260970000204', 'undergraduate', 10, 1, 'physical', '{\"0\":{\"name\":\"IMG_20260107_071326_173.jpg\",\"path\":\"uploads\\/1767779706_grade12results_IMG_20260107_071326_173.jpg\"},\"1\":{\"name\":\"Screenshot_20260107-112416.jpg\",\"path\":\"uploads\\/1767779706_previousschool_Screenshot_20260107-112416.jpg\"},\"2\":{\"name\":\"IMG_20240530_122339_036.jpg\",\"path\":\"uploads\\/1767779706_nrc_copy_IMG_20240530_122339_036.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"183442\\/22\\/1\"}', 'approved', NULL, 23, '2026-01-07 10:53:55', '2026-01-07 09:55:06', NULL),
(81, 'Abraham Zezu', 'abrahamzezu712@gmail.com', '+260970000204', 'undergraduate', 10, 1, 'physical', '{\"0\":{\"name\":\"IMG_20260107_071326_173.jpg\",\"path\":\"uploads\\/1767779762_grade12results_IMG_20260107_071326_173.jpg\"},\"1\":{\"name\":\"Screenshot_20260107-112416.jpg\",\"path\":\"uploads\\/1767779762_previousschool_Screenshot_20260107-112416.jpg\"},\"2\":{\"name\":\"IMG_20240530_122339_036.jpg\",\"path\":\"uploads\\/1767779762_nrc_copy_IMG_20240530_122339_036.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"183442\\/22\\/1\"}', 'approved', NULL, 23, '2026-01-07 10:53:51', '2026-01-07 09:56:02', NULL),
(82, 'Abraham Zezu', 'abrahamzezu712@gmail.com', '+260970000204', 'undergraduate', 10, 1, 'physical', '{\"0\":{\"name\":\"IMG_20260107_071326_173.jpg\",\"path\":\"uploads\\/1767779798_grade12results_IMG_20260107_071326_173.jpg\"},\"1\":{\"name\":\"Screenshot_20260107-112416.jpg\",\"path\":\"uploads\\/1767779798_previousschool_Screenshot_20260107-112416.jpg\"},\"2\":{\"name\":\"IMG_20240530_122339_036.jpg\",\"path\":\"uploads\\/1767779798_nrc_copy_IMG_20240530_122339_036.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"183442\\/22\\/1\"}', 'approved', NULL, 23, '2026-01-07 10:53:40', '2026-01-07 09:56:38', NULL),
(83, 'Abraham Zezu', 'abrahamzezu712@gmail.com', '+260970000204', 'undergraduate', 10, 1, 'physical', '{\"0\":{\"name\":\"IMG_20260107_071326_173.jpg\",\"path\":\"uploads\\/1767779845_grade12results_IMG_20260107_071326_173.jpg\"},\"1\":{\"name\":\"Screenshot_20260107-112416.jpg\",\"path\":\"uploads\\/1767779845_previousschool_Screenshot_20260107-112416.jpg\"},\"2\":{\"name\":\"IMG_20240530_122339_036.jpg\",\"path\":\"uploads\\/1767779845_nrc_copy_IMG_20240530_122339_036.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"183442\\/22\\/1\"}', 'approved', NULL, 23, '2026-01-07 10:53:34', '2026-01-07 09:57:25', NULL),
(84, 'Abraham Zezu', 'abrahamzezu712@gmail.com', '+260970000204', 'undergraduate', 10, 1, 'physical', '{\"0\":{\"name\":\"IMG_20260107_071326_173.jpg\",\"path\":\"uploads\\/1767780756_grade12results_IMG_20260107_071326_173.jpg\"},\"1\":{\"name\":\"Screenshot_20260107-112416.jpg\",\"path\":\"uploads\\/1767780756_previousschool_Screenshot_20260107-112416.jpg\"},\"2\":{\"name\":\"IMG_20240530_122353_536.jpg\",\"path\":\"uploads\\/1767780756_nrc_copy_IMG_20240530_122353_536.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"183442\\/22\\/1\"}', 'approved', NULL, 23, '2026-01-07 10:53:30', '2026-01-07 10:12:36', NULL),
(85, 'Abraham Zezu', 'abrahamzezu712@gmail.com', '+260970000204', 'undergraduate', 10, 1, 'physical', '{\"0\":{\"name\":\"IMG_20260107_071326_173.jpg\",\"path\":\"uploads\\/1767780825_grade12results_IMG_20260107_071326_173.jpg\"},\"1\":{\"name\":\"Screenshot_20260107-112416.jpg\",\"path\":\"uploads\\/1767780825_previousschool_Screenshot_20260107-112416.jpg\"},\"2\":{\"name\":\"IMG_20240530_122353_536.jpg\",\"path\":\"uploads\\/1767780825_nrc_copy_IMG_20240530_122353_536.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"183442\\/22\\/1\"}', 'approved', NULL, 23, '2026-01-07 10:53:27', '2026-01-07 10:13:45', NULL),
(86, 'Abraham Zezu', 'abrahamzezu712@gmail.com', '+260970000204', 'undergraduate', 10, 1, 'physical', '{\"0\":{\"name\":\"IMG_20260107_071326_173.jpg\",\"path\":\"uploads\\/1767780897_grade12results_IMG_20260107_071326_173.jpg\"},\"1\":{\"name\":\"Screenshot_20260107-112416.jpg\",\"path\":\"uploads\\/1767780897_previousschool_Screenshot_20260107-112416.jpg\"},\"2\":{\"name\":\"IMG_20240530_122353_536.jpg\",\"path\":\"uploads\\/1767780897_nrc_copy_IMG_20240530_122353_536.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"183442\\/22\\/1\"}', 'approved', NULL, 23, '2026-01-07 10:53:23', '2026-01-07 10:14:57', NULL),
(87, 'Abraham Zezu', 'abrahamzezu712@gmail.com', '+260970000204', 'undergraduate', 10, 1, 'physical', '{\"0\":{\"name\":\"IMG_20260107_071326_173.jpg\",\"path\":\"uploads\\/1767780992_grade12results_IMG_20260107_071326_173.jpg\"},\"1\":{\"name\":\"Screenshot_20260107-112416.jpg\",\"path\":\"uploads\\/1767780992_previousschool_Screenshot_20260107-112416.jpg\"},\"2\":{\"name\":\"IMG_20240530_122353_536.jpg\",\"path\":\"uploads\\/1767780992_nrc_copy_IMG_20240530_122353_536.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"183442\\/22\\/1\"}', 'approved', NULL, 23, '2026-01-07 10:52:09', '2026-01-07 10:16:32', NULL),
(88, 'Abraham Zezu', 'abrahamzezu712@gmail.com', '+260970000204', 'undergraduate', 10, 1, 'physical', '{\"0\":{\"name\":\"IMG_20260107_071317_393.jpg\",\"path\":\"uploads\\/1767783095_grade12results_IMG_20260107_071317_393.jpg\"},\"1\":{\"name\":\"Screenshot_20260107-112416.jpg\",\"path\":\"uploads\\/1767783095_previousschool_Screenshot_20260107-112416.jpg\"},\"2\":{\"name\":\"IMG_20240530_122339_036.jpg\",\"path\":\"uploads\\/1767783095_nrc_copy_IMG_20240530_122339_036.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"183442\\/22\\/1\"}', 'approved', NULL, 23, '2026-01-07 10:53:19', '2026-01-07 10:51:35', NULL),
(89, 'Abel Sinkala', 'sinkalable@gmail.com', '0764128947', 'undergraduate', 42, 1, 'physical', '{\"0\":{\"name\":\"1766610228367.jpg\",\"path\":\"uploads\\/1767787285_grade12results_1766610228367.jpg\"},\"1\":{\"name\":\"17677871048538968864498621240643.jpg\",\"path\":\"uploads\\/1767787285_previousschool_17677871048538968864498621240643.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"307546\\/13\\/1\"}', 'rejected', 'Incomplete Application.', 23, '2026-01-07 12:21:15', '2026-01-07 12:01:25', NULL),
(90, 'Abel Sinkala', 'sinkalable@gmail.com', '0764128947', 'undergraduate', 42, 1, 'physical', '{\"0\":{\"name\":\"IMG_20251029_133854_974-1.jpg\",\"path\":\"uploads\\/1767787679_grade12results_IMG_20251029_133854_974-1.jpg\"},\"1\":{\"name\":\"17677873488246462102074268616147.jpg\",\"path\":\"uploads\\/1767787679_previousschool_17677873488246462102074268616147.jpg\"},\"2\":{\"name\":\"17677874469003842381678258126042.jpg\",\"path\":\"uploads\\/1767787679_nrc_copy_17677874469003842381678258126042.jpg\"},\"recommended_by\":\"\",\"gender\":\"Male\",\"nrc_number\":\"307546\\/13\\/1\"}', 'rejected', 'Incomplete Application.', 23, '2026-01-07 12:20:49', '2026-01-07 12:07:59', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `course`
--

CREATE TABLE `course` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `credits` int(11) DEFAULT 3,
  `programme_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course`
--

INSERT INTO `course` (`id`, `code`, `name`, `credits`, `programme_id`, `department_id`, `description`, `created_at`, `updated_at`) VALUES
(1, 'DBA1010', 'Business and Company Law', 5, 15, NULL, 'Company Law in Business', '2025-10-10 12:25:17', '2025-11-29 11:55:40'),
(2, 'CS101', 'Introduction to Programming', 3, 1, 1, NULL, '2025-10-10 21:00:09', '2025-10-10 21:00:09'),
(3, 'CS102', 'Web Development Fundamentals', 3, 1, 1, NULL, '2025-10-10 21:00:09', '2025-10-10 21:00:09'),
(7, '13/C-01-A', 'Workshop Practices and Principles', 3, 35, NULL, '', '2026-01-03 05:49:51', '2026-01-03 05:49:51'),
(8, '13/C-02-A', 'Domestic Electrical Installation', 3, 35, NULL, '', '2026-01-03 05:50:52', '2026-01-03 05:50:52'),
(9, '251-01-A', 'Business Mathematics', 5, 12, NULL, '', '2026-01-03 06:46:23', '2026-01-03 06:46:23'),
(10, '251-02-A', 'Business Accounting', 5, 12, NULL, '', '2026-01-03 06:47:30', '2026-01-03 06:47:30'),
(11, '251-03-A', 'Business Communication', 5, 12, NULL, '', '2026-01-03 06:48:37', '2026-01-03 06:48:37'),
(12, '251-04-A', 'Business Law', 5, 12, NULL, '', '2026-01-03 06:50:48', '2026-01-03 06:50:48'),
(13, '251-05-A', 'Economics', 5, 12, NULL, '', '2026-01-03 06:51:35', '2026-01-03 06:51:35'),
(14, '251-06-A', 'Computer Applications Packages', 5, 12, NULL, '', '2026-01-03 06:52:53', '2026-01-03 06:52:53'),
(15, '251-07-A', 'Business Administration', 5, 12, NULL, '', '2026-01-03 06:53:59', '2026-01-03 09:08:40'),
(16, '251-08-B', 'Management Accounting', 5, 12, NULL, '', '2026-01-03 06:55:27', '2026-01-03 09:06:25'),
(17, '251-09-B', 'Human Resource Management', 5, 12, NULL, '', '2026-01-03 06:56:39', '2026-01-03 09:05:34'),
(18, '251-10-B', 'Principles of Marketing', 5, 12, NULL, '', '2026-01-03 06:57:42', '2026-01-03 09:09:21'),
(19, '251-11-B', 'Organizational Behaviour', 5, 12, NULL, '', '2026-01-03 06:59:17', '2026-01-03 09:08:03'),
(20, '251-12-B', 'Decision Making', 5, 12, NULL, '', '2026-01-03 07:00:06', '2026-01-03 09:03:32'),
(21, '251-17-C', 'Business Research Methods', 5, 12, NULL, '', '2026-01-03 07:01:11', '2026-01-03 09:12:31'),
(22, '251-14-C', 'Financial Management', 5, 12, NULL, '', '2026-01-03 07:02:02', '2026-01-03 09:04:54'),
(23, '251-15-C', 'Management Information Systems', 5, 12, NULL, '', '2026-01-03 07:03:08', '2026-01-03 09:07:19'),
(24, '251-16-C', 'Entrepreneurship', 5, 12, NULL, '', '2026-01-03 09:02:02', '2026-01-03 09:04:07'),
(25, '251-13-B', 'Production & Operations Management', 5, 12, NULL, '', '2026-01-03 09:15:16', '2026-01-03 09:15:16'),
(26, '251-18-C', 'International Business', 5, 12, NULL, '', '2026-01-03 09:16:19', '2026-01-03 09:16:19'),
(27, '251-19-C', 'Strategic Management', 5, 12, NULL, '', '2026-01-03 09:18:00', '2026-01-03 09:18:00'),
(28, '251-20-C', 'Dissertation', 5, 12, NULL, '', '2026-01-03 09:20:24', '2026-01-03 09:20:24'),
(29, '109-01-A', 'Human Resource Management', 5, 14, NULL, '', '2026-01-03 09:36:35', '2026-01-03 09:36:35'),
(30, '109-02-A', 'Business Communication', 5, 14, NULL, '', '2026-01-03 09:37:18', '2026-01-03 09:37:18'),
(31, '109-03-A', 'Employee Relations & Employment Law', 5, 14, NULL, '', '2026-01-03 09:38:50', '2026-01-03 09:38:50'),
(32, '109-04-A', 'Mathematics & Statistics', 5, 14, NULL, '', '2026-01-03 09:40:19', '2026-01-03 09:40:19'),
(33, '109-05-A', 'Business Law', 5, 14, NULL, '', '2026-01-03 09:40:55', '2026-01-03 09:40:55'),
(34, '109-06-A', 'Application Packages', 5, 14, NULL, '', '2026-01-03 09:41:49', '2026-01-03 09:41:49'),
(35, '109-01-B', 'Human Resource Management II', 5, 14, NULL, '', '2026-01-03 09:43:05', '2026-01-03 09:43:05'),
(36, '109-02-B', 'Employee Relations', 5, 14, NULL, '', '2026-01-03 09:43:57', '2026-01-03 09:43:57'),
(37, '109-03-B', 'Economics', 5, 14, NULL, '', '2026-01-03 09:44:35', '2026-01-03 09:44:35'),
(38, '109-04-B', 'Business Accounting', 5, 14, NULL, '', '2026-01-03 09:45:19', '2026-01-03 09:45:19'),
(39, '109-05-B', 'Management Principles', 5, 14, NULL, '', '2026-01-03 09:46:30', '2026-01-03 09:46:30'),
(40, '109-06-B', 'Organizational Behaviour', 5, 14, NULL, '', '2026-01-03 09:47:33', '2026-01-03 09:47:33'),
(41, '109-01-C', 'Strategic Human Resource Management', 5, 14, NULL, '', '2026-01-03 09:48:37', '2026-01-03 09:48:37'),
(42, '109-02-C', 'Human Resource Development', 5, 14, NULL, '', '2026-01-03 09:50:02', '2026-01-03 09:50:02'),
(43, '109-03-C', 'Human Resource Planning', 5, 14, NULL, '', '2026-01-03 09:50:37', '2026-01-03 09:50:37'),
(44, '110-04-C', 'Entrepreneurship', 5, 14, NULL, '', '2026-01-03 09:51:28', '2026-01-03 09:51:28'),
(45, '109-05-C', 'Employment Law', 5, 14, NULL, '', '2026-01-03 09:52:18', '2026-01-03 09:54:35'),
(46, '109-06-C', 'Research Project', 5, 14, NULL, '', '2026-01-03 09:53:28', '2026-01-03 09:53:28'),
(47, '124-01-A', 'Occupational Health & Safety', 3, 9, NULL, '', '2026-01-03 10:00:32', '2026-01-03 10:00:32'),
(48, '124-02-A', 'Environmental Management', 3, 9, NULL, '', '2026-01-03 10:01:22', '2026-01-03 10:01:22'),
(49, '124-03-A', 'Construction Site Safety Procedures', 3, 9, NULL, '', '2026-01-03 10:02:25', '2026-01-03 10:02:25'),
(50, '124-04-A', 'Communication Skills', 3, 9, NULL, '', '2026-01-03 10:03:11', '2026-01-03 10:03:11'),
(51, '124-05-A', 'Introduction to Computer Applications', 3, 9, NULL, '', '2026-01-03 10:04:09', '2026-01-03 10:04:09'),
(52, '124-06-A', 'Entrepreneurship', 3, 9, NULL, '', '2026-01-03 10:05:10', '2026-01-03 10:05:10'),
(53, '205-01-A', 'Legal & Safety Standards', 3, 1, NULL, '', '2026-01-03 11:33:19', '2026-01-03 11:33:19'),
(54, '205-02-A', 'Project Management', 3, 1, NULL, '', '2026-01-03 11:37:29', '2026-01-03 11:37:29'),
(55, '205-03-A', 'Construction Processes Management', 3, 1, NULL, '', '2026-01-03 11:38:36', '2026-01-03 11:38:36'),
(56, '205-04-A', 'Communication Skills', 3, 1, NULL, '', '2026-01-03 11:39:24', '2026-01-07 10:50:32'),
(57, '205-05-A', 'Entrepreneurship', 3, 1, NULL, '', '2026-01-03 11:42:11', '2026-01-03 11:42:11'),
(58, 'ICT1110', 'Introduction to Computers', 3, 1, NULL, '', '2026-01-03 11:44:25', '2026-01-07 10:50:49'),
(59, '416-01-A', 'Communication Skills', 3, 38, NULL, '', '2026-01-03 16:44:31', '2026-01-03 16:44:31'),
(60, '416-02-A', 'Introduction to Computers', 3, 38, NULL, '', '2026-01-03 16:45:13', '2026-01-03 16:45:13'),
(61, '416-03-A', 'Entrepreneurship', 3, 38, NULL, '', '2026-01-03 16:45:45', '2026-01-03 16:45:45'),
(62, '416-04-A', 'Engine Systems', 3, 38, NULL, '', '2026-01-03 16:46:26', '2026-01-03 16:46:26'),
(63, '416-05-A', 'Vehicle Systems', 3, 38, NULL, '', '2026-01-03 16:47:15', '2026-01-03 16:47:15'),
(64, '166-01-A', 'Workshop Processes', 5, 8, NULL, '', '2026-01-03 16:50:20', '2026-01-03 16:50:20'),
(65, '166-02-A', 'Engineering Mathematics', 5, 8, NULL, '', '2026-01-03 16:51:05', '2026-01-03 16:51:05'),
(66, '166-03-A', 'Engineering Science', 5, 8, NULL, '', '2026-01-03 16:51:40', '2026-01-03 16:51:40'),
(67, '166-04-A', 'Engineering Drawing', 5, 8, NULL, '', '2026-01-03 16:52:40', '2026-01-03 16:52:40'),
(69, '166-06-A', 'Introduction To Computers', 5, 8, NULL, '', '2026-01-03 16:53:59', '2026-01-03 16:53:59'),
(70, '166-07-A', 'Engine & Transmission Systems', 5, 8, NULL, '', '2026-01-03 16:55:14', '2026-01-03 16:55:14'),
(71, '166-08-B', 'Automotive Electricity', 5, 8, NULL, '', '2026-01-03 16:58:20', '2026-01-03 16:58:20'),
(72, '166-09-B', 'Braking System', 5, 8, NULL, '', '2026-01-03 16:59:02', '2026-01-03 16:59:02'),
(73, '166-10-B', 'Steering & Suspension Systems', 5, 8, NULL, '', '2026-01-03 17:00:28', '2026-01-03 17:00:28'),
(74, '166-11-B', 'Entrepreneurship', 5, 8, NULL, '', '2026-01-03 17:01:00', '2026-01-03 17:01:00'),
(75, '362-04-A', 'Entrepreneurship-Solar', 3, 6, NULL, '', '2026-01-05 08:22:55', '2026-01-07 11:10:06'),
(76, '362-01-A', 'Workshop Practice & Processes', 3, 6, NULL, '', '2026-01-05 12:25:21', '2026-01-05 12:25:21'),
(77, '362-02-A', 'Electrical Principles', 3, 6, NULL, '', '2026-01-05 12:26:48', '2026-01-05 12:26:48'),
(78, '362-03-A', 'Solar System Technology', 3, 6, NULL, '', '2026-01-05 12:28:18', '2026-01-05 12:28:18'),
(79, 'STM-1011', 'Solar Technology', 3, 6, NULL, '', '2026-01-07 10:57:25', '2026-01-07 10:57:25');

-- --------------------------------------------------------

--
-- Table structure for table `course_assignment`
--

CREATE TABLE `course_assignment` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `academic_year` varchar(20) DEFAULT '2024',
  `semester` enum('1','2','Summer') DEFAULT '1',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_assignment`
--

INSERT INTO `course_assignment` (`id`, `course_id`, `lecturer_id`, `academic_year`, `semester`, `assigned_at`, `is_active`) VALUES
(1, 2, 2, '2024', '1', '2025-10-10 21:00:09', 1),
(2, 3, 2, '2024', '1', '2025-10-10 21:00:09', 1),
(4, 2, 10, 'January', '1', '2025-12-01 11:06:11', 1),
(13, 2, 41, '2024', '1', '2025-12-19 14:17:45', 1),
(16, 3, 41, '2024', '1', '2025-12-19 14:17:45', 1),
(22, 38, 48, '2024', '1', '2026-01-07 09:13:32', 1),
(23, 1, 48, '2024', '1', '2026-01-07 09:13:32', 1),
(24, 30, 48, '2024', '1', '2026-01-07 09:13:32', 1),
(25, 33, 48, '2024', '1', '2026-01-07 09:13:32', 1),
(26, 9, 48, '2024', '1', '2026-01-07 09:13:32', 1),
(27, 21, 48, '2024', '1', '2026-01-07 09:13:32', 1),
(28, 24, 48, '2024', '1', '2026-01-07 09:13:32', 1),
(29, 75, 48, '2025/2026', '', '2026-01-07 11:55:31', 1);

-- --------------------------------------------------------

--
-- Table structure for table `course_enrollment`
--

CREATE TABLE `course_enrollment` (
  `id` int(11) NOT NULL,
  `student_user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `semester` enum('1','2','Summer') DEFAULT '1',
  `status` enum('enrolled','pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_score` decimal(5,2) DEFAULT NULL,
  `grade` varchar(5) DEFAULT NULL,
  `published` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_enrollment`
--

INSERT INTO `course_enrollment` (`id`, `student_user_id`, `course_id`, `academic_year`, `semester`, `status`, `created_at`, `total_score`, `grade`, `published`) VALUES
(1, 4, 2, '2024', '1', 'enrolled', '2025-10-10 21:00:09', NULL, NULL, 0),
(2, 4, 3, '2024', '1', 'enrolled', '2025-10-10 21:00:09', NULL, NULL, 0),
(9, 88, 77, NULL, '1', 'approved', '2026-01-07 11:14:31', NULL, NULL, 0),
(10, 88, 75, NULL, '1', 'approved', '2026-01-07 11:14:31', NULL, NULL, 0),
(11, 88, 79, NULL, '1', 'approved', '2026-01-07 11:14:31', NULL, NULL, 0),
(12, 88, 64, NULL, '1', 'approved', '2026-01-07 11:14:31', NULL, NULL, 0),
(13, 89, 77, NULL, '1', 'approved', '2026-01-07 11:18:43', NULL, NULL, 0),
(14, 89, 48, NULL, '1', 'approved', '2026-01-07 11:18:43', NULL, NULL, 0),
(15, 89, 79, NULL, '1', 'approved', '2026-01-07 11:18:43', NULL, NULL, 0),
(16, 89, 64, NULL, '1', 'approved', '2026-01-07 11:18:43', NULL, NULL, 0),
(17, 90, 77, NULL, '1', 'approved', '2026-01-07 11:21:00', NULL, NULL, 0),
(18, 90, 75, NULL, '1', 'approved', '2026-01-07 11:21:00', NULL, NULL, 0),
(19, 90, 79, NULL, '1', 'approved', '2026-01-07 11:21:00', NULL, NULL, 0),
(20, 90, 64, NULL, '1', 'approved', '2026-01-07 11:21:00', NULL, NULL, 0),
(21, 91, 77, NULL, '1', 'approved', '2026-01-07 11:22:55', NULL, NULL, 0),
(22, 91, 75, NULL, '1', 'approved', '2026-01-07 11:22:55', NULL, NULL, 0),
(23, 91, 79, NULL, '1', 'approved', '2026-01-07 11:22:55', NULL, NULL, 0),
(24, 91, 64, NULL, '1', 'approved', '2026-01-07 11:22:55', NULL, NULL, 0),
(25, 92, 77, NULL, '1', 'approved', '2026-01-07 11:24:37', NULL, NULL, 0),
(26, 92, 75, NULL, '1', 'approved', '2026-01-07 11:24:37', NULL, NULL, 0),
(27, 92, 79, NULL, '1', 'approved', '2026-01-07 11:24:37', NULL, NULL, 0),
(28, 92, 64, NULL, '1', 'approved', '2026-01-07 11:24:37', NULL, NULL, 0),
(29, 78, 77, NULL, '1', 'approved', '2026-01-07 11:26:31', NULL, NULL, 0),
(30, 78, 75, NULL, '1', 'approved', '2026-01-07 11:26:31', NULL, NULL, 0),
(31, 78, 79, NULL, '1', 'approved', '2026-01-07 11:26:31', NULL, NULL, 0),
(32, 78, 64, NULL, '1', 'approved', '2026-01-07 11:26:31', NULL, NULL, 0),
(33, 77, 77, NULL, '1', 'approved', '2026-01-07 11:29:58', NULL, NULL, 0),
(34, 77, 75, NULL, '1', 'approved', '2026-01-07 11:29:58', NULL, NULL, 0),
(35, 77, 79, NULL, '1', 'approved', '2026-01-07 11:29:58', NULL, NULL, 0),
(36, 77, 64, NULL, '1', 'approved', '2026-01-07 11:29:58', NULL, NULL, 0),
(41, 75, 77, NULL, '1', 'approved', '2026-01-07 11:35:35', NULL, NULL, 0),
(42, 75, 75, NULL, '1', 'approved', '2026-01-07 11:35:35', NULL, NULL, 0),
(43, 75, 79, NULL, '1', 'approved', '2026-01-07 11:35:35', NULL, NULL, 0),
(44, 75, 64, NULL, '1', 'approved', '2026-01-07 11:35:35', NULL, NULL, 0),
(45, 74, 77, NULL, '1', 'approved', '2026-01-07 11:37:15', NULL, NULL, 0),
(46, 74, 75, NULL, '1', 'approved', '2026-01-07 11:37:15', NULL, NULL, 0),
(47, 74, 79, NULL, '1', 'approved', '2026-01-07 11:37:15', NULL, NULL, 0),
(48, 74, 64, NULL, '1', 'approved', '2026-01-07 11:37:15', NULL, NULL, 0),
(49, 73, 77, NULL, '1', 'approved', '2026-01-07 11:38:48', NULL, NULL, 0),
(50, 73, 75, NULL, '1', 'approved', '2026-01-07 11:38:48', NULL, NULL, 0),
(51, 73, 79, NULL, '1', 'approved', '2026-01-07 11:38:48', NULL, NULL, 0),
(52, 73, 64, NULL, '1', 'approved', '2026-01-07 11:38:48', NULL, NULL, 0),
(53, 79, 77, NULL, '1', 'approved', '2026-01-07 11:40:26', NULL, NULL, 0),
(54, 79, 75, NULL, '1', 'approved', '2026-01-07 11:40:26', NULL, NULL, 0),
(55, 79, 79, NULL, '1', 'approved', '2026-01-07 11:40:26', NULL, NULL, 0),
(56, 79, 64, NULL, '1', 'approved', '2026-01-07 11:40:26', NULL, NULL, 0),
(57, 80, 77, NULL, '1', 'approved', '2026-01-07 11:42:06', NULL, NULL, 0),
(58, 80, 75, NULL, '1', 'approved', '2026-01-07 11:42:06', NULL, NULL, 0),
(59, 80, 79, NULL, '1', 'approved', '2026-01-07 11:42:06', NULL, NULL, 0),
(60, 80, 64, NULL, '1', 'approved', '2026-01-07 11:42:06', NULL, NULL, 0),
(61, 85, 77, NULL, '1', 'approved', '2026-01-07 11:44:09', NULL, NULL, 0),
(62, 85, 75, NULL, '1', 'approved', '2026-01-07 11:44:09', NULL, NULL, 0),
(63, 85, 79, NULL, '1', 'approved', '2026-01-07 11:44:09', NULL, NULL, 0),
(64, 85, 64, NULL, '1', 'approved', '2026-01-07 11:44:09', NULL, NULL, 0),
(65, 84, 77, NULL, '1', 'approved', '2026-01-07 11:46:18', NULL, NULL, 0),
(66, 84, 75, NULL, '1', 'approved', '2026-01-07 11:46:18', NULL, NULL, 0),
(67, 84, 79, NULL, '1', 'approved', '2026-01-07 11:46:18', NULL, NULL, 0),
(68, 84, 64, NULL, '1', 'approved', '2026-01-07 11:46:18', NULL, NULL, 0),
(69, 83, 77, NULL, '1', 'approved', '2026-01-07 11:48:11', NULL, NULL, 0),
(70, 83, 75, NULL, '1', 'approved', '2026-01-07 11:48:11', NULL, NULL, 0),
(71, 83, 79, NULL, '1', 'approved', '2026-01-07 11:48:11', NULL, NULL, 0),
(72, 83, 64, NULL, '1', 'approved', '2026-01-07 11:48:11', NULL, NULL, 0),
(73, 87, 73, NULL, '1', 'approved', '2026-01-07 12:11:37', NULL, NULL, 0),
(74, 87, 64, NULL, '1', 'approved', '2026-01-07 12:11:37', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `course_registration`
--

CREATE TABLE `course_registration` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `term` varchar(50) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `finance_cleared` tinyint(1) DEFAULT 0,
  `finance_cleared_at` timestamp NULL DEFAULT NULL,
  `finance_cleared_by` int(11) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `school_id` int(11) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `head_of_department` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`id`, `name`, `school_id`, `code`, `description`, `head_of_department`, `created_at`, `updated_at`) VALUES
(1, 'Information and Communication Technology', 1, '001', 'IT related section', 'Samuel Sianamate', '2025-10-10 11:49:44', '2025-10-10 11:49:44'),
(3, 'Finance', 7, '003', '', 'Ms. Jessica Kababa', '2025-10-10 21:00:09', '2025-10-30 13:19:41'),
(4, 'Business Administration', 7, '002', '', 'Ms Muleya Munkombwe C', '2025-10-10 21:00:09', '2025-10-30 13:17:57');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive','terminated') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `branch_code` varchar(50) DEFAULT NULL,
  `nrc_number` varchar(50) DEFAULT NULL,
  `tax_pin` varchar(50) DEFAULT NULL,
  `cv_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_id`, `first_name`, `last_name`, `email`, `phone`, `department_id`, `position`, `hire_date`, `salary`, `status`, `created_at`, `updated_at`, `address`, `city`, `state`, `country`, `postal_code`, `bank_name`, `account_number`, `account_name`, `branch_code`, `nrc_number`, `tax_pin`, `cv_path`) VALUES
(2, 'LSC001', 'sam', 'smith', 'employee@example.com', '+260979667723', 1, 'CEO', '2025-12-23', 8000.00, 'active', '2025-12-23 11:18:55', '2025-12-23 11:18:55', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `exam_schedules`
--

CREATE TABLE `exam_schedules` (
  `id` int(11) NOT NULL,
  `exam_title` varchar(255) NOT NULL,
  `exam_type` varchar(50) NOT NULL,
  `programme_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(100) DEFAULT NULL,
  `invigilator_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'Cash',
  `receipt_number` varchar(100) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'approved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fee_types`
--

CREATE TABLE `fee_types` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fee_types`
--

INSERT INTO `fee_types` (`id`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Application Fee', 'One-time application processing fee', 1, '2025-10-31 13:52:02', '2025-10-31 13:52:02'),
(2, 'Tuition Fee', 'Main tuition/academic fee', 1, '2025-10-31 13:52:02', '2025-10-31 13:52:02'),
(3, 'Internet Fee', 'Internet access fee', 1, '2025-10-31 13:52:02', '2025-10-31 13:52:02'),
(4, 'Sports Fee', 'Sports facility fee', 1, '2025-10-31 13:52:02', '2025-10-31 13:52:02'),
(5, 'Library Fee', 'Library access fee', 1, '2025-10-31 13:52:02', '2025-10-31 13:52:02'),
(6, 'Laboratory Fee', 'Computer/equipment laboratory fee', 1, '2025-10-31 13:52:02', '2025-10-31 13:52:02'),
(7, 'Ream of Paper', 'Paper supply fee', 1, '2025-10-31 13:52:02', '2025-10-31 13:52:02'),
(8, 'Uniform', 'Student uniform fee', 1, '2025-10-31 13:52:02', '2025-10-31 13:52:02'),
(9, 'Exam Fee', 'Examination fee', 1, '2025-10-31 13:52:02', '2025-10-31 13:52:02'),
(10, 'Registration Fee', 'Student registration fee', 1, '2025-10-31 13:52:02', '2025-10-31 13:52:02');

-- --------------------------------------------------------

--
-- Table structure for table `finance_transactions`
--

CREATE TABLE `finance_transactions` (
  `id` int(11) NOT NULL,
  `student_user_id` int(11) DEFAULT NULL,
  `type` enum('income','expense') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `party_type` varchar(50) DEFAULT NULL,
  `party_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `finance_transactions`
--

INSERT INTO `finance_transactions` (`id`, `student_user_id`, `type`, `amount`, `description`, `party_type`, `party_name`, `created_at`) VALUES
(16, NULL, 'income', 4159.00, 'School fees payment for first-time registration', 'Student', 'Chomba Ntambi', '2026-01-05 08:32:34');

-- --------------------------------------------------------

--
-- Table structure for table `grading_scale`
--

CREATE TABLE `grading_scale` (
  `id` int(11) NOT NULL,
  `min_score` decimal(5,2) NOT NULL,
  `max_score` decimal(5,2) NOT NULL,
  `grade` varchar(5) NOT NULL,
  `points` decimal(3,1) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `grading_scale`
--

INSERT INTO `grading_scale` (`id`, `min_score`, `max_score`, `grade`, `points`, `created_at`) VALUES
(1, 85.00, 100.00, 'A+', 4.0, '2025-10-10 14:01:09');

-- --------------------------------------------------------

--
-- Table structure for table `intake`
--

CREATE TABLE `intake` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `intake`
--

INSERT INTO `intake` (`id`, `name`, `start_date`, `end_date`, `description`, `created_at`, `updated_at`) VALUES
(1, 'January', '2026-01-11', '2026-04-11', 'First intake of the academic year', '2025-10-10 13:22:55', '2025-10-27 11:45:10'),
(2, 'April', '2026-04-12', '2026-07-03', 'Second Intake of the Academic year', '2025-10-10 13:24:03', '2025-10-27 11:44:31'),
(3, 'October', '2025-10-01', '2025-12-10', 'Fourth Intake the Academic Year', '2025-10-13 11:01:50', '2025-10-27 11:43:24'),
(4, 'July', '2025-09-01', '2025-12-15', 'Fall semester intake for 2025', '2025-10-13 11:07:59', '2025-10-17 07:56:43');

-- --------------------------------------------------------

--
-- Table structure for table `intake_courses`
--

CREATE TABLE `intake_courses` (
  `id` int(11) NOT NULL,
  `intake_id` int(11) NOT NULL,
  `term` varchar(50) NOT NULL,
  `course_id` int(11) NOT NULL,
  `programme_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `intake_courses`
--

INSERT INTO `intake_courses` (`id`, `intake_id`, `term`, `course_id`, `programme_id`) VALUES
(1, 2, '1', 1, NULL),
(3, 2, '1', 2, NULL),
(6, 2, '1', 3, NULL),
(16, 1, '1', 1, NULL),
(17, 1, '1', 2, NULL),
(19, 1, '1', 2, 33),
(22, 1, '1', 3, 33);

-- --------------------------------------------------------

--
-- Table structure for table `lecturer_attendance`
--

CREATE TABLE `lecturer_attendance` (
  `id` int(11) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `programme_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('Present','Absent','Late','On Leave') NOT NULL,
  `session_type` varchar(50) DEFAULT 'Lecture',
  `session_time` time DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lecturer_courses`
--

CREATE TABLE `lecturer_courses` (
  `lecturer_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_emergency_access`
--

CREATE TABLE `maintenance_emergency_access` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `granted_by` int(11) NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'Cash',
  `reference_number` varchar(100) DEFAULT NULL,
  `status` enum('paid','pending','failed') DEFAULT 'paid',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `pay_period_start` date NOT NULL,
  `pay_period_end` date NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `allowances` decimal(10,2) DEFAULT 0.00,
  `deductions` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `net_salary` decimal(10,2) NOT NULL,
  `tax_calculation` text DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pending_students`
--

CREATE TABLE `pending_students` (
  `id` int(11) NOT NULL,
  `student_number` varchar(50) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `programme_id` int(11) DEFAULT NULL,
  `intake_id` int(11) DEFAULT NULL,
  `documents` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `registration_status` enum('pending','pending_approval','approved','rejected') DEFAULT 'pending',
  `temp_password` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `finance_cleared` tinyint(1) DEFAULT 0,
  `finance_cleared_at` timestamp NULL DEFAULT NULL,
  `finance_cleared_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pending_students`
--

INSERT INTO `pending_students` (`id`, `student_number`, `full_name`, `email`, `programme_id`, `intake_id`, `documents`, `created_at`, `payment_method`, `payment_amount`, `transaction_id`, `payment_proof`, `registration_status`, `temp_password`, `updated_at`, `finance_cleared`, `finance_cleared_at`, `finance_cleared_by`, `rejection_reason`) VALUES
(3, NULL, 'sam smith', 'sianamtesamuel@gmail.com', NULL, NULL, '[{\"name\":\"intakes_2025-10-17.csv\",\"path\":\"uploads\\/1760967252_grade12results_intakes_2025-10-17.csv\"},{\"name\":\"student_docket_LSC000001.pdf\",\"path\":\"uploads\\/1760967252_previousschool_student_docket_LSC000001.pdf\"}]', '2025-10-27 14:13:39', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(4, NULL, 'John Sample Student', 'john.sample@lsc.ac.zm', 1, 4, '[{\"name\":\"Transcript.pdf\",\"path\":\"\\/uploads\\/transcript.pdf\"},{\"name\":\"ID_Copy.pdf\",\"path\":\"\\/uploads\\/id_copy.pdf\"}]', '2025-10-27 14:15:31', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(5, NULL, 'Kondwani Banda', 'kondwani@gmail.com', 25, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1761904390_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1761904390_previousschool_IT-DETAILS (2)-1.pdf\"},\"recommended_by\":\"none\"}', '2025-11-07 09:48:20', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(6, NULL, 'Kondwani Banda', 'kondwani@gmail.com', 25, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1761904390_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1761904390_previousschool_IT-DETAILS (2)-1.pdf\"},\"recommended_by\":\"none\"}', '2025-11-07 10:36:46', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(7, NULL, 'Amosiana Sam', 'sianamatesamuel@gmail.com', 7, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762513266_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762513266_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-07 11:02:10', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(8, NULL, 'Amosiana Sam', 'sianamatesamuel@gmail.com', 7, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762513266_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762513266_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-07 12:15:08', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(9, NULL, 'Amosiana Sam', 'sianamatesamuel@gmail.com', 7, 1, '{\"0\":{\"name\":\"lsucmpph.coreftp\",\"path\":\"uploads\\/1762519536_grade12results_lsucmpph.coreftp\"},\"1\":{\"name\":\"Secure lsucmpph.coreftp\",\"path\":\"uploads\\/1762519536_previousschool_Secure lsucmpph.coreftp\"},\"recommended_by\":\"Jones\"}', '2025-11-07 12:46:07', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(10, NULL, 'long courses', 'sianamatesamuel@gmail.com', 12, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-07 14:22:24', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(11, NULL, 'long courses', 'sianamatesamuel@gmail.com', 12, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-07 14:29:34', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(12, NULL, 'long courses', 'sianamatesamuel@gmail.com', 12, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-08 09:05:51', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(13, NULL, 'long courses', 'sianamatesamuel@gmail.com', 12, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-08 09:09:25', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(14, NULL, 'long courses', 'sianamatesamuel@gmail.com', 12, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762592759_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762592759_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-08 09:09:56', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(15, NULL, 'Anna Mulenga', 'anna@gmail.com', 20, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1762002503_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1762002503_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"Banda\"}', '2025-11-08 09:22:47', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(16, NULL, 'Anna Mulenga', 'anna@gmail.com', 20, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1762002503_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1762002503_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"Banda\"}', '2025-11-08 14:32:02', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(17, NULL, 'Anna Mulenga', 'anna@gmail.com', 20, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1762002503_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1762002503_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"Banda\"}', '2025-11-10 07:40:24', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(18, NULL, 'Anna Mulenga', 'anna@gmail.com', 20, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1762002503_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1762002503_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"Banda\"}', '2025-11-13 09:15:43', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(19, NULL, 'Anna Mulenga', 'anna@gmail.com', 20, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1762002503_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1762002503_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"Banda\"}', '2025-11-14 06:31:14', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(20, NULL, 'Anna Mulenga', 'anna@gmail.com', 20, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1762002503_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1762002503_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"Banda\"}', '2025-11-17 08:14:49', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(21, NULL, 'moses Phiri', 'Moses@example.com', 6, 1, '{\"0\":{\"name\":\"lsuc 2026 admissioin advert.png\",\"path\":\"uploads\\/1761835405_grade12results_lsuc 2026 admissioin advert.png\"},\"1\":{\"name\":\"lsuc 2026 admissioin Advert (1).png\",\"path\":\"uploads\\/1761835405_previousschool_lsuc 2026 admissioin Advert (1).png\"},\"recommended_by\":\"\"}', '2025-11-17 12:51:19', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL, NULL),
(22, NULL, 'sam smith', 'sianamtesamuel@gmail.com', 33, 1, '{\"0\":{\"name\":\"Screenshot (169).png\",\"path\":\"uploads\\/1764079902_grade12results_Screenshot (169).png\"},\"1\":{\"name\":\"Screenshot (169).png\",\"path\":\"uploads\\/1764079902_previousschool_Screenshot (169).png\"},\"recommended_by\":\"Jones\"}', '2025-11-25 14:12:31', NULL, NULL, NULL, NULL, 'rejected', NULL, '2026-01-02 15:57:12', 0, NULL, NULL, 'Note Qualified'),
(23, NULL, 'sam smith', 'sianamtesamuel@gmail.com', 33, 1, NULL, '2025-11-25 14:16:29', 'mobile_money', 100.00, 'none', 'uploads/payment_proofs/6925ba3db38f8_1764080189.png', 'approved', NULL, '2025-11-25 14:18:00', 0, NULL, NULL, NULL),
(24, NULL, 'sam smith', 'sianamtesamuel@gmail.com', 33, 1, NULL, '2025-11-26 15:25:53', '', 1500.00, 'none', 'uploads/payment_proofs/69271c0114c93_1764170753.png', 'rejected', NULL, '2026-01-02 15:57:20', 0, NULL, NULL, 'as you work to finalise the catalogue of programmes'),
(25, NULL, 'John Mbewe', 'john@gmai.com', 26, 2, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1761912785_grade12results_IT-DETAILS (2)-1 (1).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1761912785_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"none\"}', '2025-11-27 08:17:37', NULL, NULL, NULL, NULL, 'rejected', NULL, '2026-01-02 15:57:27', 0, NULL, NULL, 'as you work to finalise the catalogue of programmes'),
(26, NULL, 'long courses', 'sianamatesamuel@gmail.com', 12, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1763367295_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1763367295_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-27 08:19:56', NULL, NULL, NULL, NULL, 'rejected', NULL, '2026-01-02 15:57:35', 0, NULL, NULL, 'as you work to finalise the catalogue of programmes'),
(27, NULL, 'sam smith', 'smithamosiana@gmail.com', 28, 1, '{\"0\":{\"name\":\"Screenshot (188).png\",\"path\":\"uploads\\/1764232014_grade12results_Screenshot (188).png\"},\"1\":{\"name\":\"Screenshot (188).png\",\"path\":\"uploads\\/1764232014_previousschool_Screenshot (188).png\"},\"recommended_by\":\"Jones\"}', '2025-11-27 08:27:22', NULL, NULL, NULL, NULL, 'rejected', NULL, '2026-01-02 15:57:41', 0, NULL, NULL, 'as you work to finalise the catalogue of programmes'),
(28, NULL, 'sam smith', 'smithamosiana@gmail.com', 28, 1, NULL, '2025-11-27 08:44:58', 'mobile_money', 2000.00, 'none', 'uploads/payment_proofs/69280f8a3abd8_1764233098.png', 'approved', NULL, '2025-11-27 08:45:55', 0, NULL, NULL, NULL),
(29, 'LSC2025000029', 'Husted Chola', 'hustedchola114@gmail.com', 6, 1, NULL, '2025-11-27 10:41:44', 'bank_transfer', 1500.00, '0000', 'uploads/payment_proofs/69282ae82b2a9_1764240104.png', 'approved', NULL, '2025-11-27 10:43:22', 0, NULL, NULL, NULL),
(30, 'LSC2025000030', 'Husted Chola', 'hustedchola114@gmail.com', 6, 1, NULL, '2025-11-27 13:08:53', 'bank_transfer', 1500.00, '0000', 'uploads/payment_proofs/69284d6551afa_1764248933.png', 'approved', NULL, '2025-12-31 12:49:08', 1, '2025-12-31 12:49:08', 3, NULL),
(31, 'LSC2025000031', 'Whiteson Chilambwe', 'chilambwewhiteson@gmail.com', 19, 1, NULL, '2025-11-27 14:48:57', 'mobile_money', 1800.00, '0000', 'uploads/payment_proofs/692864d913ab7_1764254937.png', 'approved', NULL, '2025-12-01 14:59:00', 1, '2025-12-01 14:59:00', 3, NULL),
(32, 'LSC2025000032', 'peter chikubula', 'peterchikubula@lsuczm.com', 15, 1, NULL, '2025-11-27 15:26:48', 'mobile_money', 2000.00, '0000', 'uploads/payment_proofs/69286db856ab6_1764257208.png', 'approved', NULL, '2025-11-27 15:32:52', 0, NULL, NULL, NULL),
(33, 'LSC2025000033', 'Mildred Chisenga', 'mildredchisenga43@gmail.com', 21, 1, NULL, '2025-11-28 13:58:53', 'mobile_money', 2000.00, '0000', 'uploads/payment_proofs/6929aa9d30df3_1764338333.png', 'approved', NULL, '2025-11-28 14:01:09', 0, NULL, NULL, NULL),
(34, 'LSC2026000034', 'Chomba Ntambi', 'ntambichomba@gmail.com', 24, 1, NULL, '2026-01-05 08:27:38', 'bank_transfer', 4159.00, '0862530901639531', 'uploads/payment_proofs/695b75faed7ca_1767601658.jpeg', 'approved', NULL, '2026-01-05 08:32:34', 1, '2026-01-05 08:32:34', 3, NULL),
(35, 'LSC2026000035', 'Winnie Chenga', 'winniechenga@gmail.com', 12, 1, NULL, '2026-01-05 12:53:16', 'bank_transfer', 1000.00, '0000', 'uploads/payment_proofs/695bb43c12854_1767617596.jpeg', 'approved', NULL, '2026-01-05 13:01:49', 1, '2026-01-05 13:01:49', 3, NULL),
(36, NULL, 'Winnie Chenga', 'winniechenga@gmail.com', 12, 1, NULL, '2026-01-05 13:04:52', 'bank_transfer', 1000.00, '0000', 'uploads/payment_proofs/695bb6f441754_1767618292.jpeg', 'pending_approval', NULL, '2026-01-05 13:04:52', 0, NULL, NULL, NULL),
(37, NULL, 'sam smith', 'sianamtesamuel@gmail.com', NULL, NULL, '{\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', '2026-01-06 09:19:52', NULL, NULL, NULL, NULL, 'pending', NULL, '2026-01-06 09:19:52', 0, NULL, NULL, NULL),
(38, NULL, 'sam smith', 'sianamatesamuel@gmail.com', 11, NULL, '{\"occupation\":\"\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', '2026-01-06 11:23:51', NULL, NULL, NULL, NULL, 'pending', NULL, '2026-01-06 11:23:51', 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'view_dashboard', 'Access to dashboard overview', '2025-10-08 10:45:08'),
(2, 'manage_users', 'Create, edit, and delete users', '2025-10-08 10:45:08'),
(3, 'manage_roles', 'Create and edit roles and permissions', '2025-10-08 10:45:08'),
(4, 'view_users', 'View user listings', '2025-10-08 10:45:08'),
(5, 'create_users', 'Create new user accounts', '2025-10-08 10:45:08'),
(6, 'edit_users', 'Edit existing user accounts', '2025-10-08 10:45:08'),
(7, 'delete_users', 'Delete user accounts', '2025-10-08 10:45:08'),
(8, 'bulk_upload_users', 'Upload multiple users via Excel', '2025-10-08 10:45:09'),
(9, 'manage_schools', 'Manage school structures', '2025-10-08 10:45:09'),
(10, 'manage_departments', 'Manage department structures', '2025-10-08 10:45:09'),
(11, 'manage_programmes', 'Manage academic programmes', '2025-10-08 10:45:09'),
(12, 'manage_courses', 'Manage course listings', '2025-10-08 10:45:09'),
(13, 'view_results', 'View academic results', '2025-10-08 10:45:09'),
(14, 'manage_results', 'Input and manage CA and exam results', '2025-10-08 10:45:09'),
(15, 'approve_enrollments', 'Approve student enrollment requests', '2025-10-08 10:45:10'),
(16, 'approve_registrations', 'Approve course registration requests', '2025-10-08 10:45:10'),
(17, 'view_reports', 'Access to system reports', '2025-10-08 10:45:10'),
(18, 'generate_reports', 'Generate custom reports', '2025-10-08 10:45:11'),
(19, 'view_analytics', 'Access to system analytics', '2025-10-08 10:45:11'),
(20, 'manage_finance', 'Manage financial transactions', '2025-10-08 10:45:11'),
(21, 'view_finance', 'View financial reports', '2025-10-08 10:45:11'),
(22, 'system_settings', 'Access to system configuration', '2025-10-08 10:45:11'),
(23, 'manage_academic_structure', 'Manage schools, departments, programmes, courses', '2025-10-16 10:51:27'),
(24, 'enrollment_approvals', 'Review and approve enrollment applications', '2025-10-16 10:51:27'),
(25, 'course_registrations', 'Approve student course registrations', '2025-10-16 10:51:27'),
(26, 'reports', 'Generate and download system reports', '2025-10-16 10:51:28'),
(27, 'profile_access', 'View and edit personal profile', '2025-10-16 10:51:28'),
(28, 'Course Registration', 'Approve Student Registration', '2025-12-22 13:43:16');

-- --------------------------------------------------------

--
-- Table structure for table `programme`
--

CREATE TABLE `programme` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `duration_years` int(11) DEFAULT 4,
  `school_id` int(11) DEFAULT NULL,
  `qualification_level` enum('Certificate','Diploma','Bachelor','Master','PhD') DEFAULT 'Bachelor',
  `credits_required` int(11) DEFAULT 120,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `duration` int(11) DEFAULT NULL,
  `category` enum('undergraduate','short_course') DEFAULT 'undergraduate'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `programme`
--

INSERT INTO `programme` (`id`, `name`, `department_id`, `code`, `description`, `duration_years`, `school_id`, `qualification_level`, `credits_required`, `is_active`, `created_at`, `updated_at`, `duration`, `category`) VALUES
(1, 'Certificate Civil Engineering & Construction', NULL, 'DBA', 'Diploma in Business srudies', 4, 1, 'Bachelor', 120, 1, '2025-10-10 12:16:10', '2025-10-30 13:58:13', 0, 'undergraduate'),
(5, 'Certificate in Electrical Engineering (Craft) - 2 Years', NULL, '1', '', 4, 1, 'Bachelor', 120, 1, '2025-10-30 13:52:40', '2026-01-02 14:16:14', 1, 'undergraduate'),
(6, 'Certificate in Solar Technology', NULL, '2', NULL, 4, 1, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 0, 'undergraduate'),
(7, 'Certificate Civil Engineering & Construction', NULL, '3', NULL, 4, 1, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 0, 'undergraduate'),
(8, 'Certificate in Automotive Engineering (Craft)', NULL, 'AE', '', 4, 1, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2026-01-02 14:14:20', 1, 'undergraduate'),
(9, 'Certificate in Occupational Safety Health and Environment', NULL, '5', NULL, 4, 1, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 0, 'undergraduate'),
(10, 'Diploma in Computer Studies (ICT’S)', NULL, '6', NULL, 4, 1, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 3, 'undergraduate'),
(11, 'Certificate in Computer Studies (ICT’S)', NULL, '7', NULL, 4, 1, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 0, 'undergraduate'),
(12, 'Diploma Business in Business Administration', NULL, '8', NULL, 4, 7, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 3, 'undergraduate'),
(13, 'Diploma in Procurement', NULL, '9', NULL, 4, 7, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 3, 'undergraduate'),
(14, 'Diploma in Human Resource Management', NULL, '10', NULL, 4, 7, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 3, 'undergraduate'),
(15, 'Diploma in Accountancy', NULL, '11', NULL, 4, 7, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 3, 'undergraduate'),
(16, 'Certificate in Secretarial and Office Management', NULL, '12', NULL, 4, 7, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 1, 'undergraduate'),
(17, 'Diploma in Transport and Logistics', NULL, '13', NULL, 4, 7, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 3, 'undergraduate'),
(18, 'Diploma in Marketing', NULL, '14', NULL, 4, 7, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 3, 'undergraduate'),
(19, 'Diploma in Secondary Teaching', NULL, '15', NULL, 4, 5, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 3, 'undergraduate'),
(20, 'Diploma in Primary Teaching', NULL, '16', NULL, 4, 5, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 3, 'undergraduate'),
(21, 'Diploma in Law', NULL, '17', NULL, 4, 5, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 3, 'undergraduate'),
(22, 'Certificate in Early Childhood Education', NULL, '18', NULL, 4, 5, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 1, 'undergraduate'),
(23, 'Diploma in General Agriculture', NULL, '19', NULL, 4, 2, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 3, 'undergraduate'),
(24, 'Certificate in General Agriculture', NULL, '20', NULL, 4, 2, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 1, 'undergraduate'),
(25, 'Certificate in Food Production', NULL, '21', NULL, 4, 2, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 1, 'undergraduate'),
(26, 'Diploma in Public Health', NULL, '22', NULL, 4, 6, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 3, 'undergraduate'),
(27, 'Diploma in Environmental Health', NULL, '23', NULL, 4, 6, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 3, 'undergraduate'),
(28, 'Certificate in Nursing and Health Care Assistant', NULL, '24', NULL, 4, 6, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 0, 'undergraduate'),
(33, 'Bachelor of Science in Computer Science', NULL, 'BSC-CS', NULL, 4, 1, 'Bachelor', 120, 1, '2025-11-17 13:09:16', '2025-11-17 13:09:16', 4, 'undergraduate'),
(35, 'Certificate in Electrical Engineering (Level III)  - 6 Months', NULL, 'EE111', '6 Months Trade in Engineering', 4, 1, 'Bachelor', 120, 1, '2026-01-02 14:06:18', '2026-01-02 14:06:18', 1, 'undergraduate'),
(36, 'Certificate in Electrical Engineering (Level II)  - 1 Year', NULL, 'EE11', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 14:07:48', '2026-01-02 14:07:48', 1, 'undergraduate'),
(37, 'Certificate in Electrical Engineering (Level I )  - 1 Year 6 Months', NULL, 'EE1', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 14:10:15', '2026-01-02 14:10:15', 1, 'undergraduate'),
(38, 'Certificate in Automotive Engineering (Level III) - 6 Months', NULL, 'AEIII', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 14:19:05', '2026-01-02 14:19:05', 1, 'undergraduate'),
(39, 'Certificate in Automotive Engineering (Level I) - 1 Year 6 Months', NULL, 'AEI', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 14:22:41', '2026-01-02 14:22:41', 1, 'undergraduate'),
(40, 'Certificate in Crop Production and Management', NULL, 'CPM', '', 4, 2, 'Bachelor', 120, 1, '2026-01-02 14:27:57', '2026-01-02 14:27:57', 1, 'short_course'),
(41, 'Certificate in Computer Studies (ICT Essentials)', NULL, 'CS', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 14:28:47', '2026-01-02 14:28:47', 1, 'short_course'),
(42, 'Certificate in Solar Design Installation and Testing', NULL, 'SD', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 14:29:37', '2026-01-02 14:29:37', 1, 'short_course'),
(43, 'Certificate in Artificial Intelligence and Machine Learning', NULL, 'AI', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 14:34:42', '2026-01-02 14:34:42', 1, 'short_course'),
(44, 'Certificate in Electric Cars Technology, Maintenance and Service', NULL, 'ECT', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 14:45:31', '2026-01-02 14:45:31', 1, 'short_course'),
(45, 'Certificate in Psychosocial Counselling', NULL, 'PC', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 14:46:21', '2026-01-02 14:46:21', 1, 'short_course'),
(46, 'Certificate in Monitoring and Evaluation', NULL, 'ME', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 14:56:26', '2026-01-02 14:56:26', 1, 'short_course'),
(47, 'Certificate in Teaching and Lecturing Methodology', NULL, 'TM', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 14:57:21', '2026-01-02 14:57:21', 1, 'short_course'),
(48, 'Electrical Power Systems Fundamentals', NULL, 'EP', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 14:58:15', '2026-01-02 14:58:15', 1, 'short_course'),
(49, 'Certificate in Mechanical Engineering Fundamentals', NULL, 'MEF', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 14:59:39', '2026-01-02 14:59:39', 1, 'short_course'),
(50, 'Certificate in Vehicle Heating, Ventilation and Air Conditioning', NULL, 'VHV', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 15:05:20', '2026-01-02 15:05:20', 1, 'short_course'),
(51, 'Certificate in Agric Grain Commodity Supply Chain Management', NULL, 'AGS', '', 4, 2, 'Bachelor', 120, 1, '2026-01-02 15:08:27', '2026-01-02 15:08:27', 1, 'short_course'),
(52, 'Certificate in Construction Cost and Quantity Estimation', NULL, 'CCQ', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 15:10:35', '2026-01-02 15:10:35', 1, 'short_course'),
(53, 'Certificate in Financial Management for SME\'s and Corporates', NULL, 'FM', '', 4, 7, 'Bachelor', 120, 1, '2026-01-02 15:11:41', '2026-01-02 15:11:41', 1, 'short_course'),
(54, 'Certificate in Business Leadership and Supervision', NULL, 'BLS', '', 4, 7, 'Bachelor', 120, 1, '2026-01-02 15:13:12', '2026-01-02 15:13:12', 1, 'short_course'),
(55, 'Certificate in Mental Health and Financial Literacy', NULL, 'MHF', '', 4, 1, 'Bachelor', 120, 1, '2026-01-02 15:15:07', '2026-01-02 15:15:07', 1, 'short_course'),
(56, 'Certificate in Meat Processing and Value Addition', NULL, 'MP', '', 4, 2, 'Bachelor', 120, 1, '2026-01-02 15:25:02', '2026-01-02 15:25:02', 1, 'short_course'),
(57, 'Corporate Staff Retreat', NULL, 'CSR', '', 4, 7, 'Bachelor', 120, 1, '2026-01-02 15:25:52', '2026-01-02 15:25:52', 1, 'short_course');

-- --------------------------------------------------------

--
-- Table structure for table `programme_fees`
--

CREATE TABLE `programme_fees` (
  `id` int(11) NOT NULL,
  `programme_id` int(11) NOT NULL,
  `fee_name` varchar(150) NOT NULL,
  `fee_amount` decimal(10,2) NOT NULL,
  `fee_type` enum('one_time','per_term','per_year') NOT NULL DEFAULT 'per_term',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `programme_fees`
--

INSERT INTO `programme_fees` (`id`, `programme_id`, `fee_name`, `fee_amount`, `fee_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Application Fee', 50.00, 'one_time', 'One-time application processing fee', 1, '2025-10-31 13:31:26', '2025-10-31 13:31:26'),
(2, 1, 'Tuition Fee', 2500.00, 'per_term', 'Tuition fee per term', 1, '2025-10-31 13:31:26', '2025-10-31 13:31:26'),
(3, 1, 'Internet Fee', 50.00, 'per_term', 'Internet access fee per term', 1, '2025-10-31 13:31:26', '2025-10-31 13:31:26'),
(4, 1, 'Sports Fee', 30.00, 'per_term', 'Sports facility fee per term', 1, '2025-10-31 13:31:26', '2025-10-31 13:31:26'),
(5, 1, 'Library Fee', 20.00, 'per_year', 'Annual library access fee', 1, '2025-10-31 13:31:26', '2025-10-31 13:31:26'),
(6, 1, 'Uniform for Business Students', 150.00, 'one_time', 'Uniform for Business Students - Once Off', 1, '2025-10-31 13:31:26', '2025-10-31 13:31:26'),
(8, 12, 'Application Fee', 50.00, 'one_time', 'One-time application processing fee', 1, '2025-10-31 13:33:02', '2025-10-31 13:33:02'),
(9, 12, 'Tuition Fee', 2500.00, 'per_term', 'Tuition fee per term', 1, '2025-10-31 13:33:02', '2025-10-31 13:33:02'),
(10, 12, 'Internet Fee', 50.00, 'per_term', 'Internet access fee per term', 1, '2025-10-31 13:33:02', '2025-10-31 13:33:02'),
(11, 12, 'Sports Fee', 30.00, 'per_term', 'Sports facility fee per term', 1, '2025-10-31 13:33:02', '2025-10-31 13:33:02'),
(12, 12, 'Library Fee', 20.00, 'per_year', 'Annual library access fee', 1, '2025-10-31 13:33:02', '2025-10-31 13:33:02'),
(13, 12, 'Uniform for Business Students', 150.00, 'one_time', 'Uniform for Business Students - Once Off', 1, '2025-10-31 13:33:02', '2025-10-31 13:33:02'),
(14, 10, 'Application Fee', 50.00, 'one_time', 'One-time application processing fee', 1, '2025-10-31 13:33:02', '2025-10-31 13:33:02'),
(15, 10, 'Tuition Fee', 3000.00, 'per_term', 'Tuition fee per term', 1, '2025-10-31 13:33:02', '2025-10-31 13:33:02'),
(16, 10, 'Laboratory Fee', 100.00, 'per_term', 'Computer laboratory fee per term', 1, '2025-10-31 13:33:02', '2025-10-31 13:33:02'),
(17, 10, 'Internet Fee', 50.00, 'per_term', 'Internet access fee per term', 1, '2025-10-31 13:33:02', '2025-10-31 13:33:02'),
(18, 10, 'Sports Fee', 30.00, 'per_term', 'Sports facility fee per term', 1, '2025-10-31 13:33:02', '2025-10-31 13:33:02'),
(19, 10, 'Library Fee', 20.00, 'per_year', 'Annual library access fee', 1, '2025-10-31 13:33:02', '2025-10-31 13:33:02'),
(20, 10, 'Ream of Paper', 25.00, 'per_year', 'Ream of Paper - Per Year', 1, '2025-10-31 13:33:02', '2025-10-31 13:33:02'),
(21, 10, 'Uniform for IT Students', 200.00, 'one_time', 'Uniform for IT Students - Once Off', 1, '2025-10-31 13:33:02', '2025-10-31 13:33:02');

-- --------------------------------------------------------

--
-- Table structure for table `registered_students`
--

CREATE TABLE `registered_students` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `student_email` varchar(255) NOT NULL,
  `student_number` varchar(20) DEFAULT NULL,
  `programme_name` varchar(255) DEFAULT NULL,
  `intake_name` varchar(255) DEFAULT NULL,
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `registration_type` enum('course','first_time') NOT NULL,
  `status` enum('pending_notification','email_sent') DEFAULT 'pending_notification',
  `email_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registered_students`
--

INSERT INTO `registered_students` (`id`, `student_id`, `student_name`, `student_email`, `student_number`, `programme_name`, `intake_name`, `payment_amount`, `registration_type`, `status`, `email_sent_at`, `created_at`, `updated_at`) VALUES
(1, 0, 'Whiteson Chilambwe', 'chilambwewhiteson@gmail.com', 'LSC2025000001', '', '', 1800.00, 'first_time', 'email_sent', '2025-12-19 13:50:01', '2025-12-01 14:59:00', '2025-12-19 13:50:01'),
(2, 0, 'Husted Chola', 'hustedchola114@gmail.com', 'LSC2026000003', 'Certificate in Solar Technology', 'January', 1500.00, 'first_time', 'email_sent', '2026-01-05 13:29:23', '2025-12-31 12:49:08', '2026-01-05 13:29:23'),
(3, 0, 'Chomba Ntambi', 'ntambichomba@gmail.com', 'LSC2026000001', 'Certificate in General Agriculture', 'January', 4159.00, 'first_time', 'email_sent', '2026-01-05 08:33:22', '2026-01-05 08:32:34', '2026-01-05 08:33:22'),
(4, 0, 'Winnie Chenga', 'winniechenga@gmail.com', 'LSC2026000002', 'Diploma Business in Business Administration', 'January', 1000.00, 'first_time', 'email_sent', '2026-01-05 13:03:06', '2026-01-05 13:01:49', '2026-01-05 13:03:06');

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `ca_score` decimal(5,2) DEFAULT 0.00,
  `exam_score` decimal(5,2) DEFAULT 0.00,
  `total_score` decimal(5,2) GENERATED ALWAYS AS (`ca_score` + `exam_score`) STORED,
  `grade` varchar(4) DEFAULT NULL,
  `admin_comment` text DEFAULT NULL,
  `uploaded_by_user_id` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `results`
--

INSERT INTO `results` (`id`, `enrollment_id`, `ca_score`, `exam_score`, `grade`, `admin_comment`, `uploaded_by_user_id`, `uploaded_at`) VALUES
(1, 1, 85.00, NULL, NULL, 'Outstanding performance in this course. Demonstrated excellent understanding of the material.', 41, '2025-12-19 14:43:32'),
(2, 2, 78.00, 0.00, NULL, NULL, 2, '2025-12-23 07:02:59'),
(3, 2, 78.00, 0.00, NULL, NULL, 2, '2025-12-23 07:03:03'),
(4, 18, NULL, 72.56, NULL, NULL, 48, '2026-01-07 12:14:26'),
(5, 22, NULL, 0.00, NULL, NULL, 48, '2026-01-07 12:14:26'),
(6, 26, NULL, 54.00, NULL, NULL, 48, '2026-01-07 12:14:26'),
(7, 50, NULL, 67.38, NULL, NULL, 48, '2026-01-07 12:14:26'),
(8, 42, NULL, 57.80, NULL, NULL, 48, '2026-01-07 12:14:26');

-- --------------------------------------------------------

--
-- Table structure for table `result_publishing`
--

CREATE TABLE `result_publishing` (
  `id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `programme_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `publish_date` date NOT NULL,
  `deadline_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('scheduled','published','cancelled') DEFAULT 'scheduled',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `result_type`
--

CREATE TABLE `result_type` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `result_type`
--

INSERT INTO `result_type` (`id`, `name`, `weight`, `created_at`) VALUES
(1, 'CA', 40.00, '2025-10-10 14:00:03');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Super Admin', 'Full system admin', '2025-10-06 11:09:34'),
(2, 'Lecturer', 'Course lecturer', '2025-10-06 11:09:34'),
(3, 'Sub Admin (Finance)', 'Finance officer', '2025-10-06 11:09:34'),
(4, 'Student', 'Student', '2025-10-06 11:09:34'),
(6, 'super_admin', 'Super Admin role', '2025-10-16 10:51:26'),
(7, 'sub_admin_finance', 'Sub Admin (Finance) role', '2025-10-16 10:51:27'),
(8, 'Enrollments', 'Handling all Student Enrollments', '2025-10-20 12:39:43'),
(9, 'Enrollment Officer', 'Handles student enrollment applications', '2025-10-20 13:07:41'),
(11, 'Academics Coordinator', 'Academics coordinator responsible for academic operations', '2025-12-22 14:07:44'),
(12, 'HR Manager', 'Human Resources Manager', '2025-12-23 09:58:56');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(11, 1),
(1, 2),
(6, 2),
(7, 2),
(1, 3),
(6, 3),
(3, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(11, 12),
(1, 14),
(2, 14),
(6, 14),
(11, 14),
(1, 15),
(1, 16),
(11, 16),
(1, 17),
(11, 17),
(1, 18),
(11, 18),
(1, 19),
(1, 20),
(3, 20),
(1, 21),
(3, 21),
(1, 22),
(6, 23),
(11, 23),
(6, 24),
(6, 25),
(11, 25),
(6, 26),
(7, 26),
(11, 26),
(2, 27),
(4, 27),
(6, 27),
(7, 27),
(11, 27);

-- --------------------------------------------------------

--
-- Table structure for table `school`
--

CREATE TABLE `school` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `established_year` int(11) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school`
--

INSERT INTO `school` (`id`, `name`, `description`, `established_year`, `contact_email`, `contact_phone`, `address`, `created_at`) VALUES
(1, 'Engineering and Technology', 'A dynamic center of innovation where cutting-edge technology education meets real-world application.', 2025, '', '', '', '2025-10-08 20:58:49'),
(2, 'Agriculture and Hospitality', 'General Agriculture Programmes', 2025, '', '', '', '2025-10-08 20:59:50'),
(5, 'Education and Social Sciences', 'Primary and Secondary Teaching', 2025, '', '', '', '2025-10-10 21:00:08'),
(6, 'Health Care Management', '', 2025, '', '', '', '2025-10-30 13:14:32'),
(7, 'Business Management', 'Business Administration ', 0, '', '', '', '2025-10-30 13:15:45');

-- --------------------------------------------------------

--
-- Table structure for table `staff_profile`
--

CREATE TABLE `staff_profile` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `staff_id` varchar(50) DEFAULT NULL,
  `NRC` varchar(50) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_profile`
--

INSERT INTO `staff_profile` (`user_id`, `full_name`, `staff_id`, `NRC`, `gender`, `qualification`, `bio`) VALUES
(2, 'Mary Lecturer', 'LEC001', '12345678', 'Female', 'B.Ed. Computing', NULL),
(3, 'Peter Finance', 'FIN001', '87654321', 'Male', 'B.Com Finance', NULL),
(10, 'John Lecturer', 'LEC002', '234567890', 'Male', 'PhD Computer Science', NULL),
(20, 'Enrollment Officer', 'ENR001', '123456/78/90', 'Male', 'Bachelor of Education', NULL),
(22, 'Jessica Kababa', '', '', '', '', NULL),
(23, 'Winnie Chenga', 'STF-2025-0023', 'none', 'Female', '', NULL),
(41, 'Samuel Sianamate', 'STF-2025-0041', '00000', 'Male', 'Degree', NULL),
(48, 'Muleya Munkombwe', 'STF-2026-0048', '', 'Female', 'Degree', NULL),
(49, 'Musonda L Chikubula', 'STF-2026-0049', '', 'Female', 'Degree', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_accommodation`
--

CREATE TABLE `student_accommodation` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `check_in_date` date DEFAULT NULL,
  `check_out_date` date DEFAULT NULL,
  `status` enum('active','checked_out','terminated') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_fees`
--

CREATE TABLE `student_fees` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `fee_type` varchar(100) NOT NULL,
  `amount_due` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `balance` decimal(10,2) GENERATED ALWAYS AS (`amount_due` - `amount_paid`) STORED,
  `due_date` date DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `status` enum('pending','partial','paid','overdue') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_profile`
--

CREATE TABLE `student_profile` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `student_number` varchar(20) DEFAULT NULL,
  `NRC` varchar(50) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `programme_id` int(11) DEFAULT NULL,
  `school_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `intake_id` int(11) DEFAULT NULL,
  `results_access` tinyint(1) DEFAULT 1,
  `profile_photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_profile`
--

INSERT INTO `student_profile` (`user_id`, `full_name`, `student_number`, `NRC`, `gender`, `programme_id`, `school_id`, `department_id`, `balance`, `intake_id`, `results_access`, `profile_photo`) VALUES
(4, 'Alice Student', 'LSC000001', '11223344', 'Female', NULL, NULL, NULL, 0.00, NULL, 1, '../uploads/profile_photos/student_4_1766068619.jpeg'),
(12, 'Bob Student', 'LSC000002', '456789012', 'Male', 1, 1, 1, 0.00, NULL, 1, NULL),
(24, 'long courses', 'LSC2025000027', NULL, NULL, 12, NULL, NULL, 0.00, 1, 1, NULL),
(26, 'long courses', 'LSC2025000028', NULL, NULL, 12, NULL, NULL, 0.00, 1, 1, NULL),
(27, 'Anna Mulenga', 'LSC2025000017', NULL, NULL, 20, NULL, NULL, 0.00, 1, 0, NULL),
(33, 'sam smith', 'LSC2025000036', NULL, NULL, 33, NULL, NULL, 0.00, 1, 1, NULL),
(34, 'John Mbewe', 'LSC2025000015', NULL, NULL, 26, NULL, NULL, 0.00, 2, 1, NULL),
(35, 'long courses', 'LSC2025000034', NULL, NULL, 12, NULL, NULL, 0.00, 1, 1, NULL),
(36, 'sam smith', 'LSC2025000037', NULL, NULL, 28, NULL, NULL, 0.00, 1, 1, NULL),
(37, 'Husted Chola', 'LSC2025000029', NULL, NULL, 6, NULL, NULL, 0.00, 1, 1, NULL),
(38, 'Whiteson Chilambwe', 'LSC2025000031', NULL, NULL, 19, NULL, NULL, 0.00, 1, 1, NULL),
(39, 'peter chikubula', 'LSC2025000032', NULL, NULL, 15, NULL, NULL, 0.00, 1, 1, NULL),
(40, 'Mildred Chisenga', 'LSC2025000033', '', '', 21, 0, NULL, 0.00, 1, 0, NULL),
(44, 'Husted Chola', 'LSC2025000030', NULL, NULL, 6, NULL, NULL, 0.00, 1, 1, NULL),
(45, 'Chomba Ntambi', 'LSC2026000034', '', 'Male', 24, 0, NULL, 0.00, 1, 1, NULL),
(46, 'Winnie Chenga', 'LSC2026000035', NULL, NULL, 12, NULL, NULL, 0.00, 1, 1, '../uploads/profile_photos/student_46_1767619098.jpeg'),
(51, 'Christpher Sifuba', 'LSC000124', '244885/19/1', 'Male', 19, NULL, NULL, 0.00, NULL, 1, NULL),
(52, 'Kelly Jonathan Katongo', 'LSC000123', '864748/10/1', 'Male', 12, NULL, NULL, 0.00, NULL, 1, NULL),
(54, 'Noah  Chimwemwe Tembo', 'LSC000122', '651660/10/1', 'Male', 12, NULL, NULL, 0.00, NULL, 1, NULL),
(57, 'Enock Musonda Mutati', 'LSC000089', NULL, 'Male', 4, NULL, NULL, 0.00, NULL, 1, NULL),
(58, 'Breadly Boris Ochurub', 'LSC000112', NULL, 'Male', 6, NULL, NULL, 0.00, NULL, 1, NULL),
(64, 'Simon Kataila Singangwe', 'LSC000033', NULL, 'Male', 12, NULL, NULL, 0.00, NULL, 1, NULL),
(65, 'Saidy Mutemwa Sepiso', 'LSC000153', '284293/83/1', 'Male', 6, NULL, NULL, 0.00, NULL, 1, NULL),
(68, 'George Mungandi', 'LSC000144', '283779/81/1', 'Male', 6, NULL, NULL, 0.00, NULL, 1, NULL),
(72, 'Kamuwamya Kanyincha', 'LSC000146', '279510/81/1', 'Male', 6, NULL, NULL, 0.00, NULL, 1, NULL),
(73, 'Lusimo Liswaniso', 'LSC000147', '294198/81/1', 'Male', 6, 1, NULL, 0.00, 3, 1, NULL),
(75, 'Solomon Mwale', 'LSC000152', '282423/81/1', 'Male', 6, 1, NULL, 0.00, 3, 1, NULL),
(86, 'Ngebe Mwendoi', 'LSC000137', '286202/81/1', 'Male', 6, NULL, NULL, 0.00, NULL, 1, NULL),
(89, 'Jeremiah Kanenga', 'LSC000140', '286170/81/1', 'Male', 6, 1, NULL, 0.00, 3, 1, NULL),
(90, 'Mukwabila Swalelo', 'LSC000126', '290279/81/1', 'Male', 6, 0, NULL, 0.00, 3, 1, NULL),
(91, 'Mulele Livini', 'LSC000127', '239055/81/1', 'Male', 6, 1, NULL, 0.00, 3, 1, NULL),
(92, 'Amukusana Siskena', 'LSC000128', '402991/82/1', 'Male', 6, 1, NULL, 0.00, 3, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_result`
--

CREATE TABLE `student_result` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'system_name', 'Lusaka South College SRMS', '2025-12-17 09:18:22', '2025-12-17 09:18:22'),
(2, 'system_email', 'admin@lsc.ac.zm', '2025-12-17 09:18:22', '2025-12-17 09:18:22'),
(3, 'timezone', 'Africa/Lusaka', '2025-12-17 09:18:22', '2025-12-17 09:18:22'),
(4, 'maintenance_mode', '0', '2025-12-17 09:18:22', '2025-12-31 07:06:46'),
(65, 'maintenance_end_time', '', '2025-12-24 10:43:45', '2025-12-31 07:06:46');

-- --------------------------------------------------------

--
-- Table structure for table `tax_brackets`
--

CREATE TABLE `tax_brackets` (
  `id` int(11) NOT NULL,
  `bracket_name` varchar(100) NOT NULL,
  `min_income` decimal(10,2) NOT NULL,
  `max_income` decimal(10,2) DEFAULT NULL,
  `tax_rate` decimal(5,2) NOT NULL,
  `fixed_amount` decimal(10,2) DEFAULT 0.00,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `timetables`
--

CREATE TABLE `timetables` (
  `id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `programme_id` int(11) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('pending','generated','published') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timetables`
--

INSERT INTO `timetables` (`id`, `academic_year`, `semester`, `programme_id`, `start_time`, `end_time`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2024/2025', 'Semester 1', 33, '08:00:00', '10:00:00', 'pending', 42, '2025-12-23 07:18:55', '2025-12-23 07:18:55'),
(2, '2024/2025', 'Semester 1', 1, '12:00:00', '13:00:00', 'pending', 42, '2025-12-23 07:20:01', '2025-12-23 07:20:01');

-- --------------------------------------------------------

--
-- Table structure for table `timetable_entries`
--

CREATE TABLE `timetable_entries` (
  `id` int(11) NOT NULL,
  `timetable_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `lecturer_id` int(11) DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  `class_group` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `email`, `contact`, `created_at`, `is_active`) VALUES
(1, 'admin@lsc.ac.zm', '$2y$10$Ql4zbovdDanITHB/S1XyRuCnucXwNltWZB3/d1087fMrBYl7rWaCW', 'admin@lsc.ac.zm', '0971234567', '2025-10-06 11:09:34', 1),
(2, 'lecturer1@lsc.ac.zm', '$2y$10$omKuKWgpamvviowQyG6xuuFAk0fdZV.UWNpkyVX/H3BtObnSw7iEC', 'lecturer1@lsc.ac.zm', '0972345678', '2025-10-06 11:09:35', 1),
(3, 'finance@lsc.ac.zm', '$2y$10$BZKn7yk9mwX1bvy6b9O1YuOXic2dK8hBDXvpRCjp6sWk0qWbfyHcS', 'finance@lsc.ac.zm', '0973456789', '2025-10-06 11:09:35', 1),
(4, 'LSC000001', '$2y$10$Jt7C.9qGMtHlrtJR8/Fgnea07V7AQJZRX8L6Sv1zH5S0BCwmSL2f6', 'student1@lsc.ac.zm', '0974567890', '2025-10-06 11:09:35', 1),
(9, 'admin.jane@lsc.ac.zm', '$2y$10$L7zT0FGI5QRKQKEFxyxET.v9uundepd.ycO3jJ98Dhj0pb8LMKbfi', 'admin.jane@lsc.ac.zm', '0977123456', '2025-10-08 11:20:02', 1),
(10, 'lecturer.john@lsc.ac.zm', '$2y$10$KpIQ4TtTpgw/RFLYqvIIuuOkDZIgxBEK3NBbyT1t7eDwKNinBBta2', 'lecturer.john@lsc.ac.zm', '0976234567', '2025-10-08 11:20:02', 1),
(12, 'LSC000002', '$2y$10$tHP5fDRunGCQVUuv4nYaLOkzovMU9kD5K5.a2EbPO13eSzmM16JSW', 'student.bob@lsc.ac.zm', '0974456789', '2025-10-08 11:20:03', 1),
(20, 'enrollment@lsc.ac.zm', '$2y$10$nYyhkWcYqiY3AYpIe8.VmOLzDb5VMKDBOiI/LEZOmMcZg4MHH9yhm', 'enrollment@lsc.ac.zm', NULL, '2025-10-20 13:12:38', 1),
(22, 'Jessy', '$2y$10$6Yq.Iw/sAUGaOy8QpSdEoed0pw1QrgCxFuELw2ReSRE8a4Yu5d5Yu', 'jessicakababa@gmail.com', '0979667723', '2025-10-29 12:13:52', 1),
(23, 'Winnie', '$2y$10$N4tCV73a.efcPeVTjWM6IuNsN.5QbY2jodszbyPMae0UuX68uKboa', 'winniechenga@gmail.com', 'none', '2025-10-31 14:17:46', 1),
(24, 'LSC2025000027', '$2y$10$LQu8Xijof7lVyi35TfjCEuznQFEOdeRpVSZZhc38NW5JFYvu51S1S', 'sianamatesamuel@gmail.com', '0978654534', '2025-11-07 14:22:24', 1),
(26, 'LSC2025000028', '$2y$10$G75b6U3SvYcMRSxYksEBMurZIuB8EvEoJtgjXR.C2aUhkC5AEhwZa', 'sianamatesamuel@gmail.com', '0978654534', '2025-11-08 09:09:56', 1),
(27, 'LSC2025000017', '$2y$10$qLJa5Oy1uA9j4CzzaEVc/uXtFzcfB9VdW9FBOrz06Y.q5g5Aekqdm', 'anna@gmail.com', NULL, '2025-11-08 09:22:47', 1),
(33, 'LSC2025000036', '$2y$10$zcyyFjzkUoPSrJzJc138pOAMnAAE2NKQO.U.dQP1LDQPmGW/X5iCy', 'sianamtesamuel@gmail.com', '0979667723', '2025-11-25 14:12:31', 1),
(34, 'LSC2025000015', '$2y$10$hD0cYGD0l8QBMjtf7AdB3uc6tEKAS4uJBscAOFuh5mrFPKyHPgd0e', 'john@gmai.com', NULL, '2025-11-27 08:17:37', 1),
(35, 'LSC2025000034', '$2y$10$Be6VPPSMyHXMedo.qvvcf.6gEj9hdkQhSqwM8EHmG89eCYFCJpr1e', 'sianamatesamuel@gmail.com', '0978654534', '2025-11-27 08:19:56', 1),
(36, 'LSC2025000037', '$2y$10$IntUZdKVYt8yOioH3Iyss.2J.Fpmxt.3d3h7yL1t9ETL0Y..uYsAa', 'smithamosiana@gmail.com', '0979667723', '2025-11-27 08:27:23', 1),
(37, 'LSC2025000029', '$2y$10$Rdn.ZYmdobysPF9CI8k3depFrsxKbONQW.Hto9wiGPysqnDB8tFTq', 'hustedchola114@gmail.com', '', '2025-11-27 10:43:22', 1),
(38, 'LSC2025000031', '$2y$10$oQ9Fmts1t0fpbaCxHY0YU.DG.Z0Hv/5lTRnJi.fL9ZYmUUHTi3MFq', 'chilambwewhiteson@gmail.com', '', '2025-11-27 14:49:24', 1),
(39, 'LSC2025000032', '$2y$10$kljX0K6DlsNyh7oPqEWCg.QlfgXWm0WlVDN6MW/zklEGEQozTr3wi', 'peterchikubula@lsuczm.com', '', '2025-11-27 15:32:52', 1),
(40, 'LSC2025000033', '$2y$10$dMtZckfM4TrQtEJj3vueJuWLhwIIgFSxc9l8FK4l0xnThpe.l08wy', 'mildredchisenga43@gmail.com', '', '2025-11-28 14:01:09', 1),
(41, 'sianamate', '$2y$10$SVDIL2YGWc.x1H3TAlZJM.TL8Sz0OLvkczIGC9QsgX0jsTfZjoBB.', 'sianamatesamuel@gmail.com', '0979667723', '2025-12-19 14:15:37', 1),
(42, 'academics@lsc.ac.zm', '$2y$10$elWoyawXYsPhrubbOB7W7OtnCtbrbY20jaqi20Vcy0qcyCPInECiG', 'academics@lsc.ac.zm', '0975678901', '2025-12-22 14:07:44', 1),
(43, 'hrmanager', '$2y$10$2WeBKvDEp5TbTRf/uDALme0zWHzodUgFTDU2Wpb8j/.1p6HlsmWlW', 'hr@lsc.edu', 'HR Manager', '2025-12-23 10:01:28', 1),
(44, 'LSC2025000030', '$2y$10$bU/xOO1jKP6AwrsSDX9BWOt4bWr4YsJgafy3hSEDUda2zg5N1zjcS', 'hustedchola114@gmail.com', '', '2025-12-31 12:46:10', 1),
(45, 'LSC2026000034', '$2y$10$yOwDNMpRfz15BjXhz0qg6OAFLMKAivoMYQeZJU2fUIdIRkgyBGrnS', 'ntambichomba@gmail.com', '', '2026-01-05 08:30:14', 1),
(46, 'LSC2026000035', '$2y$10$9JzKcbdfXKa9R3Uiy3MTsOV56szWnj8a8mI8D4tjzYMcNt4j6hua6', 'winniechenga@gmail.com', '', '2026-01-05 12:57:40', 1),
(47, 'Muleya', '$2y$10$C0fg66CcNEdo6agYGvxs5eBaTlYWkOR.uyqQTIfjAGcFdKf7BARIO', 'muleyamunkombwe709@gmail.com', '+260 977832894', '2026-01-05 14:50:23', 1),
(48, 'Munkombwe', '$2y$10$KHBeKrBUHmbJY1nolzC2ieMm6rqbphacXcFLZMGh8xNscCOKomWAC', 'muleyamunkombwe709@gmail.com', '+260 977832894', '2026-01-05 14:59:29', 1),
(49, 'Musonda', '$2y$10$c57FZCFdqrPeeSbBd.0wHOBOU4I01dpNQyVcmm1uULWcNTiZpzBhy', 'musondaleahc@gmail.com', '+260 964812583', '2026-01-05 15:03:39', 1),
(50, 'Admin', '$2y$10$aCPdakyJ1xN9SppM2lyYEeVKNGr4HJNXCDuOH.KclVzzHHFrwCzx6', 'Admin@lsuczm.com', '', '2026-01-06 06:45:01', 1),
(51, 'LSC000124', '$2y$10$lHOGflWMmEGxq.hQa3h2ae33keZgehcnRl0wBg6J.aDn96I3MeFwq', 'sifubachristoper23@gmail.com', '0958101120', '2026-01-06 07:03:03', 1),
(52, 'LSC000123', '$2y$10$JbydgjUAMbjOBjQfG3Q7huiZ0Ip72GLK2afAMmXuAu83x4npRf2oK', 'kellykatongo@gmail.com', '0776947405', '2026-01-06 07:03:03', 1),
(53, 'LSC000125', '$2y$10$sIcX/anBQHOorXav2epREOPhdu8pS.P92lkIKgX7BaBdSW/uLKxR2', 'chamaesther998@gmail.com', '0967603634', '2026-01-06 07:03:03', 1),
(54, 'LSC000122', '$2y$10$WRNwwqTuslCE2EQq9HJDz.0P1zIH8JsmD8/VrEgpGfW8Ag9P5AHUW', 'NoahTembo@gmail.com', '0978826521', '2026-01-06 07:03:03', 1),
(55, 'LSC000108', '$2y$10$GNSblEhXW5EY4CnhfHnWUOhRBHkn754jiGDDmJmHJ4mowghLYgRb2', 'katongom860@gmail.com', '0773063622', '2026-01-06 07:03:03', 1),
(56, 'LSC000116', '$2y$10$3p.H9MOnFPZqUNd14esOcOpdO69CC/dR3FQKXHdjW0ZPROySbfqIK', 'adrianbanda4478@gmail.com', '0770815750', '2026-01-06 07:03:03', 1),
(57, 'LSC000089', '$2y$10$utpCRaBc95x03.9OVqhhweptJDhEFNUuA6chDrkU4TAMjz/YKl3Oy', 'Enockmutati01@gmail.com', '0771929341', '2026-01-06 07:03:03', 1),
(58, 'LSC000112', '$2y$10$lBSA1EdxrBGrDLMvM3ejQuH5BP/Fqevc45HhxjJoZUgkOEYl3wC26', 'borisohurub@gmail.com', '246814194991', '2026-01-06 07:03:03', 1),
(59, 'LSC000075', '$2y$10$zUq/4LM5c1Zf9U/NE/Lx6uBZPSOwxW/X7FuXLwjSpTy0apjuliitK', 'tembofrancina2001@gmail.com', '0972125485', '2026-01-06 07:03:04', 1),
(60, 'LSC000080', '$2y$10$BsrQEGJn6CWcS3F95SurDOUYfZWJLeXtCcjDwq.bAUJUBGNRo.A4m', 'namwingacatherine@gmail.com', '0765676725', '2026-01-06 07:03:04', 1),
(61, 'LSC000084', '$2y$10$X1MPNSMEGDfvaUAd4POA8uhEJcl5Nk5waBC2bW2kCfgmlnPbvNT2.', 'Josphatkaundula8@gmail.com', '0974220352', '2026-01-06 07:03:04', 1),
(62, 'LSC000086', '$2y$10$tmAdALHDtjnxnzr3ufXzZO9ZiQJ3MRpQToQRy5Hza5OlmOEx3zTva', NULL, '0971892254', '2026-01-06 07:03:04', 1),
(63, 'LSC000027', '$2y$10$66TjtOl8jJu7.BEeZopFM.EqPZNhMDl1WtP47zB0LnBE/v9aZa.f2', 'Isabellakayz1@gmail.com', '0975116558', '2026-01-06 07:03:04', 1),
(64, 'LSC000033', '$2y$10$jOFBTPyk1XNrAVEwNhecJO9YzF03CiV2.YxrXP0NoFxVev47p4gBa', 'Simonsinyangwe12@gmail.com', '0765370746', '2026-01-06 07:03:04', 1),
(65, 'LSC000153', '$2y$10$ume9Qb72BL39Ed6f3uEXB.f.KAIh4jH45A/8f9P6vpPozQWIl19KS', NULL, '0950769851', '2026-01-06 07:03:04', 1),
(66, 'LSC000129', '$2y$10$HMk2HoX6uajKjaSd9kOsc.LTVgxSxxiXP4J8E5bRfe9jNhcE.jlqm', 'Kebbykapishi@gmail.com', '0953400328', '2026-01-06 07:03:04', 1),
(67, 'LSC000151', '$2y$10$jZeLaMYnLRvAlKNK6.dqUOpjQAAosEvlg8pBwvhTRCIEU1n9fWA2G', 'derrickmungandi@gmail.com', '0974142972', '2026-01-06 07:03:04', 1),
(68, 'LSC000144', '$2y$10$8Chx/CGhEQdYXVUWHFnGqOJKbAEPPy93bQ9sjP.o/Xyy7pz7hqQJW', NULL, '0772779346', '2026-01-06 07:03:04', 1),
(69, 'LSC000142', '$2y$10$pzcLFblm/Sywrjufajl2AujW7p8HasdQUxsZ9oEz2S2VZqWI0P0t2', NULL, '0773026834', '2026-01-06 07:03:04', 1),
(70, 'LSC000148', '$2y$10$JGRubTXFIToUcxcetAPfE.dqeKKamOUgUbhSAMJTYqLHFs3xILVZi', 'pumulosikota@gmail.com', '0971160198', '2026-01-06 07:03:04', 1),
(71, 'LSC000149', '$2y$10$j93SnNsl8ysbZaskBeRkIOEgqkSEGwIFBRuFh21JTtR7sVd//MjFy', 'chrispinemushele@gmail.com', '0778773248', '2026-01-06 07:03:04', 1),
(72, 'LSC000146', '$2y$10$HGgg1cnZw40RRG4VzX9ttuNlPkMxG/eAnAbwj2uiIXPIj6w322qsO', NULL, '0765881122', '2026-01-06 07:03:04', 1),
(73, 'LSC000147', '$2y$10$VuI57kU.sr8Bh5WD/sjd3uP4lgKHmHjOObLPsgmMfTJ6PSO6KQ0uG', 'lusimo@gmail.com', '0954871423', '2026-01-06 07:03:05', 1),
(74, 'LSC000150', '$2y$10$VlkbzLnCj2OSl6SU46Qr0.pnOnIVFWtfE0SeHiX1F6aZenqeJTWyS', 'samsonmuchaya527@gmail.com', '0974390966', '2026-01-06 07:03:05', 1),
(75, 'LSC000152', '$2y$10$1YC3iPm7kTNb4oROh1kZoeh2340RjjCbNbW7uyo1.Bcak6jjhCXDO', 'solomon@gmail.com', '0761064451', '2026-01-06 07:03:05', 1),
(77, 'LSC000143', '$2y$10$hTO9n.FA9nEqrSH0v9PV2eZD/gKNc0CYnrfiffcPZJ6Tono8dESuO', 'Katongi@gmail.com', '0978084936', '2026-01-06 07:03:05', 1),
(78, 'LSC000145', '$2y$10$SoYf0PDd0EYPPzpCcrJugeIwCRd4MxxMLRAcPV4ghVQazus2pdfSW', 'Harrykahalu@gmail.com', '0975715863', '2026-01-06 07:03:05', 1),
(79, 'LSC000134', '$2y$10$Ww6v9k/lw6/nIrSf/mi3P.SkzYdSHPKHXRIkNIpoxWpF4aNLeYKZi', 'isaackasnga48@gmail.com', '0975895694', '2026-01-06 07:03:05', 1),
(80, 'LSC000133', '$2y$10$v/w.sO9h9c0Z0LeQEW6G2.aLVTSqxx4aF2HnTl7bjHYLnwpG3bZA2', 'Matthewsbanda114@gmail.com', '0977848786', '2026-01-06 07:03:05', 1),
(81, 'LSC000132', '$2y$10$3V3l8gg2kES1.He7hc2Qi.CjdtYoDdfIyZwS2.x7QxE85h1YnRpFK', 'chrismukelabi@gmail.com', '0765797667', '2026-01-06 07:03:05', 1),
(82, 'LSC000131', '$2y$10$7A2szznH4DlcTXvx1r8V8eyaaOmd92lSaOpMMG/JIPDos37bdmwqe', 'Ngwelelantanda57@gmail.com', '0766809015', '2026-01-06 07:03:05', 1),
(83, 'LSC000130', '$2y$10$wAcT1YfUGFr0ylx.EHHD4euz3UEVyEKWk4TzbpBcOuuELmX/xn6fK', 'Chilumbuliwena82@gmail.com', '0774973058', '2026-01-06 07:03:05', 1),
(84, 'LSC000141', '$2y$10$pOeRVyziIlTCtmmeCi8AWupftkjS41c1gd1zRZEkcP9uSunQ91A1G', 'Kasangakahilu2@gmail.com', '0770561725', '2026-01-06 07:03:05', 1),
(85, 'LSC000136', '$2y$10$EmngnS0kGTJigUR8C5WDu.yuTCcIThJLZL9JJFpnWiyR2/mdEbgqW', 'Oliver@gmail.com', '0974127397', '2026-01-06 07:03:05', 1),
(86, 'LSC000137', '$2y$10$x1nCTeUYlKtMvz.z6eYTjeZu0MKEHdz6w/CwTF77zSD2AkW1MA1gm', NULL, '0951312256', '2026-01-06 07:03:05', 1),
(87, 'LSC000138', '$2y$10$Gjg.UjQgbEPEieQp0KI0mewPai04WIdETPs2o8toqEVEmre8AHK0m', 'judymulowa@gmail.com', '0971008846', '2026-01-06 07:03:06', 1),
(88, 'LSC000139', '$2y$10$BLlwPe05MPItjsHYDR/5wOofnsZ/olzaJTxKrPs0MCl9.wm7vaRRi', 'sililom@gmail.com', '0950243584', '2026-01-06 07:03:06', 1),
(89, 'LSC000140', '$2y$10$On5SKyf0e63DN3gLLc9OROYKydaaeBPJHyj/Tvsbfowu8EjrcalaG', 'Jeremiah@gmail.com', '0954030150', '2026-01-06 07:03:06', 1),
(90, 'LSC000126', '$2y$10$E6vCsq8ie4ZGqX4jOPVD.eGpflrVMSfXSGwcFvDgYvUtELx5ew3h2', 'mukwabilaswailelo@gmail.com', '0956481107', '2026-01-06 07:03:06', 1),
(91, 'LSC000127', '$2y$10$mNvr6YCLn0jU/gxXN6gxHuKymyAr0yPmjk.I5Srt7vFhVQDg.acV2', 'Channylivingi@gmail.com', '0972005280', '2026-01-06 07:03:06', 1),
(92, 'LSC000128', '$2y$10$neIhqP65gQj.arM330eWou1.Hpe2tTvkqx9ESsjDG.Lw39GBnwRey', 'giftsisikena0@gmail.com', '0979817936', '2026-01-06 07:03:06', 1),
(93, 'LSC000023', '$2y$10$B10ZUXM.dKQ7x4mYkPhJdeGlaVxqsWG11HyWlDqXlIL/gbYQ5RtU2', NULL, '0978814471', '2026-01-06 07:03:06', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `granted` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1),
(9, 1),
(50, 1),
(2, 2),
(10, 2),
(41, 2),
(48, 2),
(3, 3),
(49, 3),
(4, 4),
(12, 4),
(24, 4),
(26, 4),
(27, 4),
(33, 4),
(34, 4),
(35, 4),
(36, 4),
(37, 4),
(38, 4),
(39, 4),
(40, 4),
(44, 4),
(45, 4),
(46, 4),
(51, 4),
(52, 4),
(53, 4),
(54, 4),
(55, 4),
(56, 4),
(57, 4),
(58, 4),
(59, 4),
(60, 4),
(61, 4),
(62, 4),
(63, 4),
(64, 4),
(65, 4),
(66, 4),
(67, 4),
(68, 4),
(69, 4),
(70, 4),
(71, 4),
(72, 4),
(73, 4),
(74, 4),
(75, 4),
(77, 4),
(78, 4),
(79, 4),
(80, 4),
(81, 4),
(82, 4),
(83, 4),
(84, 4),
(85, 4),
(86, 4),
(87, 4),
(88, 4),
(89, 4),
(90, 4),
(91, 4),
(92, 4),
(93, 4),
(20, 9),
(22, 9),
(23, 9),
(42, 11),
(47, 11),
(43, 12);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_calendars`
--
ALTER TABLE `academic_calendars`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_academic_year` (`academic_year`),
  ADD KEY `idx_semester` (`semester`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `academic_year_comments`
--
ALTER TABLE `academic_year_comments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_year_comment` (`student_user_id`,`academic_year`),
  ADD KEY `added_by_user_id` (`added_by_user_id`);

--
-- Indexes for table `accommodation_applications`
--
ALTER TABLE `accommodation_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `accommodation_rooms`
--
ALTER TABLE `accommodation_rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_room` (`block_name`,`room_number`);

--
-- Indexes for table `admin_profile`
--
ALTER TABLE `admin_profile`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `programme_id` (`programme_id`),
  ADD KEY `intake_id` (`intake_id`),
  ADD KEY `fk_applications_processed_by` (`processed_by`);

--
-- Indexes for table `course`
--
ALTER TABLE `course`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `programme_id` (`programme_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `course_assignment`
--
ALTER TABLE `course_assignment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`course_id`,`lecturer_id`,`academic_year`,`semester`),
  ADD KEY `lecturer_id` (`lecturer_id`);

--
-- Indexes for table `course_enrollment`
--
ALTER TABLE `course_enrollment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_user_id` (`student_user_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `course_registration`
--
ALTER TABLE `course_registration`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `exam_schedules`
--
ALTER TABLE `exam_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invigilator_id` (`invigilator_id`),
  ADD KEY `idx_exam_date` (`exam_date`),
  ADD KEY `idx_programme_id` (`programme_id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fee_types`
--
ALTER TABLE `fee_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `finance_transactions`
--
ALTER TABLE `finance_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_user_id` (`student_user_id`);

--
-- Indexes for table `grading_scale`
--
ALTER TABLE `grading_scale`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `intake`
--
ALTER TABLE `intake`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `intake_courses`
--
ALTER TABLE `intake_courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `intake_id` (`intake_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `fk_intake_courses_programme` (`programme_id`);

--
-- Indexes for table `lecturer_attendance`
--
ALTER TABLE `lecturer_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_lecturer_id` (`lecturer_id`),
  ADD KEY `idx_programme_id` (`programme_id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `lecturer_courses`
--
ALTER TABLE `lecturer_courses`
  ADD PRIMARY KEY (`lecturer_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `maintenance_emergency_access`
--
ALTER TABLE `maintenance_emergency_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_access` (`user_id`),
  ADD KEY `granted_by` (`granted_by`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_pay_period` (`pay_period_start`,`pay_period_end`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `pending_students`
--
ALTER TABLE `pending_students`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `programme`
--
ALTER TABLE `programme`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `FK_programme_school` (`school_id`),
  ADD KEY `FK_programme_department` (`department_id`);

--
-- Indexes for table `programme_fees`
--
ALTER TABLE `programme_fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `programme_id` (`programme_id`);

--
-- Indexes for table `registered_students`
--
ALTER TABLE `registered_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_number` (`student_number`);

--
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enrollment_id` (`enrollment_id`),
  ADD KEY `uploaded_by_user_id` (`uploaded_by_user_id`);

--
-- Indexes for table `result_publishing`
--
ALTER TABLE `result_publishing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_publish_date` (`publish_date`),
  ADD KEY `idx_programme_id` (`programme_id`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `result_type`
--
ALTER TABLE `result_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `school`
--
ALTER TABLE `school`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_profile`
--
ALTER TABLE `staff_profile`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`);

--
-- Indexes for table `student_accommodation`
--
ALTER TABLE `student_accommodation`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_allocation` (`student_id`,`status`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `student_fees`
--
ALTER TABLE `student_fees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `student_profile`
--
ALTER TABLE `student_profile`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `FK_student_intake` (`intake_id`);

--
-- Indexes for table `student_result`
--
ALTER TABLE `student_result`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enrollment_id` (`enrollment_id`),
  ADD KEY `type_id` (`type_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tax_brackets`
--
ALTER TABLE `tax_brackets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`active`);

--
-- Indexes for table `timetables`
--
ALTER TABLE `timetables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_academic_year` (`academic_year`),
  ADD KEY `idx_semester` (`semester`),
  ADD KEY `idx_programme_id` (`programme_id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `timetable_entries`
--
ALTER TABLE `timetable_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_timetable_id` (`timetable_id`),
  ADD KEY `idx_day_of_week` (`day_of_week`),
  ADD KEY `idx_course_id` (`course_id`),
  ADD KEY `idx_lecturer_id` (`lecturer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`user_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_calendars`
--
ALTER TABLE `academic_calendars`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `academic_year_comments`
--
ALTER TABLE `academic_year_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `accommodation_applications`
--
ALTER TABLE `accommodation_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `accommodation_rooms`
--
ALTER TABLE `accommodation_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `course`
--
ALTER TABLE `course`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `course_assignment`
--
ALTER TABLE `course_assignment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `course_enrollment`
--
ALTER TABLE `course_enrollment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `course_registration`
--
ALTER TABLE `course_registration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `exam_schedules`
--
ALTER TABLE `exam_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `fee_types`
--
ALTER TABLE `fee_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `finance_transactions`
--
ALTER TABLE `finance_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `grading_scale`
--
ALTER TABLE `grading_scale`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `intake`
--
ALTER TABLE `intake`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `intake_courses`
--
ALTER TABLE `intake_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `lecturer_attendance`
--
ALTER TABLE `lecturer_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_emergency_access`
--
ALTER TABLE `maintenance_emergency_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pending_students`
--
ALTER TABLE `pending_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `programme`
--
ALTER TABLE `programme`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `programme_fees`
--
ALTER TABLE `programme_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `registered_students`
--
ALTER TABLE `registered_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `result_publishing`
--
ALTER TABLE `result_publishing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `result_type`
--
ALTER TABLE `result_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `school`
--
ALTER TABLE `school`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `student_accommodation`
--
ALTER TABLE `student_accommodation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_fees`
--
ALTER TABLE `student_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `student_result`
--
ALTER TABLE `student_result`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `tax_brackets`
--
ALTER TABLE `tax_brackets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `timetables`
--
ALTER TABLE `timetables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `timetable_entries`
--
ALTER TABLE `timetable_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_calendars`
--
ALTER TABLE `academic_calendars`
  ADD CONSTRAINT `academic_calendars_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `academic_year_comments`
--
ALTER TABLE `academic_year_comments`
  ADD CONSTRAINT `academic_year_comments_ibfk_1` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `academic_year_comments_ibfk_2` FOREIGN KEY (`added_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `accommodation_applications`
--
ALTER TABLE `accommodation_applications`
  ADD CONSTRAINT `accommodation_applications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student_profile` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `accommodation_applications_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_profile`
--
ALTER TABLE `admin_profile`
  ADD CONSTRAINT `admin_profile_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`programme_id`) REFERENCES `programme` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`intake_id`) REFERENCES `intake` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_applications_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `course`
--
ALTER TABLE `course`
  ADD CONSTRAINT `course_ibfk_1` FOREIGN KEY (`programme_id`) REFERENCES `programme` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `course_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `course_assignment`
--
ALTER TABLE `course_assignment`
  ADD CONSTRAINT `course_assignment_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `course` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_assignment_ibfk_2` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_enrollment`
--
ALTER TABLE `course_enrollment`
  ADD CONSTRAINT `course_enrollment_ibfk_1` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_enrollment_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `course` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_registration`
--
ALTER TABLE `course_registration`
  ADD CONSTRAINT `course_registration_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_registration_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `course` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_registration_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `department`
--
ALTER TABLE `department`
  ADD CONSTRAINT `department_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `exam_schedules`
--
ALTER TABLE `exam_schedules`
  ADD CONSTRAINT `exam_schedules_ibfk_1` FOREIGN KEY (`programme_id`) REFERENCES `programme` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_schedules_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `course` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_schedules_ibfk_3` FOREIGN KEY (`invigilator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `exam_schedules_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `finance_transactions`
--
ALTER TABLE `finance_transactions`
  ADD CONSTRAINT `finance_transactions_ibfk_1` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `intake_courses`
--
ALTER TABLE `intake_courses`
  ADD CONSTRAINT `fk_intake_courses_programme` FOREIGN KEY (`programme_id`) REFERENCES `programme` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `intake_courses_ibfk_1` FOREIGN KEY (`intake_id`) REFERENCES `intake` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `intake_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `course` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lecturer_attendance`
--
ALTER TABLE `lecturer_attendance`
  ADD CONSTRAINT `lecturer_attendance_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lecturer_attendance_ibfk_2` FOREIGN KEY (`programme_id`) REFERENCES `programme` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lecturer_attendance_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `course` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lecturer_attendance_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lecturer_courses`
--
ALTER TABLE `lecturer_courses`
  ADD CONSTRAINT `lecturer_courses_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `lecturer_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `course` (`id`);

--
-- Constraints for table `maintenance_emergency_access`
--
ALTER TABLE `maintenance_emergency_access`
  ADD CONSTRAINT `maintenance_emergency_access_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_emergency_access_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `programme`
--
ALTER TABLE `programme`
  ADD CONSTRAINT `FK_programme_department_upd` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_programme_school` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `programme_fees`
--
ALTER TABLE `programme_fees`
  ADD CONSTRAINT `programme_fees_ibfk_1` FOREIGN KEY (`programme_id`) REFERENCES `programme` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `results`
--
ALTER TABLE `results`
  ADD CONSTRAINT `results_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `course_enrollment` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ibfk_2` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `result_publishing`
--
ALTER TABLE `result_publishing`
  ADD CONSTRAINT `result_publishing_ibfk_1` FOREIGN KEY (`programme_id`) REFERENCES `programme` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `result_publishing_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `course` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `result_publishing_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_profile`
--
ALTER TABLE `staff_profile`
  ADD CONSTRAINT `staff_profile_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_accommodation`
--
ALTER TABLE `student_accommodation`
  ADD CONSTRAINT `student_accommodation_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student_profile` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_accommodation_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `accommodation_rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_accommodation_ibfk_3` FOREIGN KEY (`application_id`) REFERENCES `accommodation_applications` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_profile`
--
ALTER TABLE `student_profile`
  ADD CONSTRAINT `FK_student_intake` FOREIGN KEY (`intake_id`) REFERENCES `intake` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_profile_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_result`
--
ALTER TABLE `student_result`
  ADD CONSTRAINT `student_result_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `course_enrollment` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_result_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `result_type` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `timetables`
--
ALTER TABLE `timetables`
  ADD CONSTRAINT `timetables_ibfk_1` FOREIGN KEY (`programme_id`) REFERENCES `programme` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timetables_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `timetable_entries`
--
ALTER TABLE `timetable_entries`
  ADD CONSTRAINT `timetable_entries_ibfk_1` FOREIGN KEY (`timetable_id`) REFERENCES `timetables` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timetable_entries_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `course` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `timetable_entries_ibfk_3` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
