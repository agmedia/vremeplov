<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('clean:authors')->dailyAt('00:03');
        $schedule->command('clean:publishers')->dailyAt('00:04');
        // API
        $schedule->command('check:api_products_akmk')->dailyAt('00:10');
        $schedule->command('import:api_products_akmk')->dailyAt('00:12');
        $schedule->command('update:api_products_akmk')->dailyAt('00:20');
        $schedule->command('report:api_products_akmk')->dailyAt('00:30');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
