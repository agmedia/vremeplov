-- 007_search_autosuggest_indexes.sql
-- Purpose: lightweight indexes for desktop autosuggest/search endpoint.

SET @db := DATABASE();

-- authors: suggest by title with status filter
SET @idx := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @db AND table_name = 'authors' AND index_name = 'authors_status_title_index'
);
SET @sql := IF(@idx = 0,
    'ALTER TABLE `authors` ADD INDEX `authors_status_title_index` (`status`, `title`)',
    'SELECT "authors_status_title_index exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- products: suggest by status/name + by status/sku + sort/filter helpers
SET @idx := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @db AND table_name = 'products' AND index_name = 'products_status_name_index'
);
SET @sql := IF(@idx = 0,
    'ALTER TABLE `products` ADD INDEX `products_status_name_index` (`status`, `name`)',
    'SELECT "products_status_name_index exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @db AND table_name = 'products' AND index_name = 'products_status_sku_index'
);
SET @sql := IF(@idx = 0,
    'ALTER TABLE `products` ADD INDEX `products_status_sku_index` (`status`, `sku`)',
    'SELECT "products_status_sku_index exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @db AND table_name = 'products' AND index_name = 'products_status_author_viewed_index'
);
SET @sql := IF(@idx = 0,
    'ALTER TABLE `products` ADD INDEX `products_status_author_viewed_index` (`status`, `author_id`, `viewed`)',
    'SELECT "products_status_author_viewed_index exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @db AND table_name = 'products' AND index_name = 'products_status_quantity_viewed_index'
);
SET @sql := IF(@idx = 0,
    'ALTER TABLE `products` ADD INDEX `products_status_quantity_viewed_index` (`status`, `quantity`, `viewed`)',
    'SELECT "products_status_quantity_viewed_index exists"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
