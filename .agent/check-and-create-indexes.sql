-- Check which indexes already exist on production
-- Run this first to see what you have:

SHOW INDEX FROM exeat_requests WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM students WHERE Key_name LIKE 'idx_%';

-- Then run ONLY the missing ones from the list below:

-- For exeat_requests table:
-- CREATE INDEX idx_exeat_requests_status ON exeat_requests(status);  -- You already have this!
-- CREATE INDEX idx_exeat_requests_matric_no ON exeat_requests(matric_no);
-- CREATE INDEX idx_exeat_requests_student_id ON exeat_requests(student_id);
-- CREATE INDEX idx_exeat_requests_status_updated_at ON exeat_requests(status, updated_at);
-- CREATE INDEX idx_exeat_requests_departure_date ON exeat_requests(departure_date);
-- CREATE INDEX idx_exeat_requests_return_date ON exeat_requests(return_date);

-- For students table:
-- CREATE INDEX idx_students_fname ON students(fname);
-- CREATE INDEX idx_students_lname ON students(lname);
-- CREATE INDEX idx_students_mname ON students(mname);

-- After running the missing ones, verify all indexes exist:
SHOW INDEX FROM exeat_requests WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM students WHERE Key_name LIKE 'idx_%';
