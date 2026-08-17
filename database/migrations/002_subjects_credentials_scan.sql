-- Migration 002: subjects (class sessions), teacher/student login linkage,
-- section adviser FK, per-subject attendance scanning, configurable thresholds.
-- Run once against an existing `attendance_system` database.
USE `attendance_system`;

-- 1. Subjects = a class/session offering (the spec's "Course"), distinct from the
--    existing `courses` table which represents the student's degree Program.
--    One row = one weekly day+time slot; a class held on multiple days needs one
--    row per day sharing the same code/section.
CREATE TABLE `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(30) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `teacher_id` INT NULL,
  `section_id` INT NOT NULL,
  `day_of_week` ENUM('Mon','Tue','Wed','Thu','Fri','Sat','Sun') NOT NULL,
  `start_time` TIME NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Login linkage: teachers/students gain an optional linked users row.
ALTER TABLE `teachers` ADD COLUMN `user_id` INT NULL AFTER `id`;
ALTER TABLE `teachers` ADD UNIQUE KEY `uniq_teachers_user` (`user_id`);
ALTER TABLE `teachers` ADD CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `students` ADD COLUMN `user_id` INT NULL AFTER `id`;
ALTER TABLE `students` ADD UNIQUE KEY `uniq_students_user` (`user_id`);
ALTER TABLE `students` ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- 3. Section adviser becomes a real teacher reference. The old free-text `adviser`
--    column is kept (deprecated, read-only) for historical reference; admins must
--    manually re-pick advisers via the new dropdown after this migration.
ALTER TABLE `sections` ADD COLUMN `adviser_id` INT NULL AFTER `adviser`;
ALTER TABLE `sections` ADD CONSTRAINT `fk_sections_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL;

-- 4. Attendance becomes per-subject-session rather than once-per-day-globally.
--    NULLs from historical rows do not collide under a unique index.
ALTER TABLE `attendance` ADD COLUMN `subject_id` INT NULL AFTER `section_id`;
ALTER TABLE `attendance` ADD CONSTRAINT `fk_attendance_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE SET NULL;
ALTER TABLE `attendance` ADD UNIQUE KEY `uniq_attendance_student_subject_date` (`student_id`,`subject_id`,`date`);

-- 5. Configurable late/absent thresholds backing the shared status helper.
INSERT IGNORE INTO `settings` (`name`, `value`) VALUES
  ('late_grace_minutes', '0'),
  ('absent_cutoff_minutes', '20');
