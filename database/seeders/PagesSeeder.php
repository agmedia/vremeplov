<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        DB::insert(
            "INSERT INTO `pages` (`category_id`, `group`, `subgroup`, `title`, `short_description`, `description`, `meta_title`, `meta_description`, `slug`, `keywords`, `image`, `publish_date`, `viewed`, `featured`, `status`, `created_at`, `updated_at`) VALUES
              (null, 'page', 'Home', 'Homepage', null, null, null, null, 'homepage', '0', null, null, 0, 0, 1, NOW(), NOW())"
        );
    }
}
