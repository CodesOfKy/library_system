-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 27, 2026 at 09:25 AM
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
-- Database: `arduino_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `email`, `password`) VALUES
(1, 'admin@gmail.com', '$2y$10$Xb/IcQHmPC1u8/FWMBxcj.nCtOHjL1665OdmvbvbH52GA.QtlODtG');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `admin_email` varchar(100) NOT NULL,
  `action` varchar(30) NOT NULL,
  `details` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `admin_email`, `action`, `details`, `created_at`) VALUES
(1, 'admin@gmail.com', 'Admin login', 'Logged in from IP ::1', '2026-11-27 16:15:14'),
(2, 'admin@gmail.com', 'Re-validation', '123456 requested: Change of grade / year level', '2026-11-27 16:15:28'),
(3, 'admin@gmail.com', 'Re-validation', '123456 confirmed, card scanned; grade: (blank) to Grade 11; course: (blank) to GAS; section: (blank) to A', '2026-11-27 16:15:50'),
(4, 'admin@gmail.com', 'Re-validation', '202410139 requested: Change of name', '2026-11-27 16:17:42'),
(5, 'admin@gmail.com', 'Re-validation', '202410139 confirmed, card scanned; name: NICOLE KYLIE A. FRANCISCO to NICOLE KYLIE ALVAREZ FRANCISCO; grade: (blank) to Grade 11; course: (blank) to GAS; section: (blank) to A', '2026-11-27 16:18:12');

-- --------------------------------------------------------

--
-- Table structure for table `library_in`
--

CREATE TABLE `library_in` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_in`
--

INSERT INTO `library_in` (`id`, `student_id`, `login_time`) VALUES
(109, '123456', '2026-10-25 09:04:28'),
(110, '123456', '2026-10-25 09:04:36'),
(111, '123456', '2026-10-25 09:05:34'),
(112, '123456', '2026-10-25 09:05:37'),
(113, '123456', '2026-10-25 09:05:44'),
(114, '123456', '2026-10-25 09:05:57'),
(116, '123456', '2026-10-25 09:06:26'),
(123, '202410139', '2026-11-03 02:14:33'),
(126, '123456', '2026-11-03 02:14:54'),
(127, '123465', '2026-11-07 08:17:51'),
(130, '123456', '2026-11-27 08:14:17'),
(131, '020103', '2026-11-27 08:15:05');

-- --------------------------------------------------------

--
-- Table structure for table `library_out`
--

CREATE TABLE `library_out` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `logout_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_out`
--

INSERT INTO `library_out` (`id`, `student_id`, `logout_time`) VALUES
(38, '123456', '2026-10-25 09:04:32'),
(39, '123456', '2026-10-25 09:04:43'),
(40, '123456', '2026-10-25 09:05:36'),
(41, '123456', '2026-10-25 09:05:39'),
(42, '123456', '2026-10-25 09:05:45'),
(43, '123456', '2026-10-25 09:05:58'),
(51, '202410139', '2026-11-03 02:14:37');

-- --------------------------------------------------------

--
-- Table structure for table `options`
--

