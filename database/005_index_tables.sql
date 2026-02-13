CREATE TABLE product_category_backup AS
SELECT * FROM product_category;

-- 1) build a clean table with the same structure
CREATE TABLE product_category_new LIKE product_category;

-- 2) insert unique pairs only
INSERT INTO product_category_new (product_id, category_id)
SELECT DISTINCT product_id, category_id
FROM product_category;

-- 3) add the unique key you want
ALTER TABLE product_category_new
    ADD UNIQUE KEY pc_product_category_unique (product_id, category_id);

-- 4) swap tables (minimal downtime)
RENAME TABLE
  product_category TO product_category_old,
  product_category_new TO product_category;

-- 5) verify, then drop old table
DROP TABLE product_category_old;

ALTER TABLE products
    ADD INDEX `products_status_index` (`status`),
  ADD INDEX `products_quantity_index` (`quantity`),
  ADD INDEX `products_group_index` (`group`),
  ADD INDEX `products_author_id_index` (`author_id`),
  ADD INDEX `products_publisher_id_index` (`publisher_id`),
  ADD INDEX `products_price_index` (`price`),
  ADD INDEX `products_special_index` (`special`),
  ADD INDEX `products_updated_at_index` (`updated_at`),
  ADD UNIQUE KEY `products_slug_unique` (`slug`);

ALTER TABLE categories
    ADD INDEX `categories_parent_id_index` (`parent_id`),
  ADD INDEX `categories_status_index` (`status`),
  ADD INDEX `categories_group_index` (`group`),
  ADD INDEX `categories_slug_index` (`slug`),
  ADD INDEX `categories_sort_order_index` (`sort_order`);


ALTER TABLE product_category
    ADD UNIQUE KEY `pc_product_category_unique` (`product_id`,`category_id`);

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
    PRIMARY KEY (`id`),
    KEY `wishlist_product_sent_index` (`product_id`, `sent`),
    KEY `wishlist_email_product_index` (`email`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
