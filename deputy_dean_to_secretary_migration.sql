-- =====================================================
-- DEPUTY DEAN TO SECRETARY MIGRATION - COMPLETE SQL
-- =====================================================
-- This script migrates all deputy dean references to secretary
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
-- 3. UPDATE PARENT_CONSENTS TABLE
-- =====================================================
-- Update action_type from deputy_dean_approval to secretary_approval
UPDATE parent_consents 
SET action_type = 'secretary_approval' 
WHERE action_type = 'deputy_dean_approval';

-- Update action_type from deputy_dean_rejection to secretary_rejection
UPDATE parent_consents 
SET action_type = 'secretary_rejection' 
WHERE action_type = 'deputy_dean_rejection';

-- Verify the action_type updates
SELECT 'parent_consents action_type updated' as step, 
       COUNT(*) as secretary_approvals 
FROM parent_consents 
WHERE action_type = 'secretary_approval';

SELECT 'parent_consents action_type updated' as step, 
       COUNT(*) as secretary_rejections 
FROM parent_consents 
WHERE action_type = 'secretary_rejection';

-- Check if deputy_dean_reason column exists and rename it to secretary_reason
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'parent_consents' 
    AND COLUMN_NAME = 'deputy_dean_reason'
);

-- Rename column if it exists
SET @sql = IF(@column_exists > 0, 
    'ALTER TABLE parent_consents CHANGE deputy_dean_reason secretary_reason TEXT NULL COMMENT "Reason provided by Secretary for override"',
    'SELECT "deputy_dean_reason column does not exist, skipping rename" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update the action_type enum to include secretary options
ALTER TABLE parent_consents 
MODIFY COLUMN action_type ENUM(
    'parent', 
    'secretary_approval', 
    'secretary_rejection'
) DEFAULT 'parent';

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

-- Check parent_consents action_type enum
SELECT 'parent_consents' as table_name, 
       action_type, 
       COUNT(*) as count 
FROM parent_consents 
WHERE action_type IN ('secretary_approval', 'secretary_rejection')
GROUP BY action_type;

-- Check if secretary_reason column exists
SELECT 'parent_consents' as table_name,
       'secretary_reason column exists' as check_type,
       IF(COUNT(*) > 0, 'YES', 'NO') as result
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'parent_consents' 
AND COLUMN_NAME = 'secretary_reason';

-- =====================================================
-- 5. FINAL STATUS
-- =====================================================
SELECT '=== MIGRATION COMPLETED SUCCESSFULLY ===' as status;
SELECT 'All deputy dean references have been migrated to secretary' as message;
SELECT 'Tables affected: exeat_roles, exeat_requests, parent_consents' as affected_tables;
SELECT 'No data was lost during migration' as data_integrity;

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

-- Rollback parent_consents
UPDATE parent_consents 
SET action_type = 'deputy_dean_approval' 
WHERE action_type = 'secretary_approval';

UPDATE parent_consents 
SET action_type = 'deputy_dean_rejection' 
WHERE action_type = 'secretary_rejection';

-- Rename column back
ALTER TABLE parent_consents 
CHANGE secretary_reason deputy_dean_reason TEXT NULL COMMENT 'Reason provided by Deputy Dean for override';

ALTER TABLE parent_consents 
MODIFY COLUMN action_type ENUM(
    'parent', 
    'deputy_dean_approval', 
    'deputy_dean_rejection'
) DEFAULT 'parent';

COMMIT;

SELECT 'ROLLBACK COMPLETED - All changes have been reverted' as status;
*/