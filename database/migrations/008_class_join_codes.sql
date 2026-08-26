-- "Join Class" was reassigning a student's whole section (via sections.join_code),
-- so students saw every subject ever attached to that section, from any teacher.
-- This adds a per-class join code on subjects itself, plus an enrollments table
-- so joining a class code only adds that one class to a student's list.

ALTER TABLE `subjects`
  ADD COLUMN `join_code` VARCHAR(8) NULL DEFAULT NULL AFTER `status`,
  ADD UNIQUE KEY `uniq_subjects_join_code` (`join_code`);

CREATE TABLE IF NOT EXISTS `enrollments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_student_subject` (`student_id`, `subject_id`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
