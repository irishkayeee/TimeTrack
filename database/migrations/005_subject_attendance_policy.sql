ALTER TABLE `subjects`
  ADD COLUMN `absent_cutoff_minutes` INT NULL DEFAULT NULL AFTER `important_note`;
