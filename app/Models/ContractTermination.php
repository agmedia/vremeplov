<?php

namespace App\Models;

use App\Models\Back\Orders\Order;
use Illuminate\Database\Eloquent\Model;

class ContractTermination extends Model
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DECLINED = 'declined';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'user_id' => 'integer',
        'order_id' => 'integer',
        'order_date' => 'date',
        'received_date' => 'date',
        'statement' => 'boolean',
        'submitted_at' => 'datetime',
        'consumer_notified_at' => 'datetime',
        'admin_notified_at' => 'datetime',
        'handled_by' => 'integer',
        'handled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_RECEIVED => 'Zaprimljeno',
            self::STATUS_PROCESSING => 'U obradi',
            self::STATUS_COMPLETED => 'Dovršeno',
            self::STATUS_DECLINED => 'Odbijeno',
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

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function mailData(): array
    {
        return [
            'reference' => $this->reference,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'country' => $this->country,
            'order_number' => $this->order_number,
            'order_date' => optional($this->order_date)->toDateString(),
            'received_date' => optional($this->received_date)->toDateString(),
            'items' => $this->items,
            'iban' => $this->iban,
            'statement' => $this->statement,
            'submitted_at' => $this->submitted_at,
        ];
    }
}
