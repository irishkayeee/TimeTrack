-- Renames the "section" concept (class group, e.g. "1st Year - Section A") to "room".
-- The pre-existing physical-location field on subjects (subjects.room, e.g. "IT Lab 1")
-- is renamed to subject_room to avoid colliding with the new room_id/rooms naming.

RENAME TABLE sections TO rooms;

ALTER TABLE rooms
  CHANGE COLUMN section_name room_name VARCHAR(80) NOT NULL;

ALTER TABLE attendance
  CHANGE COLUMN section_id room_id INT(11) DEFAULT NULL;

ALTER TABLE students
  CHANGE COLUMN section_id room_id INT(11) DEFAULT NULL;

ALTER TABLE subjects
  CHANGE COLUMN section_id room_id INT(11) NOT NULL,
  CHANGE COLUMN room subject_room VARCHAR(60) DEFAULT NULL;

ALTER TABLE teachers
  CHANGE COLUMN section_id room_id INT(11) DEFAULT NULL;
