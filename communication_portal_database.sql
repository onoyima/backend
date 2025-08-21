-- =====================================================
-- Communication Portal Database Schema
-- Generated from Laravel Migrations
-- =====================================================

-- Set SQL mode and character set
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- =====================================================
-- Table: communication_templates
-- Purpose: Store reusable message templates
-- =====================================================
DROP TABLE IF EXISTS `communication_templates`;
CREATE TABLE `communication_templates` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `subject_template` varchar(255) DEFAULT NULL COMMENT 'For email templates',
  `content_template` longtext NOT NULL,
  `template_type` enum('email','sms','whatsapp','multi_channel') NOT NULL,
  `channels` json NOT NULL COMMENT 'Supported channels ["email", "sms", "whatsapp"]',
  `variables` json DEFAULT NULL COMMENT 'Available template variables',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by_id` bigint(20) UNSIGNED NOT NULL,
  `created_by_type` varchar(255) NOT NULL COMMENT 'App\\Models\\Staff',
  `category` varchar(255) DEFAULT NULL COMMENT 'academic, administrative, emergency, etc.',
  `metadata` json DEFAULT NULL COMMENT 'Additional template data',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `communication_templates_name_index` (`name`),
  KEY `communication_templates_template_type_index` (`template_type`),
  KEY `communication_templates_is_active_index` (`is_active`),
  KEY `communication_templates_created_by_id_created_by_type_index` (`created_by_id`,`created_by_type`),
  KEY `communication_templates_category_index` (`category`),
  KEY `communication_templates_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: communication_messages
-- Purpose: Store all communication messages
-- =====================================================
DROP TABLE IF EXISTS `communication_messages`;
CREATE TABLE `communication_messages` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `message_type` enum('announcement','alert','notification','academic','administrative','emergency','marketing','reminder') NOT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `channels` json NOT NULL COMMENT '["email", "sms", "whatsapp"]',
  `sender_id` bigint(20) UNSIGNED NOT NULL,
  `sender_type` varchar(255) NOT NULL COMMENT 'App\\Models\\Staff or App\\Models\\Student',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `status` enum('draft','pending_approval','approved','scheduled','sending','sent','failed','cancelled') NOT NULL DEFAULT 'draft',
  `approval_required` tinyint(1) NOT NULL DEFAULT 0,
  `template_id` bigint(20) UNSIGNED DEFAULT NULL,
  `metadata` json DEFAULT NULL COMMENT 'Additional data like variables, settings',
  `delivery_report` json DEFAULT NULL COMMENT 'Summary of delivery results',
  `total_recipients` int(11) NOT NULL DEFAULT 0,
  `successful_deliveries` int(11) NOT NULL DEFAULT 0,
  `failed_deliveries` int(11) NOT NULL DEFAULT 0,
  `pending_deliveries` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `communication_messages_sender_id_sender_type_index` (`sender_id`,`sender_type`),
  KEY `communication_messages_status_index` (`status`),
  KEY `communication_messages_message_type_index` (`message_type`),
  KEY `communication_messages_priority_index` (`priority`),
  KEY `communication_messages_scheduled_at_index` (`scheduled_at`),
  KEY `communication_messages_sent_at_index` (`sent_at`),
  KEY `communication_messages_created_at_index` (`created_at`),
  KEY `communication_messages_template_id_foreign` (`template_id`),
  CONSTRAINT `communication_messages_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `communication_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: communication_message_recipients
