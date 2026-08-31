<?php

namespace App\Services\Shipping;

use App\Models\Back\Orders\Order;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class BoxNowService
{
    public const CARRIER = 'boxnow';

    /** @var string|null */
    private $token;

    /** @var BoxNowSettingsService */
    private $settings;

    public function __construct(BoxNowSettingsService $settings)
    {
        $this->settings = $settings;
    }

    public function createDeliveryRequest(Order $order): array
    {
        $payload = $this->deliveryPayload($order);

        // Autorizacija se izvršava prije delivery POST-a, ali nakon lokalne
        // validacije. Samo prekid tijekom samog kreiranja pošiljke ima nejasan
        // ishod koji zahtijeva recovery GET.
        $request = $this->authorizedRequest()->asJson();

        try {
            $response = $request->post($this->url('/delivery-requests'), $payload);
        } catch (ConnectionException $exception) {
            return $this->recoverDeliveryRequest($order, $exception, false);
        }

        if (! $response->successful()) {
            $exception = new RuntimeException(
                $this->errorMessage($response->json(), 'Box Now pošiljka nije kreirana.')
            );

            if ($this->isOrderNumberConflict($response)) {
                return $this->recoverDeliveryRequest($order, $exception, true);
            }

            if ($this->isAmbiguousCreateFailure($response)) {
                return $this->recoverDeliveryRequest($order, $exception, false);
            }

            throw $exception;
        }

        $payload = $response->json() ?: [];
        $parcelId = trim((string) data_get($payload, 'parcels.0.id', ''));

        if ($parcelId === '') {
            throw new RuntimeException('Box Now nije vratio ID pošiljke.');
        }

        return [
            'carrier' => self::CARRIER,
            'parcel_id' => $parcelId,
            'tracking_code' => $parcelId,
            'tracking_url' => $this->trackingUrl($parcelId),
            'status_code' => 'new',
            'status' => $this->statusLabel('new'),
            'tracked_at' => now(),
            'payload' => $payload,
        ];
    }

    public function track(Order $order): array
    {
        $parcelId = $this->knownParcelId($order);
        $query = ['limit' => 1];

        if ($parcelId !== '') {
            $query['parcelId'] = $parcelId;
        } else {
            $query['orderNumber'] = $this->orderNumber($order);
        }

        $response = $this->authorizedRequest()
            ->acceptJson()
            ->get($this->url('/parcels'), $query);

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), 'Box Now status nije dohvaćen.'));
        }

        $payload = $response->json() ?: [];
        $parcel = $this->firstParcel($payload);
        $returnedParcelId = trim((string) (data_get($parcel, 'id') ?: data_get($parcel, 'parcelId')));

        if ($returnedParcelId === '') {
            throw new RuntimeException('Box Now nije pronašao pošiljku za ovu narudžbu.');
        }

        if ($parcelId !== '' && $returnedParcelId !== $parcelId) {
            throw new RuntimeException('Box Now je vratio drugu pošiljku od zatražene.');
        }

        $parcelId = $returnedParcelId;
        $state = Str::lower($this->latestState($parcel));

        return [
            'carrier' => self::CARRIER,
            'parcel_id' => $parcelId,
            'tracking_code' => $parcelId,
            'tracking_url' => $this->trackingUrl($parcelId),
            'status_code' => $state !== '' ? $state : null,
            'status' => $state !== '' ? $this->statusLabel($state) : 'Box Now status nije dostupan.',
            'tracked_at' => now(),
            'payload' => $payload,
            'is_delivered' => $state === 'delivered',
        ];
    }

    public function label(Order $order): array
    {
        $parcelId = $this->knownParcelId($order);

        if ($parcelId === '') {
            throw new RuntimeException('Box Now pošiljka još nema ID za preuzimanje adresnice.');
        }

        $response = $this->authorizedRequest()
            ->accept('application/pdf')
            ->get($this->url('/parcels/' . rawurlencode($parcelId) . '/label.pdf'));

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), 'Box Now adresnica nije dohvaćena.'));
        }

        $contents = $response->body();

        if (! str_starts_with($contents, '%PDF-')) {
            throw new RuntimeException('Box Now nije vratio ispravnu PDF adresnicu.');
        }

        $safeParcelId = preg_replace('/[^A-Za-z0-9_-]/', '-', $parcelId) ?: 'parcel';

        return [
            'contents' => $contents,
            'filename' => 'boxnow-' . $safeParcelId . '.pdf',
        ];
    }

    public function trackingUrl(string $parcelId): ?string
    {
        $parcelId = trim($parcelId);
        $baseUrl = trim((string) $this->settings->get()['tracking_url']);

        if ($parcelId === '' || $baseUrl === '') {
            return null;
        }

        if (Str::contains($baseUrl, '{parcel}')) {
            return str_replace('{parcel}', urlencode($parcelId), $baseUrl);
        }

        if (Str::contains($baseUrl, 'track.boxnow.hr')) {
            if (preg_match('/([?&]track=)([^&]*)/', $baseUrl)) {
                return preg_replace('/([?&]track=)([^&]*)/', '$1' . urlencode($parcelId), $baseUrl);
            }

            return $baseUrl . (Str::contains($baseUrl, '?') ? '&' : '?') . 'track=' . urlencode($parcelId);
        }

        return rtrim($baseUrl, '/') . '/' . urlencode($parcelId);
    }

    public function statusLabel(?string $state): string
    {
        $state = Str::lower(trim((string) $state));

        return [
            'new' => 'Čeka se preuzimanje iz trgovine.',
            'in-depot' => 'Pošiljka se dostavlja.',
            'intransit' => 'Pošiljka se dostavlja.',
            'in-transit' => 'Pošiljka se dostavlja.',
            'final-destination' => 'Pošiljka se nalazi u odabranom paketomatu.',
            'in-final-destination' => 'Pošiljka se nalazi u odabranom paketomatu.',
            'delivered' => 'Pošiljka je preuzeta.',
            'returned' => 'Pošiljka je vraćena pošiljatelju.',
            'expired' => 'Isteklo je vrijeme preuzimanja i pošiljka se vraća pošiljatelju.',
            'expired-return' => 'Isteklo je vrijeme preuzimanja i pošiljka se vraća pošiljatelju.',
            'canceled' => 'Pošiljka je otkazana.',
            'cancelled' => 'Pošiljka je otkazana.',
            'lost' => 'Pošiljka se pronalazi.',
            'missing' => 'Pošiljka se pronalazi.',
            'accepted-to-locker' => 'Pošiljka je u procesu dostave.',
            'accepted-for-return' => 'Pošiljka je u procesu povrata.',
            'wait-for-load' => 'Pošiljka čeka preuzimanje iz paketomata.',
        ][$state] ?? ($state !== '' ? 'Box Now status: ' . $state : 'Box Now status nije dostupan.');
    }

    public static function terminalStatusCodes(): array
    {
        // expired/lost/missing još mogu prijeći u drugi status i zato se
        // nastavljaju pratiti.
        return ['delivered', 'returned', 'expired-return', 'canceled', 'cancelled'];
    }

    private function recoverDeliveryRequest(Order $order, \Throwable $cause, bool $conflict): array
    {
        // POST se namjerno ne ponavlja. Nakon nejasnog ishoda provjerava se
        // jedinstveni orderNumber kako se ne bi kreirala dupla pošiljka.
        foreach ([0, 100000, 250000] as $delayMicroseconds) {
            if ($delayMicroseconds > 0) {
                usleep($delayMicroseconds);
            }

            try {
                $tracking = $this->track($order);
                $tracking['recovered'] = true;

                return $tracking;
            } catch (\Throwable $recoveryException) {
                // Novokreirana pošiljka može kratko kasniti u rezultatima pretrage.
            }
        }

        if ($conflict) {
            throw new RuntimeException(
                'Box Now javlja da pošiljka za ovu narudžbu već postoji, ali njezin ID nije moguće dohvatiti. Pokušajte ponovno.',
                0,
                $cause
            );
        }

        throw new RuntimeException(
            'Box Now nije potvrdio je li pošiljka kreirana. Pokušajte ponovno; postojeća pošiljka prepoznat će se po broju narudžbe.',
            0,
            $cause
        );
    }

    private function isOrderNumberConflict(Response $response): bool
    {
        if ($response->status() === 409) {
            return true;
        }

        $payload = $response->json();
        $serialized = is_string($payload)
            ? $payload
            : (json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $serialized = Str::upper($serialized);

        return Str::contains($serialized, ['P410', 'ORDER NUMBER CONFLICT']);
    }

    private function isAmbiguousCreateFailure(Response $response): bool
    {
        return in_array($response->status(), [408, 429], true) || $response->serverError();
    }

    private function authorizedRequest(): PendingRequest
    {
        $request = Http::withToken($this->accessToken())
            ->timeout(25)
            ->withoutRedirecting();
        $partnerId = $this->settings->get()['api_partner_id'];

        if ($partnerId !== '') {
            $request->withHeaders(['X-PartnerID' => $partnerId]);
        }

        return $request;
    }

    private function accessToken(): string
    {
        if ($this->token) {
            return $this->token;
        }

        $this->assertConfigured();
        $response = Http::asJson()
            ->timeout(20)
            ->withoutRedirecting()
            ->post($this->url('/auth-sessions'), [
                'grant_type' => 'client_credentials',
                'client_id' => $this->settings->get()['client_id'],
                'client_secret' => $this->settings->get()['client_secret'],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), 'Box Now autorizacija nije uspjela.'));
        }

        $this->token = trim((string) data_get($response->json(), 'access_token', ''));

        if ($this->token === '') {
            throw new RuntimeException('Box Now nije vratio pristupni token.');
        }

        return $this->token;
    }

    private function assertConfigured(): void
    {
        $missing = collect($this->settings->missingConfiguration());

        if ($missing->isNotEmpty()) {
            throw new RuntimeException('Box Now nije konfiguriran u adminu. Nedostaje: ' . $missing->implode(', ') . '.');
        }

        if (! $this->settings->isAllowedApiUrl()) {
            throw new RuntimeException('Box Now API URL nije dopušten. Provjerite službenu HTTPS adresu u adminu.');
        }
    }

    private function deliveryPayload(Order $order): array
    {
        $settings = $this->settings->get();
        $lockerId = $this->lockerId($order);

        if ($lockerId === '') {
            throw new RuntimeException('Box Now paketomat nije upisan na narudžbi.');
        }

        $paymentCode = Str::lower(trim((string) $order->payment_code));

        if ($paymentCode === 'pickup') {
            throw new RuntimeException('Plaćanje kod osobnog preuzimanja nije dopušteno za Box Now.');
        }

        $paymentMode = $paymentCode === 'cod' ? 'cod' : 'prepaid';

        if ($paymentMode === 'cod' && ! $settings['cod_enabled']) {
            throw new RuntimeException('Plaćanje pouzećem nije omogućeno u Box Now postavkama.');
        }

        $payload = [
            'orderNumber' => $this->orderNumber($order),
            'invoiceValue' => $this->money($order->total),
            'paymentMode' => $paymentMode,
            'amountToBeCollected' => $paymentMode === 'cod' ? $this->money($order->total) : '0.00',
            'allowReturn' => $settings['allow_return'],
            'origin' => [
                'contactNumber' => $this->normalizePhone($settings['origin_phone']),
                'contactEmail' => $settings['origin_email'],
                'contactName' => $settings['origin_name'],
                'locationId' => $settings['warehouse_location_id'],
            ],
            'destination' => [
                'contactNumber' => $this->normalizePhone($order->shipping_phone ?: $order->payment_phone),
                'contactEmail' => (string) ($order->shipping_email ?: $order->payment_email),
                'contactName' => trim((string) ($order->shipping_fname ?: $order->payment_fname) . ' ' . (string) ($order->shipping_lname ?: $order->payment_lname)),
                'locationId' => $lockerId,
            ],
            'items' => [$this->itemPayload($order)],
        ];

        if ($settings['email_label_on_create']) {
            $payload['notifyOnAccepted'] = $settings['origin_email'];
        }

        return $payload;
    }

    private function itemPayload(Order $order): array
    {
        $products = $order->products;
        $first = $products->first();
        $value = (float) $products->sum('total');

        return [
            // BOX NOW traži ID artikla jedinstven na razini e-shopa. Budući
            // da se svi proizvodi šalju kao jedan paket, order ID + redni broj
            // stabilan je i ne ponavlja se u drugim narudžbama.
            'id' => $this->orderNumber($order) . '-1',
            'name' => (string) ($first->name ?? ('Narudžba #' . $order->id)),
            'value' => $this->money($value > 0 ? $value : $order->total),
            'weight' => 0,
        ];
    }

    private function lockerId(Order $order): string
    {
        $pickup = trim((string) $order->commentp);
        $position = strrpos($pickup, '_');

        if ($position === false) {
            return '';
        }

        $lockerId = trim(substr($pickup, $position + 1));

        if ($lockerId === '' || strlen($lockerId) > 191 || ! preg_match('/^[A-Za-z0-9-]+$/', $lockerId)) {
            return '';
        }

        return $lockerId;
    }

    private function knownParcelId(Order $order): string
    {
        $parcelId = trim((string) $order->shipping_parcel_id);

        if ($parcelId !== '') {
            return $parcelId;
        }

        return Str::lower(trim((string) $order->shipping_carrier)) === self::CARRIER
            ? trim((string) $order->tracking_code)
            : '';
    }

    private function orderNumber(Order $order): string
    {
        $prefix = trim((string) $this->settings->get()['order_prefix']);

        return $prefix . '-' . $order->id;
    }

    private function normalizePhone($phone): string
    {
        $raw = trim((string) $phone);
        $digits = preg_replace('/[^0-9]/', '', $raw) ?: '';

        if ($digits === '') {
            return '';
        }

        if (Str::startsWith($raw, '+')) {
            $normalized = '+' . $digits;
        } elseif (Str::startsWith($digits, '00')) {
            $normalized = '+' . substr($digits, 2);
        } elseif (Str::startsWith($digits, '385')) {
            $normalized = '+' . $digits;
        } elseif (Str::startsWith($digits, '0')) {
            $normalized = '+385' . substr($digits, 1);
        } else {
            $normalized = $digits;
        }

        // Korisnici često upisuju međunarodni hrvatski broj kao +385 (0)...
        // Lokalna nula nakon pozivnog broja ne pripada E.164 formatu.
        if (Str::startsWith($normalized, '+3850')) {
            $normalized = '+385' . substr($normalized, 5);
        }

        return $normalized;
    }

    private function latestState(array $parcel): string
    {
        $direct = trim((string) (data_get($parcel, 'event') ?: data_get($parcel, 'state') ?: data_get($parcel, 'parcelState')));

        if ($direct !== '') {
            return $direct;
        }

        $events = data_get($parcel, 'events', []);

        if (! is_array($events) || empty($events)) {
            return '';
        }

        usort($events, function ($left, $right) {
            return $this->timestamp(data_get($left, 'createTime', data_get($left, 'time')))
                <=> $this->timestamp(data_get($right, 'createTime', data_get($right, 'time')));
        });

        $latest = end($events) ?: [];

        return trim((string) (data_get($latest, 'event') ?: data_get($latest, 'type') ?: data_get($latest, 'state')));
    }

    private function timestamp($value): int
    {
        try {
            return $value ? Carbon::parse($value)->timestamp : 0;
        } catch (\Throwable $exception) {
            return 0;
        }
    }

    private function firstParcel(array $payload): array
    {
        $data = data_get($payload, 'data', data_get($payload, 'parcels', $payload));

        if (isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }

        return is_array($data) ? $data : [];
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->settings->get()['base_url'], '/') . '/' . ltrim($path, '/');
    }

    private function errorMessage($payload, string $fallback): string
    {
        $message = data_get($payload, 'message') ?: data_get($payload, 'error');

        if (is_array($message)) {
            $message = data_get($message, 'message') ?: json_encode($message);
        }

        return trim((string) $message) ?: $fallback;
    }

    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
