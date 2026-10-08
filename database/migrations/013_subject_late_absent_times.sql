-- Per-class attendance policy as two clock offsets from the class start time:
-- scans from start + late_after_minutes are Late, and from start + absent_after_minutes
-- (or no scan by then) are Absent. NULL on both keeps the school default rule.
ALTER TABLE `subjects`
  ADD COLUMN `late_after_minutes` INT NULL DEFAULT NULL AFTER `absent_cutoff_minutes`,
  ADD COLUMN `absent_after_minutes` INT NULL DEFAULT NULL AFTER `late_after_minutes`;
