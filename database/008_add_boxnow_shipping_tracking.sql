-- BOX NOW polja za live bazu (MySQL/MariaDB).
-- Skripta je idempotentna i može se sigurno pokrenuti više puta.
-- Ne pokreće Laravel migracije i ne briše postojeće podatke.

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'commentp'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `commentp` TEXT NULL AFTER `comment`',
    'SELECT ''orders.commentp already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_carrier'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `shipping_carrier` VARCHAR(32) NULL DEFAULT NULL AFTER `shipping_code`',
    'SELECT ''orders.shipping_carrier already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_parcel_id'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `shipping_parcel_id` VARCHAR(191) NULL DEFAULT NULL AFTER `tracking_code`',
    'SELECT ''orders.shipping_parcel_id already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_tracking_url'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `shipping_tracking_url` VARCHAR(500) NULL DEFAULT NULL AFTER `shipping_parcel_id`',
    'SELECT ''orders.shipping_tracking_url already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_tracking_status_code'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `shipping_tracking_status_code` VARCHAR(64) NULL DEFAULT NULL AFTER `shipping_tracking_url`',
    'SELECT ''orders.shipping_tracking_status_code already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_tracking_status'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `shipping_tracking_status` VARCHAR(255) NULL DEFAULT NULL AFTER `shipping_tracking_status_code`',
    'SELECT ''orders.shipping_tracking_status already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_tracking_updated_at'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `shipping_tracking_updated_at` TIMESTAMP NULL DEFAULT NULL AFTER `shipping_tracking_status`',
    'SELECT ''orders.shipping_tracking_updated_at already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_tracking_attempted_at'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `shipping_tracking_attempted_at` TIMESTAMP NULL DEFAULT NULL AFTER `shipping_tracking_updated_at`',
    'SELECT ''orders.shipping_tracking_attempted_at already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_tracking_payload'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `shipping_tracking_payload` LONGTEXT NULL DEFAULT NULL AFTER `shipping_tracking_attempted_at`',
    'SELECT ''orders.shipping_tracking_payload already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'orders_shipping_tracking_attempted_at_index'
);
SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE `orders` ADD INDEX `orders_shipping_tracking_attempted_at_index` (`shipping_tracking_attempted_at`)',
    'SELECT ''orders_shipping_tracking_attempted_at_index already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'orders_shipping_carrier_index'
);
SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE `orders` ADD INDEX `orders_shipping_carrier_index` (`shipping_carrier`)',
    'SELECT ''orders_shipping_carrier_index already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'orders_shipping_parcel_id_index'
);
SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE `orders` ADD INDEX `orders_shipping_parcel_id_index` (`shipping_parcel_id`)',
    'SELECT ''orders_shipping_parcel_id_index already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'orders_shipping_tracking_status_code_index'
);
SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE `orders` ADD INDEX `orders_shipping_tracking_status_code_index` (`shipping_tracking_status_code`)',
    'SELECT ''orders_shipping_tracking_status_code_index already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'orders_shipping_tracking_updated_at_index'
);
SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE `orders` ADD INDEX `orders_shipping_tracking_updated_at_index` (`shipping_tracking_updated_at`)',
    'SELECT ''orders_shipping_tracking_updated_at_index already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
