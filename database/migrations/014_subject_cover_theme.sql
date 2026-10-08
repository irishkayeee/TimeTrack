-- Teacher-chosen cover for a class card: a color key and a pattern key
-- (see subjectCoverColors() / subjectCoverPatterns() in includes/functions.php).
-- NULL keeps the automatic color picked from the subject id.
ALTER TABLE `subjects`
  ADD COLUMN `cover_color` VARCHAR(20) NULL DEFAULT NULL AFTER `absent_after_minutes`,
  ADD COLUMN `cover_pattern` VARCHAR(20) NULL DEFAULT NULL AFTER `cover_color`;
