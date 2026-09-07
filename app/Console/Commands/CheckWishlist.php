<?php

namespace App\Console\Commands;

use App\Models\Back\Marketing\Wishlist;
use Illuminate\Console\Command;

class CheckWishlist extends Command
{
    protected $signature = 'check:wishlist';

    protected $description = 'Prikazuje broj wishlist prijava spremnih za ručno slanje.';

    public function handle()
    {
        $ready = Wishlist::check_CRON();
        $this->info("Spremno za ručno slanje: {$ready}. Automatsko slanje je isključeno.");

        return self::SUCCESS;
    }
}
