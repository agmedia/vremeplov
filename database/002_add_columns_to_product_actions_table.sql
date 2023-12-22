ALTER TABLE `product_actions`
    ADD COLUMN `quantity` INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `logged`;