-- Purpose: Store message recipients and delivery status
-- =====================================================
DROP TABLE IF EXISTS `communication_message_recipients`;
CREATE TABLE `communication_message_recipients` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` bigint(20) UNSIGNED NOT NULL,
  `recipient_id` bigint(20) UNSIGNED DEFAULT NULL,
  `recipient_type` varchar(255) DEFAULT NULL COMMENT 'App\\Models\\Staff or App\\Models\\Student',
  `recipient_email` varchar(255) NOT NULL,
  `recipient_phone` varchar(255) DEFAULT NULL,
  `channel` enum('email','sms','whatsapp') NOT NULL,
  `delivery_status` enum('pending','sending','delivered','failed','bounced') NOT NULL DEFAULT 'pending',
  `delivery_attempts` int(11) NOT NULL DEFAULT 0,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `unsubscribed_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL COMMENT 'Provider response, tracking data, etc.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `communication_message_recipients_message_id_index` (`message_id`),
  KEY `comm_msg_recipients_recipient_idx` (`recipient_id`,`recipient_type`),
  KEY `communication_message_recipients_recipient_email_index` (`recipient_email`),
  KEY `communication_message_recipients_recipient_phone_index` (`recipient_phone`),
  KEY `communication_message_recipients_channel_index` (`channel`),
  KEY `communication_message_recipients_delivery_status_index` (`delivery_status`),
  KEY `communication_message_recipients_delivered_at_index` (`delivered_at`),
  KEY `communication_message_recipients_read_at_index` (`read_at`),
  CONSTRAINT `communication_message_recipients_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `communication_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: communication_message_approvals
-- Purpose: Store message approval workflow
-- =====================================================
DROP TABLE IF EXISTS `communication_message_approvals`;
CREATE TABLE `communication_message_approvals` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` bigint(20) UNSIGNED NOT NULL,
  `approver_id` bigint(20) UNSIGNED NOT NULL,
  `approver_type` varchar(255) NOT NULL COMMENT 'App\\Models\\Staff',
  `approval_level` int(11) NOT NULL DEFAULT 1 COMMENT '1, 2, 3 for multi-level approval',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `metadata` json DEFAULT NULL COMMENT 'Additional approval data',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `comm_msg_approvals_msg_approver_level_unique` (`message_id`,`approver_id`,`approval_level`),
  KEY `communication_message_approvals_message_id_index` (`message_id`),
  KEY `communication_message_approvals_approver_id_approver_type_index` (`approver_id`,`approver_type`),
  KEY `communication_message_approvals_status_index` (`status`),
  KEY `communication_message_approvals_approval_level_index` (`approval_level`),
  KEY `communication_message_approvals_approved_at_index` (`approved_at`),
  KEY `communication_message_approvals_rejected_at_index` (`rejected_at`),
  CONSTRAINT `communication_message_approvals_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `communication_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: communication_message_attachments
-- Purpose: Store message file attachments
-- =====================================================
DROP TABLE IF EXISTS `communication_message_attachments`;
CREATE TABLE `communication_message_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` bigint(20) UNSIGNED NOT NULL,
  `filename` varchar(255) NOT NULL COMMENT 'Generated filename',
  `original_filename` varchar(255) NOT NULL COMMENT 'Original uploaded filename',
  `file_path` varchar(255) NOT NULL COMMENT 'Storage path',
  `file_size` bigint(20) UNSIGNED NOT NULL COMMENT 'File size in bytes',
  `mime_type` varchar(255) NOT NULL,
  `file_hash` varchar(255) DEFAULT NULL COMMENT 'For duplicate detection',
  `is_inline` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Inline vs attachment',
  `metadata` json DEFAULT NULL COMMENT 'Additional file metadata',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `communication_message_attachments_message_id_index` (`message_id`),
  KEY `communication_message_attachments_mime_type_index` (`mime_type`),
  KEY `communication_message_attachments_file_hash_index` (`file_hash`),
  KEY `communication_message_attachments_is_inline_index` (`is_inline`),
  CONSTRAINT `communication_message_attachments_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `communication_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: communication_preferences
-- Purpose: Store user communication preferences
-- =====================================================
DROP TABLE IF EXISTS `communication_preferences`;
CREATE TABLE `communication_preferences` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `user_type` varchar(255) NOT NULL COMMENT 'App\\Models\\Staff or App\\Models\\Student',
  `channel` enum('email','sms','whatsapp') NOT NULL,
  `message_type` enum('announcement','alert','notification','academic','administrative','emergency','marketing','reminder') DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `frequency` enum('immediate','hourly','daily','weekly','monthly') NOT NULL DEFAULT 'immediate',
  `quiet_hours_start` time DEFAULT NULL,
  `quiet_hours_end` time DEFAULT NULL,
  `timezone` varchar(255) NOT NULL DEFAULT 'Africa/Lagos',
  `language` varchar(5) NOT NULL DEFAULT 'en',
  `metadata` json DEFAULT NULL COMMENT 'Additional preference data',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `comm_prefs_user_channel_msgtype_unique` (`user_id`,`user_type`,`channel`,`message_type`),
  KEY `communication_preferences_user_id_user_type_index` (`user_id`,`user_type`),
  KEY `communication_preferences_channel_index` (`channel`),
  KEY `communication_preferences_message_type_index` (`message_type`),
  KEY `communication_preferences_is_enabled_index` (`is_enabled`),
  KEY `communication_preferences_frequency_index` (`frequency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: communication_providers
