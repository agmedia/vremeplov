<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookPurchaseRequest extends Model
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DECLINED = 'declined';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'photos' => 'array',
        'submitted_at' => 'datetime',
        'handled_by' => 'integer',
        'handled_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_RECEIVED => 'Zaprimljeno',
            self::STATUS_PROCESSING => 'U obradi',
            self::STATUS_COMPLETED => 'Dovršeno',
            self::STATUS_DECLINED => 'Nije za otkup',
        ];
    }

    public static function statusColors(): array
    {
        return [
            self::STATUS_RECEIVED => 'primary',
            self::STATUS_PROCESSING => 'warning',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_DECLINED => 'danger',
        ];
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function photo(int $index): ?array
    {
        $photos = is_array($this->photos) ? array_values($this->photos) : [];
        $photo = $photos[$index] ?? null;

        return is_array($photo) ? $photo : null;
    }
}
