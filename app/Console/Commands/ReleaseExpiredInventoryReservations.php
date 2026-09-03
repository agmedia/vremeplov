<?php

namespace App\Console\Commands;

use App\Services\Inventory\OrderInventoryService;
use Illuminate\Console\Command;

class ReleaseExpiredInventoryReservations extends Command
{
    protected $signature = 'inventory:release-expired {--limit=100}';

    protected $description = 'Release stock held by expired unfinished checkout reservations';

    public function handle(OrderInventoryService $inventory): int
    {
        $released = $inventory->expireReservations(max(1, (int) $this->option('limit')));

        $this->info(sprintf('Released %d expired inventory reservation(s).', $released));

        return 0;
    }
}
