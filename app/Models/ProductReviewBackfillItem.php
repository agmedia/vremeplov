<?php

namespace App\Models;

use App\Models\Back\Orders\Order;
use Illuminate\Database\Eloquent\Model;

class ProductReviewBackfillItem extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'attempts' => 'integer',
        'last_attempt_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function backfill()
    {
        return $this->belongsTo(ProductReviewBackfill::class, 'backfill_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
