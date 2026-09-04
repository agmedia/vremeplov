<?php

namespace App\Models\Back\Orders;

use Illuminate\Database\Eloquent\Model;

class OrderMailDelivery extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'attempts' => 'integer',
        'next_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
