ALTER TABLE `sections`
  ADD COLUMN `join_code` VARCHAR(8) NULL DEFAULT NULL AFTER `adviser_id`,
  ADD UNIQUE KEY `uniq_sections_join_code` (`join_code`);
