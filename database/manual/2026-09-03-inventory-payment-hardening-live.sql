-- Vremeplov: inventory and payment hardening (LIVE)
-- Generated 2026-09-03.
--
-- RUN ONCE, during a short maintenance window, after a verified database backup.
-- This is the SQL alternative to these four Laravel migrations:
--   2026_09_03_120000_add_inventory_tracking_to_orders
--   2026_09_03_130000_add_wspay_transaction_idempotency_key
--   2026_09_03_140000_add_payment_attempt_snapshot_to_orders
--   2026_09_03_150000_create_payment_provider_references_table
--
-- Do not run this after those migrations have already been applied. The script
-- changes schema and backfills payment identifiers, but deliberately does NOT
-- alter any product quantity or retroactively allocate legacy orders.

SET NAMES utf8mb4;

-- Reference quantity used by the final no-stock-change verification.
SET @target_product_id := (
    SELECT MIN(id) FROM products WHERE sku = 'K49769X'
);
SET @target_quantity_before := (
    SELECT quantity FROM products WHERE id = @target_product_id
);

SELECT
    id,
    sku,
    name,
    quantity AS quantity_before_schema_change
FROM products
WHERE id = @target_product_id;

-- 1. Explicit inventory reservation/commit/release state and immutable ledger.
ALTER TABLE orders
    ADD COLUMN inventory_reserved_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN inventory_committed_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN inventory_released_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN inventory_reservation_expires_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN inventory_reservation_version INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN inventory_allocation_error VARCHAR(500) NULL,
    ADD KEY orders_inventory_reserved_at_index (inventory_reserved_at),
    ADD KEY orders_inventory_reservation_expires_at_index (inventory_reservation_expires_at);

