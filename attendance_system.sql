-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 17, 2026 at 01:56 PM
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
-- Database: `attendance_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `status` enum('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `scan_type` varchar(50) NOT NULL DEFAULT 'present',
  `date` date NOT NULL,
  `time` time NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `course_id`, `section_id`, `subject_id`, `status`, `scan_type`, `date`, `time`, `created_at`) VALUES
(19, 1, 1, 1, 4, 'present', 'present', '2026-07-13', '06:58:00', '2026-08-17 14:28:30'),
(20, 1, 1, 1, 5, 'late', 'late', '2026-07-15', '08:55:00', '2026-08-17 14:28:30'),
(21, 1, 1, 1, 4, 'present', 'present', '2026-07-20', '06:59:00', '2026-08-17 14:28:30'),
(22, 1, 1, 1, 6, 'absent', 'absent', '2026-07-22', '10:30:00', '2026-08-17 14:28:30'),
(23, 1, 1, 1, 4, 'present', 'present', '2026-07-27', '06:57:00', '2026-08-17 14:28:30'),
(24, 1, 1, 1, 7, 'present', 'present', '2026-07-29', '12:58:00', '2026-08-17 14:28:30'),
(25, 1, 1, 1, 5, 'present', 'present', '2026-08-03', '08:44:00', '2026-08-17 14:28:30'),
(26, 1, 1, 1, 8, 'late', 'late', '2026-08-05', '15:02:00', '2026-08-17 14:28:30'),
(27, 1, 1, 1, 4, 'present', 'present', '2026-08-10', '06:58:00', '2026-08-17 14:28:30'),
(28, 1, 1, 1, 9, 'present', 'present', '2026-08-12', '16:29:00', '2026-08-17 14:28:30'),
(29, 1, 1, 1, 6, 'present', 'present', '2026-08-15', '10:29:00', '2026-08-17 14:28:30'),
(30, 1, 1, 1, 5, 'late', 'late', '2026-08-16', '08:52:00', '2026-08-17 14:28:30'),
(31, 1, NULL, 1, 10, 'present', 'present', '2026-08-10', '09:02:00', '2026-08-17 15:59:43'),
(32, 1, NULL, 1, 10, 'present', 'present', '2026-08-03', '08:58:00', '2026-08-17 15:59:43'),
(33, 1, NULL, 1, 10, 'late', 'late', '2026-07-27', '09:12:00', '2026-08-17 15:59:43'),
(34, 1, NULL, 1, 10, 'present', 'present', '2026-07-20', '09:00:00', '2026-08-17 15:59:43'),
(35, 2, NULL, 2, 11, 'present', 'present', '2026-08-12', '13:01:00', '2026-08-17 15:59:43'),
(36, 2, NULL, 2, 11, 'late', 'late', '2026-08-05', '13:10:00', '2026-08-17 15:59:43'),
(37, 2, NULL, 2, 11, 'present', 'present', '2026-07-29', '12:59:00', '2026-08-17 15:59:43'),
(38, 2, NULL, 2, 11, 'absent', 'absent', '2026-07-22', '14:20:00', '2026-08-17 15:59:43'),
(39, 3, NULL, 3, 12, 'present', 'present', '2026-08-14', '10:03:00', '2026-08-17 15:59:43'),
(40, 3, NULL, 3, 12, 'present', 'present', '2026-08-07', '09:58:00', '2026-08-17 15:59:43'),
(41, 3, NULL, 3, 12, 'present', 'present', '2026-07-31', '10:01:00', '2026-08-17 15:59:43'),
(42, 3, NULL, 3, 12, 'late', 'late', '2026-07-24', '10:15:00', '2026-08-17 15:59:43'),
(43, 1, 1, 1, 10, 'absent', 'absent', '2026-08-17', '18:24:05', '2026-08-17 18:24:05');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `code`, `name`, `description`, `created_at`) VALUES
(1, 'BSIT', 'Bachelor of Science in Information Technology', 'IT and programming course', '2026-07-15 17:57:31'),
(2, 'BSED', 'Bachelor of Secondary Education', 'Education and teaching course', '2026-07-15 17:57:31'),
(3, 'BSBA', 'Bachelor of Science in Business Administration', 'Business management course', '2026-07-15 17:57:31');

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip` varchar(60) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `action`, `ip`, `created_at`) VALUES
(1, 1, 'Logged in', '::1', '2026-07-16 13:02:48'),
(2, 1, 'Logged out', '::1', '2026-07-16 13:20:31'),
(3, 1, 'Logged in', '::1', '2026-07-16 17:30:57'),
(4, 1, 'Logged out', '::1', '2026-07-16 17:39:13'),
(5, 2, 'Logged in', '::1', '2026-07-16 17:45:49'),
(6, 2, 'Logged out', '::1', '2026-07-16 17:46:51'),
(7, 2, 'Logged in', '::1', '2026-07-16 18:26:54'),
(8, 2, 'Logged out', '::1', '2026-07-16 18:26:57'),
(9, 2, 'Logged in', '::1', '2026-07-16 18:28:20'),
(10, 2, 'Logged out', '::1', '2026-07-16 19:01:22'),
(11, 1, 'Logged in', '::1', '2026-08-02 14:15:02'),
(12, NULL, 'Logged in', '::1', '2026-08-02 14:19:15'),
(13, 1, 'Scanned QR for student S1001', '::1', '2026-08-02 14:20:39'),
(14, NULL, 'Logged in', '::1', '2026-08-02 14:22:07'),
(15, 1, 'Logged in', '::1', '2026-08-02 17:17:31'),
(16, 5, 'Logged in', '::1', '2026-08-02 17:18:07'),
(17, 6, 'Logged in', '::1', '2026-08-02 17:18:08'),
(18, 1, 'Logged in', '::1', '2026-08-02 18:27:11'),
(19, 1, 'Logged out', '::1', '2026-08-02 18:27:21'),
(20, 6, 'Logged in', '::1', '2026-08-02 18:27:41'),
(21, 6, 'Logged out', '::1', '2026-08-02 18:28:06'),
(22, 5, 'Logged in', '::1', '2026-08-02 18:28:30'),
(23, 6, 'Logged in', '::1', '2026-08-17 10:59:03'),
(24, 6, 'Logged in', '::1', '2026-08-17 11:09:51'),
(25, 6, 'Logged in', '::1', '2026-08-17 11:25:44'),
(26, 1, 'Logged in', '::1', '2026-08-17 11:25:58'),
(27, 6, 'Logged out', '::1', '2026-08-17 12:19:18'),
(28, 6, 'Logged in', '::1', '2026-08-17 12:23:07'),
(29, 1, 'Scanned QR for student S1001', '::1', '2026-08-17 12:49:45'),
(30, 1, 'Scanned QR for student S1001', '::1', '2026-08-17 12:49:45'),
(31, 6, 'Changed password', '::1', '2026-08-17 13:22:41'),
(32, 6, 'Logged in', '::1', '2026-08-17 13:22:50'),
(33, 6, 'Changed password', '::1', '2026-08-17 13:23:52'),
(34, 6, 'Logged out', '::1', '2026-08-17 15:17:04'),
(35, 5, 'Logged in', '::1', '2026-08-17 15:17:56'),
(36, 5, 'Logged out', '::1', '2026-08-17 15:18:45'),
(37, 6, 'Logged in', '::1', '2026-08-17 15:19:05'),
(38, 6, 'Logged out', '::1', '2026-08-17 15:28:36'),
(39, 5, 'Logged in', '::1', '2026-08-17 15:30:54'),
(40, 5, 'Logged in', '::1', '2026-08-17 15:53:55'),
(41, 5, 'Logged out', '::1', '2026-08-17 17:37:57'),
(42, 6, 'Logged in', '::1', '2026-08-17 17:38:40'),
(43, 6, 'Logged in', '::1', '2026-08-17 17:49:49'),
(44, 6, 'Logged in', '::1', '2026-08-17 17:56:26'),
(45, 5, 'Logged in', '::1', '2026-08-17 18:04:26'),
(46, 5, 'Scanned QR for student S1001', '::1', '2026-08-17 18:24:05');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `year_level` varchar(30) NOT NULL,
  `section_name` varchar(80) NOT NULL,
  `course_id` int(11) NOT NULL,
  `adviser` varchar(120) DEFAULT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `year_level`, `section_name`, `course_id`, `adviser`, `adviser_id`, `created_at`) VALUES
