<?php

namespace App\Console\Commands;

use App\Models\Back\Settings\Api\AkademskaKnjigaMk;
use Illuminate\Console\Command;

class SendReportApi_akmk extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:api_products_akmk';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send report about sold products to akademska-knjiga.mk.';

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
        return (new AkademskaKnjigaMk())->process(['method' => 'send-report']);
    }
}
