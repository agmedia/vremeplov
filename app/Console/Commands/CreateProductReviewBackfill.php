<?php

namespace App\Console\Commands;

use App\Models\Back\Orders\Order;
use App\Services\ProductReviewBackfillService;
use App\Services\ProductReviewRequestService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CreateProductReviewBackfill extends Command
{
    protected $signature = 'reviews:backfill
                            {--from= : Početni datum razdoblja (YYYY-MM-DD)}
                            {--to= : Završni datum razdoblja (YYYY-MM-DD)}
                            {--limit=1000 : Najveći broj poruka}
                            {--interval= : Razmak između poruka u sekundama; zadano je sigurni minimum}
                            {--dry-run : Samo prikaži broj i uzorak kandidata}
                            {--yes : Preskoči interaktivnu potvrdu}';

    protected $description = 'Kreira kontrolirani batch poziva za recenziju za starije narudžbe';

    public function handle(
        ProductReviewBackfillService $backfills,
        ProductReviewRequestService $requests
    ): int {
        $dates = $this->dates();
        if ($dates === null) {
            return 1;
        }
        [$from, $to] = $dates;

        $limit = (int) $this->option('limit');
        $maxOrders = max(1, (int) config('reviews.backfill_max_orders', 5000));
        if ($limit < 1 || $limit > $maxOrders) {
            $this->error("Opcija --limit mora biti između 1 i {$maxOrders}.");

            return 1;
        }

        $interval = $this->option('interval') !== null
            ? (int) $this->option('interval')
            : (int) config('reviews.backfill_default_interval_seconds', 120);
        $intervalOptions = (array) config('reviews.backfill_interval_options', [120]);
        if (! in_array($interval, $intervalOptions, true)) {
            $this->error('Nedopušten razmak. Odaberite: ' . implode(', ', $intervalOptions) . ' sekundi.');

            return 1;
        }

        $selection = $backfills->selectCandidates($from, $to, min(10, $limit));
        $selectedCount = min($selection['eligible_count'], $limit);

        $this->table(
            ['Narudžba', 'Kvalificirana od', 'E-mail'],
            $selection['orders']->map(fn (Order $order) => [
                $order->id,
                $requests->eligibleAt($order)->format('d.m.Y. H:i'),
                $this->maskedEmail((string) $order->payment_email),
            ])->all()
        );
        $this->info("Pronađeno {$selection['eligible_count']}; u batch ulazi {$selectedCount}; razmak {$interval} s.");

        if ($this->option('dry-run')) {
            $this->info('Dry-run: batch nije kreiran i ništa nije poslano.');

            return 0;
        }

        if (! config('reviews.request_emails_enabled')) {
            $this->error('Slanje poziva za recenziju je isključeno (REVIEW_REQUEST_EMAILS_ENABLED=false).');

            return 1;
        }

        if (! $backfills->isAvailable()) {
            $this->error('Nedostaju tablice za povijesno slanje. Pokrenite migracije.');

            return 1;
        }

        if ($selectedCount === 0) {
            $this->warn('Nema kvalificiranih narudžbi za odabrano razdoblje.');

            return 0;
        }

        if (! $this->option('yes') && ! $this->confirm("Kreirati batch za {$selectedCount} stvarnih poruka?")) {
            $this->warn('Odustano; ništa nije kreirano ni poslano.');

            return 0;
        }

        $batch = $backfills->create($from, $to, $limit, $interval);
        $this->info("Batch #{$batch->id} je kreiran. Scheduler će poslati {$batch->total_count} poruka s razmakom {$interval} s.");

        return 0;
    }

    private function dates(): ?array
    {
        $fromValue = (string) $this->option('from');
        $toValue = (string) $this->option('to');

        try {
            $from = Carbon::createFromFormat('Y-m-d', $fromValue)->startOfDay();
            $to = Carbon::createFromFormat('Y-m-d', $toValue)->endOfDay();
        } catch (\Throwable $exception) {
            $this->error('Opcije --from i --to su obavezne i moraju biti datumi u formatu YYYY-MM-DD.');

            return null;
        }

        if ($from->format('Y-m-d') !== $fromValue || $to->format('Y-m-d') !== $toValue) {
            $this->error('Opcije --from i --to moraju biti valjani datumi u formatu YYYY-MM-DD.');

            return null;
        }

        if ($from->gt($to)) {
            $this->error('Početni datum ne smije biti nakon završnog datuma.');

            return null;
        }

        $lookbackDays = max(1, (int) config('reviews.request_order_lookback_days', 60));
        $latestAllowed = now()->startOfDay()->subDays($lookbackDays + 1);
        if ($to->copy()->startOfDay()->gt($latestAllowed)) {
            $this->error("Povijesni batch smije obuhvatiti samo narudžbe starije od {$lookbackDays} dana.");

            return null;
        }

        return [$from, $to];
    }

    private function maskedEmail(string $email): string
    {
        return preg_replace('/^(.).*(@.+)$/', '$1***$2', trim($email)) ?: '***';
    }
}
