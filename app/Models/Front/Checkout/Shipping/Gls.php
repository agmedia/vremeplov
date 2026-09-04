<?php

namespace App\Models\Front\Checkout\Shipping;

use App\Models\Back\Orders\Order;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SoapClient;
use \stdClass;

/**
 * Class Cod
 * @package App\Models\Front\Checkout\Payment
 */
class Gls
{

    /**
     * @var int
     */
    private $order;


    /**
     * Cod constructor.
     *
     * @param $order
     */
    public function __construct($order)
    {
        $this->order = $order;
    }


    public function resolve()
    {
        try {
            $clientNumber = (int) config('services.gls.client_number');
            $username = trim((string) config('services.gls.username'));
            $pwd = (string) config('services.gls.password');

            $this->ensureConfigurationIsComplete($clientNumber, $username, $pwd);

            $password = hash('sha512', $pwd, true);

            $brojracuna = $this->order['id'];

            $komentar = $this->order['commentp'];

            $idmjesta = substr($komentar, strpos($komentar, "_") + 1);

            $parcels                 = [];
            $parcel                  = new StdClass();
            $parcel->ClientNumber    = $clientNumber;
            $parcel->ClientReference = $brojracuna;
            $parcel->CODAmount       = $this->getTotal();
            $parcel->CODReference    = $brojracuna;
            // $parcel->Content = "CONTENT";
            $parcel->Count                    = 1;
            $deliveryAddress                  = new StdClass();
            $deliveryAddress->ContactEmail    = $this->order['payment_email'];
            $deliveryAddress->ContactName     = $this->order['payment_fname'] . ' ' . $this->order['payment_lname'];
            $deliveryAddress->ContactPhone    = $this->order['payment_phone'];
            $deliveryAddress->Name            = $this->order['payment_fname'] . ' ' . $this->order['payment_lname'];
            $deliveryAddress->Street          = $this->order['payment_address'];
            $deliveryAddress->HouseNumber     = "";
            $deliveryAddress->City            = $this->order['payment_city'];
            $deliveryAddress->ZipCode         = $this->order['payment_zip'];
            $deliveryAddress->CountryIsoCode  = "HR";
            $deliveryAddress->HouseNumberInfo = "";
            $parcel->DeliveryAddress          = $deliveryAddress;
            $pickupAddress                    = new StdClass();
            $pickupAddress->ContactName       = (string) config('services.gls.pickup.contact_name');
            $pickupAddress->ContactPhone      = (string) config('services.gls.pickup.contact_phone');
            $pickupAddress->ContactEmail      = (string) config('services.gls.pickup.contact_email');
            $pickupAddress->Name              = (string) config('services.gls.pickup.name');
            $pickupAddress->Street            = (string) config('services.gls.pickup.street');
            $pickupAddress->HouseNumber       = (string) config('services.gls.pickup.house_number');
            $pickupAddress->City              = (string) config('services.gls.pickup.city');
            $pickupAddress->ZipCode           = (string) config('services.gls.pickup.zip_code');
            $pickupAddress->CountryIsoCode    = (string) config('services.gls.pickup.country_code');
            $pickupAddress->HouseNumberInfo   = "";
            $parcel->PickupAddress            = $pickupAddress;
            $parcel->PickupDate               = date('Y-m-d');
            if( $this->order['shipping_code']=='gls_paketomat'){
                $service1 = new StdClass();
                $service1->Code = "PSD";
                $parameter1 = new StdClass();
                $parameter1->StringValue = $idmjesta;
                $service1->PSDParameter = $parameter1;
                $services = [];
                $services[] = $service1;
                $parcel->ServiceList = $services;
            }

            $parcels[] = $parcel;

            //The service URL:
            $wsdl = (string) config('services.gls.wsdl');

            $soapOptions = [
                'soap_version' => SOAP_1_1,
                'exceptions' => true,
                'connection_timeout' => max(1, (int) config('services.gls.connection_timeout', 20)),
            ];

            if (file_exists(base_path('cacert.pem'))) {
                $soapOptions['stream_context'] = stream_context_create([
                    'ssl' => ['cafile' => base_path('cacert.pem')],
                ]);
            }

            //Parcel service:
            $serviceName = "ParcelService";

            return $this->PrepareLabels(
                $username,
                $password,
                $parcels,
                str_replace('SERVICE_NAME', $serviceName, $wsdl),
                $soapOptions,
                $this->order
            );

        } catch (\Throwable $e) {
            Log::error('GLS shipment creation failed.', [
                'order_id' => data_get($this->order, 'id'),
                'error' => $e->getMessage(),
            ]);

            return [
                'PrepareLabelsError' => [
                    ['ErrorDescription' => $e->getMessage()],
                ],
            ];
        }
    }