CREATE TABLE inventory_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    reservation_version INT UNSIGNED NOT NULL,
    action VARCHAR(16) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    stock_before INT UNSIGNED NULL,
    stock_after INT UNSIGNED NULL,
    reason VARCHAR(191) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY inventory_movements_order_version (order_id, reservation_version),
    KEY inventory_movements_product_date (product_id, created_at),
    UNIQUE KEY inventory_movement_once (
        order_id,
        product_id,
        reservation_version,
        action
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. WSPay/provider event idempotency. Only the earliest historical row for a
-- provider reference is adopted; all other historical audit rows are retained.
ALTER TABLE order_transactions
    ADD COLUMN provider_event VARCHAR(32) NULL AFTER payment_partner,
    ADD COLUMN idempotency_key CHAR(64) NULL AFTER pg_order_id;

UPDATE order_transactions AS target
JOIN (
    SELECT
        TRIM(transactions.pg_order_id) AS provider_reference,
        MIN(transactions.id) AS canonical_id
    FROM order_transactions AS transactions
    INNER JOIN orders
        ON orders.id = transactions.order_id
    WHERE orders.payment_code = 'wspay'
      AND transactions.pg_order_id IS NOT NULL
      AND TRIM(transactions.pg_order_id) <> ''
    GROUP BY TRIM(transactions.pg_order_id)
) AS canonical
    ON canonical.canonical_id = target.id
SET target.idempotency_key = SHA2(
    CONCAT('wspay|provider|', canonical.provider_reference),
    256
);

ALTER TABLE order_transactions
    ADD UNIQUE KEY order_transactions_order_idempotency_unique (idempotency_key);

-- 3. Frozen payment-attempt facts. The encrypted verification key is populated
-- only for new attempts by the application; no live secret appears in this SQL.
ALTER TABLE orders
    ADD COLUMN payment_attempt_started_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN payment_attempt_provider VARCHAR(32) NULL,
    ADD COLUMN payment_attempt_reference VARCHAR(191) NULL,
    ADD COLUMN payment_expected_amount_minor BIGINT UNSIGNED NULL,
    ADD COLUMN payment_expected_currency CHAR(3) NULL,
    ADD COLUMN payment_attempt_environment VARCHAR(16) NULL,
    ADD COLUMN payment_attempt_merchant VARCHAR(191) NULL,
    ADD COLUMN payment_attempt_verification_key TEXT NULL,
    ADD COLUMN payment_attempt_order_hash CHAR(64) NULL,
    ADD COLUMN payment_attempt_reservation_version INT UNSIGNED NULL,
    ADD COLUMN payment_review_error VARCHAR(500) NULL,
    ADD COLUMN confirmation_sent_at TIMESTAMP NULL DEFAULT NULL,
    ADD KEY orders_payment_attempt_started_at_index (payment_attempt_started_at),
    ADD UNIQUE KEY orders_payment_attempt_provider_reference_unique (
        payment_attempt_provider,
        payment_attempt_reference
    );

-- 4. A provider transaction/reference may belong to exactly one order globally.
CREATE TABLE payment_provider_references (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(32) NOT NULL,
    reference VARCHAR(191) NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY payment_provider_reference_unique (provider, reference),
    KEY payment_provider_references_order_id_index (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO payment_provider_references (
    provider,
    reference,
    order_id,
    created_at,
    updated_at
)
SELECT
    'wspay',
    canonical.provider_reference,
    transaction_row.order_id,
    COALESCE(transaction_row.created_at, CURRENT_TIMESTAMP),
    COALESCE(transaction_row.updated_at, CURRENT_TIMESTAMP)
FROM (
    SELECT
        TRIM(transactions.pg_order_id) AS provider_reference,
        MIN(transactions.id) AS canonical_id
    FROM order_transactions AS transactions
    INNER JOIN orders
        ON orders.id = transactions.order_id
    WHERE orders.payment_code = 'wspay'
      AND transactions.pg_order_id IS NOT NULL
      AND TRIM(transactions.pg_order_id) <> ''
    GROUP BY TRIM(transactions.pg_order_id)
) AS canonical
INNER JOIN order_transactions AS transaction_row
    ON transaction_row.id = canonical.canonical_id;

-- Register the four migrations so a later `php artisan migrate` does not try to
-- apply the same DDL again. This does not register any unrelated migration.
SET @migration_batch := (
    SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations
);

INSERT INTO migrations (migration, batch)
SELECT '2026_09_03_120000_add_inventory_tracking_to_orders', @migration_batch
WHERE NOT EXISTS (
    SELECT 1 FROM migrations
    WHERE migration = '2026_09_03_120000_add_inventory_tracking_to_orders'
);

INSERT INTO migrations (migration, batch)
SELECT '2026_09_03_130000_add_wspay_transaction_idempotency_key', @migration_batch
WHERE NOT EXISTS (
    SELECT 1 FROM migrations
    WHERE migration = '2026_09_03_130000_add_wspay_transaction_idempotency_key'
);

INSERT INTO migrations (migration, batch)
SELECT '2026_09_03_140000_add_payment_attempt_snapshot_to_orders', @migration_batch
WHERE NOT EXISTS (
    SELECT 1 FROM migrations
    WHERE migration = '2026_09_03_140000_add_payment_attempt_snapshot_to_orders'
);

INSERT INTO migrations (migration, batch)
SELECT '2026_09_03_150000_create_payment_provider_references_table', @migration_batch
WHERE NOT EXISTS (
    SELECT 1 FROM migrations
    WHERE migration = '2026_09_03_150000_create_payment_provider_references_table'
);

-- Post-deploy checks. `quantity_delta` must be 0.
SELECT
    id,
    sku,
    name,
    @target_quantity_before AS quantity_before_schema_change,
    quantity AS quantity_after_schema_change,
    quantity - @target_quantity_before AS quantity_delta
FROM products
WHERE id = @target_product_id;

SELECT
    migration,
    batch
FROM migrations
WHERE migration IN (
    '2026_09_03_120000_add_inventory_tracking_to_orders',
    '2026_09_03_130000_add_wspay_transaction_idempotency_key',
    '2026_09_03_140000_add_payment_attempt_snapshot_to_orders',
    '2026_09_03_150000_create_payment_provider_references_table'
)
ORDER BY migration;

SELECT
    COUNT(*) AS inventory_movement_rows_after_deploy
FROM inventory_movements;

SELECT
    COUNT(*) AS adopted_wspay_provider_references
FROM payment_provider_references
WHERE provider = 'wspay';

SELECT
    reference,
    COUNT(DISTINCT order_id) AS owner_count
FROM payment_provider_references
GROUP BY provider, reference
HAVING COUNT(DISTINCT order_id) > 1;
