<?php

namespace App\Services\Mailchimp;

use App\Support\FilesystemSemaphore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NewsletterSyncService
{
    public const STATUS_LABELS = [
        'pending' => 'Čeka sinkronizaciju',
        'synced' => 'Sinkronizirano',
        'pending_confirmation' => 'Čeka potvrdu e-maila',
        'preserved' => 'Sačuvan Mailchimp status',
        'skipped' => 'Samo lokalno',
        'retry' => 'Ponovni pokušaj',
        'error' => 'Potrebna provjera',
        'disabled' => 'Sinkronizacija isključena',
        'unconfigured' => 'Povezivanje nije dovršeno',
        'busy' => 'Sinkronizacija je u tijeku',
        'unavailable' => 'Potrebna nadogradnja baze',
    ];

    private $settings;
    private $api;

    public function __construct(MailchimpConnectionSettings $settings, MailchimpApi $api)
    {
        $this->settings = $settings;
        $this->api = $api;
    }

    public function available(): bool
    {
        return Schema::hasTable('newsletter_subscribers')
            && Schema::hasColumn('newsletter_subscribers', 'mailchimp_sync_status');
    }

    /** Configuration summary only: safe to display and never exposes credentials. */
    public function connectionStatus(): array
    {
        $connection = $this->settings->connection();
        $available = $this->available();
        $configured = $this->api->configured($connection);
        return [
            'enabled' => (bool) ($connection['enabled'] ?? false),
            'configured' => $configured,
            'available' => $available,
            'ready' => $available && $configured && (bool) ($connection['enabled'] ?? false),
            'audience_id' => (string) ($connection['audience_id'] ?? ''),
            'server_prefix' => (string) ($connection['server_prefix'] ?? ''),
            'status' => ! $available ? 'unavailable' : (! $configured ? 'unconfigured' : (! ($connection['enabled'] ?? false) ? 'disabled' : 'ready')),
        ];
    }

    public function sync(int $subscriberId): array
    {
        if (! $this->available()) {
            return $this->result('unavailable', null, 'schema_missing');
        }

        $contactLock = null;
        try {
            $contactLock = $this->subscriberLock($subscriberId)->acquire(0);
            if ($contactLock === null) {
                return $this->result('busy', null, 'contact_busy');
            }

            $subscriber = DB::table('newsletter_subscribers')->where('id', $subscriberId)->first();
            if (! $subscriber) {
                return $this->result('skipped', null, 'subscriber_missing');
            }
            if (! $subscriber->status || ! $subscriber->gdpr) {
                return $this->save($subscriberId, 'skipped', null, ! $subscriber->status ? 'local_inactive' : 'consent_missing');
            }
            $email = strtolower(trim((string) $subscriber->email));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->save($subscriberId, 'error', null, 'invalid_email');
            }

            $connection = $this->settings->connection();
            if (! ($connection['enabled'] ?? false)) {
                return $this->save($subscriberId, 'disabled', null, 'sync_disabled', 3600);
            }
            if (! $this->api->configured($connection)) {
                return $this->save($subscriberId, 'unconfigured', null, 'configuration_missing', 3600);
            }

            DB::table('newsletter_subscribers')->where('id', $subscriberId)->update([
                'mailchimp_last_attempt_at' => now(),
                'mailchimp_sync_attempts' => DB::raw('mailchimp_sync_attempts + 1'),
            ]);
            $hash = md5($email);
            $member = $this->api->member($connection, $hash);
            if ($member !== null) {
                return $this->applyRemoteStatus($subscriberId, $member['status']);
            }

            // A previously known remote address which disappears may have been
            // archived or deleted. Never recreate it automatically.
            if ($subscriber->mailchimp_synced_at !== null || $subscriber->mailchimp_member_status !== null) {
                return $this->save($subscriberId, 'preserved', 'archived', 'remote_member_removed', null, true);
            }
            if ($subscriber->source !== 'homepage' || $subscriber->subscribed_at === null) {
                return $this->save($subscriberId, 'skipped', null, 'explicit_signup_required');
            }

            if ($this->api->isArchived($connection, $hash)) {
                return $this->save($subscriberId, 'preserved', 'archived', 'remote_status_preserved', null, true);
            }
            $audience = $this->api->audience($connection);
            if (! isset($audience['id']) || ! is_string($audience['id'])
                || ! hash_equals($connection['audience_id'], $audience['id'])) {
                throw new MailchimpApiException('invalid_audience_response', true);
            }
            // Recheck local consent immediately before the only remote write.
            $current = DB::table('newsletter_subscribers')->where('id', $subscriberId)->first();
            if (! $current || ! $current->status || ! $current->gdpr || strtolower(trim($current->email)) !== $email) {
                return $this->save($subscriberId, 'skipped', null, 'consent_changed');
            }
            $doubleOptIn = ! isset($audience['double_optin']) || ! is_bool($audience['double_optin'])
                ? true : $audience['double_optin'];
            try {
                $created = $this->api->createMember($connection, $email, $doubleOptIn);
            } catch (MailchimpApiException $exception) {
                // A create collision can happen after GET. Re-read, never upsert.
                if ($exception->errorCode === 'remote_http_400') {
                    $existing = $this->api->member($connection, $hash);
                    if ($existing !== null) {
                        return $this->applyRemoteStatus($subscriberId, $existing['status']);
                    }
                }
                throw $exception;
            }
            if (! isset($created['id'], $created['status']) || ! is_string($created['id'])
                || ! hash_equals($hash, strtolower($created['id'])) || ! is_string($created['status'])) {
                throw new MailchimpApiException('invalid_member_response', true);
            }
            return $this->applyRemoteStatus($subscriberId, $created['status']);
        } catch (MailchimpApiException $exception) {
            return $this->save($subscriberId, $exception->retryable ? 'retry' : 'error', null, $exception->errorCode,
                $exception->retryable ? $this->retryDelay($subscriberId) : null);
        } catch (Throwable $exception) {
            // Do not store/log exception messages: HTTP errors may contain PII.
            return $this->save($subscriberId, 'retry', null, 'sync_unavailable', 300);
        } finally {
            if ($contactLock !== null) {
                $contactLock->release();
            }
        }
    }

    public function retryPending(int $limit = 25): array
    {
        if (! $this->available()) {
            return ['processed' => 0, 'unavailable' => 1];
        }
        $ids = DB::table('newsletter_subscribers')
            ->where('status', true)->where('gdpr', true)
            ->where(function ($query) {
                $query->whereIn('mailchimp_sync_status', ['pending', 'retry', 'disabled', 'unconfigured'])
                    ->orWhere(function ($query) {
                        $query->whereIn('mailchimp_sync_status', ['synced', 'pending_confirmation'])
                            ->where(function ($query) {
                                $query->whereNull('mailchimp_last_attempt_at')
                                    ->orWhere('mailchimp_last_attempt_at', '<=', now()->subDay());
                            });
                    });
            })
            ->where(function ($query) {
                $query->whereNull('mailchimp_next_attempt_at')->orWhere('mailchimp_next_attempt_at', '<=', now());
            })
            ->orderBy('id')->limit(max(1, min(25, $limit)))->pluck('id')->all();
        return $this->syncIds($ids, $limit);
    }

    public function syncIds(array $ids, int $limit = 25): array
    {
        $counts = ['processed' => 0];
        $ids = array_slice(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })), 0, max(1, min(25, $limit)));
        foreach ($ids as $id) {
            $result = $this->sync($id);
            $counts['processed']++;
            $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
        }
        return $counts;
    }

    /** Shared with webhook reconciliation to keep local state changes serialized. */
    public function subscriberLock(int $id): FilesystemSemaphore
    {
        return new FilesystemSemaphore(storage_path('framework/mailchimp-locks/contact-' . $id), 1);
    }

    public function applyRemoteStatus(int $id, string $remoteStatus): array
    {
        $allowed = ['subscribed', 'unsubscribed', 'cleaned', 'pending', 'transactional', 'archived'];
        if (! in_array($remoteStatus, $allowed, true)) {
            return $this->save($id, 'preserved', 'unknown', 'remote_status_unknown');
        }
        if ($remoteStatus === 'subscribed') {
            return $this->save($id, 'synced', $remoteStatus, null, null, false, true);
        }
        if ($remoteStatus === 'pending') {
            return $this->save($id, 'pending_confirmation', $remoteStatus, null, null, false, true);
        }
        return $this->save($id, 'preserved', $remoteStatus, 'remote_status_preserved', null,
            in_array($remoteStatus, ['unsubscribed', 'cleaned', 'archived'], true), true);
    }

    public function suppressAfterEmailChange(int $id): array
    {
        return $this->save($id, 'preserved', 'email_changed', 'remote_email_changed', null, true, true);
    }

    private function retryDelay(int $id): int
    {
        $attempts = (int) DB::table('newsletter_subscribers')->where('id', $id)->value('mailchimp_sync_attempts');
        return min(21600, 60 * (2 ** min(8, max(0, $attempts - 1))));
    }

    private function save(int $id, string $status, ?string $remoteStatus, ?string $errorCode,
        ?int $retrySeconds = null, bool $deactivate = false, bool $seenRemotely = false): array
    {
        $values = [
            'mailchimp_sync_status' => $status,
            'mailchimp_last_error' => $errorCode,
            'mailchimp_next_attempt_at' => $retrySeconds === null ? null : now()->addSeconds($retrySeconds),
        ];
        if ($remoteStatus !== null) {
            $values['mailchimp_member_status'] = $remoteStatus;
        }
        if ($deactivate) {
            $values['status'] = false;
        }
        if ($seenRemotely) {
            $values['mailchimp_synced_at'] = now();
        }
        DB::table('newsletter_subscribers')->where('id', $id)->update($values);
        return $this->result($status, $remoteStatus, $errorCode);
    }

    private function result(string $status, ?string $remoteStatus, ?string $errorCode): array
    {
        return ['status' => $status, 'remote_status' => $remoteStatus, 'error_code' => $errorCode];
    }
}
