-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 29, 2025 at 09:12 AM
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
(9, 'Jane Admin', 'ADM002', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `programme_id` int(11) DEFAULT NULL,
  `intake_id` int(11) DEFAULT NULL,
  `documents` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `full_name`, `email`, `programme_id`, `intake_id`, `documents`, `status`, `rejection_reason`, `created_at`) VALUES
(1, 'John Sample Student', 'john.sample@lsc.ac.zm', 1, 4, '[{\"name\":\"Transcript.pdf\",\"path\":\"\\/uploads\\/transcript.pdf\"},{\"name\":\"ID_Copy.pdf\",\"path\":\"\\/uploads\\/id_copy.pdf\"}]', 'approved', NULL, '2025-10-13 11:07:59'),
(2, 'sam smith', 'sianamtesamuel@gmail.com', NULL, NULL, '[{\"name\":\"intakes_2025-10-17.csv\",\"path\":\"uploads\\/1760967252_grade12results_intakes_2025-10-17.csv\"},{\"name\":\"student_docket_LSC000001.pdf\",\"path\":\"uploads\\/1760967252_previousschool_student_docket_LSC000001.pdf\"}]', 'approved', NULL, '2025-10-20 13:34:12'),
(3, 'Test Sianamate', 'sianamatesamuel@gmail.com', NULL, NULL, '{\"0\":{\"name\":\"student_docket_LSC000001.pdf\",\"path\":\"uploads\\/1760969019_grade12results_student_docket_LSC000001.pdf\"},\"1\":{\"name\":\"student_docket_LSC000001.pdf\",\"path\":\"uploads\\/1760969019_previousschool_student_docket_LSC000001.pdf\"},\"recommended_by\":\"none\"}', 'approved', NULL, '2025-10-20 14:03:39'),
(4, 'sam smith', 'sianamtesamuel@gmail.com', NULL, NULL, '{\"occupation\":\"\",\"schedule\":\"weekdays\",\"experience\":\"\",\"goals\":\"agent\"}', 'approved', NULL, '2025-10-27 11:04:35'),
(5, 'Tes1 Test2', 'test@mail.com', 3, NULL, '{\"0\":{\"name\":\"student_docket_LSC000001 (1).pdf\",\"path\":\"uploads\\/1761572818_grade12results_student_docket_LSC000001 (1).pdf\"},\"1\":{\"name\":\"student_docket_LSC000001 (1).pdf\",\"path\":\"uploads\\/1761572818_previousschool_student_docket_LSC000001 (1).pdf\"},\"recommended_by\":\"none\"}', 'approved', NULL, '2025-10-27 13:46:58'),
(6, 'john Doe', 'doe@example.com', 2, NULL, '{\"0\":{\"name\":\"intakes_2025-10-17.csv\",\"path\":\"uploads\\/1761574219_grade12results_intakes_2025-10-17.csv\"},\"1\":{\"name\":\"intakes_2025-10-17.csv\",\"path\":\"uploads\\/1761574219_previousschool_intakes_2025-10-17.csv\"},\"recommended_by\":\"none\"}', 'pending', NULL, '2025-10-27 14:10:19');

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
(1, 'DBA1010', 'Business and Company Law', 5, 1, NULL, 'Company Law in Business', '2025-10-10 12:25:17', '2025-10-10 12:25:17'),
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
(3, 4, 2, '2024', '1', '2025-10-10 21:00:09', 1);

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
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
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
(3, 'Computer Science', 1, NULL, NULL, NULL, '2025-10-10 21:00:09', '2025-10-10 21:00:09'),
(4, 'Business Administration', 2, NULL, NULL, NULL, '2025-10-10 21:00:09', '2025-10-10 21:00:09');

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
-- Table structure for table `finance_transactions`
--