(1, '1st Year', 'Section A', 1, 'Mr. Reyes', NULL, '2026-07-15 17:57:32'),
(2, '2nd Year', 'Section B', 2, 'Ms. Santos', NULL, '2026-07-15 17:57:32'),
(3, '3rd Year', 'Section C', 3, 'Dr. Cruz', NULL, '2026-07-15 17:57:32');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `name` varchar(100) NOT NULL,
  `value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`name`, `value`) VALUES
('absent_cutoff_minutes', '20'),
('late_grace_minutes', '0');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `student_id` varchar(60) NOT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `gender` enum('Male','Female') NOT NULL DEFAULT 'Male',
  `birthday` date DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `year_level` varchar(30) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `guardian_name` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `photo` varchar(255) DEFAULT NULL,
  `qr_code` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `student_id`, `first_name`, `last_name`, `gender`, `birthday`, `course_id`, `year_level`, `section_id`, `guardian_name`, `phone`, `email`, `status`, `photo`, `qr_code`, `created_at`) VALUES
(1, 6, 'S1001', 'Leila', 'Lopez', 'Female', '2006-04-12', 1, '1st Year', 1, 'Maria Lopez', '09171234567', 'ana.lopez@example.com', 'active', 'uploads/student_1786946833.png', 'S1001', '2026-07-15 17:57:32'),
(2, NULL, 'S1002', 'Jon', 'Garcia', 'Male', '2005-08-01', 2, '2nd Year', 2, 'Pedro Garcia', '09172345678', 'jon.garcia@example.com', 'active', NULL, 'S1002', '2026-07-15 17:57:32'),
(3, NULL, 'S1003', 'Mia', 'Delos', 'Female', '2007-01-20', 3, '3rd Year', 3, 'Anna Delos', '09173456789', 'mia.delos@example.com', 'active', NULL, 'S1003', '2026-07-15 17:57:32');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `section_id` int(11) NOT NULL,
  `day_of_week` enum('Mon','Tue','Wed','Thu','Fri','Sat','Sun') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `room` varchar(60) DEFAULT NULL,
  `credit_units` int(11) DEFAULT NULL,
  `important_note` text DEFAULT NULL,
  `absent_cutoff_minutes` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `code`, `name`, `teacher_id`, `section_id`, `day_of_week`, `start_time`, `end_time`, `room`, `credit_units`, `important_note`, `absent_cutoff_minutes`, `status`, `created_at`) VALUES
(4, 'IT 204', 'Database Systems', 4, 1, 'Mon', '07:00:00', '08:30:00', 'IT Lab 1', 3, 'Please bring your laptop for the database normalization laboratory activity.', NULL, 'active', '2026-08-17 11:29:03'),
(5, 'IT 205', 'Web Development', 5, 1, 'Tue', '08:45:00', '10:15:00', 'IT Lab 2', 3, NULL, NULL, 'active', '2026-08-17 11:29:03'),
(6, 'IT 206', 'Data Structures', 6, 1, 'Mon', '10:30:00', '12:00:00', 'IT Lab 3', 3, NULL, NULL, 'active', '2026-08-17 11:29:03'),
(7, 'GE 102', 'Purposive Communication', 7, 1, 'Tue', '13:00:00', '14:30:00', 'B-204', 3, NULL, NULL, 'active', '2026-08-17 11:29:03'),
(8, 'IT 207', 'Computer Networks', 8, 1, 'Fri', '14:45:00', '16:15:00', 'IT Lab 4', 3, NULL, NULL, 'active', '2026-08-17 11:29:03'),
(9, 'BA 3203', 'Statistics for Business', 9, 1, 'Wed', '16:30:00', '18:00:00', 'B-205', 3, NULL, NULL, 'active', '2026-08-17 11:29:03'),
(10, 'IT 208', 'Mobile App Development', 3, 1, 'Mon', '09:00:00', '10:50:00', 'IT Lab 5', 3, NULL, NULL, 'active', '2026-08-17 15:59:22'),
(11, 'IT 209', 'Systems Analysis and Design', 3, 2, 'Wed', '13:00:00', '14:30:00', 'IT Lab 1', 3, NULL, NULL, 'active', '2026-08-17 15:59:22'),
(12, 'CS 301', 'Human Computer Interaction', 3, 3, 'Fri', '10:00:00', '11:30:00', 'B-301', 3, NULL, NULL, 'active', '2026-08-17 15:59:22'),
(13, 'IT 208', 'Mobile App Development', 3, 1, 'Tue', '09:00:00', '10:50:00', 'IT Lab 5', 3, NULL, NULL, 'active', '2026-08-17 19:08:14'),
(14, 'CS 301', 'Human Computer Interaction', 3, 3, 'Mon', '10:00:00', '11:30:00', 'B-301', 3, NULL, NULL, 'active', '2026-08-17 19:43:19');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `teacher_id` varchar(60) NOT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `subject` varchar(150) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `user_id`, `teacher_id`, `first_name`, `last_name`, `subject`, `section_id`, `phone`, `email`, `photo`, `status`, `created_at`) VALUES
(3, 5, 'T1001', 'Liza', 'Reyes', 'Mathematics', 1, '0', '', NULL, 'active', '2026-08-02 17:17:38'),
(4, NULL, 'T2001', 'Allen', 'Reyes', 'Database Systems', 1, '09170001111', 'allen.reyes@example.com', NULL, 'active', '2026-08-17 11:28:40'),
(5, NULL, 'T2002', 'Danilo', 'Cruz', 'Web Development', NULL, NULL, NULL, NULL, 'active', '2026-08-17 11:28:40'),
(6, NULL, 'T2003', 'Maria', 'Santos', 'Data Structures', NULL, NULL, NULL, NULL, 'active', '2026-08-17 11:28:40'),
(7, NULL, 'T2004', 'Liza', 'Garcia', 'Purposive Communication', NULL, NULL, NULL, NULL, 'active', '2026-08-17 11:28:40'),
(8, NULL, 'T2005', 'John', 'Mark', 'Computer Networks', NULL, NULL, NULL, NULL, 'active', '2026-08-17 11:28:40'),
(9, NULL, 'T2006', 'Oliver', 'Hernandez', 'Statistics for Business', NULL, NULL, NULL, NULL, 'active', '2026-08-17 11:28:40');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(80) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','teacher','student') NOT NULL DEFAULT 'admin',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `lock_until` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `status`, `failed_attempts`, `lock_until`, `remember_token`, `reset_token`, `reset_expires`, `created_at`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$jB5SoYhUvWGnyWrNR6GrU.zec/XpHRtbdoyqVN8dHBhL2esIOQXVS', 'superadmin', 'active', 0, NULL, NULL, NULL, NULL, '2026-07-15 17:57:31'),
(2, 'teacher', 'teacher@example.com', '$2y$10$SapIzysE6B4UcNRi2o8UPOt1i0316eKsAyOD31TIZCqytXZlugVxi', 'teacher', 'active', 0, NULL, NULL, NULL, NULL, '2026-07-15 17:57:31'),
(5, 'T1001', 't1001@timetrack.local', '$2y$10$SW/XWYw0.TdSz1dxLv7ee.q9uiBxeZx8Ot3T3pf5M4zJ9ERsiyZ9G', 'teacher', 'active', 0, NULL, NULL, NULL, NULL, '2026-08-02 17:17:38'),
(6, 'S1001', 'ana.lopez@example.com', '$2y$10$NnyYHKNOlCEgHbhznMUdru9aKVSKmF.9ozhWm1VyBj/RZWsdA5id2', 'student', 'active', 0, NULL, NULL, NULL, NULL, '2026-08-02 17:17:49');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_attendance_student_subject_date` (`student_id`,`subject_id`,`date`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `fk_attendance_subject` (`subject_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `fk_sections_adviser` (`adviser_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`name`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `uniq_students_user` (`user_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teacher_id` (`teacher_id`),
  ADD UNIQUE KEY `uniq_teachers_user` (`user_id`),
  ADD KEY `section_id` (`section_id`);

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
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_attendance_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `fk_sections_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `subjects_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
