<?php

namespace App\Console\Commands;

use App\Models\Back\Catalog\Author;
use App\Models\Back\Settings\Api\AkademskaKnjigaMk;
use Illuminate\Console\Command;

class CheckApi_akmk extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:api_products_akmk';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check & set akademska-knjiga.mk for new products.';

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
        return (new AkademskaKnjigaMk())->process(['method' => 'check-products']);
    }
}
