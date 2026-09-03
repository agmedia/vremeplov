<?php

namespace App\Services\Payments;

use App\Models\Back\Orders\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class PaymentAttemptService
{
    private const DEFAULT_REFERENCE_BYTES = 32;
    private const WSPAY_REFERENCE_BYTES = 20;

    /**
     * Freeze the values sent to an external payment provider. Once frozen, an
     * order must not be silently reused with a different amount or provider.
     */
    public function start(
        Order $order,
        string $provider,
        ?string $reference,
        string $currency,
        string $environment,
        string $merchant,
        ?string $verificationSecret = null
    ): Order {
        return DB::transaction(function () use (
            $order,
            $provider,
            $reference,
            $currency,
            $environment,
            $merchant,
            $verificationSecret
        ) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $provider = strtolower(trim($provider));
            $reference = trim((string) $reference);
            $currency = strtoupper(trim($currency));
            $environment = strtolower(trim($environment));
            $merchant = trim($merchant);
            $amountMinor = DecimalAmount::fromDatabase($locked->total);
            $orderHash = $this->orderHash($locked);

            if ($provider === ''
                || ! hash_equals($provider, strtolower(trim((string) $locked->payment_code)))
                || ! preg_match('/^[A-Z]{3}$/D', $currency)
                || $amountMinor === null
                || $amountMinor <= 0
                || $environment === ''
                || mb_strlen($environment) > 16
                || $merchant === ''
                || mb_strlen($merchant) > 191) {
                throw new \RuntimeException('Podaci pokušaja plaćanja nisu valjani.', 409);
            }

            if ($locked->payment_attempt_started_at !== null) {
                if (! hash_equals($provider, strtolower((string) $locked->payment_attempt_provider))
                    || ($reference !== ''
                        && ! hash_equals($reference, (string) $locked->payment_attempt_reference))
                    || ! hash_equals($currency, strtoupper((string) $locked->payment_expected_currency))
                    || (int) $locked->payment_expected_amount_minor !== $amountMinor
                    || ! hash_equals((string) $locked->payment_attempt_order_hash, $orderHash)
                    || (int) $locked->payment_attempt_reservation_version
                        !== (int) $locked->inventory_reservation_version) {
                    throw new \RuntimeException(
                        'Iznos ili način plaćanja promijenjen je nakon otvaranja platne stranice. Napravite novu narudžbu.',
                        409
                    );
                }

                // WSPay accepts at most 40 characters for ShoppingCartID. The
                // first snapshot implementation generated 64-character tokens,
                // so transparently repair attempts that WSPay could not accept.
                if ($provider === 'wspay'
                    && $reference === ''
                    && preg_match(
                        '/^[a-f0-9]{64}$/D',
                        (string) $locked->payment_attempt_reference
                    )) {
                    $saved = $locked->forceFill([
                        'payment_attempt_reference' => $this->generateReference($provider),
                    ])->save();

                    if (! $saved) {
                        throw new \RuntimeException('Pokušaj plaćanja nije moguće sigurno spremiti.', 409);
                    }

                    return $locked->fresh();
                }

                // Mode and merchant deliberately come from the first attempt.
                // A later settings change must not reinterpret an in-flight payment.
                return $locked;
            }

            if ($reference === '') {
                $reference = $this->generateReference($provider);
            }

            if (mb_strlen($reference) > 191) {
                throw new \RuntimeException('Oznaka pokušaja plaćanja nije valjana.', 409);
            }

            $saved = $locked->forceFill([
                'payment_attempt_started_at' => now(),
                'payment_attempt_provider' => $provider,
                'payment_attempt_reference' => $reference,
                'payment_expected_amount_minor' => $amountMinor,
                'payment_expected_currency' => $currency,
                'payment_attempt_environment' => $environment,
                'payment_attempt_merchant' => $merchant,
                'payment_attempt_verification_key' => $verificationSecret !== null
                    ? Crypt::encryptString($verificationSecret)
                    : null,
                'payment_attempt_order_hash' => $orderHash,
                'payment_attempt_reservation_version' => (int) $locked->inventory_reservation_version,
            ])->save();

            if (! $saved) {
                throw new \RuntimeException('Pokušaj plaćanja nije moguće sigurno spremiti.', 409);
            }

            return $locked->fresh();
        });
    }

    public function verificationSecret(Order $order): ?string
    {
        if (! $order->payment_attempt_verification_key) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $order->payment_attempt_verification_key);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    public function matchesSnapshot(Order $order): bool
    {
        if ($order->payment_attempt_started_at === null
            || ! preg_match('/^[a-f0-9]{64}$/D', (string) $order->payment_attempt_order_hash)) {
            return false;
        }

        return hash_equals(
            (string) $order->payment_attempt_order_hash,
            $this->orderHash($order)
        ) && (int) $order->payment_attempt_reservation_version
            === (int) $order->inventory_reservation_version;
    }

    public function matchesCheckoutData(Order $order, array $data): bool
    {
        $cartItems = collect(data_get($data, 'cart.items', []))
            ->mapToGroups(function ($item) {
                return [(int) $item->id => (int) $item->quantity];
            })
            ->map(function ($quantities) {
                return $quantities->sum();
            })
            ->sortKeys()
            ->all();
        $orderItems = DB::table('order_products')
            ->where('order_id', $order->id)
            ->select('product_id', DB::raw('SUM(quantity) AS quantity'))
            ->groupBy('product_id')
            ->orderBy('product_id')
            ->get()
            ->mapWithKeys(function ($item) {
                return [(int) $item->product_id => (int) $item->quantity];
            })
            ->all();
        $address = (array) data_get($data, 'address', []);

        return $cartItems === $orderItems
            && DecimalAmount::fromDatabase(data_get($data, 'cart.total', 0))
                === (int) $order->payment_expected_amount_minor
            && strtolower((string) data_get($data, 'payment.code', ''))
                === strtolower((string) $order->payment_code)
            && strtolower((string) data_get($data, 'shipping.code', ''))
                === strtolower((string) $order->shipping_code)
            && trim((string) ($address['fname'] ?? '')) === trim((string) $order->payment_fname)
            && trim((string) ($address['lname'] ?? '')) === trim((string) $order->payment_lname)
            && trim((string) ($address['address'] ?? '')) === trim((string) $order->payment_address)
            && trim((string) ($address['state'] ?? '')) === trim((string) $order->payment_state)
            && trim((string) ($address['zip'] ?? '')) === trim((string) $order->payment_zip)
            && trim((string) ($address['city'] ?? '')) === trim((string) $order->payment_city)
            && trim((string) ($address['email'] ?? '')) === trim((string) $order->payment_email)
            && trim((string) ($address['phone'] ?? '')) === trim((string) $order->payment_phone);
    }

    private function orderHash(Order $order): string
    {
        $items = DB::table('order_products')
            ->where('order_id', $order->id)
            ->orderBy('product_id')
            ->orderBy('id')
            ->get(['product_id', 'quantity', 'price', 'total'])
            ->map(function ($item) {
                return [
                    'product_id' => (int) $item->product_id,
                    'quantity' => (int) $item->quantity,
                    'price' => number_format((float) $item->price, 4, '.', ''),
                    'total' => number_format((float) $item->total, 4, '.', ''),
                ];
            })
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'payment_code' => strtolower(trim((string) $order->payment_code)),
            'shipping_code' => strtolower(trim((string) $order->shipping_code)),
            'total' => number_format((float) $order->total, 4, '.', ''),
            'items' => $items,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function generateReference(string $provider): string
    {
        $bytes = $provider === 'wspay'
            ? self::WSPAY_REFERENCE_BYTES
            : self::DEFAULT_REFERENCE_BYTES;

        return bin2hex(random_bytes($bytes));
    }
}