-- Purpose: Store third-party service provider configurations
-- =====================================================
DROP TABLE IF EXISTS `communication_providers`;
CREATE TABLE `communication_providers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Provider name (e.g., "SendGrid", "Twilio")',
  `provider_type` varchar(255) NOT NULL COMMENT 'Provider service type (e.g., "sendgrid", "twilio", "mailgun")',
  `channel` enum('email','sms','whatsapp') NOT NULL,
  `configuration` json NOT NULL COMMENT 'Provider-specific configuration (API keys, endpoints, etc.)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `priority` int(11) NOT NULL DEFAULT 1 COMMENT 'Lower number = higher priority',
  `rate_limit` int(11) DEFAULT NULL COMMENT 'Messages per minute',
  `daily_limit` int(11) DEFAULT NULL COMMENT 'Messages per day',
  `monthly_limit` int(11) DEFAULT NULL COMMENT 'Messages per month',
  `cost_per_message` decimal(8,4) DEFAULT NULL COMMENT 'Cost per message',
  `currency` varchar(3) NOT NULL DEFAULT 'NGN',
  `metadata` json DEFAULT NULL COMMENT 'Additional provider data',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `communication_providers_channel_is_default_unique` (`channel`,`is_default`),
  KEY `communication_providers_name_index` (`name`),
  KEY `communication_providers_provider_type_index` (`provider_type`),
  KEY `communication_providers_channel_index` (`channel`),
  KEY `communication_providers_is_active_index` (`is_active`),
  KEY `communication_providers_is_default_index` (`is_default`),
  KEY `communication_providers_priority_index` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: communication_message_statistics
-- Purpose: Store message delivery and engagement statistics
-- =====================================================
DROP TABLE IF EXISTS `communication_message_statistics`;
CREATE TABLE `communication_message_statistics` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` bigint(20) UNSIGNED NOT NULL,
  `channel` enum('email','sms','whatsapp') NOT NULL,
  `total_recipients` int(11) NOT NULL DEFAULT 0,
  `delivered_count` int(11) NOT NULL DEFAULT 0,
  `failed_count` int(11) NOT NULL DEFAULT 0,
  `pending_count` int(11) NOT NULL DEFAULT 0,
  `read_count` int(11) NOT NULL DEFAULT 0,
  `clicked_count` int(11) NOT NULL DEFAULT 0,
  `unsubscribed_count` int(11) NOT NULL DEFAULT 0,
  `bounce_count` int(11) NOT NULL DEFAULT 0,
  `complaint_count` int(11) NOT NULL DEFAULT 0,
  `delivery_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage',
  `open_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage',
  `click_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage',
  `unsubscribe_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage',
  `bounce_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage',
  `complaint_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage',
  `average_delivery_time` decimal(8,2) DEFAULT NULL COMMENT 'Seconds',
  `cost` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'Total cost for this channel',
  `currency` varchar(3) NOT NULL DEFAULT 'NGN',
  `metadata` json DEFAULT NULL COMMENT 'Additional statistics data',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `communication_message_statistics_message_id_channel_unique` (`message_id`,`channel`),
  KEY `communication_message_statistics_message_id_index` (`message_id`),
  KEY `communication_message_statistics_channel_index` (`channel`),
  KEY `communication_message_statistics_delivery_rate_index` (`delivery_rate`),
  KEY `communication_message_statistics_open_rate_index` (`open_rate`),
  KEY `communication_message_statistics_click_rate_index` (`click_rate`),
  KEY `communication_message_statistics_created_at_index` (`created_at`),
  CONSTRAINT `communication_message_statistics_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `communication_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Insert Sample Data (Optional)
