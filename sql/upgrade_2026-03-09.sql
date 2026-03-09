-- =============================================
-- ملف ترقية قاعدة البيانات
-- نظام التعليم المستمر
-- تاريخ: 2026-03-09
-- =============================================
-- طريقة التطبيق:
--   mysql -u root -p continuous_education < sql/upgrade_2026-03-09.sql
-- أو استيراده عبر phpMyAdmin
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------
-- 1. إضافة عمود email للمشاركين (إن لم يكن موجوداً)
-- -----------------------------------------------
ALTER TABLE `participants`
    ADD COLUMN IF NOT EXISTS `email` VARCHAR(255) NULL DEFAULT NULL AFTER `phone`;

-- -----------------------------------------------
-- 2. إضافة حقل نسبة الحضور الدنيا في الدورات
-- -----------------------------------------------
ALTER TABLE `courses`
    ADD COLUMN IF NOT EXISTS `min_attendance_pct` INT(3) NOT NULL DEFAULT 80 AFTER `status`;

-- -----------------------------------------------
-- 3. جدول الشهادات
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `certificates` (
    `id`               INT(11)      NOT NULL AUTO_INCREMENT,
    `course_id`        INT(11)      NOT NULL,
    `participant_id`   INT(11)      NOT NULL,
    `certificate_code` VARCHAR(64)  NOT NULL,
    `issuer_name`      VARCHAR(255) NOT NULL DEFAULT 'نظام التعليم المستمر',
    `issued_at`        TIMESTAMP    NOT NULL DEFAULT current_timestamp(),
    `created_at`       TIMESTAMP    NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cert_code`              (`certificate_code`),
    UNIQUE KEY `uq_cert_course_participant` (`course_id`, `participant_id`),
    KEY `cert_participant_idx`             (`participant_id`),
    CONSTRAINT `cert_course_fk`
        FOREIGN KEY (`course_id`)      REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `cert_participant_fk`
        FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------
-- 4. جدول طلبات التسجيل عبر الإنترنت
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `course_registration_requests` (
    `id`          INT(11)                             NOT NULL AUTO_INCREMENT,
    `course_id`   INT(11)                             NOT NULL,
    `full_name`   VARCHAR(255)                        NOT NULL,
    `phone`       VARCHAR(20)                         DEFAULT NULL,
    `email`       VARCHAR(255)                        DEFAULT NULL,
    `gender`      ENUM('ذكر','أنثى')                  DEFAULT NULL,
    `work_place`  VARCHAR(255)                        DEFAULT NULL,
    `status`      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `created_at`  TIMESTAMP                           NOT NULL DEFAULT current_timestamp(),
    `handled_at`  TIMESTAMP                           NULL     DEFAULT NULL,
    `handled_by`  INT(11)                             DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `req_course_idx` (`course_id`),
    KEY `req_status_idx` (`status`),
    CONSTRAINT `reg_course_fk`
        FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------
-- 5. جدول القاعات التدريبية
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `rooms` (
    `id`         INT(11)      NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `capacity`   INT(11)      DEFAULT NULL,
    `location`   VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------
-- 6. جدول جدولة القاعات للدورات
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `course_room_schedule` (
    `id`            INT(11)      NOT NULL AUTO_INCREMENT,
    `course_id`     INT(11)      NOT NULL,
    `room_id`       INT(11)      NOT NULL,
    `start_date`    DATE         NOT NULL,
    `end_date`      DATE         NOT NULL,
    `lecture_time`  VARCHAR(100) DEFAULT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `sched_course_idx` (`course_id`),
    KEY `sched_room_idx`   (`room_id`),
    CONSTRAINT `schedule_course_fk`
        FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `schedule_room_fk`
        FOREIGN KEY (`room_id`)   REFERENCES `rooms`   (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------
-- 7. جدول الحضور اليومي
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS `attendance` (
    `id`             INT(11)                  NOT NULL AUTO_INCREMENT,
    `course_id`      INT(11)                  NOT NULL,
    `participant_id` INT(11)                  NOT NULL,
    `att_date`       DATE                     NOT NULL,
    `status`         ENUM('present','absent') NOT NULL DEFAULT 'present',
    `created_at`     TIMESTAMP                NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_attendance` (`course_id`, `participant_id`, `att_date`),
    KEY `att_course_idx`       (`course_id`),
    KEY `att_participant_idx`  (`participant_id`),
    CONSTRAINT `att_course_fk`
        FOREIGN KEY (`course_id`)      REFERENCES `courses`      (`id`) ON DELETE CASCADE,
    CONSTRAINT `att_participant_fk`
        FOREIGN KEY (`participant_id`) REFERENCES `participants`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- انتهى ملف الترقية
-- =============================================
