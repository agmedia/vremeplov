<?php

namespace App\Services\Shipping;

use App\Models\Back\Orders\Order;
use Carbon\Carbon;
use RuntimeException;
use SoapClient;
use Throwable;

class GlsTrackingService
{
    public const CARRIER = 'gls';

    public function trackOrder(Order $order): array
    {
        $parcelNumber = trim((string) $order->tracking_code);

        if ($parcelNumber === '') {
            $parcelNumber = $this->parcelNumberForOrder($order);
        }

        if ($parcelNumber !== '') {
            return $this->track($parcelNumber);
        }

        return [
            'carrier' => self::CARRIER,
            'parcel_id' => $order->shipping_parcel_id,
            'tracking_code' => null,
            'tracking_url' => null,
            'status_code' => '51',
            'status' => $this->statusCodeMeaning('51'),
            'tracked_at' => now(),
            'payload' => [
                'lookup' => 'ParcelNumber još nije dostupan u GLS parcel listi.',
                'parcel_id' => $order->shipping_parcel_id,
                'client_reference' => (string) $order->id,
            ],
        ];
    }

    public function track(string $parcelNumber): array
    {
        $parcelNumber = trim($parcelNumber);

        if ($parcelNumber === '') {
            throw new RuntimeException('GLS tracking broj nije upisan.');
        }

        $response = $this->client()->GetParcelStatuses([
            'getParcelStatusesRequest' => [
                'Username' => config('services.gls.username'),
                'Password' => $this->hashedPassword(),
                'ParcelNumber' => (int) $parcelNumber,
                'ReturnPOD' => false,
                'LanguageIsoCode' => config('services.gls.language', 'HR'),
            ],
        ]);

        $result = $this->toArray($response->GetParcelStatusesResult ?? []);
        $errors = $this->normalizeRows(data_get($result, 'GetParcelStatusErrors.ErrorInfo', data_get($result, 'GetParcelStatusErrors', [])));

        if (! empty($errors)) {
            throw new RuntimeException('GLS tracking greška: ' . $this->formatErrors($errors));
        }

        $statuses = $this->normalizeRows(data_get($result, 'ParcelStatusList.ParcelStatus', data_get($result, 'ParcelStatusList', [])));
        $latest = $this->latestStatus($statuses);
        $statusCode = trim((string) data_get($latest, 'StatusCode', ''));
        $description = trim((string) data_get($latest, 'StatusDescription', ''));
        $info = trim((string) data_get($latest, 'StatusInfo', ''));

        if ($description === '' && $statusCode !== '') {
            $description = $this->statusCodeMeaning($statusCode);
        }

        $displayStatus = trim($description . ($info !== '' ? ' - ' . $info : ''));
        $resolvedParcelNumber = (string) data_get($result, 'ParcelNumber', $parcelNumber);

        return [
            'carrier' => self::CARRIER,
            'tracking_code' => $resolvedParcelNumber,
            'tracking_url' => $this->trackingUrl($resolvedParcelNumber),
            'status_code' => $statusCode ?: null,
            'status' => $displayStatus ?: 'GLS status nije dostupan.',
            'tracked_at' => now(),
            'payload' => $result,
            'is_delivered' => in_array($statusCode, ['5', '92'], true),
        ];
    }

    public function trackingUrl(string $parcelNumber): ?string
    {
        $parcelNumber = trim($parcelNumber);
        $url = trim((string) config('services.gls.tracking_url'));

        if ($url === '' || $parcelNumber === '') {
            return null;
        }

        if (str_contains($url, '{tracking_code}')) {
            return str_replace('{tracking_code}', rawurlencode($parcelNumber), $url);
        }

        return rtrim($url, '/') . '/?match=' . rawurlencode($parcelNumber);
    }

    private function client(): SoapClient
    {
        $options = [
            'soap_version' => SOAP_1_1,
            'connection_timeout' => max(1, (int) config('services.gls.connection_timeout', 20)),
        ];

        if (file_exists(base_path('cacert.pem'))) {
            $options['stream_context'] = stream_context_create([
                'ssl' => [
                    'cafile' => base_path('cacert.pem'),
                ],
            ]);
        }

        return new SoapClient(config('services.gls.wsdl'), $options);
    }

    private function parcelNumberForOrder(Order $order): string
    {
        $response = $this->client()->GetParcelList([
            'getParcelListRequest' => [
                'Username' => config('services.gls.username'),
                'Password' => $this->hashedPassword(),
                'PickupDateFrom' => $this->parcelListDateFrom($order),
                'PickupDateTo' => now()->addDays(7)->format('Y-m-d'),
                'PrintDateFrom' => null,
                'PrintDateTo' => null,
            ],
        ]);

        $result = $this->toArray($response->GetParcelListResult ?? []);
        $errors = $this->normalizeRows(data_get($result, 'GetParcelListErrors.ErrorInfo', data_get($result, 'GetParcelListErrors', [])));

        if (! empty($errors)) {
            throw new RuntimeException('GLS parcel list greška: ' . $this->formatErrors($errors));
        }

        $rows = $this->normalizeRows(data_get($result, 'PrintDataInfoList.PrintDataInfo', data_get($result, 'PrintDataInfoList', [])));
        $orderId = (string) $order->id;
        $parcelId = (string) $order->shipping_parcel_id;

        foreach ($rows as $row) {
            if (! $this->matchesOrder($row, $orderId, $parcelId)) {
                continue;
            }

            $parcelNumber = $this->firstFilled($row, ['ParcelNumber', 'ParcelNo', 'ParcelNum']);

            if ($parcelNumber !== '') {
                return $parcelNumber;
            }
        }

        return '';
    }