-- =====================================================

-- Sample communication providers
INSERT INTO `communication_providers` (`name`, `provider_type`, `channel`, `configuration`, `is_active`, `is_default`, `priority`, `rate_limit`, `daily_limit`, `cost_per_message`, `currency`) VALUES
('SendGrid', 'sendgrid', 'email', '{"api_key": "your_sendgrid_api_key", "from_email": "noreply@yourschool.edu", "from_name": "Your School"}', 1, 1, 1, 100, 10000, 0.0050, 'USD'),
('Termii', 'termii', 'sms', '{"api_key": "your_termii_api_key", "sender_id": "YourSchool", "channel": "generic"}', 1, 1, 1, 50, 5000, 0.0200, 'NGN'),
('WhatsApp Cloud API', 'whatsapp_cloud', 'whatsapp', '{"access_token": "your_whatsapp_token", "phone_number_id": "your_phone_number_id", "webhook_verify_token": "your_verify_token"}', 1, 1, 1, 80, 8000, 0.0100, 'USD');

-- Sample communication templates
INSERT INTO `communication_templates` (`name`, `description`, `subject_template`, `content_template`, `template_type`, `channels`, `variables`, `is_active`, `created_by_id`, `created_by_type`, `category`) VALUES
('Welcome Message', 'Welcome message for new students', 'Welcome to {{school_name}}', 'Dear {{student_name}},\n\nWelcome to {{school_name}}! We are excited to have you join our community.\n\nBest regards,\nAdministration', 'multi_channel', '["email", "sms"]', '["student_name", "school_name"]', 1, 1, 'App\\Models\\Staff', 'academic'),
('Emergency Alert', 'Emergency notification template', 'URGENT: {{alert_title}}', 'EMERGENCY ALERT\n\n{{alert_message}}\n\nPlease follow instructions from school authorities.\n\nTime: {{timestamp}}', 'multi_channel', '["email", "sms", "whatsapp"]', '["alert_title", "alert_message", "timestamp"]', 1, 1, 'App\\Models\\Staff', 'emergency'),
('Exam Reminder', 'Examination reminder for students', 'Exam Reminder: {{exam_subject}}', 'Dear {{student_name}},\n\nThis is a reminder that your {{exam_subject}} examination is scheduled for {{exam_date}} at {{exam_time}}.\n\nVenue: {{exam_venue}}\n\nGood luck!', 'email', '["email"]', '["student_name", "exam_subject", "exam_date", "exam_time", "exam_venue"]', 1, 1, 'App\\Models\\Staff', 'academic');

-- =====================================================
-- Commit Transaction
-- =====================================================
COMMIT;

-- =====================================================
-- Notes:
-- 1. Replace placeholder values in sample data with actual values
-- 2. Ensure foreign key references match your existing tables
-- 3. Adjust AUTO_INCREMENT values as needed
-- 4. Configure proper indexes for your specific use case
-- 5. Set up proper backup and maintenance procedures
-- =====================================================
