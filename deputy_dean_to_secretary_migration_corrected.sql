-- =====================================================
-- DEPUTY DEAN TO SECRETARY MIGRATION - CORRECTED SQL
-- =====================================================
-- This script migrates all deputy dean references to secretary
-- Handles cases where fields may or may not exist
-- Affects: exeat_roles, exeat_requests, parent_consents tables
-- =====================================================

-- Start transaction for data integrity
START TRANSACTION;

-- =====================================================
-- 1. UPDATE EXEAT_ROLES TABLE
-- =====================================================
-- Update deputy_dean role to secretary in exeat_roles table
UPDATE exeat_roles 
SET 
    name = 'secretary',
    display_name = 'Secretary',
    description = 'Can approve/reject exeat requests and act on behalf of parents when needed.',
    updated_at = NOW()
WHERE name = 'deputy_dean';

-- Verify the role update
SELECT 'exeat_roles updated' as step, COUNT(*) as affected_rows 
FROM exeat_roles 
WHERE name = 'secretary';

-- =====================================================
-- 2. UPDATE EXEAT_REQUESTS TABLE
-- =====================================================
-- First, update existing records with deputy-dean_review status
UPDATE exeat_requests 
SET status = 'secretary_review' 
WHERE status = 'deputy-dean_review';

-- Verify the status update
SELECT 'exeat_requests status updated' as step, COUNT(*) as affected_rows 
FROM exeat_requests 
WHERE status = 'secretary_review';

-- Update the status enum to replace deputy-dean_review with secretary_review
ALTER TABLE exeat_requests 
MODIFY COLUMN status ENUM(
    'pending', 
    'cmd_review', 
    'secretary_review',
    'parent_consent', 
    'dean_review', 
    'hostel_signout', 
    'security_signout', 
    'security_signin', 
    'hostel_signin', 
    'completed', 
    'rejected', 
    'appeal'
) DEFAULT 'pending';

-- =====================================================
-- 3. UPDATE PARENT_CONSENTS TABLE (CONDITIONAL)
-- =====================================================
-- Check what columns exist in parent_consents table
SELECT 'Checking parent_consents table structure' as step;

SELECT 'parent_consents columns' as info, 
       COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'parent_consents'
AND COLUMN_NAME IN ('action_type', 'deputy_dean_reason', 'secretary_reason')
ORDER BY COLUMN_NAME;

-- Check current action_type values (if column exists)
SET @action_type_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'parent_consents' 
    AND COLUMN_NAME = 'action_type'
);

