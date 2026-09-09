<?php

namespace App\Models\Back\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NewsletterSubscriber extends Model
{
    protected $table = 'newsletter_subscribers';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'gdpr' => 'boolean',
        'status' => 'boolean',
        'subscribed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Public re-submissions intentionally do not reactivate or overwrite an
     * existing address. That keeps a future unsubscribe state authoritative.
     */
    public static function subscribeFromHomepage(string $email, ?int $userId = null): void
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return;
        }

        $now = now();

        DB::table((new static())->getTable())->insertOrIgnore([
            'email' => $email,
            'user_id' => $userId,
            'source' => 'homepage',
            'gdpr' => true,
            'status' => true,
            'subscribed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
