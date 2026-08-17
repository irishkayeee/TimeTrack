-- Attendance Management System SQL Schema
CREATE DATABASE IF NOT EXISTS `attendance_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `attendance_system`;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(80) NOT NULL UNIQUE,
  `email` VARCHAR(120) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('superadmin','admin','teacher','student') NOT NULL DEFAULT 'admin',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `failed_attempts` INT NOT NULL DEFAULT 0,
  `lock_until` DATETIME NULL,
  `remember_token` VARCHAR(255) NULL,
  `reset_token` VARCHAR(255) NULL,
  `reset_expires` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(30) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- `sections.adviser` is a deprecated free-text fallback; `adviser_id` (FK added below,
-- once `teachers` exists) is the real adviser assignment used by the UI.
CREATE TABLE `sections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `year_level` VARCHAR(30) NOT NULL,
  `section_name` VARCHAR(80) NOT NULL,
  `course_id` INT NOT NULL,
  `adviser` VARCHAR(120) NULL,
  `adviser_id` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- `user_id` links a student record to its login account (see `users`).
CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `student_id` VARCHAR(60) NOT NULL UNIQUE,
  `first_name` VARCHAR(120) NOT NULL,
  `last_name` VARCHAR(120) NOT NULL,
  `gender` ENUM('Male','Female') NOT NULL DEFAULT 'Male',
  `birthday` DATE NULL,
  `course_id` INT NULL,
  `year_level` VARCHAR(30) NULL,
  `section_id` INT NULL,
  `guardian_name` VARCHAR(150) NULL,
  `phone` VARCHAR(50) NULL,
  `email` VARCHAR(120) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `photo` VARCHAR(255) NULL,
  `qr_code` VARCHAR(120) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_students_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- `user_id` links a teacher record to its login account (see `users`).
CREATE TABLE `teachers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `teacher_id` VARCHAR(60) NOT NULL UNIQUE,
  `first_name` VARCHAR(120) NOT NULL,
  `last_name` VARCHAR(120) NOT NULL,
  `subject` VARCHAR(150) NULL,
  `section_id` INT NULL,
  `phone` VARCHAR(50) NULL,
  `email` VARCHAR(120) NULL,
  `photo` VARCHAR(255) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_teachers_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Deferred: `sections.adviser_id` can only reference `teachers` once it exists.
ALTER TABLE `sections` ADD CONSTRAINT `fk_sections_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL;

