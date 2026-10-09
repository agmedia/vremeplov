<?php

namespace App\Services\Mailchimp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MailchimpWebhookService
{
    private $settings;
    private $sync;

    public function __construct(MailchimpConnectionSettings $settings, NewsletterSyncService $sync)
    {
        $this->settings = $settings;
        $this->sync = $sync;
    }

    public function authorized(Request $request): bool
    {
        $connection = $this->settings->connection();
        $token = $connection['webhook_token'] ?? '';
        $received = $request->route('token') ?? $request->query('token');
        if (! is_string($token) || strlen($token) < 32 || ! is_string($received) || ! hash_equals($token, $received)) {
            return false;
        }
        if ($request->isMethod('GET')) {
            return true;
        }
        if (! $request->isMethod('POST') || ($connection['webhook_signing_invalid'] ?? false)) {
            return false;
        }
        $secret = (string) ($connection['webhook_signing_secret'] ?? '');
        if ($secret !== '') {
            $signature = (string) $request->header('X-Mailchimp-Signature', '');
            if (! preg_match('/^t=(\d{1,12}),\s*v1=([a-f0-9]{64})$/iD', $signature, $parts)
                || abs(time() - (int) $parts[1]) > 300) {
                return false;
            }
            $expected = hash_hmac('sha256', $parts[1] . '.' . $request->getContent(), $secret);
            return hash_equals($expected, strtolower($parts[2]));
        }

        return true;
    }

    /** Call only after authorized(); this never creates contacts or reactivates consent. */
    public function handle(array $payload): bool
    {
        $connection = $this->settings->connection();
        $listId = $payload['data']['list_id'] ?? null;
        if (! is_string($listId) || ! is_string($connection['audience_id'] ?? null)
            || $connection['audience_id'] === '' || ! hash_equals($connection['audience_id'], $listId)
            || ! $this->sync->available()) {
            return false;
        }
        $type = $payload['type'] ?? null;
        if (! in_array($type, ['unsubscribe', 'cleaned', 'subscribe', 'upemail'], true)) {
            return true;
        }
        $email = $type === 'upemail' ? ($payload['data']['old_email'] ?? null) : ($payload['data']['email'] ?? null);
        if (! is_string($email) || ! filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $subscriber = DB::table('newsletter_subscribers')
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($email))])->first();
        if (! $subscriber) {
            return true;
        }
        $lock = $this->sync->subscriberLock((int) $subscriber->id)->acquire(0);
        if ($lock === null) {
            return false;
        }
        try {
            if ($type === 'upemail') {
                $this->sync->suppressAfterEmailChange((int) $subscriber->id);
                return true;
            }
            if ($type === 'subscribe') {
                // Remote subscription is not permission to reverse a local opt-out.
                $current = DB::table('newsletter_subscribers')->where('id', $subscriber->id)->first();
                if ($current && $current->status && $current->gdpr) {
                    $this->sync->applyRemoteStatus((int) $subscriber->id, 'subscribed');
                }
                return true;
            }
            $status = $type === 'cleaned' ? 'cleaned'
                : (($payload['data']['action'] ?? '') === 'delete' ? 'archived' : 'unsubscribed');
            $this->sync->applyRemoteStatus((int) $subscriber->id, $status);
            return true;
        } finally {
            $lock->release();
        }
    }
}
