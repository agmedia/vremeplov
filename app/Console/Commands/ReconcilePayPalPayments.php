<?php

namespace App\Console\Commands;

use App\Services\Payments\PayPalReconciliationService;
use Illuminate\Console\Command;

class ReconcilePayPalPayments extends Command
{
    protected $signature = 'payments:reconcile-paypal {--dry-run} {--limit=200}';
    protected $description = 'Usklađuje nedostajuće PayPal potvrde s verificiranim PayPal izvještajem';

    public function handle(PayPalReconciliationService $service): int
    {
        $stats = $service->reconcile((bool) $this->option('dry-run'), max(1, (int) $this->option('limit')));

        $this->line(sprintf(
            'Kandidati: %d; podudaranja: %d; potvrđeno: %d; za provjeru: %d; greške: %d.',
            $stats['candidates'],
            $stats['matched'],
            $stats['confirmed'],
            $stats['review'],
            $stats['errors']
        ));

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
