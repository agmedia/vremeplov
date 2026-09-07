<?php

namespace App\Console\Commands;

use App\Services\Orders\AbandonedCartService;
use Illuminate\Console\Command;

class SendAbandonedCartReminders extends Command
{
    protected $signature = 'orders:send-abandoned-cart-reminders {--dry-run} {--limit=25}';
    protected $description = 'Šalje sigurne poveznice za nastavak nedovršene kupnje';

    public function handle(AbandonedCartService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! config('abandoned_cart.enabled') && ! $dryRun) {
            $this->warn('Podsjetnici su isključeni. Postavite ABANDONED_CART_EMAILS_ENABLED=true nakon odobrenja.');
            return self::SUCCESS;
        }

        if (! $service->isAvailable()) {
            $this->error('Nedostaje migracija za evidenciju podsjetnika.');
            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $processed = 0;
        $failed = 0;

        for ($sequence = 1; $sequence <= 2 && $processed < $limit; $sequence++) {
            foreach ($service->candidates($sequence, $limit - $processed, $dryRun) as $order) {
                if (! $dryRun && ! $service->send($order, $sequence)) {
                    $failed++;
                }
                $processed++;
            }
        }

        $this->info(($dryRun ? 'Dry-run kandidati: ' : 'Obrađeno: ') . $processed . '. Neuspjelo: ' . $failed . '.');
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