    private function ensureConfigurationIsComplete(int $clientNumber, string $username, string $password): void
    {
        $required = [
            'GLS_CLIENT_NUMBER' => $clientNumber > 0,
            'GLS_USERNAME' => $username !== '',
            'GLS_PASSWORD' => $password !== '',
            'GLS_WSDL' => filled(config('services.gls.wsdl')),
            'GLS_PICKUP_CONTACT_NAME' => filled(config('services.gls.pickup.contact_name')),
            'GLS_PICKUP_CONTACT_PHONE' => filled(config('services.gls.pickup.contact_phone')),
            'GLS_PICKUP_CONTACT_EMAIL' => filled(config('services.gls.pickup.contact_email')),
            'GLS_PICKUP_NAME' => filled(config('services.gls.pickup.name')),
            'GLS_PICKUP_STREET' => filled(config('services.gls.pickup.street')),
            'GLS_PICKUP_HOUSE_NUMBER' => filled(config('services.gls.pickup.house_number')),
            'GLS_PICKUP_CITY' => filled(config('services.gls.pickup.city')),
            'GLS_PICKUP_ZIP_CODE' => filled(config('services.gls.pickup.zip_code')),
            'GLS_PICKUP_COUNTRY_CODE' => filled(config('services.gls.pickup.country_code')),
        ];

        $missing = array_keys(array_filter($required, function ($configured) {
            return ! $configured;
        }));

        if ($missing !== []) {
            throw new RuntimeException('Nedostaje GLS konfiguracija: ' . implode(', ', $missing));
        }
    }


    private function getTotal()
    {
        if ($this->order['payment_code'] == 'cod') {
            $mani = $this->order['total'];
            $mani = number_format((float) $mani, 2, '.', '');

        } else {
            $mani = 0;
        }

        return $mani;
    }


    /**
     * Label(s) generation by the service.
     *
     * @param $username
     * @param $password
     * @param $parcels
     * @param $wsdl
     * @param $soapOptions
     *
     * @return void
     */
    private function PrintLabels($username, $password, $parcels, $wsdl, $soapOptions)
    {
        //Test request:
        $printLabelsRequest = array('Username'   => $username,
                                    'Password'   => $password,
                                    'ParcelList' => $parcels);

        $request = array("printLabelsRequest" => $printLabelsRequest);

        //Service client creation:
        $client = new SoapClient($wsdl, $soapOptions);

        //Service calling:
        $response = $client->PrintLabels($request);

        if ($response != null && count((array) $response->PrintLabelsResult->PrintLabelsErrorList) == 0 && $response->PrintLabelsResult->Labels != "") {
            //Label(s) saving:

            $this->response->setOutput(json_encode('OK'));
        }
    }


