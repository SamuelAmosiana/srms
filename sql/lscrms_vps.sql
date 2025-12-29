-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 24, 2025 at 12:22 PM
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
-- Database: `lscrms`
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
(44, 'sam smith', 'sianamtesamuel@gmail.com', '0979667723', 'short_course', 34, NULL, NULL, '{\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'pending', NULL, NULL, '2025-12-04 10:04:42', '2025-12-04 10:04:42', NULL),
(45, 'sam smith', 'sianamtesamuel@gmail.com', '0979667723', 'short_course', 34, NULL, NULL, '{\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'pending', NULL, NULL, '2025-12-05 08:13:17', '2025-12-05 08:13:17', NULL),
(46, 'sam smith', 'sianamtesamuel@gmail.com', '0979667723', 'short_course', 34, NULL, NULL, '{\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'pending', NULL, NULL, '2025-12-15 06:36:12', '2025-12-15 06:36:12', NULL),
(47, 'sam smith', 'sianamtesamuel@gmail.com', '0979667723', 'short_course', 34, NULL, NULL, '{\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'pending', NULL, NULL, '2025-12-18 08:20:09', '2025-12-18 08:20:09', NULL),
(48, 'sam smith', 'sianamtesamuel@gmail.com', '0979667723', 'short_course', 34, NULL, NULL, '{\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'pending', NULL, NULL, '2025-12-18 08:20:26', '2025-12-18 08:20:26', NULL),
(49, 'sam smith', 'sianamtesamuel@gmail.com', '0979667723', 'short_course', 34, NULL, NULL, '{\"occupation\":\"none\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"\"}', 'rejected', 'no meeting requiremenrts', 4, '2025-12-19 07:36:26', '2025-12-18 08:28:12', '2025-12-19 07:36:26');

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
(4, 'CS201', 'Database Systems', 3, 1, 1, NULL, '2025-10-10 21:00:09', '2025-10-10 21:00:09'),
(5, 'CS202', 'Software Engineering', 3, 1, 1, NULL, '2025-10-10 21:00:09', '2025-10-10 21:00:09'),
(6, 'BIT301', 'Network Administration', 3, 1, 1, NULL, '2025-10-10 21:00:09', '2025-10-10 21:00:09');

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
(3, 4, 2, '2024', '1', '2025-10-10 21:00:09', 1),
(4, 2, 10, 'January', '1', '2025-12-01 11:06:11', 1),
(13, 2, 41, '2024', '1', '2025-12-19 14:17:45', 1),
(14, 6, 41, '2024', '1', '2025-12-19 14:17:45', 1),
(15, 5, 41, '2024', '1', '2025-12-19 14:17:45', 1),
(16, 3, 41, '2024', '1', '2025-12-19 14:17:45', 1);

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
(2, 4, 3, '2024', '1', 'enrolled', '2025-10-10 21:00:09', NULL, NULL, 0);

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

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `category`, `description`, `amount`, `expense_date`, `payment_method`, `receipt_number`, `approved_by`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Office Supplies', 'Stationery and printing materials', 150.00, '2024-01-10', 'Cash', 'RCP001', NULL, 'approved', '2025-10-10 14:51:27', '2025-10-10 14:51:27'),
(2, 'Utilities', 'Electricity bill for January', 800.00, '2024-01-31', 'Bank Transfer', 'ELEC002', NULL, 'approved', '2025-10-10 14:51:27', '2025-10-10 14:51:27'),
(3, 'Maintenance', 'Computer lab equipment repair', 1200.00, '2024-02-05', 'Cheque', 'CHQ003', NULL, 'approved', '2025-10-10 14:51:27', '2025-10-10 14:51:27'),
(4, 'Transportation', 'Staff transport allowance', 300.00, '2024-02-01', 'Cash', 'TRANS004', NULL, 'approved', '2025-10-10 14:51:27', '2025-10-10 14:51:27');

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
(1, 4, 'income', 500.00, 'Fee payment', NULL, NULL, '2025-11-26 22:00:00'),
(2, NULL, 'income', 5000.00, 'School fees', NULL, NULL, '2025-11-27 22:00:00'),
(3, NULL, 'income', 5000.00, 'School fees', NULL, NULL, '2025-11-27 22:00:00'),
(4, NULL, 'income', 1500.00, 'Student Fee Payment', NULL, NULL, '2025-01-14 22:00:00'),
(5, NULL, 'expense', 200.00, 'Office Supplies', NULL, NULL, '2025-01-15 22:00:00'),
(6, NULL, 'income', 1500.00, 'Student Fee Payment', NULL, NULL, '2025-01-14 22:00:00'),
(7, NULL, 'expense', 200.00, 'Office Supplies', NULL, NULL, '2025-01-15 22:00:00'),
(8, NULL, 'income', 1500.00, 'Student Fee Payment', NULL, NULL, '2025-01-14 22:00:00'),
(9, NULL, 'expense', 200.00, 'Office Supplies', NULL, NULL, '2025-01-15 22:00:00'),
(10, NULL, 'income', 1500.00, 'Student Fee Payment', 'Student', 'John Doe', '2025-01-14 22:00:00'),
(11, NULL, 'expense', 200.00, 'Office Supplies', 'Vendor', 'ABC Stationers', '2025-01-15 22:00:00'),
(12, NULL, 'expense', 150.00, 'Field Work Transport', 'Other', 'Peter Chikubula', '2025-11-27 22:00:00'),
(13, NULL, 'expense', 5000.00, 'Sports', 'Student', 'ZUSA', '2025-11-13 22:00:00'),
(14, NULL, 'income', 1800.00, 'School fees payment for first-time registration', 'Student', 'Whiteson Chilambwe', '2025-12-01 14:59:00');

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
(2, 2, '1', 4, NULL),
(3, 2, '1', 2, NULL),
(4, 2, '1', 6, NULL),
(5, 2, '1', 5, NULL),
(6, 2, '1', 3, NULL),
(16, 1, '1', 1, NULL),
(17, 1, '1', 2, NULL),
(18, 1, '1', 5, NULL),
(19, 1, '1', 2, 33),
(20, 1, '1', 6, 33),
(21, 1, '1', 5, 33),
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

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `student_id`, `amount`, `payment_date`, `payment_method`, `reference_number`, `status`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, 1500.00, '2024-01-15', 'Bank Transfer', 'TXN001', 'paid', 'Tuition Fee Payment', '2025-10-10 14:51:23', '2025-10-10 14:51:23'),
(2, 2, 2000.00, '2024-01-20', 'Cash', 'CASH002', 'paid', 'Full Semester Fee', '2025-10-10 14:51:23', '2025-10-10 14:51:23'),
(3, 3, 750.00, '2024-02-01', 'Mobile Money', 'MM003', 'paid', 'Partial Payment', '2025-10-10 14:51:23', '2025-10-10 14:51:23'),
(4, 4, 210.00, '2025-11-26', 'mobile_money', '0000', 'pending', '../uploads/payment_proofs/6926f6dc01357_1764161244.png', '2025-11-26 12:47:24', '2025-11-26 12:47:24'),
(5, 4, 12.00, '2025-12-19', 'mobile_money', '0000', 'pending', 'Payment for academic year 2025/2026', '2025-12-19 13:13:27', '2025-12-19 13:13:27');

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

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`id`, `employee_id`, `pay_period_start`, `pay_period_end`, `basic_salary`, `allowances`, `deductions`, `tax_amount`, `net_salary`, `tax_calculation`, `payment_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, '2025-12-31', '2026-01-31', 8000.00, 0.00, 0.00, 0.00, 8000.00, '[]', NULL, 'pending', '2025-12-23 12:25:57', '2025-12-23 12:25:57'),
(2, 2, '2025-12-31', '2026-01-31', 8000.00, 0.00, 0.00, 0.00, 8000.00, '[]', NULL, 'pending', '2025-12-23 12:26:31', '2025-12-23 12:26:31');

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
  `finance_cleared_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pending_students`
--

INSERT INTO `pending_students` (`id`, `student_number`, `full_name`, `email`, `programme_id`, `intake_id`, `documents`, `created_at`, `payment_method`, `payment_amount`, `transaction_id`, `payment_proof`, `registration_status`, `temp_password`, `updated_at`, `finance_cleared`, `finance_cleared_at`, `finance_cleared_by`) VALUES
(3, NULL, 'sam smith', 'sianamtesamuel@gmail.com', NULL, NULL, '[{\"name\":\"intakes_2025-10-17.csv\",\"path\":\"uploads\\/1760967252_grade12results_intakes_2025-10-17.csv\"},{\"name\":\"student_docket_LSC000001.pdf\",\"path\":\"uploads\\/1760967252_previousschool_student_docket_LSC000001.pdf\"}]', '2025-10-27 14:13:39', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(4, NULL, 'John Sample Student', 'john.sample@lsc.ac.zm', 1, 4, '[{\"name\":\"Transcript.pdf\",\"path\":\"\\/uploads\\/transcript.pdf\"},{\"name\":\"ID_Copy.pdf\",\"path\":\"\\/uploads\\/id_copy.pdf\"}]', '2025-10-27 14:15:31', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(5, NULL, 'Kondwani Banda', 'kondwani@gmail.com', 25, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1761904390_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1761904390_previousschool_IT-DETAILS (2)-1.pdf\"},\"recommended_by\":\"none\"}', '2025-11-07 09:48:20', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(6, NULL, 'Kondwani Banda', 'kondwani@gmail.com', 25, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1761904390_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1761904390_previousschool_IT-DETAILS (2)-1.pdf\"},\"recommended_by\":\"none\"}', '2025-11-07 10:36:46', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(7, NULL, 'Amosiana Sam', 'sianamatesamuel@gmail.com', 7, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762513266_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762513266_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-07 11:02:10', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(8, NULL, 'Amosiana Sam', 'sianamatesamuel@gmail.com', 7, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762513266_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762513266_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-07 12:15:08', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(9, NULL, 'Amosiana Sam', 'sianamatesamuel@gmail.com', 7, 1, '{\"0\":{\"name\":\"lsucmpph.coreftp\",\"path\":\"uploads\\/1762519536_grade12results_lsucmpph.coreftp\"},\"1\":{\"name\":\"Secure lsucmpph.coreftp\",\"path\":\"uploads\\/1762519536_previousschool_Secure lsucmpph.coreftp\"},\"recommended_by\":\"Jones\"}', '2025-11-07 12:46:07', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(10, NULL, 'long courses', 'sianamatesamuel@gmail.com', 12, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-07 14:22:24', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(11, NULL, 'long courses', 'sianamatesamuel@gmail.com', 12, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-07 14:29:34', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(12, NULL, 'long courses', 'sianamatesamuel@gmail.com', 12, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-08 09:05:51', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(13, NULL, 'long courses', 'sianamatesamuel@gmail.com', 12, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762525322_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-08 09:09:25', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(14, NULL, 'long courses', 'sianamatesamuel@gmail.com', 12, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762592759_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1762592759_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-08 09:09:56', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(15, NULL, 'Anna Mulenga', 'anna@gmail.com', 20, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1762002503_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1762002503_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"Banda\"}', '2025-11-08 09:22:47', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(16, NULL, 'Anna Mulenga', 'anna@gmail.com', 20, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1762002503_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1762002503_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"Banda\"}', '2025-11-08 14:32:02', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(17, NULL, 'Anna Mulenga', 'anna@gmail.com', 20, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1762002503_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1762002503_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"Banda\"}', '2025-11-10 07:40:24', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(18, NULL, 'Anna Mulenga', 'anna@gmail.com', 20, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1762002503_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1762002503_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"Banda\"}', '2025-11-13 09:15:43', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(19, NULL, 'Anna Mulenga', 'anna@gmail.com', 20, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1762002503_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1762002503_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"Banda\"}', '2025-11-14 06:31:14', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(20, NULL, 'Anna Mulenga', 'anna@gmail.com', 20, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1.pdf\",\"path\":\"uploads\\/1762002503_grade12results_IT-DETAILS (2)-1.pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1762002503_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"Banda\"}', '2025-11-17 08:14:49', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(21, NULL, 'moses Phiri', 'Moses@example.com', 6, 1, '{\"0\":{\"name\":\"lsuc 2026 admissioin advert.png\",\"path\":\"uploads\\/1761835405_grade12results_lsuc 2026 admissioin advert.png\"},\"1\":{\"name\":\"lsuc 2026 admissioin Advert (1).png\",\"path\":\"uploads\\/1761835405_previousschool_lsuc 2026 admissioin Advert (1).png\"},\"recommended_by\":\"\"}', '2025-11-17 12:51:19', NULL, NULL, NULL, NULL, 'pending', NULL, '2025-11-24 11:13:35', 0, NULL, NULL),
(22, NULL, 'sam smith', 'sianamtesamuel@gmail.com', 33, 1, '{\"0\":{\"name\":\"Screenshot (169).png\",\"path\":\"uploads\\/1764079902_grade12results_Screenshot (169).png\"},\"1\":{\"name\":\"Screenshot (169).png\",\"path\":\"uploads\\/1764079902_previousschool_Screenshot (169).png\"},\"recommended_by\":\"Jones\"}', '2025-11-25 14:12:31', NULL, NULL, NULL, NULL, 'pending_approval', NULL, '2025-11-25 14:12:31', 0, NULL, NULL),
(23, NULL, 'sam smith', 'sianamtesamuel@gmail.com', 33, 1, NULL, '2025-11-25 14:16:29', 'mobile_money', 100.00, 'none', 'uploads/payment_proofs/6925ba3db38f8_1764080189.png', 'approved', NULL, '2025-11-25 14:18:00', 0, NULL, NULL),
(24, NULL, 'sam smith', 'sianamtesamuel@gmail.com', 33, 1, NULL, '2025-11-26 15:25:53', '', 1500.00, 'none', 'uploads/payment_proofs/69271c0114c93_1764170753.png', 'pending_approval', NULL, '2025-11-26 15:25:53', 0, NULL, NULL),
(25, NULL, 'John Mbewe', 'john@gmai.com', 26, 2, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1761912785_grade12results_IT-DETAILS (2)-1 (1).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (1).pdf\",\"path\":\"uploads\\/1761912785_previousschool_IT-DETAILS (2)-1 (1).pdf\"},\"recommended_by\":\"none\"}', '2025-11-27 08:17:37', NULL, NULL, NULL, NULL, 'pending_approval', NULL, '2025-11-27 08:17:37', 0, NULL, NULL),
(26, NULL, 'long courses', 'sianamatesamuel@gmail.com', 12, 1, '{\"0\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1763367295_grade12results_IT-DETAILS (2)-1 (2).pdf\"},\"1\":{\"name\":\"IT-DETAILS (2)-1 (2).pdf\",\"path\":\"uploads\\/1763367295_previousschool_IT-DETAILS (2)-1 (2).pdf\"},\"recommended_by\":\"Jones\"}', '2025-11-27 08:19:56', NULL, NULL, NULL, NULL, 'pending_approval', NULL, '2025-11-27 08:19:56', 0, NULL, NULL),
(27, NULL, 'sam smith', 'smithamosiana@gmail.com', 28, 1, '{\"0\":{\"name\":\"Screenshot (188).png\",\"path\":\"uploads\\/1764232014_grade12results_Screenshot (188).png\"},\"1\":{\"name\":\"Screenshot (188).png\",\"path\":\"uploads\\/1764232014_previousschool_Screenshot (188).png\"},\"recommended_by\":\"Jones\"}', '2025-11-27 08:27:22', NULL, NULL, NULL, NULL, 'pending_approval', NULL, '2025-11-27 08:27:22', 0, NULL, NULL),
(28, NULL, 'sam smith', 'smithamosiana@gmail.com', 28, 1, NULL, '2025-11-27 08:44:58', 'mobile_money', 2000.00, 'none', 'uploads/payment_proofs/69280f8a3abd8_1764233098.png', 'approved', NULL, '2025-11-27 08:45:55', 0, NULL, NULL),
(29, 'LSC2025000029', 'Husted Chola', 'hustedchola114@gmail.com', 6, 1, NULL, '2025-11-27 10:41:44', 'bank_transfer', 1500.00, '0000', 'uploads/payment_proofs/69282ae82b2a9_1764240104.png', 'approved', NULL, '2025-11-27 10:43:22', 0, NULL, NULL),
(30, NULL, 'Husted Chola', 'hustedchola114@gmail.com', 6, 1, NULL, '2025-11-27 13:08:53', 'bank_transfer', 1500.00, '0000', 'uploads/payment_proofs/69284d6551afa_1764248933.png', 'pending_approval', NULL, '2025-11-27 13:08:53', 0, NULL, NULL),
(31, 'LSC2025000031', 'Whiteson Chilambwe', 'chilambwewhiteson@gmail.com', 19, 1, NULL, '2025-11-27 14:48:57', 'mobile_money', 1800.00, '0000', 'uploads/payment_proofs/692864d913ab7_1764254937.png', 'approved', NULL, '2025-12-01 14:59:00', 1, '2025-12-01 14:59:00', 3),
(32, 'LSC2025000032', 'peter chikubula', 'peterchikubula@lsuczm.com', 15, 1, NULL, '2025-11-27 15:26:48', 'mobile_money', 2000.00, '0000', 'uploads/payment_proofs/69286db856ab6_1764257208.png', 'approved', NULL, '2025-11-27 15:32:52', 0, NULL, NULL),
(33, 'LSC2025000033', 'Mildred Chisenga', 'mildredchisenga43@gmail.com', 21, 1, NULL, '2025-11-28 13:58:53', 'mobile_money', 2000.00, '0000', 'uploads/payment_proofs/6929aa9d30df3_1764338333.png', 'approved', NULL, '2025-11-28 14:01:09', 0, NULL, NULL);

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
(5, 'Certificate in Electrical Engineering', NULL, '1', NULL, 4, 1, 'Bachelor', 120, 1, '2025-10-30 13:52:40', '2025-10-30 13:58:34', 1, 'undergraduate'),
(6, 'Certificate in Solar Technology', NULL, '2', NULL, 4, 1, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 0, 'undergraduate'),
(7, 'Certificate Civil Engineering & Construction', NULL, '3', NULL, 4, 1, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 0, 'undergraduate'),
(8, 'Certificate in Automotive Engineering', NULL, '4', NULL, 4, 1, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 1, 'undergraduate'),
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
(29, 'Certificate in Computer Studies (ICT’S)', NULL, '25', NULL, 4, 6, 'Bachelor', 120, 1, '2025-10-30 13:58:34', '2025-10-30 13:58:34', 0, 'undergraduate'),
(32, 'Web Development Fundamentals', NULL, 'WD-FUND', NULL, 4, 1, 'Bachelor', 120, 1, '2025-11-17 13:09:16', '2025-11-17 13:09:16', 1, 'short_course'),
(33, 'Bachelor of Science in Computer Science', NULL, 'BSC-CS', NULL, 4, 1, 'Bachelor', 120, 1, '2025-11-17 13:09:16', '2025-11-17 13:09:16', 4, 'undergraduate'),
(34, 'Solar Design and Installation 8th December 2025', NULL, '001', 'CPD course', 4, 1, 'Bachelor', 120, 1, '2025-11-17 13:15:46', '2025-11-17 13:15:46', 1, 'short_course');

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
(1, 0, 'Whiteson Chilambwe', 'chilambwewhiteson@gmail.com', 'LSC2025000001', '', '', 1800.00, 'first_time', 'email_sent', '2025-12-19 13:50:01', '2025-12-01 14:59:00', '2025-12-19 13:50:01');

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
(3, 2, 78.00, 0.00, NULL, NULL, 2, '2025-12-23 07:03:03');

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

--
-- Dumping data for table `result_publishing`
--

INSERT INTO `result_publishing` (`id`, `academic_year`, `semester`, `programme_id`, `course_id`, `publish_date`, `deadline_date`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2024/2025', 'Semester 1', 10, 4, '2025-12-24', '2025-12-25', '', 'scheduled', 42, '2025-12-23 07:52:09', '2025-12-23 07:52:09');

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
(1, 2),
(1, 3),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(1, 22),
(2, 14),
(2, 27),
(3, 4),
(3, 20),
(3, 21),
(4, 27),
(6, 2),
(6, 3),
(6, 14),
(6, 23),
(6, 24),
(6, 25),
(6, 26),
(6, 27),
(7, 2),
(7, 26),
(7, 27),
(11, 1),
(11, 12),
(11, 14),
(11, 16),
(11, 17),
(11, 18),
(11, 23),
(11, 25),
(11, 26),
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
(41, 'Samuel Sianamate', 'STF-2025-0041', '00000', 'Male', 'Degree', NULL);

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

--
-- Dumping data for table `student_fees`
--

INSERT INTO `student_fees` (`id`, `student_id`, `fee_type`, `amount_due`, `amount_paid`, `due_date`, `academic_year`, `semester`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Tuition Fee', 3000.00, 1500.00, '2024-03-31', '2024', 'Semester 1', 'partial', '2025-10-10 14:51:24', '2025-10-10 14:51:24'),
(2, 2, 'Tuition Fee', 3000.00, 2000.00, '2024-03-31', '2024', 'Semester 1', 'partial', '2025-10-10 14:51:24', '2025-10-10 14:51:24'),
(3, 3, 'Library Fee', 200.00, 0.00, '2024-02-28', '2024', 'Semester 1', 'pending', '2025-10-10 14:51:24', '2025-10-10 14:51:24'),
(4, 4, 'Laboratory Fee', 500.00, 500.00, '2024-01-31', '2024', 'Semester 1', 'paid', '2025-10-10 14:51:24', '2025-10-10 14:51:24');

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
(40, 'Mildred Chisenga', 'LSC2025000033', '', '', 21, 0, NULL, 0.00, 1, 0, NULL);

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
(4, 'maintenance_mode', '1', '2025-12-17 09:18:22', '2025-12-24 10:24:05'),
(65, 'maintenance_end_time', '2025-12-26 23:59', '2025-12-24 10:43:45', '2025-12-24 10:43:45');

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
(43, 'hrmanager', '$2y$10$2WeBKvDEp5TbTRf/uDALme0zWHzodUgFTDU2Wpb8j/.1p6HlsmWlW', 'hr@lsc.edu', 'HR Manager', '2025-12-23 10:01:28', 1);

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
(2, 2),
(3, 3),
(4, 4),
(9, 1),
(10, 2),
(12, 4),
(20, 9),
(22, 9),
(23, 9),
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
(41, 2),
(42, 11),
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `course`
--
ALTER TABLE `course`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `course_assignment`
--
ALTER TABLE `course_assignment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `course_enrollment`
--
ALTER TABLE `course_enrollment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `programme`
--
ALTER TABLE `programme`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `programme_fees`
--
ALTER TABLE `programme_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `registered_students`
--
ALTER TABLE `registered_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

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
