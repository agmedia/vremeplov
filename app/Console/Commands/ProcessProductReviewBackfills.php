<?php

namespace App\Console\Commands;

use App\Models\ProductReviewBackfill;
use App\Models\ProductReviewBackfillItem;
use App\Models\ProductReviewInvitation;
use App\Services\ProductReviewBackfillService;
use App\Services\ProductReviewRequestService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessProductReviewBackfills extends Command
{
    protected $signature = 'reviews:process-backfills
                            {--batch= : Obradi samo batch s ovim ID-em}
                            {--max-seconds= : Najdulje trajanje ovog ciklusa}';

    protected $description = 'Šalje povijesne pozive za recenzije tempom spremljenim u batchu';

    public function handle(
        ProductReviewBackfillService $backfills,
        ProductReviewRequestService $requests
    ): int {
        if (! config('reviews.request_emails_enabled')) {
            $this->warn('Slanje poziva za recenziju je isključeno.');

            return 0;
        }

        if (! $backfills->isAvailable()) {
            $this->error('Nedostaju tablice za povijesno slanje. Pokrenite migracije.');

            return 1;
        }

        $maxSeconds = $this->option('max-seconds') !== null
            ? (int) $this->option('max-seconds')
            : (int) config('reviews.backfill_run_seconds', 50);
        $maxSeconds = max(1, min($maxSeconds, 58));
        $lock = Cache::lock('product-review-backfills-processing', $maxSeconds + 30);

        if (! $lock->get()) {
            $this->warn('Drugi proces već obrađuje povijesne pozive.');

            return 0;
        }

        try {
            return $this->process($requests, $maxSeconds);
        } finally {
            $lock->release();
        }
    }

    private function process(ProductReviewRequestService $requests, int $maxSeconds): int
    {
        $batch = $this->batch();
        if (! $batch) {
            $this->info('Nema aktivnih batcheva za obradu.');

            return 0;
        }

        if ($batch->status === ProductReviewBackfill::STATUS_PENDING) {
            $batch->forceFill([
                'status' => ProductReviewBackfill::STATUS_RUNNING,
                'started_at' => $batch->started_at ?: now(),
            ])->save();
        }

        $batch->items()
            ->where('status', ProductReviewBackfillItem::STATUS_PROCESSING)
            ->where('updated_at', '<=', now()->subMinutes(15))
            ->update(['status' => ProductReviewBackfillItem::STATUS_PENDING]);

        $deadline = microtime(true) + $maxSeconds;
        $handled = 0;

        while (microtime(true) < $deadline) {
            $batch->refresh();
            if (! $batch->isActive()) {
                break;
            }

            $interval = max(
                120,
                (int) config('reviews.minimum_interval_seconds', 120),
                (int) $batch->interval_seconds
            );
            $lastBatchAttemptAt = $batch->items()
                ->whereNotNull('last_attempt_at')
                ->max('last_attempt_at');

            if ($lastBatchAttemptAt !== null
                && Carbon::parse($lastBatchAttemptAt)->addSeconds($interval)->isFuture()) {
                break;
            }

            $item = $batch->items()
                ->where('status', ProductReviewBackfillItem::STATUS_PENDING)
                ->orderBy('id')
                ->first();

            if (! $item) {
                $this->finish($batch);
                break;
            }

            $previousAttempts = (int) $item->attempts;
            $previousLastAttemptAt = $item->last_attempt_at;
            $item->forceFill([
                'status' => ProductReviewBackfillItem::STATUS_PROCESSING,
                'attempts' => $previousAttempts + 1,
                'last_attempt_at' => now(),
                'last_error' => null,
            ])->save();

            // send() ponovno provjerava status, artikle i normaliziranu adresu.
            $order = $requests->findOrderForRequest((int) $item->order_id);

            if (! $order) {
                $wasSent = $this->invitationWasSentForOrder((int) $item->order_id);
                $this->markTerminal(
                    $batch,
                    $item,
                    $wasSent ? ProductReviewBackfillItem::STATUS_SENT : ProductReviewBackfillItem::STATUS_SKIPPED,
                    $wasSent ? null : 'Narudžba više nije dostupna.'
                );
                $handled++;
            } else {
                $result = null;

                try {
                    $result = $requests->send($order);
                } catch (\Throwable $exception) {
                    $handled++;
                    $this->retryOrFail($batch, $item, $exception->getMessage());
                }

                if ($result !== null) {
                    $handled++;

                    if ($result['status'] === ProductReviewRequestService::STATUS_SENT) {
                        $this->markTerminal($batch, $item, ProductReviewBackfillItem::STATUS_SENT);
                        $this->info("POSLANO: batch #{$batch->id}, narudžba #{$item->order_id}");
                    } elseif ($result['status'] === ProductReviewRequestService::STATUS_SKIPPED) {
                        $wasSent = $this->invitationWasSentForOrder((int) $item->order_id);
                        $this->markTerminal(
                            $batch,
                            $item,
                            $wasSent ? ProductReviewBackfillItem::STATUS_SENT : ProductReviewBackfillItem::STATUS_SKIPPED,
                            $wasSent ? null : $result['message']
                        );
                    } elseif ($result['status'] === ProductReviewRequestService::STATUS_DEFERRED) {
                        $item->forceFill([
                            'status' => ProductReviewBackfillItem::STATUS_PENDING,
                            'attempts' => $previousAttempts,
                            'last_attempt_at' => $previousLastAttemptAt,
                            'last_error' => $result['message'],
                        ])->save();
                        $this->info($result['message'] ?: 'Sljedeći review mail ostaje na čekanju.');
                        break;
                    } elseif ($result['attempts'] < max(1, (int) config('reviews.request_max_attempts', 3))) {
                        $item->forceFill([
                            'status' => ProductReviewBackfillItem::STATUS_PENDING,
                            'last_error' => $result['message'],
                        ])->save();
                        $this->warn("PONOVNI POKUŠAJ: batch #{$batch->id}, narudžba #{$item->order_id}");
                    } else {
                        $this->markTerminal($batch, $item, ProductReviewBackfillItem::STATUS_FAILED, $result['message']);
                        $this->error("NEUSPJELO: batch #{$batch->id}, narudžba #{$item->order_id}");
                    }
                }
            }

            if (microtime(true) + $interval > $deadline) {
                break;
            }

            usleep($interval * 1000000);
        }

        $this->finishIfEmpty($batch);
        $freshBatch = $batch->fresh();
        $this->info("Batch #{$batch->id}: ovaj ciklus {$handled}, ukupno {$freshBatch->processed_count}/{$freshBatch->total_count} obrađeno.");

        return 0;
    }

    private function batch(): ?ProductReviewBackfill
    {
        $query = ProductReviewBackfill::query()
            ->whereIn('status', [
                ProductReviewBackfill::STATUS_PENDING,
                ProductReviewBackfill::STATUS_RUNNING,
            ]);

        if ($this->option('batch') !== null) {
            $query->whereKey((int) $this->option('batch'));
        }

        return $query->orderBy('id')->first();
    }

    private function retryOrFail(ProductReviewBackfill $batch, ProductReviewBackfillItem $item, string $error): void
    {
        if ((int) $item->attempts < max(1, (int) config('reviews.request_max_attempts', 3))) {
            $item->forceFill([
                'status' => ProductReviewBackfillItem::STATUS_PENDING,
                'last_error' => $error,
            ])->save();
            $this->warn("PONOVNI POKUŠAJ: batch #{$batch->id}, narudžba #{$item->order_id}");

            return;
        }

        $this->markTerminal($batch, $item, ProductReviewBackfillItem::STATUS_FAILED, $error);
        $this->error("NEUSPJELO: batch #{$batch->id}, narudžba #{$item->order_id}");
    }

    private function invitationWasSentForOrder(int $orderId): bool
    {
        return ProductReviewInvitation::query()
            ->where('order_id', $orderId)
            ->whereNotNull('sent_at')
            ->exists();
    }

    private function markTerminal(
        ProductReviewBackfill $batch,
        ProductReviewBackfillItem $item,
        string $status,
        ?string $error = null
    ): void {
        $item->forceFill([
            'status' => $status,
            'last_error' => $error,
            'processed_at' => now(),
        ])->save();

        $batch->increment('processed_count');
        if ($status === ProductReviewBackfillItem::STATUS_SENT) {
            $batch->increment('sent_count');
        } elseif ($status === ProductReviewBackfillItem::STATUS_SKIPPED) {
            $batch->increment('skipped_count');
        } elseif ($status === ProductReviewBackfillItem::STATUS_FAILED) {
            $batch->increment('failed_count');
        }
    }

    private function finishIfEmpty(ProductReviewBackfill $batch): void
    {
        $pending = $batch->items()
            ->whereIn('status', [
                ProductReviewBackfillItem::STATUS_PENDING,
                ProductReviewBackfillItem::STATUS_PROCESSING,
            ])
            ->exists();

        if (! $pending && $batch->fresh()->isActive()) {
            $this->finish($batch);
        }
    }

    private function finish(ProductReviewBackfill $batch): void
    {
        $batch->forceFill([
            'status' => ProductReviewBackfill::STATUS_COMPLETED,
            'finished_at' => now(),
        ])->save();
    }
}