    private function parcelListDateFrom(Order $order): string
    {
        try {
            return Carbon::make($order->created_at ?: now())->subDay()->format('Y-m-d');
        } catch (Throwable $exception) {
            return now()->subDays(30)->format('Y-m-d');
        }
    }

    private function hashedPassword(): string
    {
        return hash('sha512', (string) config('services.gls.password'), true);
    }

    private function latestStatus(array $statuses): array
    {
        if (empty($statuses)) {
            return [];
        }

        usort($statuses, function (array $left, array $right) {
            return $this->statusTimestamp($left) <=> $this->statusTimestamp($right);
        });

        return end($statuses) ?: [];
    }

    private function statusTimestamp(array $status): int
    {
        try {
            $date = data_get($status, 'StatusDate');

            return $date ? Carbon::parse($date)->timestamp : 0;
        } catch (Throwable $exception) {
            return 0;
        }
    }

    private function toArray($value): array
    {
        return json_decode(json_encode($value), true) ?: [];
    }

    private function normalizeRows($rows): array
    {
        $rows = $this->toArray($rows);

        if (empty($rows)) {
            return [];
        }

        if (array_key_exists('StatusCode', $rows) || array_key_exists('ErrorCode', $rows)) {
            return [$rows];
        }

        if ($this->isList($rows)) {
            return array_values(array_filter($rows, 'is_array'));
        }

        return [$rows];
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function formatErrors(array $errors): string
    {
        return collect($errors)
            ->map(function (array $error) {
                $code = data_get($error, 'ErrorCode', data_get($error, 'ErrorNumber'));
                $message = data_get($error, 'ErrorDescription', data_get($error, 'ErrorMessage', 'Nepoznata greška'));

                return trim(($code ? '#' . $code . ' ' : '') . $message);
            })
            ->filter()
            ->implode('; ');
    }

    private function matchesOrder(array $row, string $orderId, string $parcelId): bool
    {
        $references = [
            $this->firstFilled($row, ['ClientReference', 'CustomerReference', 'Reference', 'CODReference']),
            $this->firstFilled($row, ['ParcelId', 'ParcelID']),
        ];

        return collect($references)
            ->filter()
            ->contains(function (string $reference) use ($orderId, $parcelId) {
                return $reference === $orderId || ($parcelId !== '' && $reference === $parcelId);
            });
    }

    private function firstFilled(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) data_get($row, $key, ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function statusCodeMeaning(string $statusCode): string
    {
        return [
            '1' => 'Pošiljka je predana GLS-u.',
            '2' => 'Pošiljka je napustila paketni centar.',
            '3' => 'Pošiljka je stigla u paketni centar.',
            '4' => 'Pošiljka je planirana za dostavu tijekom dana.',
            '5' => 'Pošiljka je dostavljena.',
            '6' => 'Pošiljka je pohranjena u paketnom centru.',
            '7' => 'Pošiljka je pohranjena u paketnom centru.',
            '8' => 'Pošiljka je u GLS paketnom centru i primatelj ju preuzima sam.',
            '9' => 'Pošiljka je pohranjena za novi datum dostave.',
            '11' => 'Pošiljka nije dostavljena jer je primatelj na odmoru.',
            '12' => 'Pošiljka nije dostavljena jer primatelj nije bio prisutan.',
            '14' => 'Pošiljka nije dostavljena jer je primatelj bio zatvoren.',
            '16' => 'Pošiljka nije dostavljena jer primatelj nije imao gotovinu.',
            '17' => 'Primatelj je odbio preuzimanje pošiljke.',
            '18' => 'Za dostavu su potrebne dodatne informacije o adresi.',
            '20' => 'Pošiljka nije dostavljena zbog pogrešne ili nepotpune adrese.',
            '23' => 'Pošiljka je vraćena pošiljatelju.',
            '40' => 'Pošiljka je vraćena pošiljatelju.',
            '51' => 'Podaci o pošiljci su uneseni u GLS sustav; pošiljka još nije predana GLS-u.',
            '54' => 'Pošiljka je dostavljena u paketomat.',
            '55' => 'Pošiljka je dostavljena u ParcelShop.',
            '56' => 'Pošiljka je pohranjena u GLS ParcelShopu.',
            '57' => 'Pošiljka je dosegla maksimalno vrijeme čuvanja u ParcelShopu.',
            '58' => 'Pošiljka je dostavljena susjedu.',
            '80' => 'Pošiljka je proslijeđena na željenu adresu.',
            '92' => 'Pošiljka je dostavljena.',
            '97' => 'Pošiljka je smještena u paketomat.',
            '401' => 'Problem s kapacitetom paketomata.',
            '402' => 'Pošiljka je prevelika za paketomat.',
            '403' => 'Pošiljka je oštećena.',
            '404' => 'Tehnički problem s paketomata.',
            '420' => 'Neispravan pretinac paketomata.',
        ][$statusCode] ?? 'GLS status #' . $statusCode;
    }
}
