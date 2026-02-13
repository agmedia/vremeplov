<?php

namespace App\Console\Commands;

use App\Models\Back\Marketing\Wishlist;
use Illuminate\Console\Command;

class CheckWishlist extends Command
{
    protected $signature = 'check:wishlist';

    protected $description = 'Check wishlist & send emails if product is available.';

    public function handle()
    {
        return Wishlist::check_CRON();
    }
}
