<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedStorefrontContentSettings extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $exists = DB::table('settings')
            ->where('code', 'app')
            ->where('key', 'storefront_content')
            ->exists();

        if ($exists) {
            return;
        }

        $content = [
            'announcement_text' => 'Besplatna dostava U RH za narudžbe iznad 70 €',
            'footer_title' => 'Antikvarijat Vremeplov',
            'footer_address' => 'Zvonimirova 24',
            'footer_postal_code' => '10000',
            'footer_city' => 'Zagreb',
            'footer_phone' => '091 762 7441',
            'footer_weekday_hours' => 'Pon-Pet: 09 - 14h i 16 - 19h',
            'footer_saturday_hours' => 'Sub: 10 - 13h',
            'instagram_url' => 'https://www.instagram.com/antikvarijatvremeplov',
            'facebook_url' => 'https://www.facebook.com/antikavrijatvremeplov',
        ];

        $legacy = DB::table('settings')
            ->where('code', 'app')
            ->where('key', 'basic')
            ->value('value');
        $legacy = json_decode((string) $legacy, true);
        $legacy = is_array($legacy) ? $legacy : [];

        if (isset($legacy[0]) && is_array($legacy[0])) {
            $legacy = $legacy[0];
        }

        foreach ([
            'title' => 'footer_title',
            'address' => 'footer_address',
            'zip' => 'footer_postal_code',
            'city' => 'footer_city',
            'phone' => 'footer_phone',
        ] as $oldKey => $newKey) {
            if (isset($legacy[$oldKey]) && trim((string) $legacy[$oldKey]) !== '') {
                $content[$newKey] = $legacy[$oldKey];
            }
        }

        DB::table('settings')->insert([
            'user_id' => null,
            'code' => 'app',
            'key' => 'storefront_content',
            'value' => json_encode([$content], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'json' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('code', 'app')
            ->where('key', 'storefront_content')
            ->delete();
    }
}