CREATE TABLE `options` (
  `id` int(11) NOT NULL,
  `kind` enum('grade','course') NOT NULL,
  `level` enum('SHS','College') NOT NULL,
  `name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `options`
--

INSERT INTO `options` (`id`, `kind`, `level`, `name`) VALUES
(1, 'grade', 'SHS', 'Grade 11'),
(2, 'grade', 'SHS', 'Grade 12'),
(3, 'grade', 'College', '1st Year'),
(4, 'grade', 'College', '2nd Year'),
(5, 'grade', 'College', '3rd Year'),
(6, 'grade', 'College', '4th Year'),
(7, 'course', 'SHS', 'GAS'),
(9, 'course', 'SHS', 'HUMSS'),
(8, 'course', 'SHS', 'ICT'),
(10, 'course', 'College', 'Entrep');

-- --------------------------------------------------------

--
-- Table structure for table `revalidations`
--

CREATE TABLE `revalidations` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `status` enum('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `docs_checked` varchar(255) NOT NULL DEFAULT '',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `revalidations`
--

INSERT INTO `revalidations` (`id`, `student_id`, `reason`, `status`, `docs_checked`, `requested_at`, `confirmed_at`) VALUES
(1, '123456', 'Change of grade / year level', 'confirmed', 'student_id,reg_form', '2026-11-27 16:15:28', '2026-11-27 16:15:50'),
(2, '202410139', 'Change of name', 'confirmed', 'student_id,reg_form', '2026-11-27 16:17:42', '2026-11-27 16:18:12');

-- --------------------------------------------------------

--
-- Table structure for table `rfid_logs`
--

CREATE TABLE `rfid_logs` (
  `id` int(11) NOT NULL,
  `uid` varchar(20) NOT NULL,
  `scanned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rfid_logs`
--

INSERT INTO `rfid_logs` (`id`, `uid`, `scanned_at`) VALUES
(282, 'CDB52F8E', '2026-11-27 08:15:56'),
(283, '79CCD106', '2026-11-27 08:18:00'),
(284, '1572D406', '2026-11-27 08:18:02'),
(285, 'CDB52F8E', '2026-11-27 08:18:03'),
(286, 'B4321106', '2026-11-27 08:18:10');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL DEFAULT '',
  `middle_name` varchar(50) NOT NULL DEFAULT '',
  `last_name` varchar(50) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL,
  `rfid_uid` varchar(20) NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `level` varchar(10) NOT NULL DEFAULT 'SHS',
  `grade` varchar(20) DEFAULT NULL,
  `course` varchar(30) DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `first_name`, `middle_name`, `last_name`, `name`, `rfid_uid`, `registered_at`, `level`, `grade`, `course`, `section`) VALUES
(23, '123456', 'SPONGEBOB', '', 'SQUAREPANTS', 'SPONGEBOB SQUAREPANTS', 'CDB52F8E', '2026-10-25 09:04:24', 'SHS', 'Grade 11', 'GAS', 'A'),
(26, '202410139', 'NICOLE KYLIE', 'ALVAREZ', 'FRANCISCO', 'NICOLE KYLIE ALVAREZ FRANCISCO', 'B4321106', '2026-11-03 02:14:26', 'SHS', 'Grade 11', 'GAS', 'A'),
(27, '123465', 'Nebula', 'Sirius', 'Cosmic', 'Nebula Sirius Cosmic', '79CCD106', '2026-11-07 08:17:46', 'SHS', NULL, NULL, NULL),
(28, '020103', 'Skadi', 'Skadidi', 'Skadoosh', 'Skadi Skadidi Skadoosh', '25351106', '2026-11-27 08:15:02', 'College', '1st Year', 'Entrep', 'as');

-- --------------------------------------------------------

--
-- Table structure for table `student_status`
--

CREATE TABLE `student_status` (
  `student_id` varchar(20) NOT NULL,
  `status` enum('in','out') NOT NULL DEFAULT 'out',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_status`
--

INSERT INTO `student_status` (`student_id`, `status`, `updated_at`) VALUES
('020103', 'in', '2026-11-27 08:15:05'),
('123456', 'in', '2026-11-27 08:14:17');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `rfid_uid` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `name`, `rfid_uid`, `created_at`) VALUES
(1, 'Chuhay Quilao', '25351106', '2026-11-07 07:34:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `library_in`
--
ALTER TABLE `library_in`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `library_out`
--
ALTER TABLE `library_out`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `options`
--
ALTER TABLE `options`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kind` (`kind`,`level`,`name`);

--
-- Indexes for table `revalidations`
--
ALTER TABLE `revalidations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rfid_logs`
--
ALTER TABLE `rfid_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `rfid_uid` (`rfid_uid`),
  ADD UNIQUE KEY `full_name` (`first_name`,`last_name`);

--
-- Indexes for table `student_status`
--
ALTER TABLE `student_status`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rfid_uid` (`rfid_uid`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `library_in`
--
ALTER TABLE `library_in`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=132;

--
-- AUTO_INCREMENT for table `library_out`
--
ALTER TABLE `library_out`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `options`
--
ALTER TABLE `options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `revalidations`
--
ALTER TABLE `revalidations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `rfid_logs`
--
ALTER TABLE `rfid_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=287;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
