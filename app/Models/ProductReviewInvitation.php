<?php

namespace App\Models;

use App\Models\Back\Marketing\Review;
use App\Models\Back\Orders\Order;
use Illuminate\Database\Eloquent\Model;

class ProductReviewInvitation extends Model
{
    protected $fillable = [
        'order_id',
        'token_hash',
        'recipient_email',
        'recipient_email_normalized',
        'recipient_name',
        'eligible_at',
        'sent_at',
        'completed_at',
        'attempts',
        'last_attempt_at',
        'last_error',
    ];

    protected $hidden = [
        'token_hash',
        'recipient_email',
        'recipient_email_normalized',
        'last_error',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'eligible_at' => 'datetime',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'attempts' => 'integer',
        'last_attempt_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'invitation_id');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
