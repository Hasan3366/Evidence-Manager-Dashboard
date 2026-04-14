-- ============================================================
-- Evidence Manager Dashboard - Database Schema
-- MySQL 5.7+ / MariaDB 10.3+
-- ============================================================
-- SETUP INSTRUCTIONS:
--   1. Create a new database:  CREATE DATABASE evidence_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   2. Import this file:       mysql -u root -p evidence_manager < schema.sql
--   3. Log in with:            username = admin | password = Admin@1234
--   4. IMPORTANT: Change the default password immediately after first login.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables (for clean reinstall — remove these lines in production)
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `evidence_files`;
DROP TABLE IF EXISTS `evidence_notes`;
DROP TABLE IF EXISTS `evidence`;
DROP TABLE IF EXISTS `users`;

-- ============================================================
-- TABLE: users
-- Stores administrator and officer accounts
-- ============================================================
CREATE TABLE `users` (
    `id`            INT UNSIGNED            NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(50)             NOT NULL,
    `password_hash` VARCHAR(255)            NOT NULL    COMMENT 'BCrypt hash — generated with password_hash()',
    `full_name`     VARCHAR(100)            NOT NULL,
    `role`          ENUM('admin','officer') NOT NULL    DEFAULT 'officer',
    `badge_number`  VARCHAR(30)             DEFAULT NULL,
    `active`        TINYINT(1)              NOT NULL    DEFAULT 1 COMMENT '1 = active, 0 = disabled',
    `created_at`    DATETIME                NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME                NOT NULL    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: evidence
-- One row per piece of evidence logged at a scene
-- ============================================================
CREATE TABLE `evidence` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `case_number`   VARCHAR(50)     NOT NULL                COMMENT 'Crime scene case reference number',
    `category`      VARCHAR(50)     NOT NULL                COMMENT 'See EVIDENCE_CATEGORIES in constants.php',
    `description`   TEXT            NOT NULL,
    `location`      VARCHAR(255)    DEFAULT NULL            COMMENT 'Scene location or address',
    `collector_id`  INT UNSIGNED    DEFAULT NULL            COMMENT 'Officer who physically collected the item',
    `collected_at`  DATETIME        DEFAULT NULL            COMMENT 'Date/time evidence was collected at the scene',
    `status`        VARCHAR(30)     NOT NULL DEFAULT 'logged' COMMENT 'Workflow status — e.g. logged | in_storage | transferred | analysed | closed',
    `created_by`    INT UNSIGNED    NOT NULL                COMMENT 'User who submitted this record',
    `updated_by`    INT UNSIGNED    DEFAULT NULL            COMMENT 'User who last modified this record',
    `created_at`    DATETIME        NOT NULL                DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL                DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_evidence_case_number`  (`case_number`),
    INDEX `idx_evidence_category`     (`category`),
    INDEX `idx_evidence_collector_id` (`collector_id`),
    INDEX `idx_evidence_collected_at` (`collected_at`),
    CONSTRAINT `fk_evidence_collector`   FOREIGN KEY (`collector_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_evidence_created_by` FOREIGN KEY (`created_by`)   REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_evidence_updated_by` FOREIGN KEY (`updated_by`)   REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: evidence_notes
-- Multiple free-text notes can be attached to one evidence record
-- ============================================================
CREATE TABLE `evidence_notes` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `evidence_id`   INT UNSIGNED    NOT NULL,
    `note_text`     TEXT            NOT NULL,
    `created_by`    INT UNSIGNED    NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_notes_evidence_id` (`evidence_id`),
    INDEX `idx_notes_created_at`  (`created_at`),
    CONSTRAINT `fk_notes_evidence`    FOREIGN KEY (`evidence_id`) REFERENCES `evidence` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notes_created_by` FOREIGN KEY (`created_by`)  REFERENCES `users`    (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: evidence_files
-- Multiple files (photos, PDFs, videos) can be attached per evidence record
-- ============================================================
CREATE TABLE `evidence_files` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `evidence_id`       INT UNSIGNED    NOT NULL,
    `original_filename` VARCHAR(255)    NOT NULL COMMENT 'Filename as provided by the uploader',
    `stored_filename`   VARCHAR(255)    NOT NULL COMMENT 'Randomised filename saved on disk — generated server-side (e.g. bin2hex + extension); never user-controlled',
    `file_path`         VARCHAR(500)    NOT NULL COMMENT 'Relative internal path from project root (e.g. uploads/evidence/abc123.jpg) — never expose this directly; serve via a PHP controller',
    `mime_type`         VARCHAR(100)    NOT NULL COMMENT 'MIME type verified server-side via finfo — do not trust the value submitted by the client',
    `file_size`         INT UNSIGNED    NOT NULL COMMENT 'File size in bytes',
    `uploaded_by`       INT UNSIGNED    NOT NULL,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_files_evidence_id` (`evidence_id`),
    INDEX `idx_files_mime_type`   (`mime_type`),
    INDEX `idx_files_created_at`  (`created_at`),
    CONSTRAINT `fk_files_evidence`    FOREIGN KEY (`evidence_id`) REFERENCES `evidence` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_files_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users`    (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: audit_log
-- Immutable chain of custody — every action on every evidence record is logged
-- ============================================================
CREATE TABLE `audit_log` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `evidence_id`   INT UNSIGNED    DEFAULT NULL COMMENT 'NULL if the action is not evidence-specific (e.g. login)',
    `user_id`       INT UNSIGNED    NOT NULL,
    `action`        VARCHAR(50)     NOT NULL     COMMENT 'e.g. created | updated | viewed | file_uploaded | deleted',
    `detail`        TEXT            DEFAULT NULL COMMENT 'Optional JSON or plain-text summary of what changed',
    `ip_address`    VARCHAR(45)     DEFAULT NULL COMMENT 'IPv4 or IPv6 address of the client at the time of the action — supports full IPv6 length (39 chars max, 45 with mapped notation)',
    `user_agent`    VARCHAR(255)    DEFAULT NULL COMMENT 'Browser/client User-Agent string — useful for detecting unusual access patterns or forensic review',
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_audit_evidence_id` (`evidence_id`),
    INDEX `idx_audit_user_id`     (`user_id`),
    INDEX `idx_audit_created_at`  (`created_at`),
    CONSTRAINT `fk_audit_evidence` FOREIGN KEY (`evidence_id`) REFERENCES `evidence` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_audit_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`    (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DEFAULT ADMIN USER SEED
--
--   Username : admin
--   Password : Admin@1234
--
-- TO REPLACE THIS HASH with your own password, run:
--   php -r "echo password_hash('YourNewPassword', PASSWORD_BCRYPT, ['cost' => 12]);"
-- Then paste the output in place of the hash string below.
-- ============================================================
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `badge_number`, `active`)
VALUES (
    'admin',
    '$2y$12$9Qyp1GfaSsk5HUOGPWMkauFLIWLrtLNoxRTlzP35pXroBvBxuf0Rq', -- Password: Admin@1234 — CHANGE THIS
    'System Administrator',
    'admin',
    'ADM-001',
    1
);
