<?php

namespace App\Console\Commands;

use App\Models\Back\Orders\Order;
use App\Services\ProductReviewRequestService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendProductReviewRequests extends Command
{
    protected $signature = 'reviews:send-requests
                            {--dry-run : Prikaži kvalificirane narudžbe bez upisa i slanja}
                            {--date= : Datum dnevnog pokretanja (YYYY-MM-DD), zadano danas}
                            {--limit= : Najveći broj narudžbi u ovom pokretanju}';

    protected $description = 'Šalje jednokratni poziv za recenziju nakon isporučene narudžbe';

    public function handle(ProductReviewRequestService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! config('reviews.request_emails_enabled') && ! $dryRun) {
            $this->warn('Slanje poziva za recenziju je isključeno (REVIEW_REQUEST_EMAILS_ENABLED=false).');

            return 0;
        }

        $runDate = now()->startOfDay();
        if ($this->option('date')) {
            $date = (string) $this->option('date');

            try {
                $runDate = Carbon::createFromFormat('!Y-m-d', $date);
            } catch (\Throwable $exception) {
                $this->error('Opcija --date mora biti valjan datum u formatu YYYY-MM-DD.');

                return 1;
            }

            if ($runDate->format('Y-m-d') !== $date) {
                $this->error('Opcija --date mora biti valjan datum u formatu YYYY-MM-DD.');

                return 1;
            }
        }

        $eligibleDay = $runDate->copy()->subDays((int) config('reviews.request_delay_days', 30));
        $orders = $service->eligibleOrders(
            $eligibleDay->copy()->startOfDay(),
            $eligibleDay->copy()->endOfDay()
        );

        if ($this->option('limit')) {
            $orders->limit(max(1, min((int) $this->option('limit'), 1000)));
        }

        $orders = $orders->get();

        if ($dryRun) {
            $this->table(
                ['Narudžba', 'Status', 'Kvalificirana od', 'E-mail'],
                $orders->map(fn (Order $order) => [
                    $order->id,
                    $order->order_status_id,
                    $service->eligibleAt($order)->format('d.m.Y. H:i'),
                    $this->maskedEmail((string) $order->payment_email),
                ])->all()
            );
            $this->info('Dry-run: ' . $orders->count() . ' kvalificiranih narudžbi; ništa nije poslano.');

            return 0;
        }

        $sent = 0;
        $failed = 0;

        foreach ($orders as $order) {
            $result = $service->send($order);
            if ($result['status'] === ProductReviewRequestService::STATUS_SENT) {
                $sent++;
            } elseif ($result['status'] === ProductReviewRequestService::STATUS_FAILED) {
                $failed++;
            }
        }

        $this->info("Pozivi za recenziju: poslano {$sent}, neuspjelo {$failed}.");

        return $failed > 0 ? 1 : 0;
    }

    private function maskedEmail(string $email): string
    {
        return preg_replace('/^(.).*(@.+)$/', '$1***$2', trim($email)) ?: '***';
    }
}
