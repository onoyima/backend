
-- =====================================================
-- DROP TABLES IF THEY EXIST
-- =====================================================
DROP TABLE IF EXISTS `notification_delivery_logs`;
DROP TABLE IF EXISTS `notification_preferences`;
DROP TABLE IF EXISTS `exeat_notifications`;

-- =====================================================
-- EXEAT NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE `exeat_notifications` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `exeat_request_id` bigint(20) UNSIGNED NOT NULL,
    `recipient_type` enum('student','staff','admin') NOT NULL,
    `recipient_id` bigint(20) UNSIGNED NOT NULL,
    `notification_type` enum('request_submitted','approval_required','approved','rejected','parent_consent_required','parent_consent_approved','parent_consent_rejected','hostel_signout_required','security_signout_required','completed','cancelled','reminder') NOT NULL,
    `title` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `data` json DEFAULT NULL,
    `status` enum('pending','sent','delivered','failed','read') DEFAULT 'pending',
    `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
    `scheduled_at` timestamp NULL DEFAULT NULL,
    `sent_at` timestamp NULL DEFAULT NULL,
    `delivered_at` timestamp NULL DEFAULT NULL,
    `read_at` timestamp NULL DEFAULT NULL,
    `failed_at` timestamp NULL DEFAULT NULL,
    `failure_reason` text DEFAULT NULL,
    `retry_count` int(11) DEFAULT 0,
    `max_retries` int(11) DEFAULT 3,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_exeat_notifications_exeat_request` (`exeat_request_id`),
    KEY `idx_exeat_notifications_recipient` (`recipient_type`, `recipient_id`),
    KEY `idx_exeat_notifications_type` (`notification_type`),
    KEY `idx_exeat_notifications_status` (`status`),
    KEY `idx_exeat_notifications_priority` (`priority`),
    KEY `idx_exeat_notifications_scheduled` (`scheduled_at`),
    KEY `idx_exeat_notifications_created` (`created_at`),
    CONSTRAINT `fk_exeat_notifications_exeat_request` FOREIGN KEY (`exeat_request_id`) REFERENCES `exeat_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTIFICATION PREFERENCES TABLE
-- =====================================================
CREATE TABLE `notification_preferences` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_type` enum('student','staff','admin') NOT NULL,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `notification_type` enum('request_submitted','approval_required','approved','rejected','parent_consent_required','parent_consent_approved','parent_consent_rejected','hostel_signout_required','security_signout_required','completed','cancelled','reminder','all') NOT NULL,
    `channel` enum('email','sms','push','in_app','all') NOT NULL,
    `enabled` boolean DEFAULT true,
    `quiet_hours_start` time DEFAULT NULL,
    `quiet_hours_end` time DEFAULT NULL,
    `frequency` enum('immediate','hourly','daily','weekly') DEFAULT 'immediate',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_notification_channel` (`user_type`, `user_id`, `notification_type`, `channel`),
    KEY `idx_notification_preferences_user` (`user_type`, `user_id`),
    KEY `idx_notification_preferences_type` (`notification_type`),
    KEY `idx_notification_preferences_channel` (`channel`),
    KEY `idx_notification_preferences_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTIFICATION DELIVERY LOGS TABLE
