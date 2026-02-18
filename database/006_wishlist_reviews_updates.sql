-- 006_wishlist_reviews_updates.sql
-- Purpose: ensure wishlist table exists and add review-related indexes used by frontend/admin.

CREATE TABLE IF NOT EXISTS `wishlist` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) unsigned NOT NULL DEFAULT '0',
    `email` varchar(191) NOT NULL,
    `product_id` bigint(20) unsigned NOT NULL DEFAULT '0',
    `sent` tinyint(4) NOT NULL DEFAULT '0',
    `sent_at` timestamp NULL DEFAULT NULL,
    `status` tinyint(4) NOT NULL DEFAULT '1',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db := DATABASE();

-- wishlist indexes
SET @idx := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @db AND table_name = 'wishlist' AND index_name = 'wishlist_product_sent_index'
);
SET @sql := IF(@idx = 0,
    'ALTER TABLE `wishlist` ADD INDEX `wishlist_product_sent_index` (`product_id`, `sent`)',
    'SELECT "wishlist_product_sent_index exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @db AND table_name = 'wishlist' AND index_name = 'wishlist_email_product_index'
);
SET @sql := IF(@idx = 0,
    'ALTER TABLE `wishlist` ADD INDEX `wishlist_email_product_index` (`email`, `product_id`)',
    'SELECT "wishlist_email_product_index exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- reviews indexes (product page, moderation list, aggregate rating)
SET @idx := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @db AND table_name = 'reviews' AND index_name = 'reviews_product_status_sort_index'
);
SET @sql := IF(@idx = 0,
    'ALTER TABLE `reviews` ADD INDEX `reviews_product_status_sort_index` (`product_id`, `status`, `sort_order`)',
    'SELECT "reviews_product_status_sort_index exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @db AND table_name = 'reviews' AND index_name = 'reviews_product_status_created_index'
);
SET @sql := IF(@idx = 0,
    'ALTER TABLE `reviews` ADD INDEX `reviews_product_status_created_index` (`product_id`, `status`, `created_at`)',
    'SELECT "reviews_product_status_created_index exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
