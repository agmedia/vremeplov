<?php

namespace App\Models\Back\Marketing;

use App\Models\Front\Catalog\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    public function scopeReadyToSend(Builder $query): Builder
    {
        return $query->active()->unsent()->whereHas('product', function (Builder $product) {
            $product->where('status', 1)->where('quantity', '>', 0);
        });
    }

    public function scopeWaitingForStock(Builder $query): Builder
    {
        return $query->active()->unsent()->whereDoesntHave('product', function (Builder $product) {
            $product->where('status', 1)->where('quantity', '>', 0);
        });
    }

    public function scopeBasic(Builder $query): Builder
    {
        return $query->select('product_id', 'email');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function isReadyToSend(): bool
    {
        return (int) $this->status === 1
            && (int) $this->sent === 0
            && $this->product
            && (int) $this->product->status === 1
            && (int) $this->product->quantity > 0;
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
        $ready = static::readyToSend()->count();

        Log::info('__Check Wishlist - manual notifications only.', ['ready' => $ready]);

        return $ready;
    }
}
