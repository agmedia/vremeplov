<?php

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Un Guard model
        Model::unguard();

        $this->command->call('migrate:fresh');

        $this->command->info('Refreshing database...');
        $this->command->comment('Refreshed!');

        $this->call(UserSedder::class);
        $this->command->line('Users created!');

        $this->call(SettingsSeeder::class);
        $this->command->line('Settings created!');

        $this->call(PagesSeeder::class);
        $this->command->line('Homepage created!');

        $this->call(BouncerSedder::class);
        $this->command->line('Roles & Permissions created!');

        $this->command->comment('Enjoy your app!');
        $this->command->comment('...');

        // ReGuard model
        Model::reguard();
    }
}
