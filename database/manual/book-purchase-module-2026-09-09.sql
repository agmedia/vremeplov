-- Tablica za prijave za otkup knjiga.
-- Sigurno za ponovni import: CREATE TABLE IF NOT EXISTS neće obrisati ni promijeniti postojeće prijave.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `book_purchase_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference` VARCHAR(32) NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `postal_code` VARCHAR(20) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `photos` JSON NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'received',
    `internal_note` TEXT NULL,
    `submitted_at` TIMESTAMP NOT NULL,
    `handled_by` BIGINT UNSIGNED NULL,
    `handled_at` TIMESTAMP NULL DEFAULT NULL,
    `ip_address` VARCHAR(64) NULL,
    `user_agent` VARCHAR(512) NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `book_purchase_requests_reference_unique` (`reference`),
    KEY `book_purchase_requests_full_name_index` (`full_name`),
    KEY `book_purchase_requests_email_index` (`email`),
    KEY `book_purchase_requests_status_index` (`status`),
    KEY `book_purchase_requests_submitted_at_index` (`submitted_at`),
    KEY `book_purchase_requests_handled_by_index` (`handled_by`),
    KEY `book_purchase_requests_status_submitted_at_index` (`status`, `submitted_at`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
