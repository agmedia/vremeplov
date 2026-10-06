<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AppServiceProviderBootTest extends TestCase
{
    public function test_boot_only_registers_lazy_view_callbacks_without_connecting_to_the_database(): void
    {
        config([
            'database.default' => 'boot_probe',
            'database.connections.boot_probe' => [
                'driver' => 'sqlite',
                'database' => base_path('storage/framework/testing/missing/boot-probe.sqlite'),
                'prefix' => '',
            ],
        ]);
        DB::purge('boot_probe');
        DB::setDefaultConnection('boot_probe');

        (new AppServiceProvider($this->app))->boot();

        $this->assertTrue(true);
    }
}