-- Subjects = a class/session offering (the spec's "Course": name, teacher, section,
-- weekly schedule) — distinct from `courses`, which represents the student's degree
-- Program. One row = one weekly day+time slot.
CREATE TABLE `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(30) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `teacher_id` INT NULL,
  `section_id` INT NOT NULL,
  `day_of_week` ENUM('Mon','Tue','Wed','Thu','Fri','Sat','Sun') NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NULL,
  `room` VARCHAR(60) NULL,
  `credit_units` INT NULL,
  `important_note` TEXT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One attendance row per student per subject-session per day (see unique key below) —
-- scanning is per class, not once a day.
CREATE TABLE `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `course_id` INT NULL,
  `section_id` INT NULL,
  `subject_id` INT NULL,
  `status` ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `scan_type` VARCHAR(50) NOT NULL DEFAULT 'present',
  `date` DATE NOT NULL,
  `time` TIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_attendance_student_subject_date` (`student_id`,`subject_id`,`date`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `settings` (
  `name` VARCHAR(100) NOT NULL PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(255) NOT NULL,
  `ip` VARCHAR(60) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed users: admin, plus one login per demo teacher/student created below.
-- (ids: 1=admin, 2=teacher/T1001, 3=T1002, 4=S1001, 5=S1002, 6=S1003)
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `status`, `created_at`) VALUES
('admin', 'admin@example.com', '$2y$10$jB5SoYhUvWGnyWrNR6GrU.zec/XpHRtbdoyqVN8dHBhL2esIOQXVS', 'superadmin', 'active', NOW()),
('teacher', 'teacher@example.com', '$2y$10$SapIzysE6B4UcNRi2o8UPOt1i0316eKsAyOD31TIZCqytXZlugVxi', 'teacher', 'active', NOW()),
('T1002', 'noel.santos@example.com', '$2y$10$SapIzysE6B4UcNRi2o8UPOt1i0316eKsAyOD31TIZCqytXZlugVxi', 'teacher', 'active', NOW()),
('S1001', 'ana.lopez@example.com', '$2y$10$SapIzysE6B4UcNRi2o8UPOt1i0316eKsAyOD31TIZCqytXZlugVxi', 'student', 'active', NOW()),
('S1002', 'jon.garcia@example.com', '$2y$10$SapIzysE6B4UcNRi2o8UPOt1i0316eKsAyOD31TIZCqytXZlugVxi', 'student', 'active', NOW()),
('S1003', 'mia.delos@example.com', '$2y$10$SapIzysE6B4UcNRi2o8UPOt1i0316eKsAyOD31TIZCqytXZlugVxi', 'student', 'active', NOW());

INSERT INTO `courses` (`code`, `name`, `description`) VALUES
('BSIT', 'Bachelor of Science in Information Technology', 'IT and programming course'),
('BSED', 'Bachelor of Secondary Education', 'Education and teaching course'),
('BSBA', 'Bachelor of Science in Business Administration', 'Business management course');

INSERT INTO `sections` (`year_level`, `section_name`, `course_id`, `adviser`, `created_at`) VALUES
('1st Year', 'Section A', 1, 'Mr. Reyes', NOW()),
('2nd Year', 'Section B', 2, 'Ms. Santos', NOW()),
('3rd Year', 'Section C', 3, 'Dr. Cruz', NOW());

INSERT INTO `students` (`user_id`, `student_id`, `first_name`, `last_name`, `gender`, `birthday`, `course_id`, `year_level`, `section_id`, `guardian_name`, `phone`, `email`, `status`, `qr_code`, `created_at`) VALUES
(4, 'S1001', 'Ana', 'Lopez', 'Female', '2006-04-12', 1, '1st Year', 1, 'Maria Lopez', '09171234567', 'ana.lopez@example.com', 'active', 'S1001', NOW()),
(5, 'S1002', 'Jon', 'Garcia', 'Male', '2005-08-01', 2, '2nd Year', 2, 'Pedro Garcia', '09172345678', 'jon.garcia@example.com', 'active', 'S1002', NOW()),
(6, 'S1003', 'Mia', 'Delos', 'Female', '2007-01-20', 3, '3rd Year', 3, 'Anna Delos', '09173456789', 'mia.delos@example.com', 'active', 'S1003', NOW());

INSERT INTO `teachers` (`user_id`, `teacher_id`, `first_name`, `last_name`, `subject`, `section_id`, `phone`, `email`, `status`, `created_at`) VALUES
(2, 'T1001', 'Liza', 'Reyes', 'Mathematics', 1, '09175551234', 'liza.reyes@example.com', 'active', NOW()),
(3, 'T1002', 'Noel', 'Santos', 'Science', 2, '09176662345', 'noel.santos@example.com', 'active', NOW());

-- Wire section advisers to the teachers just created (Section C is left unassigned).
UPDATE `sections` SET `adviser_id` = 1 WHERE `id` = 1;
UPDATE `sections` SET `adviser_id` = 2 WHERE `id` = 2;

INSERT INTO `subjects` (`code`, `name`, `teacher_id`, `section_id`, `day_of_week`, `start_time`, `status`, `created_at`) VALUES
('MATH101', 'Mathematics', 1, 1, 'Mon', '07:30:00', 'active', NOW()),
('MATH101', 'Mathematics', 1, 1, 'Wed', '07:30:00', 'active', NOW()),
('SCI101', 'Science', 2, 2, 'Tue', '08:00:00', 'active', NOW());

INSERT INTO `settings` (`name`, `value`) VALUES
('school_name', 'Premium School Attendance'),
('timezone', 'Asia/Manila'),
('attendance_time', '07:30'),
('late_time', '08:00'),
('school_year', '2025-2026'),
('semester', '1st Semester'),
('late_grace_minutes', '0'),
('absent_cutoff_minutes', '20');
