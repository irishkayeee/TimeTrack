INSERT INTO notifications (student_id, subject_id, type, title, message, is_read, created_at) VALUES
(2, 4, 'present', 'Attendance Recorded', 'You were marked present in Database Systems today.', 1, NOW() - INTERVAL 3 DAY),
(2, 5, 'late', 'Marked Late', 'You were marked late in Web Development today.', 1, NOW() - INTERVAL 2 DAY),
(2, 6, 'absent', 'Marked Absent', 'You were marked absent in Data Structures today.', 1, NOW() - INTERVAL 1 DAY),
(2, 11, 'present', 'Attendance Recorded', 'You were marked present in Systems Analysis and Design today.', 0, NOW() - INTERVAL 5 HOUR),
(2, 4, 'late', 'Marked Late', 'You were marked late in Database Systems today.', 0, NOW() - INTERVAL 1 HOUR);
