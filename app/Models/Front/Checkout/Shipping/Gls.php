<?php

namespace App\Models\Front\Checkout\Shipping;

use App\Models\Back\Orders\Order;
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
            //These parameters are needed to be optimalise depending on the environment:
            ini_set('memory_limit', '1024M');
            ini_set('max_execution_time', 600);

            //Test ClientNumber:
            $clientNumber = 383013700; //!!!NOT FOR CUSTOMER TESTING, USE YOUR OWN, USE YOUR OWN!!!
            //Test username:
            $username = "marko@plavakrava.hr"; //!!!NOT FOR CUSTOMER TESTING, USE YOUR OWN, USE YOUR OWN!!!
            //Test password:
            $pwd      = "Pringles99@"; //!!!NOT FOR CUSTOMER TESTING, USE YOUR OWN, USE YOUR OWN!!!
            $password = hash('sha512', $pwd, true);

            $brojracuna = $this->order['id'];

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
            $pickupAddress->ContactName       = "Ines Draganić";
            $pickupAddress->ContactPhone      = "+38512132487";
            $pickupAddress->ContactEmail      = "webshop@plavakrava.hr";
            $pickupAddress->Name              = "Plava krava izdavaštvo";
            $pickupAddress->Street            = "Nova cesta";
            $pickupAddress->HouseNumber       = "150";
            $pickupAddress->City              = "Zagreb";
            $pickupAddress->ZipCode           = "10000";
            $pickupAddress->CountryIsoCode    = "HR";
            $pickupAddress->HouseNumberInfo   = "";
            $parcel->PickupAddress            = $pickupAddress;
            $parcel->PickupDate               = date('Y-m-d');
            /* $service1 = new StdClass();
             $service1->Code = "PSD";
             $parameter1 = new StdClass();
             $parameter1->StringValue = "2351-CSOMAGPONT";
             $service1->PSDParameter = $parameter1;
             $services = [];
             $services[] = $service1;
             $parcel->ServiceList = $services;*/

            $parcels[] = $parcel;

            //The service URL:
            $wsdl = "https://api.mygls.hr/SERVICE_NAME.svc?singleWsdl";

            $soapOptions = array('soap_version' => SOAP_1_1, 'stream_context' => stream_context_create(array('ssl' => array('cafile' => 'cacert.pem'))));

            //Parcel service:
            $serviceName = "ParcelService";

            return $this->PrepareLabels($username, $password, $parcels, str_replace("SERVICE_NAME", $serviceName, $wsdl), $soapOptions, $this->order);

        } catch (Exception $e) {
            echo $e->getMessage();
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

        $parcelIdList = [];
        if ($response != null && count((array) $response->PrepareLabelsResult->PrepareLabelsError) == 0 && count((array) $response->PrepareLabelsResult->ParcelInfoList) > 0) {
            $parcelIdList[] = $response->PrepareLabelsResult->ParcelInfoList->ParcelInfo->ParcelId;
            $order->update(['printed' => 1]);

        }

        //Test request:
        $getPrintedLabelsRequest = array('Username'        => $username,
                                         'Password'        => $password,
                                         'ParcelIdList'    => $parcelIdList,
                                         'PrintPosition'   => 1,
                                         'ShowPrintDialog' => 0);

        return $getPrintedLabelsRequest;
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
