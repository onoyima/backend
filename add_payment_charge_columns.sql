-- SQL statements to add payment charge columns to student_exeat_debts table
-- These columns support the new direct payment functionality with 2.5% processing charge

-- Add processing_charge column (decimal, nullable, after amount)
ALTER TABLE `student_exeat_debts` 
ADD COLUMN `processing_charge` DECIMAL(10,2) NULL 
AFTER `amount`;

-- Add total_amount_with_charge column (decimal, nullable, after processing_charge)
ALTER TABLE `student_exeat_debts` 
ADD COLUMN `total_amount_with_charge` DECIMAL(10,2) NULL 
AFTER `processing_charge`;

-- Optional: Add comments to describe the columns
ALTER TABLE `student_exeat_debts` 
MODIFY COLUMN `processing_charge` DECIMAL(10,2) NULL COMMENT 'Processing charge (2.5% of original amount)',
MODIFY COLUMN `total_amount_with_charge` DECIMAL(10,2) NULL COMMENT 'Total amount including processing charge';

-- To rollback these changes (if needed), use:
-- ALTER TABLE `student_exeat_debts` DROP COLUMN `processing_charge`;
-- ALTER TABLE `student_exeat_debts` DROP COLUMN `total_amount_with_charge`;