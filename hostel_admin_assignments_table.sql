-- =====================================================
-- HOSTEL ADMIN ASSIGNMENTS TABLE - COMPLETE SQL
-- =====================================================

-- Create the hostel_admin_assignments table
CREATE TABLE `hostel_admin_assignments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vuna_accomodation_id` BIGINT UNSIGNED NOT NULL COMMENT 'Foreign key to vuna_accomodations table',
    `staff_id` BIGINT UNSIGNED NOT NULL COMMENT 'Foreign key to staff table',
    `assigned_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When the assignment was made',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active' COMMENT 'Assignment status',
    `assigned_by` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Staff ID who made the assignment (admin/dean)',
    `notes` TEXT NULL DEFAULT NULL COMMENT 'Additional notes about the assignment',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    
    PRIMARY KEY (`id`),
    
    -- Foreign Key Constraints
    CONSTRAINT `hostel_admin_assignments_vuna_accomodation_id_foreign` 
        FOREIGN KEY (`vuna_accomodation_id`) 
        REFERENCES `vuna_accomodations` (`id`) 
        ON DELETE CASCADE,
    
    CONSTRAINT `hostel_admin_assignments_staff_id_foreign` 
        FOREIGN KEY (`staff_id`) 
        REFERENCES `staff` (`id`) 
        ON DELETE CASCADE,
    
    CONSTRAINT `hostel_admin_assignments_assigned_by_foreign` 
        FOREIGN KEY (`assigned_by`) 
        REFERENCES `staff` (`id`) 
        ON DELETE SET NULL,
    
    -- Indexes for Performance
    INDEX `hostel_status_idx` (`vuna_accomodation_id`, `status`),
    INDEX `staff_status_idx` (`staff_id`, `status`),
    INDEX `hostel_admin_assignments_assigned_at_index` (`assigned_at`),
    INDEX `hostel_admin_assignments_status_index` (`status`),
    
    -- Unique Constraint to Prevent Duplicate Active Assignments
    UNIQUE KEY `unique_active_assignment` (`vuna_accomodation_id`, `staff_id`, `status`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Manages assignments of staff members to hostels as hostel admins';

-- =====================================================
-- SAMPLE DATA INSERTION
-- =====================================================

-- Insert sample hostel admin assignments
INSERT INTO `hostel_admin_assignments` (
    `vuna_accomodation_id`, 
    `staff_id`, 
    `assigned_at`, 
    `status`, 
    `assigned_by`, 
    `notes`, 
    `created_at`, 
    `updated_at`
) VALUES 
(1, 101, NOW(), 'active', 1, 'Initial assignment for CICL hostel', NOW(), NOW()),
(2, 102, NOW(), 'active', 1, 'Initial assignment for Block A hostel', NOW(), NOW()),
(1, 103, NOW(), 'active', 1, 'Additional admin for CICL hostel', NOW(), NOW()),
(3, 101, NOW(), 'active', 1, 'Multi-hostel assignment for Staff 101', NOW(), NOW());

-- =====================================================
-- USEFUL QUERIES FOR TESTING
-- =====================================================

-- 1. Get all active hostel assignments
SELECT 
    ha.id,
    ha.vuna_accomodation_id,
    va.name AS hostel_name,
    ha.staff_id,
    CONCAT(s.fname, ' ', s.lname) AS staff_name,
    ha.assigned_at,
    ha.status,
    ha.notes
FROM hostel_admin_assignments ha
JOIN vuna_accomodations va ON ha.vuna_accomodation_id = va.id
JOIN staff s ON ha.staff_id = s.id
WHERE ha.status = 'active'
ORDER BY va.name, s.fname;

-- 2. Get staff member's hostel assignments
SELECT 
    ha.id,
    va.name AS hostel_name,
    va.gender AS hostel_gender,
    ha.assigned_at,
    ha.status
FROM hostel_admin_assignments ha
JOIN vuna_accomodations va ON ha.vuna_accomodation_id = va.id
WHERE ha.staff_id = 101 AND ha.status = 'active';

-- 3. Get hostel's assigned staff members
SELECT 
    ha.id,
    CONCAT(s.fname, ' ', s.lname) AS staff_name,
    s.email AS staff_email,
    ha.assigned_at,
    ha.notes
FROM hostel_admin_assignments ha
JOIN staff s ON ha.staff_id = s.id
WHERE ha.vuna_accomodation_id = 1 AND ha.status = 'active';

-- 4. Get exeat requests that a hostel admin can see
SELECT 
    er.id,
    er.student_id,
    er.matric_no,
    er.student_accommodation,
    er.status,
    er.reason,
    er.departure_date,
    er.return_date
FROM exeat_requests er
WHERE er.student_accommodation IN (
    SELECT va.name 
    FROM hostel_admin_assignments ha
    JOIN vuna_accomodations va ON ha.vuna_accomodation_id = va.id
    WHERE ha.staff_id = 101 AND ha.status = 'active'
)
AND er.status IN ('hostel_signout', 'hostel_signin')
ORDER BY er.created_at DESC;

-- 5. Check for duplicate assignments before insertion
SELECT COUNT(*) as duplicate_count
FROM hostel_admin_assignments 
WHERE vuna_accomodation_id = 1 
  AND staff_id = 101 
  AND status = 'active';

-- 6. Get assignment statistics
SELECT 
    va.name AS hostel_name,
    COUNT(ha.id) AS assigned_staff_count,
    GROUP_CONCAT(CONCAT(s.fname, ' ', s.lname) SEPARATOR ', ') AS staff_names
FROM vuna_accomodations va
LEFT JOIN hostel_admin_assignments ha ON va.id = ha.vuna_accomodation_id AND ha.status = 'active'
LEFT JOIN staff s ON ha.staff_id = s.id
GROUP BY va.id, va.name
ORDER BY va.name;

-- =====================================================
-- MAINTENANCE QUERIES
-- =====================================================

-- Deactivate an assignment
UPDATE hostel_admin_assignments 
SET status = 'inactive', updated_at = NOW() 
WHERE id = 1;

-- Reactivate an assignment
UPDATE hostel_admin_assignments 
SET status = 'active', updated_at = NOW() 
WHERE id = 1;

-- Clean up inactive assignments older than 1 year
DELETE FROM hostel_admin_assignments 
WHERE status = 'inactive' 
  AND updated_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- =====================================================
-- PERFORMANCE OPTIMIZATION QUERIES
-- =====================================================

-- Analyze table performance
ANALYZE TABLE hostel_admin_assignments;

-- Check index usage
SHOW INDEX FROM hostel_admin_assignments;

-- Explain query performance for common operations
EXPLAIN SELECT * FROM hostel_admin_assignments 
WHERE staff_id = 101 AND status = 'active';

EXPLAIN SELECT * FROM hostel_admin_assignments 
WHERE vuna_accomodation_id = 1 AND status = 'active';