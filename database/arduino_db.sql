-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 07, 2026 at 08:20 AM
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
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `author` varchar(100) NOT NULL,
  `genre_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `genre_id`) VALUES
(1, 'Noli Me Tangere', 'Jose Rizal', 1),
(2, 'El Filibusterismo', 'Jose Rizal', 1),
(3, 'Pride and Prejudice', 'Jane Austen', 1),
(4, 'A Brief History of Time', 'Stephen Hawking', 2),
(5, 'Cosmos', 'Carl Sagan', 2),
(6, 'The Origin of Species', 'Charles Darwin', 2),
(7, 'Sapiens', 'Yuval Noah Harari', 3),
(8, 'Guns, Germs, and Steel', 'Jared Diamond', 3),
(9, 'The Histories', 'Herodotus', 3);

-- --------------------------------------------------------

--
-- Table structure for table `book_loans`
--

CREATE TABLE `book_loans` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `book_title` varchar(200) NOT NULL,
  `borrowed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `due_date` date NOT NULL,
  `returned_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `genres`
--

CREATE TABLE `genres` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `shelf_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `genres`
--

INSERT INTO `genres` (`id`, `name`, `shelf_name`) VALUES
(1, 'Fiction', 'Shelf_Fiction'),
(2, 'Science', 'Shelf_Science'),
(3, 'History', 'Shelf_History');

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
(94, '676767', '2026-10-25 09:00:12'),
(95, '676767', '2026-10-25 09:00:14'),
(96, '676767', '2026-10-25 09:00:18'),
(97, '676767', '2026-10-25 09:00:21'),
(98, '676767', '2026-10-25 09:00:38'),
(99, '676767', '2026-10-25 09:00:42'),
(100, '676767', '2026-10-25 09:02:49'),
(101, '676767', '2026-10-25 09:02:58'),
(102, '676767', '2026-10-25 09:03:03'),
(103, '676767', '2026-10-25 09:03:08'),
(104, '676767', '2026-10-25 09:03:14'),
(105, '676767', '2026-10-25 09:03:19'),
(106, '676767', '2026-10-25 09:03:24'),
(107, '676767', '2026-10-25 09:03:29'),
(108, '676767', '2026-10-25 09:03:58'),
(109, '123456', '2026-10-25 09:04:28'),
(110, '123456', '2026-10-25 09:04:36'),
(111, '123456', '2026-10-25 09:05:34'),
(112, '123456', '2026-10-25 09:05:37'),
(113, '123456', '2026-10-25 09:05:44'),
(114, '123456', '2026-10-25 09:05:57'),
(115, '676767', '2026-10-25 09:06:08'),
(116, '123456', '2026-10-25 09:06:26'),
(117, '010203', '2026-10-25 09:07:01'),
(118, '020202', '2026-10-25 09:09:30'),
(119, '020202', '2026-11-03 02:07:02'),
(120, '020202', '2026-11-03 02:07:16'),
(121, '020202', '2026-11-03 02:08:11'),
(122, '020202', '2026-11-03 02:13:45'),
(123, '202410139', '2026-11-03 02:14:33'),
(124, '010203', '2026-11-03 02:14:50'),
(125, '676767', '2026-11-03 02:14:52'),
(126, '123456', '2026-11-03 02:14:54');

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
(23, '676767', '2026-10-25 09:00:13'),
(24, '676767', '2026-10-25 09:00:16'),
(25, '676767', '2026-10-25 09:00:19'),
(26, '676767', '2026-10-25 09:00:23'),
(27, '676767', '2026-10-25 09:00:40'),
(28, '676767', '2026-10-25 09:02:46'),
(29, '676767', '2026-10-25 09:02:55'),
(30, '676767', '2026-10-25 09:03:00'),
(31, '676767', '2026-10-25 09:03:06'),
(32, '676767', '2026-10-25 09:03:12'),
(33, '676767', '2026-10-25 09:03:15'),
(34, '676767', '2026-10-25 09:03:22'),
(35, '676767', '2026-10-25 09:03:27'),
(36, '676767', '2026-10-25 09:03:30'),
(37, '676767', '2026-10-25 09:03:59'),
(38, '123456', '2026-10-25 09:04:32'),
(39, '123456', '2026-10-25 09:04:43'),
(40, '123456', '2026-10-25 09:05:36'),
(41, '123456', '2026-10-25 09:05:39'),
(42, '123456', '2026-10-25 09:05:45'),
(43, '123456', '2026-10-25 09:05:58'),
(44, '676767', '2026-10-25 09:06:09'),
(45, '010203', '2026-10-25 09:07:10'),
(46, '020202', '2026-10-25 09:09:35'),
(47, '020202', '2026-11-03 02:07:06'),
(48, '020202', '2026-11-03 02:08:09'),
(49, '020202', '2026-11-03 02:08:13'),
(50, '020202', '2026-11-03 02:13:51'),
(51, '202410139', '2026-11-03 02:14:37');

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
(223, '25351106', '2026-11-03 02:14:57');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `rfid_uid` varchar(20) NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `name`, `rfid_uid`, `registered_at`) VALUES
(22, '676767', 'HATDOG FOOTLONG', '1572D406', '2026-10-25 08:53:45'),
(23, '123456', 'SPONGEBOB SQUAREPANTS', 'CDB52F8E', '2026-10-25 09:04:24'),
(24, '010203', 'ELIZABETH A. FRANCISCO', '79CCD106', '2026-10-25 09:06:54'),
(25, '020202', 'Dutch', '2ADBF855', '2026-10-25 09:09:23'),
(26, '202410139', 'FRANCISCO, NICOLE KYLIE A.', 'B4321106', '2026-11-03 02:14:26');

-- --------------------------------------------------------

--
-- Table structure for table `student_status`
--

CREATE TABLE `student_status` (
  `student_id` varchar(20) NOT NULL,
  `status` enum('in','out') NOT NULL DEFAULT 'out',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `genre_id` (`genre_id`);

--
-- Indexes for table `book_loans`
--
ALTER TABLE `book_loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `genres`
--
ALTER TABLE `genres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `shelf_name` (`shelf_name`);

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
  ADD UNIQUE KEY `rfid_uid` (`rfid_uid`);

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
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `book_loans`
--
ALTER TABLE `book_loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `genres`
--
ALTER TABLE `genres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `library_in`
--
ALTER TABLE `library_in`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `library_out`
--
ALTER TABLE `library_out`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `rfid_logs`
--
ALTER TABLE `rfid_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=224;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `books`
--
ALTER TABLE `books`
  ADD CONSTRAINT `books_ibfk_1` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`);

--
-- Constraints for table `book_loans`
--
ALTER TABLE `book_loans`
  ADD CONSTRAINT `book_loans_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
