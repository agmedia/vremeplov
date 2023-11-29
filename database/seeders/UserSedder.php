<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSedder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // create admins
        DB::insert(
            "INSERT INTO `users` (`name`, `email`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
              ('Filip Jankoski', 'filip@agmedia.hr', '" . bcrypt('majamaja001') . "', '', NOW(), NOW()),
              ('Tomislav Jureša', 'tomislav@agmedia.hr', '" . bcrypt('bakanal') . "', '', NOW(), NOW())"
        );

        // create admins details
        DB::insert(
            "INSERT INTO `user_details` (`user_id`, `fname`, `lname`, `address`, `zip`, `city`, `state`, `phone`, `oib`, `company`, `avatar`, `bio`, `social`, `role`, `status`, `created_at`, `updated_at`) VALUES
              (1, 'Filip', 'Jankoski', 'Kovačića 23', '44320', 'Kutina', '', '', '', 'AGmedia', 'media/avatars/avatar0.jpg', 'Lorem ipsum...', '790117367', 'master', 1, NOW(), NOW()),
              (2, 'Tomislav', 'Jureša', 'Malešnica bb', '10000', 'Zagreb', '', '', '', 'AGmedia', 'media/avatars/avatar0.jpg', 'Lorem ipsum...', '', 'master', 1, NOW(), NOW())"
        );
    }
}
