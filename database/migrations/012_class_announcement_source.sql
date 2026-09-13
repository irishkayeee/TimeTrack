-- Distinguishes an announcement a teacher typed themselves from one the system
-- auto-posted (e.g. the makeup-class notice created alongside a makeup session).
-- The teacher's Edit/Delete controls are hidden for system-sourced announcements,
-- since editing/deleting the text here doesn't touch the underlying record it
-- describes (e.g. the makeup_sessions row) and would just cause the two to disagree.
ALTER TABLE `class_announcements`
  ADD COLUMN `source` VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER `teacher_id`;
