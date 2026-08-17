-- Migration 004: teacher photo, used on the student-facing subject header.
USE `attendance_system`;

ALTER TABLE `teachers` ADD COLUMN `photo` VARCHAR(255) NULL AFTER `email`;
