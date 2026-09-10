INSERT INTO `settings` (`user_id`, `code`, `key`, `value`, `json`, `created_at`, `updated_at`)
SELECT
    NULL,
    'app',
    'storefront_content',
    '[{"announcement_text":"Besplatna dostava U RH za narudžbe iznad 70 €","footer_title":"Antikvarijat Vremeplov","footer_address":"Zvonimirova 24","footer_postal_code":"10000","footer_city":"Zagreb","footer_phone":"091 762 7441","footer_weekday_hours":"Pon-Pet: 09 - 14h i 16 - 19h","footer_saturday_hours":"Sub: 10 - 13h","instagram_url":"https://www.instagram.com/antikvarijatvremeplov","facebook_url":"https://www.facebook.com/antikavrijatvremeplov"}]',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM `settings`
    WHERE `code` = 'app'
      AND `key` = 'storefront_content'
);
