<?php

namespace App\Services;

use App\Mail\WishlistArrived;
use App\Models\Back\Marketing\Wishlist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WishlistNotificationService
{
    public const STATUS_SENT = 'sent';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    /**
     * @return array{status:string, message:?string}
     */
    public function send(Wishlist $wishlist): array
    {
        $wishlistId = $wishlist->id;
        $lock = Cache::lock('wishlist-notification:' . $wishlistId, 120);

        if (! $lock->get()) {
            return $this->result(self::STATUS_SKIPPED, 'Slanje za ovu prijavu već je u tijeku.');
        }

        try {
            $wishlist = Wishlist::query()
                ->with('product:id,name,sku,image,url,quantity,status')
                ->find($wishlistId);

            if (! $wishlist || (int) $wishlist->sent === 1) {
                return $this->result(self::STATUS_SKIPPED, 'Obavijest je već poslana.');
            }

            if (! $wishlist->isReadyToSend()) {
                return $this->result(self::STATUS_SKIPPED, 'Artikl trenutačno nije dostupan za slanje obavijesti.');
            }

            $email = mb_strtolower(trim((string) $wishlist->email));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->result(self::STATUS_SKIPPED, 'E-mail adresa nije valjana.');
            }

            Mail::to($email)->send(new WishlistArrived($wishlist->product));

            $wishlist->forceFill([
                'sent' => 1,
                'status' => 0,
                'sent_at' => now(),
            ])->save();

            Log::info('Wishlist notification sent manually.', [
                'wishlist_id' => $wishlist->id,
                'product_id' => $wishlist->product_id,
            ]);

            return $this->result(self::STATUS_SENT, null);
        } catch (\Throwable $exception) {
            Log::warning('Wishlist notification failed.', [
                'wishlist_id' => $wishlistId,
                'error' => $exception->getMessage(),
            ]);

            return $this->result(
                self::STATUS_FAILED,
                Str::limit($exception->getMessage(), 500, '')
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{status:string, message:?string}
     */
    private function result(string $status, ?string $message): array
    {
        return compact('status', 'message');
    }
}
