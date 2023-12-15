<?php

namespace App\Console\Commands;

use App\Http\Controllers\Back\DashboardController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class InitialImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:initial';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initial import from OC database.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $request = new Request(['_token' => csrf_token(), 'api' => true]);

        return app(DashboardController::class)->importProducts($request);
    }
}