-- =====================================================
CREATE TABLE `notification_delivery_logs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `notification_id` bigint(20) UNSIGNED NOT NULL,
    `channel` enum('email','sms','push','in_app') NOT NULL,
    `recipient` varchar(255) NOT NULL,
    `status` enum('pending','sent','delivered','failed','bounced','rejected') NOT NULL,
    `provider` varchar(100) DEFAULT NULL,
    `provider_message_id` varchar(255) DEFAULT NULL,
    `response_data` json DEFAULT NULL,
    `error_message` text DEFAULT NULL,
    `sent_at` timestamp NULL DEFAULT NULL,
    `delivered_at` timestamp NULL DEFAULT NULL,
    `failed_at` timestamp NULL DEFAULT NULL,
    `retry_count` int(11) DEFAULT 0,
    `cost` decimal(10,4) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_notification_delivery_logs_notification` (`notification_id`),
    KEY `idx_notification_delivery_logs_channel` (`channel`),
    KEY `idx_notification_delivery_logs_status` (`status`),
    KEY `idx_notification_delivery_logs_recipient` (`recipient`),
    KEY `idx_notification_delivery_logs_provider` (`provider`),
    KEY `idx_notification_delivery_logs_sent` (`sent_at`),
    KEY `idx_notification_delivery_logs_created` (`created_at`),
    CONSTRAINT `fk_notification_delivery_logs_notification` FOREIGN KEY (`notification_id`) REFERENCES `exeat_notifications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SET AUTO-INCREMENT STARTING VALUES
-- =====================================================
ALTER TABLE `exeat_notifications` AUTO_INCREMENT = 1;
ALTER TABLE `notification_preferences` AUTO_INCREMENT = 1;
ALTER TABLE `notification_delivery_logs` AUTO_INCREMENT = 1;

-- =====================================================
-- INSERT DEFAULT NOTIFICATION PREFERENCES
-- =====================================================
-- Note: These are example default preferences
-- You may want to customize these based on your requirements

-- Default preferences for all notification types and channels
-- INSERT INTO `notification_preferences` (`user_type`, `user_id`, `notification_type`, `channel`, `enabled`, `frequency`, `created_at`, `updated_at`) VALUES
-- Example: Enable all notifications for all channels (you'll need to adjust user_id values)
-- ('student', 1, 'all', 'email', true, 'immediate', NOW(), NOW()),
-- ('student', 1, 'all', 'in_app', true, 'immediate', NOW(), NOW()),
-- ('staff', 1, 'all', 'email', true, 'immediate', NOW(), NOW()),
-- ('staff', 1, 'all', 'in_app', true, 'immediate', NOW(), NOW());

-- =====================================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
-- =====================================================

-- Additional composite indexes for common queries
CREATE INDEX `idx_exeat_notifications_recipient_status` ON `exeat_notifications` (`recipient_type`, `recipient_id`, `status`);
CREATE INDEX `idx_exeat_notifications_type_status` ON `exeat_notifications` (`notification_type`, `status`);
CREATE INDEX `idx_exeat_notifications_priority_scheduled` ON `exeat_notifications` (`priority`, `scheduled_at`);

CREATE INDEX `idx_notification_preferences_user_enabled` ON `notification_preferences` (`user_type`, `user_id`, `enabled`);
CREATE INDEX `idx_notification_preferences_type_enabled` ON `notification_preferences` (`notification_type`, `enabled`);

CREATE INDEX `idx_notification_delivery_logs_status_channel` ON `notification_delivery_logs` (`status`, `channel`);
CREATE INDEX `idx_notification_delivery_logs_notification_status` ON `notification_delivery_logs` (`notification_id`, `status`);

-- =====================================================
-- PARENT CONSENTS TABLE MODIFICATIONS
-- =====================================================
-- Add columns to existing parent_consents table for Deputy Dean functionality

-- Add acted_by_staff_id column (nullable foreign key to staff table)
ALTER TABLE `parent_consents`
ADD COLUMN `acted_by_staff_id` bigint(20) UNSIGNED NULL
AFTER `student_contact_id`,
ADD CONSTRAINT `fk_parent_consents_acted_by_staff`
FOREIGN KEY (`acted_by_staff_id`) REFERENCES `staff` (`id`)
ON DELETE SET NULL;

-- Add action_type column (enum to track who performed the consent action)
ALTER TABLE `parent_consents`
ADD COLUMN `action_type` enum('parent','deputy_dean') NOT NULL DEFAULT 'parent'
AFTER `acted_by_staff_id`;

-- Add indexes for performance optimization
CREATE INDEX `idx_parent_consents_acted_by_staff` ON `parent_consents` (`acted_by_staff_id`);
CREATE INDEX `idx_parent_consents_action_type` ON `parent_consents` (`action_type`);
CREATE INDEX `idx_parent_consents_exeat_action` ON `parent_consents` (`exeat_request_id`, `action_type`);

