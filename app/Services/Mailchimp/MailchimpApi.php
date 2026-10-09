<?php

namespace App\Services\Mailchimp;

use Illuminate\Support\Facades\Http;
use Throwable;

class MailchimpApi
{
    private const PAGE_SIZE = 1000;
    private const MAX_PAGES = 10;

    public function configured(array $connection): bool
    {
        return is_string($connection['api_key'] ?? null)
            && trim($connection['api_key']) !== ''
            && strlen($connection['api_key']) <= 256
            && ! preg_match('/[\r\n]/', $connection['api_key'])
            && preg_match('/^us\d{1,3}$/D', (string) ($connection['server_prefix'] ?? ''))
            && preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', (string) ($connection['audience_id'] ?? ''));
    }

    public function member(array $connection, string $hash): ?array
    {
        $member = $this->request($connection, 'GET', $this->membersPath($connection) . '/' . $hash, [
            'fields' => 'id,status',
        ], true);

        if ($member !== null && (! isset($member['id'], $member['status'])
            || ! is_string($member['id']) || ! hash_equals($hash, strtolower($member['id']))
            || ! is_string($member['status']))) {
            throw new MailchimpApiException('invalid_member_response', true);
        }

        return $member;
    }

    public function audience(array $connection): array
    {
        return $this->request($connection, 'GET', 'lists/' . $connection['audience_id'], [
            'fields' => 'id,double_optin',
        ]);
    }

    /** A missing direct member response does not prove an address is new. */
    public function isArchived(array $connection, string $hash): bool
    {
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $result = $this->request($connection, 'GET', $this->membersPath($connection), [
                'status' => 'archived', 'count' => self::PAGE_SIZE,
                'offset' => $page * self::PAGE_SIZE, 'fields' => 'members.id,total_items',
            ]);
            $total = $this->total($result);
            $members = $result['members'] ?? null;

            if (! is_array($members)) {
                throw new MailchimpApiException('archive_check_incomplete', true);
            }

            foreach ($members as $member) {
                if (! is_array($member) || ! is_string($member['id'] ?? null)
                    || ! preg_match('/^[a-f0-9]{32}$/iD', $member['id'])) {
                    throw new MailchimpApiException('archive_check_incomplete', true);
                }
                if (hash_equals($hash, strtolower($member['id']))) {
                    return true;
                }
            }

            $seen = $page * self::PAGE_SIZE + count($members);
            if ($seen >= $total) {
                return false;
            }
            if (count($members) !== self::PAGE_SIZE) {
                throw new MailchimpApiException('archive_check_incomplete', true);
            }
        }

        throw new MailchimpApiException('archive_check_incomplete', true);
    }

    public function createMember(array $connection, string $email, bool $doubleOptIn): array
    {
        // POST is deliberately create-only. PUT could unarchive an existing contact.
        return $this->request($connection, 'POST', $this->membersPath($connection), [
            'email_address' => $email,
            'status' => $doubleOptIn ? 'pending' : 'subscribed',
            'language' => 'hr',
        ]);
    }

    private function membersPath(array $connection): string
    {
        return 'lists/' . $connection['audience_id'] . '/members';
    }

    private function total(array $response): int
    {
        if (! isset($response['total_items']) || ! is_int($response['total_items']) || $response['total_items'] < 0) {
            throw new MailchimpApiException('invalid_collection_response', true);
        }
        return $response['total_items'];
    }

    private function request(array $connection, string $method, string $path, array $data = [], bool $allowMissing = false): ?array
    {
        if (! $this->configured($connection)) {
            throw new MailchimpApiException('configuration_missing');
        }

        try {
            $response = Http::withBasicAuth('vremeplov', $connection['api_key'])
                ->acceptJson()->asJson()->timeout(8)
                ->withOptions(['connect_timeout' => 4, 'allow_redirects' => false])
                ->send($method, 'https://' . $connection['server_prefix'] . '.api.mailchimp.com/3.0/' . $path, [
                    $method === 'GET' ? 'query' : 'json' => $data,
                ]);
        } catch (Throwable $exception) {
            throw new MailchimpApiException('connection_failed', true);
        }

        if ($allowMissing && $response->status() === 404) {
            return null;
        }
        if (! $response->successful()) {
            $status = $response->status();
            throw new MailchimpApiException('remote_http_' . $status, $status === 429 || $status >= 500);
        }
        $result = $response->json();
        if (! is_array($result)) {
            throw new MailchimpApiException('invalid_api_response', true);
        }
        return $result;
    }
}