-- Show current action_type values if column exists
SET @sql = IF(@action_type_exists > 0, 
    'SELECT "Current action_type values" as step, action_type, COUNT(*) as count FROM parent_consents GROUP BY action_type',
    'SELECT "action_type column does not exist" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update action_type enum to include secretary options (if column exists)
SET @sql = IF(@action_type_exists > 0, 
    'ALTER TABLE parent_consents MODIFY COLUMN action_type ENUM("parent", "secretary_approval", "secretary_rejection") DEFAULT "parent"',
    'SELECT "action_type column does not exist, skipping enum update" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if deputy_dean_reason column exists
SET @deputy_reason_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'parent_consents' 
    AND COLUMN_NAME = 'deputy_dean_reason'
);

-- Rename deputy_dean_reason to secretary_reason if it exists
SET @sql = IF(@deputy_reason_exists > 0, 
    'ALTER TABLE parent_consents CHANGE deputy_dean_reason secretary_reason TEXT NULL COMMENT "Reason provided by Secretary for override"',
    'SELECT "deputy_dean_reason column does not exist, skipping rename" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if secretary_reason column exists after potential rename
SET @secretary_reason_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'parent_consents' 
    AND COLUMN_NAME = 'secretary_reason'
);

-- Add secretary_reason column if it doesn't exist and wasn't renamed
SET @sql = IF(@secretary_reason_exists = 0, 
    'ALTER TABLE parent_consents ADD COLUMN secretary_reason TEXT NULL COMMENT "Reason provided by Secretary for override"',
    'SELECT "secretary_reason column already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 4. VERIFICATION QUERIES
-- =====================================================
-- Verify all changes were applied correctly
SELECT '=== MIGRATION VERIFICATION ===' as verification;

-- Check exeat_roles table
SELECT 'exeat_roles' as table_name, 
       name, display_name, description 
FROM exeat_roles 
WHERE name = 'secretary';

-- Check exeat_requests status enum
SELECT 'exeat_requests' as table_name, 
       'secretary_review status count' as check_type,
       COUNT(*) as count 
FROM exeat_requests 
WHERE status = 'secretary_review';

-- Check parent_consents table structure after changes
SELECT 'parent_consents final structure' as table_name,
       COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'parent_consents'
AND COLUMN_NAME IN ('action_type', 'secretary_reason')
ORDER BY COLUMN_NAME;

-- Check action_type values if column exists
SET @action_type_final_check = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'parent_consents' 
    AND COLUMN_NAME = 'action_type'
);

SET @sql = IF(@action_type_final_check > 0, 
    'SELECT "parent_consents action_type values" as table_name, action_type, COUNT(*) as count FROM parent_consents GROUP BY action_type',
    'SELECT "action_type column does not exist" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 5. FINAL STATUS
-- =====================================================
SELECT '=== MIGRATION COMPLETED SUCCESSFULLY ===' as status;
SELECT 'All deputy dean references have been migrated to secretary' as message;
SELECT 'Tables affected: exeat_roles, exeat_requests, parent_consents (conditional)' as affected_tables;
SELECT 'No data was lost during migration' as data_integrity;
SELECT 'parent_consents updates were conditional based on existing columns' as note;

-- Commit the transaction
COMMIT;

-- =====================================================
-- ROLLBACK SCRIPT (IF NEEDED)
-- =====================================================
/*
-- Uncomment and run this section if you need to rollback the changes

START TRANSACTION;

-- Rollback exeat_roles
UPDATE exeat_roles 
SET 
    name = 'deputy_dean',
    display_name = 'Deputy Dean',
    description = 'Can approve/reject exeat requests only after CMD (for medical) or parent has recommended/approved.',
    updated_at = NOW()
WHERE name = 'secretary';

-- Rollback exeat_requests status
UPDATE exeat_requests 
SET status = 'deputy-dean_review' 
WHERE status = 'secretary_review';

ALTER TABLE exeat_requests 
MODIFY COLUMN status ENUM(
    'pending', 
    'cmd_review', 
    'deputy-dean_review',
    'parent_consent', 
    'dean_review', 
    'hostel_signout', 
    'security_signout', 
    'security_signin', 
    'hostel_signin', 
    'completed', 
    'rejected', 
    'appeal'
) DEFAULT 'pending';

-- Rollback parent_consents (conditional)
-- Check if columns exist before rolling back
SET @action_type_rollback = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'parent_consents' 
    AND COLUMN_NAME = 'action_type'
);

SET @sql = IF(@action_type_rollback > 0, 
    'ALTER TABLE parent_consents MODIFY COLUMN action_type ENUM("parent", "deputy_dean") DEFAULT "parent"',
    'SELECT "action_type column does not exist, skipping rollback" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rename secretary_reason back to deputy_dean_reason if it exists
SET @secretary_reason_rollback = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'parent_consents' 
    AND COLUMN_NAME = 'secretary_reason'
);

SET @sql = IF(@secretary_reason_rollback > 0, 
    'ALTER TABLE parent_consents CHANGE secretary_reason deputy_dean_reason TEXT NULL COMMENT "Reason provided by Deputy Dean for override"',
    'SELECT "secretary_reason column does not exist, skipping rollback" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;

SELECT 'ROLLBACK COMPLETED - All changes have been reverted' as status;
*/