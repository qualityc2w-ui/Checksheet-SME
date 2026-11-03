-- Checksheet-SME anonymized sample database, converted to MySQL/InnoDB.
-- Import into a new, empty MySQL database (MySQL 8.0+), for example:
--   CREATE DATABASE checksheet_sme CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   mysql -u USER -p checksheet_sme < Checksheet-SME-anonymized.mysql.sql
-- This is anonymized sample data, not an operational backup.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `areas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(255) NOT NULL,
  `keterangan` VARCHAR(255) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `parent_id` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `areas_nama_unique` (`nama`),
  CONSTRAINT `fk_areas_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `areas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache` (
  `key` VARCHAR(255) NOT NULL,
  `value` LONGTEXT NOT NULL,
  `expiration` BIGINT NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
  `key` VARCHAR(255) NOT NULL,
  `owner` VARCHAR(255) NOT NULL,
  `expiration` BIGINT NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `defects` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(255) NOT NULL,
  `tipe` VARCHAR(255) NOT NULL DEFAULT 'Visual',
  `kategori` VARCHAR(255) NOT NULL DEFAULT 'Minor',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(255) NOT NULL,
  `connection` LONGTEXT NOT NULL,
  `queue` LONGTEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `finding_lookup_options` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kind` VARCHAR(30) NOT NULL,
  `value` VARCHAR(255) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `finding_lookup_options_kind_value_unique` (`kind`, `value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `finding_pics` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `department` VARCHAR(255) NOT NULL DEFAULT '',
  `phone` VARCHAR(255) NULL,
  `active` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `email` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `finding_pics_active_name_index` (`active`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inspectors` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(255) NOT NULL,
  `dept` VARCHAR(255) NOT NULL DEFAULT '',
  `nomor_wa` VARCHAR(40) NULL,
  `email` VARCHAR(255) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_batches` (
  `id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `total_jobs` BIGINT NOT NULL,
  `pending_jobs` BIGINT NOT NULL,
  `failed_jobs` BIGINT NOT NULL,
  `failed_job_ids` LONGTEXT NOT NULL,
  `options` LONGTEXT NULL,
  `cancelled_at` BIGINT NULL,
  `created_at` BIGINT NOT NULL,
  `finished_at` BIGINT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `attempts` BIGINT NOT NULL,
  `reserved_at` BIGINT NULL,
  `available_at` BIGINT NOT NULL,
  `created_at` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `migrations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` LONGTEXT NULL,
  `payload` LONGTEXT NOT NULL,
  `last_activity` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_last_activity_index` (`last_activity`),
  KEY `sessions_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` DATETIME NULL,
  `password` VARCHAR(255) NOT NULL,
  `remember_token` VARCHAR(255) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `role` VARCHAR(30) NOT NULL DEFAULT 'inspector',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` VARCHAR(100) NOT NULL,
  `nama` VARCHAR(255) NOT NULL,
  `tipe` VARCHAR(255) NULL,
  `area` VARCHAR(255) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `area_id` BIGINT UNSIGNED NULL,
  `image_path` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_kode_unique` (`kode`),
  CONSTRAINT `fk_products_area_id` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dimension_standards` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `parameter` VARCHAR(255) NOT NULL,
  `nilai_standar` DECIMAL(12,4) NOT NULL,
  `toleransi_min` DECIMAL(12,4) NOT NULL DEFAULT '0',
  `toleransi_max` DECIMAL(12,4) NOT NULL DEFAULT '0',
  `satuan` VARCHAR(255) NOT NULL DEFAULT 'mm',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_dimension_standards_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `function_standards` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `checklist` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_function_standards_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inspections` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tanggal` DATE NOT NULL,
  `product_id` BIGINT UNSIGNED NULL,
  `inspector_id` BIGINT UNSIGNED NULL,
  `shift` VARCHAR(50) NOT NULL DEFAULT 'Shift 1',
  `sesi` VARCHAR(30) NOT NULL DEFAULT 'Start',
  `sample` BIGINT NOT NULL DEFAULT '1',
  `dimensi` LONGTEXT NOT NULL,
  `visual` LONGTEXT NOT NULL,
  `fungsi` LONGTEXT NOT NULL,
  `hasil_akhir` VARCHAR(30) NOT NULL DEFAULT 'Lulus',
  `approved` TINYINT(1) NOT NULL DEFAULT '0',
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `evidence` LONGTEXT NULL,
  `area_id` BIGINT UNSIGNED NULL,
  `whatsapp_reported_at` DATETIME NULL,
  `approved_by` BIGINT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `measurement_mode` VARCHAR(30) NOT NULL DEFAULT 'manual',
  `user_id` BIGINT UNSIGNED NULL,
  `email_sent_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_inspections_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inspections_area_id` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inspections_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inspections_inspector_id` FOREIGN KEY (`inspector_id`) REFERENCES `inspectors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inspections_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `visual_standards` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `checklist` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `image_path` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_visual_standards_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `findings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(255) NULL,
  `title` VARCHAR(255) NOT NULL,
  `part_name` VARCHAR(255) NOT NULL,
  `part_type` VARCHAR(255) NOT NULL,
  `found_date` DATE NOT NULL,
  `shift` VARCHAR(40) NOT NULL,
  `area_line` VARCHAR(255) NOT NULL,
  `problem_type` VARCHAR(255) NOT NULL,
  `description` LONGTEXT NOT NULL,
  `category` VARCHAR(20) NOT NULL,
  `priority` VARCHAR(20) NOT NULL DEFAULT 'medium',
  `analysis_man` LONGTEXT NOT NULL,
  `analysis_method` LONGTEXT NOT NULL,
  `analysis_machine` LONGTEXT NOT NULL,
  `analysis_material` LONGTEXT NOT NULL,
  `root_cause` LONGTEXT NOT NULL,
  `pic_id` BIGINT UNSIGNED NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'open',
  `response_token` VARCHAR(64) NOT NULL,
  `response_token_expires_at` DATETIME NULL,
  `due_date` DATE NULL,
  `pic_plan` LONGTEXT NOT NULL,
  `responded_at` DATETIME NULL,
  `completion_note` LONGTEXT NOT NULL,
  `problem_photo_path` VARCHAR(255) NULL,
  `completion_photo_path` VARCHAR(255) NULL,
  `completed_at` DATETIME NULL,
  `verified_at` DATETIME NULL,
  `verification_note` LONGTEXT NOT NULL,
  `wa_sent_at` DATETIME NULL,
  `reporter_id` BIGINT UNSIGNED NULL,
  `reporter_name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `inspection_id` BIGINT UNSIGNED NULL,
  `inspection_section` VARCHAR(40) NULL,
  `inspection_item_index` BIGINT NULL,
  `point_check` VARCHAR(255) NULL,
  `observed_value` VARCHAR(255) NULL,
  `standard_value` VARCHAR(255) NULL,
  `minimum_value` VARCHAR(255) NULL,
  `maximum_value` VARCHAR(255) NULL,
  `unit` VARCHAR(30) NULL,
  `email_sent_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `findings_inspection_point_unique` (`inspection_id`, `inspection_section`, `inspection_item_index`),
  KEY `findings_status_found_date_index` (`status`, `found_date`),
  UNIQUE KEY `findings_response_token_unique` (`response_token`),
  KEY `findings_part_name_part_type_index` (`part_name`, `part_type`),
  UNIQUE KEY `findings_code_unique` (`code`),
  KEY `findings_area_line_index` (`area_line`),
  CONSTRAINT `fk_findings_inspection_id` FOREIGN KEY (`inspection_id`) REFERENCES `inspections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_findings_pic_id` FOREIGN KEY (`pic_id`) REFERENCES `finding_pics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_findings_reporter_id` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `areas` (`id`, `nama`, `keterangan`, `created_at`, `updated_at`, `parent_id`) VALUES
(4, 'Demo Area 4', 'Anonymized sample area', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(5, 'Demo Area 5', 'Anonymized sample area', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(6, 'Demo Area 6', 'Anonymized sample area', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(7, 'Demo Area 7', 'Anonymized sample area', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(8, 'Demo Area 8', 'Anonymized sample area', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL);

INSERT INTO `defects` (`id`, `nama`, `tipe`, `kategori`, `created_at`, `updated_at`) VALUES
(1, 'Demo Defect 1', 'Demo type', 'Demo category', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(2, 'Demo Defect 2', 'Demo type', 'Demo category', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(3, 'Demo Defect 3', 'Demo type', 'Demo category', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(4, 'Demo Defect 4', 'Demo type', 'Demo category', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(5, 'Demo Defect 5', 'Demo type', 'Demo category', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(6, 'Demo Defect 6', 'Demo type', 'Demo category', '2026-01-01 00:00:00', '2026-01-01 00:00:00');

INSERT INTO `finding_lookup_options` (`id`, `kind`, `value`, `active`, `created_at`, `updated_at`) VALUES
(1, 'demo', 'Demo option 1', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(2, 'demo', 'Demo option 2', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(3, 'demo', 'Demo option 3', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(4, 'demo', 'Demo option 4', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(5, 'demo', 'Demo option 5', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(6, 'demo', 'Demo option 6', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(7, 'demo', 'Demo option 7', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(8, 'demo', 'Demo option 8', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(9, 'demo', 'Demo option 9', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(10, 'demo', 'Demo option 10', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(11, 'demo', 'Demo option 11', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(12, 'demo', 'Demo option 12', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(13, 'demo', 'Demo option 13', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(14, 'demo', 'Demo option 14', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(15, 'demo', 'Demo option 15', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(16, 'demo', 'Demo option 16', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00');

INSERT INTO `finding_pics` (`id`, `name`, `department`, `phone`, `active`, `created_at`, `updated_at`, `email`) VALUES
(1, 'Demo PIC 1', 'Demo department', '0000000001', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00', 'demo-pic-1@example.test');

INSERT INTO `inspectors` (`id`, `nama`, `dept`, `nomor_wa`, `email`, `created_at`, `updated_at`) VALUES
(3, 'Demo Inspector 3', 'Demo department', NULL, 'demo-inspector-3@example.test', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(4, 'Demo Inspector 4', 'Demo department', NULL, 'demo-inspector-4@example.test', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(5, 'Demo Inspector 5', 'Demo department', NULL, 'demo-inspector-5@example.test', '2026-01-01 00:00:00', '2026-01-01 00:00:00');

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_10_09_000000_create_qc_inspection_tables', 2),
(5, '2026_10_09_000001_add_evidence_to_inspections', 3),
(6, '2026_10_10_000000_add_area_roles_and_inspection_reporting', 4),
(7, '2026_10_10_000001_add_building_point_check_and_approval', 5),
(8, '2026_10_10_000002_add_point_check_image_to_products', 6),
(9, '2026_10_11_000000_create_findings_module_tables', 7),
(10, '2026_10_12_000000_link_findings_to_inspections', 8),
(11, '2026_10_12_000001_add_visual_function_finding_types', 8),
(12, '2026_10_13_000000_add_measurement_mode_to_inspections', 9),
(13, '2026_10_18_000000_add_user_id_to_inspections', 10),
(14, '2026_10_18_000001_add_email_notifications', 11);

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `role`) VALUES
(1, 'Demo User 1', 'demo-user-1@example.test', NULL, 'disabled-203f737dd1bccd03e8c2d377224ce295921b8708751d170ef67513e3fc4ab8d9', NULL, '2026-01-01 00:00:00', '2026-01-01 00:00:00', 'user'),
(2, 'Demo User 2', 'demo-user-2@example.test', NULL, 'disabled-e135055a607a483e53d8c27cbf8912b77e9d7c9fdec7ebcd289fe9d24b01ff5f', NULL, '2026-01-01 00:00:00', '2026-01-01 00:00:00', 'user'),
(3, 'Demo User 3', 'demo-user-3@example.test', NULL, 'disabled-676a4e42733e52e33a24d1e0b49cc5f379f81ab58d2476d0363f3b11e7f0d671', NULL, '2026-01-01 00:00:00', '2026-01-01 00:00:00', 'user'),
(4, 'Demo User 4', 'demo-user-4@example.test', NULL, 'disabled-b79acaff5c70b2807cda5e3be87e076134efd363ab2f8401e33fec1668c9b497', NULL, '2026-01-01 00:00:00', '2026-01-01 00:00:00', 'user');

INSERT INTO `products` (`id`, `kode`, `nama`, `tipe`, `area`, `created_at`, `updated_at`, `area_id`, `image_path`) VALUES
(4, 'DEMO-4', 'Demo Product 4', 'Demo type', 'Demo area', '2026-01-01 00:00:00', '2026-01-01 00:00:00', 4, NULL),
(5, 'DEMO-5', 'Demo Product 5', 'Demo type', 'Demo area', '2026-01-01 00:00:00', '2026-01-01 00:00:00', 4, NULL),
(6, 'DEMO-6', 'Demo Product 6', 'Demo type', 'Demo area', '2026-01-01 00:00:00', '2026-01-01 00:00:00', 5, NULL);

INSERT INTO `dimension_standards` (`id`, `product_id`, `parameter`, `nilai_standar`, `toleransi_min`, `toleransi_max`, `satuan`, `created_at`, `updated_at`) VALUES
(4, 4, 'Demo Measurement 4', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(5, 4, 'Demo Measurement 5', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(6, 4, 'Demo Measurement 6', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(7, 4, 'Demo Measurement 7', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(8, 4, 'Demo Measurement 8', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(9, 4, 'Demo Measurement 9', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(10, 4, 'Demo Measurement 10', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(11, 4, 'Demo Measurement 11', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(12, 4, 'Demo Measurement 12', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(13, 4, 'Demo Measurement 13', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(14, 4, 'Demo Measurement 14', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(15, 4, 'Demo Measurement 15', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(16, 4, 'Demo Measurement 16', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(17, 4, 'Demo Measurement 17', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(18, 4, 'Demo Measurement 18', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(19, 4, 'Demo Measurement 19', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(20, 4, 'Demo Measurement 20', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(21, 4, 'Demo Measurement 21', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(22, 4, 'Demo Measurement 22', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(23, 4, 'Demo Measurement 23', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(24, 4, 'Demo Measurement 24', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(25, 4, 'Demo Measurement 25', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(26, 4, 'Demo Measurement 26', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(27, 4, 'Demo Measurement 27', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(28, 4, 'Demo Measurement 28', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(29, 4, 'Demo Measurement 29', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(30, 4, 'Demo Measurement 30', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(31, 4, 'Demo Measurement 31', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(32, 4, 'Demo Measurement 32', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(33, 5, 'Demo Measurement 33', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(34, 5, 'Demo Measurement 34', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(35, 5, 'Demo Measurement 35', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(36, 5, 'Demo Measurement 36', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(37, 5, 'Demo Measurement 37', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(38, 5, 'Demo Measurement 38', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(39, 5, 'Demo Measurement 39', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(40, 5, 'Demo Measurement 40', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(41, 5, 'Demo Measurement 41', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(42, 5, 'Demo Measurement 42', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(43, 5, 'Demo Measurement 43', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(44, 5, 'Demo Measurement 44', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(45, 5, 'Demo Measurement 45', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(46, 5, 'Demo Measurement 46', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(47, 5, 'Demo Measurement 47', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(48, 5, 'Demo Measurement 48', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(49, 5, 'Demo Measurement 49', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(50, 5, 'Demo Measurement 50', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(51, 5, 'Demo Measurement 51', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(52, 5, 'Demo Measurement 52', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(53, 5, 'Demo Measurement 53', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00');

INSERT INTO `dimension_standards` (`id`, `product_id`, `parameter`, `nilai_standar`, `toleransi_min`, `toleransi_max`, `satuan`, `created_at`, `updated_at`) VALUES
(54, 5, 'Demo Measurement 54', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(55, 5, 'Demo Measurement 55', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(56, 5, 'Demo Measurement 56', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(57, 5, 'Demo Measurement 57', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(58, 5, 'Demo Measurement 58', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(59, 6, 'Demo Measurement 59', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(60, 6, 'Demo Measurement 60', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(61, 6, 'Demo Measurement 61', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(63, 6, 'Demo Measurement 63', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(64, 6, 'Demo Measurement 64', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(65, 6, 'Demo Measurement 65', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(66, 6, 'Demo Measurement 66', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(67, 6, 'Demo Measurement 67', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(68, 6, 'Demo Measurement 68', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(69, 6, 'Demo Measurement 69', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00'),
(70, 6, 'Demo Measurement 70', 10, 0, 0, 'mm', '2026-01-01 00:00:00', '2026-01-01 00:00:00');

INSERT INTO `function_standards` (`id`, `product_id`, `checklist`, `created_at`, `updated_at`) VALUES
(4, 4, 'Demo function check 4', '2026-01-01 00:00:00', '2026-01-01 00:00:00');

INSERT INTO `inspections` (`id`, `tanggal`, `product_id`, `inspector_id`, `shift`, `sesi`, `sample`, `dimensi`, `visual`, `fungsi`, `hasil_akhir`, `approved`, `created_at`, `updated_at`, `evidence`, `area_id`, `whatsapp_reported_at`, `approved_by`, `approved_at`, `measurement_mode`, `user_id`, `email_sent_at`) VALUES
(2, '2026-01-01', 4, 3, 'Demo shift', 'Demo', 1, '[]', '[]', '[]', 'Lulus', 0, '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL, 4, NULL, NULL, NULL, 'Demo', NULL, NULL),
(3, '2026-01-01', 6, 3, 'Demo shift', 'Demo', 1, '[]', '[]', '[]', 'Lulus', 0, '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL, 5, NULL, NULL, NULL, 'Demo', NULL, NULL),
(4, '2026-01-01', 6, 3, 'Demo shift', 'Demo', 1, '[]', '[]', '[]', 'Lulus', 0, '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL, 5, NULL, NULL, NULL, 'Demo', NULL, NULL),
(5, '2026-01-01', 5, 3, 'Demo shift', 'Demo', 1, '[]', '[]', '[]', 'Lulus', 0, '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL, 4, NULL, NULL, NULL, 'Demo', NULL, NULL),
(6, '2026-01-01', 5, 4, 'Demo shift', 'Demo', 1, '[]', '[]', '[]', 'Lulus', 0, '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL, 4, NULL, NULL, NULL, 'Demo', NULL, NULL);

INSERT INTO `visual_standards` (`id`, `product_id`, `checklist`, `created_at`, `updated_at`, `image_path`) VALUES
(8, 4, 'Demo visual check 8', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(9, 6, 'Demo visual check 9', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(10, 6, 'Demo visual check 10', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(11, 6, 'Demo visual check 11', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(12, 6, 'Demo visual check 12', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(13, 6, 'Demo visual check 13', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(14, 6, 'Demo visual check 14', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(15, 6, 'Demo visual check 15', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(16, 6, 'Demo visual check 16', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(17, 6, 'Demo visual check 17', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(18, 6, 'Demo visual check 18', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(19, 6, 'Demo visual check 19', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(20, 6, 'Demo visual check 20', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL),
(21, 6, 'Demo visual check 21', '2026-01-01 00:00:00', '2026-01-01 00:00:00', NULL);

INSERT INTO `findings` (`id`, `code`, `title`, `part_name`, `part_type`, `found_date`, `shift`, `area_line`, `problem_type`, `description`, `category`, `priority`, `analysis_man`, `analysis_method`, `analysis_machine`, `analysis_material`, `root_cause`, `pic_id`, `status`, `response_token`, `due_date`, `pic_plan`, `responded_at`, `completion_note`, `problem_photo_path`, `completion_photo_path`, `completed_at`, `verified_at`, `verification_note`, `wa_sent_at`, `reporter_id`, `reporter_name`, `created_at`, `updated_at`, `inspection_id`, `inspection_section`, `inspection_item_index`, `point_check`, `observed_value`, `standard_value`, `minimum_value`, `maximum_value`, `unit`, `email_sent_at`) VALUES
(1, 'DEMO-F1', 'Demo finding 1', 'Demo part', 'Demo type', '2026-01-01', 'Demo shift', 'Demo line', 'Demo issue', 'Anonymized sample finding', 'Demo', 'medium', 'Anonymized', 'Anonymized', 'Anonymized', 'Anonymized', 'Anonymized', 1, 'open', '578dcfdefc38982e2fc2545a12366032a8f506b7f606853896b977a814d9331b', NULL, 'Anonymized', NULL, '', NULL, NULL, NULL, NULL, '', NULL, NULL, 'Demo Reporter 1', '2026-01-01 00:00:00', '2026-01-01 00:00:00', 5, 'Demo 1', 1, 'Demo check', '0', '0', '0', '0', 'unit', NULL),
(2, 'DEMO-F2', 'Demo finding 2', 'Demo part', 'Demo type', '2026-01-01', 'Demo shift', 'Demo line', 'Demo issue', 'Anonymized sample finding', 'Demo', 'medium', 'Anonymized', 'Anonymized', 'Anonymized', 'Anonymized', 'Anonymized', 1, 'open', '248fb12a0f28d246d48f399cd79c4fb7c8bb67dac446ef3e23728e679aead147', NULL, 'Anonymized', NULL, '', NULL, NULL, NULL, NULL, '', NULL, NULL, 'Demo Reporter 2', '2026-01-01 00:00:00', '2026-01-01 00:00:00', 5, 'Demo 2', 1, 'Demo check', '0', '0', '0', '0', 'unit', NULL),
(3, 'DEMO-F3', 'Demo finding 3', 'Demo part', 'Demo type', '2026-01-01', 'Demo shift', 'Demo line', 'Demo issue', 'Anonymized sample finding', 'Demo', 'medium', 'Anonymized', 'Anonymized', 'Anonymized', 'Anonymized', 'Anonymized', 1, 'open', '6aaf3242d58f641da9047865dc9b4f740d7e8617031c6b825ba1d828019bee6e', NULL, 'Anonymized', NULL, '', NULL, NULL, NULL, NULL, '', NULL, NULL, 'Demo Reporter 3', '2026-01-01 00:00:00', '2026-01-01 00:00:00', 5, 'Demo 3', 1, 'Demo check', '0', '0', '0', '0', 'unit', NULL),
(4, 'DEMO-F4', 'Demo finding 4', 'Demo part', 'Demo type', '2026-01-01', 'Demo shift', 'Demo line', 'Demo issue', 'Anonymized sample finding', 'Demo', 'medium', 'Anonymized', 'Anonymized', 'Anonymized', 'Anonymized', 'Anonymized', 1, 'open', '9684896b7a03494ebd8c2eb4ddfb6da67791c42913014a2648cbfdb788e35617', NULL, 'Anonymized', NULL, '', NULL, NULL, NULL, NULL, '', NULL, NULL, 'Demo Reporter 4', '2026-01-01 00:00:00', '2026-01-01 00:00:00', 6, 'Demo 4', 1, 'Demo check', '0', '0', '0', '0', 'unit', NULL);

UPDATE `findings` SET `response_token_expires_at` = DATE_ADD(`created_at`, INTERVAL 7 DAY) WHERE `created_at` IS NOT NULL;
UPDATE `findings` SET `response_token_expires_at` = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE `response_token_expires_at` IS NULL;

SET FOREIGN_KEY_CHECKS = 1;
