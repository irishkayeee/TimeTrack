-- Migration 003: additional subject metadata for the student subject-details view.
USE `attendance_system`;

ALTER TABLE `subjects` ADD COLUMN `end_time` TIME NULL AFTER `start_time`;
ALTER TABLE `subjects` ADD COLUMN `room` VARCHAR(60) NULL AFTER `end_time`;
ALTER TABLE `subjects` ADD COLUMN `credit_units` INT NULL AFTER `room`;
ALTER TABLE `subjects` ADD COLUMN `important_note` TEXT NULL AFTER `credit_units`;