CREATE TABLE `finance_transactions` (
  `id` int(11) NOT NULL,
  `student_user_id` int(11) DEFAULT NULL,
  `type` enum('income','expense') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `course_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(3, 3, 750.00, '2024-02-01', 'Mobile Money', 'MM003', 'paid', 'Partial Payment', '2025-10-10 14:51:23', '2025-10-10 14:51:23');

-- --------------------------------------------------------

--
-- Table structure for table `pending_students`
--

CREATE TABLE `pending_students` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `programme_id` int(11) DEFAULT NULL,
  `intake_id` int(11) DEFAULT NULL,
  `documents` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pending_students`
--

INSERT INTO `pending_students` (`id`, `full_name`, `email`, `programme_id`, `intake_id`, `documents`, `created_at`) VALUES
(3, 'sam smith', 'sianamtesamuel@gmail.com', NULL, NULL, '[{\"name\":\"intakes_2025-10-17.csv\",\"path\":\"uploads\\/1760967252_grade12results_intakes_2025-10-17.csv\"},{\"name\":\"student_docket_LSC000001.pdf\",\"path\":\"uploads\\/1760967252_previousschool_student_docket_LSC000001.pdf\"}]', '2025-10-27 14:13:39'),
(4, 'John Sample Student', 'john.sample@lsc.ac.zm', 1, 4, '[{\"name\":\"Transcript.pdf\",\"path\":\"\\/uploads\\/transcript.pdf\"},{\"name\":\"ID_Copy.pdf\",\"path\":\"\\/uploads\\/id_copy.pdf\"}]', '2025-10-27 14:15:31');

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
(27, 'profile_access', 'View and edit personal profile', '2025-10-16 10:51:28');

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
  `duration` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `programme`
--

INSERT INTO `programme` (`id`, `name`, `department_id`, `code`, `description`, `duration_years`, `school_id`, `qualification_level`, `credits_required`, `is_active`, `created_at`, `updated_at`, `duration`) VALUES
(1, 'Diploma in Business Adminstration', NULL, 'DBA', 'Diploma in Business srudies', 4, 2, 'Bachelor', 120, 1, '2025-10-10 12:16:10', '2025-10-10 12:16:10', 3),
(2, 'Bachelor of Information Technology', NULL, '001', '', 4, 3, 'Bachelor', 120, 1, '2025-10-10 21:00:08', '2025-10-17 08:00:31', 1),
(3, 'Diploma in Computer Studies', NULL, NULL, NULL, 4, NULL, 'Bachelor', 120, 1, '2025-10-10 21:00:08', '2025-10-10 21:00:08', NULL),
(4, 'Certificate in Office Administration', NULL, NULL, NULL, 4, NULL, 'Bachelor', 120, 1, '2025-10-10 21:00:08', '2025-10-10 21:00:08', NULL);

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
  `uploaded_by_user_id` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(9, 'Enrollment Officer', 'Handles student enrollment applications', '2025-10-20 13:07:41');

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
(7, 27);

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
(1, 'Engineering', 'A dynamic center of innovation where cutting-edge technology education meets real-world application.', 2025, '', '', '', '2025-10-08 20:58:49'),
(2, 'Business', '', 0, '', '', '', '2025-10-08 20:59:50'),
(3, 'School of Computing', NULL, NULL, NULL, NULL, NULL, '2025-10-10 21:00:08'),
(4, 'School of Business', NULL, NULL, NULL, NULL, NULL, '2025-10-10 21:00:08'),
(5, 'School of Education', NULL, NULL, NULL, NULL, NULL, '2025-10-10 21:00:08');

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
(11, 'Mary Finance', 'FIN002', '345678901', 'Female', 'B.Com Accounting', NULL),
(20, 'Enrollment Officer', 'ENR001', '123456/78/90', 'Male', 'Bachelor of Education', NULL);

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
  `results_access` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_profile`
--

INSERT INTO `student_profile` (`user_id`, `full_name`, `student_number`, `NRC`, `gender`, `programme_id`, `school_id`, `department_id`, `balance`, `intake_id`, `results_access`) VALUES
(4, 'Alice Student', 'LSC000001', '11223344', 'Female', NULL, NULL, NULL, 500.00, NULL, 1),
(12, 'Bob Student', 'LSC000002', '456789012', 'Male', 1, 1, 1, 0.00, NULL, 1),
(21, 'Test Sianamate', 'LSC000021', NULL, NULL, NULL, NULL, NULL, 0.00, NULL, 1);

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
(1, 'admin@lsc.ac.zm', '$2y$10$LzvINFwdQN9huLMGKYnuAe1ub.Crl3D7yoUIbcJRb8I16ouoLFJ.K', 'admin@lsc.ac.zm', '0971234567', '2025-10-06 11:09:34', 1),
(2, 'lecturer1@lsc.ac.zm', '$2y$10$Kf7EbT94C3mb0Bkw1ZD/Geal5GgLUKjHU16joazIAIfIocxvT0G5O', 'lecturer1@lsc.ac.zm', '0972345678', '2025-10-06 11:09:35', 1),
(3, 'finance@lsc.ac.zm', '$2y$10$U/daYeAschl5qRBmAn5lMexaAGCr0MpkrWXixqUQUCGx6Y0BBZSG2', 'finance@lsc.ac.zm', '0973456789', '2025-10-06 11:09:35', 1),
(4, 'LSC000001', '$2y$10$yZjxek.Un7ofZLHv.DcpGOFKUC8PCcei2uvnsq8T.XL7OLsND2IWG', 'student1@lsc.ac.zm', '0974567890', '2025-10-06 11:09:35', 1),
(9, 'admin.jane@lsc.ac.zm', '$2y$10$L7zT0FGI5QRKQKEFxyxET.v9uundepd.ycO3jJ98Dhj0pb8LMKbfi', 'admin.jane@lsc.ac.zm', '0977123456', '2025-10-08 11:20:02', 1),
(10, 'lecturer.john@lsc.ac.zm', '$2y$10$KpIQ4TtTpgw/RFLYqvIIuuOkDZIgxBEK3NBbyT1t7eDwKNinBBta2', 'lecturer.john@lsc.ac.zm', '0976234567', '2025-10-08 11:20:02', 1),
(11, 'finance.mary@lsc.ac.zm', '$2y$10$dwNr9pcmjscG71fH1FenbOcsGlHneIvmIUAOgy66nnfomTSL6sOVq', 'finance.mary@lsc.ac.zm', '0975345678', '2025-10-08 11:20:03', 1),
(12, 'LSC000002', '$2y$10$tHP5fDRunGCQVUuv4nYaLOkzovMU9kD5K5.a2EbPO13eSzmM16JSW', 'student.bob@lsc.ac.zm', '0974456789', '2025-10-08 11:20:03', 1),
(19, 'Winnie', '$2y$10$8O9v21k9USkqSmhew4vQz.ozDZH9Y2l3bOfzoFa3gO1UUvq3o6nnG', 'winnie@example.com', '', '2025-10-20 12:41:42', 1),
(20, 'enrollment@lsc.ac.zm', '$2y$10$nYyhkWcYqiY3AYpIe8.VmOLzDb5VMKDBOiI/LEZOmMcZg4MHH9yhm', 'enrollment@lsc.ac.zm', NULL, '2025-10-20 13:12:38', 1),
(21, 'sianamatesamuel@gmail.com', '$2y$10$0hOPTW9smAYZXvmVGB5ypu8KSYbIEFnVpz7Xw83otOpcxsSwayRn6', 'sianamatesamuel@gmail.com', '', '2025-10-27 12:52:15', 1);

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
(11, 3),
(12, 4),
(19, 8),
(20, 9),
(21, 4);

--
-- Indexes for dumped tables
--

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
  ADD KEY `intake_id` (`intake_id`);

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
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`);

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
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `lecturer_courses`
--
ALTER TABLE `lecturer_courses`
  ADD PRIMARY KEY (`lecturer_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enrollment_id` (`enrollment_id`),
  ADD KEY `uploaded_by_user_id` (`uploaded_by_user_id`);

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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

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
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `course`
--
ALTER TABLE `course`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `course_assignment`
--
ALTER TABLE `course_assignment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `course_enrollment`
--
ALTER TABLE `course_enrollment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `finance_transactions`
--
ALTER TABLE `finance_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pending_students`
--
ALTER TABLE `pending_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `programme`
--
ALTER TABLE `programme`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `result_type`
--
ALTER TABLE `result_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `school`
--
ALTER TABLE `school`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Constraints for dumped tables
--

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
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`intake_id`) REFERENCES `intake` (`id`) ON DELETE SET NULL;

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
  ADD CONSTRAINT `course_registration_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `course` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `department`
--
ALTER TABLE `department`
  ADD CONSTRAINT `department_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `finance_transactions`
--
ALTER TABLE `finance_transactions`
  ADD CONSTRAINT `finance_transactions_ibfk_1` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `intake_courses`
--
ALTER TABLE `intake_courses`
  ADD CONSTRAINT `intake_courses_ibfk_1` FOREIGN KEY (`intake_id`) REFERENCES `intake` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `intake_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `course` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lecturer_courses`
--
ALTER TABLE `lecturer_courses`
  ADD CONSTRAINT `lecturer_courses_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `lecturer_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `course` (`id`);

--
-- Constraints for table `programme`
--
ALTER TABLE `programme`
  ADD CONSTRAINT `FK_programme_department_upd` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_programme_school` FOREIGN KEY (`school_id`) REFERENCES `school` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `results`
--
ALTER TABLE `results`
  ADD CONSTRAINT `results_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `course_enrollment` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ibfk_2` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
