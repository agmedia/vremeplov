<?php

namespace App\Models\Back\Marketing;

use App\Mail\WishlistArrived;
use App\Models\Front\Catalog\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Wishlist extends Model
{
    protected $table = 'wishlist';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    protected $request;

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 0);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('sent', 1);
    }

    public function scopeUnsent(Builder $query): Builder
    {
        return $query->where('sent', 0);
    }

    public function scopeBasic(Builder $query): Builder
    {
        return $query->select('product_id', 'email');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function validateRequest(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'product_id' => 'required|integer',
            'recaptcha' => 'nullable|string',
        ]);

        $this->request = $request;

        return $this;
    }

    public function create()
    {
        $exists = static::where('email', $this->request->email)
            ->where('product_id', $this->request->product_id)
            ->where('sent', 0)
            ->exists();

        if ($exists) {
            return false;
        }

        try {
            $id = $this->insertGetId([
                'user_id' => auth()->guest() ? 0 : auth()->user()->id,
                'email' => $this->request->email,
                'product_id' => $this->request->product_id,
                'sent' => 0,
                'sent_at' => null,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('__Wishlist Create Failed', [
                'email' => $this->request->email,
                'product_id' => $this->request->product_id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        return $id ? $this->find($id) : false;
    }

    public static function check_CRON()
    {
        $log_start = microtime(true);

        $list = static::active()->unsent()->basic()->get();
        $ids = $list->unique('product_id')->pluck('product_id');
        $products = Product::query()->whereIn('id', $ids)->available()->basicData()->get();

        foreach ($products as $product) {
            $emails = $list->where('product_id', $product->id)->pluck('email');

            foreach ($emails as $email) {
                dispatch(function () use ($email, $product) {
                    Mail::to($email)->send(new WishlistArrived($product));
                })->afterResponse();

                $wishlistEntry = static::query()
                    ->where('product_id', $product->id)
                    ->where('email', $email)
                    ->where('sent', 0)
                    ->first();

                if ($wishlistEntry) {
                    $sentAt = now();

                    $wishlistEntry->update([
                        'sent' => 1,
                        'status' => 0,
                        'sent_at' => $sentAt,
                    ]);

                    Log::info('__Wishlist Notification Sent', [
                        'wishlist_id' => $wishlistEntry->id,
                        'product_id' => $product->id,
                        'email' => $email,
                        'sent_at' => $sentAt->toDateTimeString(),
                    ]);
                }
            }
        }

        $log_end = microtime(true);
        Log::info('__Check Wishlist - Total Execution Time: ' . number_format(($log_end - $log_start), 2, ',', '.') . ' sec.');

        return 1;
    }
}
