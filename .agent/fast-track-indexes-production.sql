-- Fast-Track Performance Indexes for Production
-- Compatible with older MySQL versions (5.7+)

-- This script checks if indexes exist before creating them
-- Safe to run multiple times

-- ============================================
-- EXEAT_REQUESTS TABLE INDEXES
-- ============================================

-- Index for status filtering
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'exeat_requests' 
    AND index_name = 'idx_exeat_requests_status');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index already exists.''', 
    'CREATE INDEX idx_exeat_requests_status ON exeat_requests(status)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Index for matric_no search
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'exeat_requests' 
    AND index_name = 'idx_exeat_requests_matric_no');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index already exists.''', 
    'CREATE INDEX idx_exeat_requests_matric_no ON exeat_requests(matric_no)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Composite index for status + updated_at
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'exeat_requests' 
    AND index_name = 'idx_exeat_requests_status_updated_at');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index already exists.''', 
    'CREATE INDEX idx_exeat_requests_status_updated_at ON exeat_requests(status, updated_at)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Index for student_id
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'exeat_requests' 
    AND index_name = 'idx_exeat_requests_student_id');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index already exists.''', 
    'CREATE INDEX idx_exeat_requests_student_id ON exeat_requests(student_id)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Index for departure_date
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'exeat_requests' 
    AND index_name = 'idx_exeat_requests_departure_date');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index already exists.''', 
    'CREATE INDEX idx_exeat_requests_departure_date ON exeat_requests(departure_date)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Index for return_date
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'exeat_requests' 
    AND index_name = 'idx_exeat_requests_return_date');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index already exists.''', 
    'CREATE INDEX idx_exeat_requests_return_date ON exeat_requests(return_date)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- ============================================
-- STUDENTS TABLE INDEXES
-- ============================================

-- Index for fname
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'students' 
    AND index_name = 'idx_students_fname');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index already exists.''', 
    'CREATE INDEX idx_students_fname ON students(fname)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Index for lname
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'students' 
    AND index_name = 'idx_students_lname');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index already exists.''', 
    'CREATE INDEX idx_students_lname ON students(lname)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Index for mname
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() AND table_name = 'students' 
    AND index_name = 'idx_students_mname');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index already exists.''', 
    'CREATE INDEX idx_students_mname ON students(mname)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- ============================================
-- VERIFICATION
-- ============================================

-- Show all indexes created
SELECT 
    table_name,
    index_name,
    GROUP_CONCAT(column_name ORDER BY seq_in_index) as columns
FROM information_schema.statistics
WHERE table_schema = DATABASE()
    AND table_name IN ('exeat_requests', 'students')
    AND index_name LIKE 'idx_%'
GROUP BY table_name, index_name
ORDER BY table_name, index_name;
