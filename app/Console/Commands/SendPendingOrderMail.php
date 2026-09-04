<?php

namespace App\Console\Commands;

use App\Services\Orders\OrderConfirmationService;
use Illuminate\Console\Command;

class SendPendingOrderMail extends Command
{
    protected $signature = 'orders:send-pending-mail {--limit=50}';

    protected $description = 'Retries durable order confirmation email deliveries';

    public function handle(OrderConfirmationService $service): int
    {
        $result = $service->processPending(max(1, (int) $this->option('limit')));

        $this->info(sprintf(
            'Mailovi obrađeni. Poslano: %d. Neuspjelo: %d.',
            $result['sent'],
            $result['failed']
        ));

        return self::SUCCESS;
    }
}
