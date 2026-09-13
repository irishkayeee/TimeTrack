-- A one-time extra session for a subject on a specific calendar date, separate
-- from its normal recurring weekly schedule (subjects.day_of_week/start_time).
-- Lets a teacher add a makeup class after a suspension/holiday without touching
-- the permanent schedule, and without needing to remember to revert anything.
CREATE TABLE `makeup_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_id` INT NOT NULL,
  `teacher_id` INT NOT NULL,
  `session_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NULL,
  `note` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `subject_id` (`subject_id`),
  KEY `session_date` (`session_date`),
  CONSTRAINT `makeup_sessions_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  CONSTRAINT `makeup_sessions_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