    /**
     * Preparing label(s) by the service.
     *
     * @param $username
     * @param $password
     * @param $parcels
     * @param $wsdl
     * @param $soapOptions
     * @param $order
     *
     * @return array
     */
    private function PrepareLabels($username, $password, $parcels, $wsdl, $soapOptions, $order)
    {
        //Test request:
        $prepareLabelsRequest = array('Username'   => $username,
                                      'Password'   => $password,
                                      'ParcelList' => $parcels);

        $request = array("prepareLabelsRequest" => $prepareLabelsRequest);

        //Service client creation:
        $client = new SoapClient($wsdl, $soapOptions);

        //Service calling:
        $response = $client->PrepareLabels($request);

        $result = $response->PrepareLabelsResult ?? null;
        $errors = json_decode(json_encode(
            $result->PrepareLabelsError
                ?? $result->PrepareLabelsErrorList
                ?? []
        ), true) ?: [];
        $parcelInfoList = json_decode(json_encode($result->ParcelInfoList ?? []), true) ?: [];
        $parcelInfo = $parcelInfoList['ParcelInfo'] ?? $parcelInfoList;

        if (isset($parcelInfo['ParcelId']) || isset($parcelInfo['ParcelNumber'])) {
            $parcelInfo = [$parcelInfo];
        }

        $parcelIdList = [];
        $parcelNumberList = [];

        foreach ($parcelInfo as $info) {
            if (! is_array($info)) {
                continue;
            }

            if (! empty($info['ParcelId'])) {
                $parcelIdList[] = (string) $info['ParcelId'];
            }

            if (! empty($info['ParcelNumber'])) {
                $parcelNumberList[] = (string) $info['ParcelNumber'];
            }
        }

        if ($response !== null && empty($errors) && ! empty($parcelIdList)) {
            $order->update(['printed' => 1]);
        }

        //Test request:
        $getPrintedLabelsRequest = array('Username'        => $username,
                                         'Password'        => $password,
                                         'ParcelIdList'    => $parcelIdList,
                                         'PrintPosition'   => 1,
                                         'ShowPrintDialog' => 0);

        return [
            'ParcelIdList' => $parcelIdList,
            'ParcelNumberList' => $parcelNumberList,
            'PrepareLabelsError' => $errors,
            'ParcelInfoList' => $parcelInfoList,
            'GetPrintedLabelsRequest' => $getPrintedLabelsRequest,
        ];
    }


    /**
     * Get label(s) by the service.
     *
     * @param $wsdl
     * @param $soapOptions
     * @param $getPrintedLabelsRequest
     *
     * @return void
     */
    private function GetPrintedLabels($wsdl, $soapOptions, $getPrintedLabelsRequest)
    {
        $request = array("getPrintedLabelsRequest" => $getPrintedLabelsRequest);

        //Service client creation:
        $client = new SoapClient($wsdl, $soapOptions);

        //Service calling:
        $response = $client->GetPrintedLabels($request);

        if ($response != null && count((array) $response->GetPrintedLabelsResult->GetPrintedLabelsErrorList) == 0 && $response->GetPrintedLabelsResult->Labels != "") {
            //Label(s) saving:
            file_put_contents('php_soap_client_GetPrintedLabels.pdf', $response->GetPrintedLabelsResult->Labels);
        }
    }


    /**
     * Get parcel(s) information by date ranges.
     *
     * @param $username
     * @param $password
     * @param $wsdl
     * @param $soapOptions
     *
     * @return void
     */
    private function GetParcelList($username, $password, $wsdl, $soapOptions)
    {
        //Test request:
        $getParcelListRequest = array('Username'       => $username,
                                      'Password'       => $password,
                                      'PickupDateFrom' => '2020-04-16',
                                      'PickupDateTo'   => '2020-04-16',
                                      'PrintDateFrom'  => null,
                                      'PrintDateTo'    => null);

        $request = array("getParcelListRequest" => $getParcelListRequest);

        //Service client creation:
        $client = new SoapClient($wsdl, $soapOptions);

        //Service calling:
        $response = $client->GetParcelList($request);

        var_dump(count((array) $response->GetParcelListResult->GetParcelListErrors));
        var_dump(count((array) $response->GetParcelListResult->PrintDataInfoList));
    }


    /**
     * Get parcel statuses.
     *
     * @param $username
     * @param $password
     * @param $wsdl
     * @param $soapOptions
     *
     * @return void
     */
    private function GetParcelStatuses($username, $password, $wsdl, $soapOptions)
    {
        //Test request:
        $getParcelStatusesRequest = array('Username'        => $username,
                                          'Password'        => $password,
                                          'ParcelNumber'    => 0,
                                          'ReturnPOD'       => true,
                                          'LanguageIsoCode' => "HR");

        $request = array("getParcelStatusesRequest" => $getParcelStatusesRequest);

        //Service client creation:
        $client = new SoapClient($wsdl, $soapOptions);

        //Service calling:
        $response = $client->GetParcelStatuses($request);

        if ($response != null) {
            var_dump(count((array) $response->GetParcelStatusesResult->GetParcelStatusErrors));
            if (count((array) $response->GetParcelStatusesResult->GetParcelStatusErrors) == 0 && $response->GetParcelStatusesResult->POD != "") {
                //POD saving:
                file_put_contents('php_soap_client_GetParcelStatuses.pdf', $response->GetParcelStatusesResult->POD);
            }
        }
    }
}
