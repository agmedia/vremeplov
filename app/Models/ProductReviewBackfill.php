<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReviewBackfill extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'requested_limit' => 'integer',
        'interval_seconds' => 'integer',
        'eligible_count' => 'integer',
        'total_count' => 'integer',
        'processed_count' => 'integer',
        'sent_count' => 'integer',
        'skipped_count' => 'integer',
        'failed_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(ProductReviewBackfillItem::class, 'backfill_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING], true);
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Čeka slanje',
            self::STATUS_RUNNING => 'Šalje se',
            self::STATUS_COMPLETED => 'Završeno',
            self::STATUS_CANCELLED => 'Zaustavljeno',
        ];
    }
}
