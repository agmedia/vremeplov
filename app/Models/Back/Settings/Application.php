<?php

namespace App\Models\Back\Settings;

use Illuminate\Http\Request;

class Application
{

    /**
     * @var Request
     */
    protected $request;


    /**
     * @param $data
     *
     * @return \stdClass[]
     */
    public static function setAdminIndexData($data): array
    {
        $response = [
            'basic' => new \stdClass(),
            'google_maps_key' => new \stdClass()
        ];

        if ($data->where('key', 'basic')->first()) {
            $response['basic'] = json_decode($data->where('key', 'basic')->first()->value)[0];
        }
        if ($data->where('key', 'google.maps')->first()) {
            $response['google_maps_key'] = json_decode($data->where('key', 'google.maps')->first()->value)[0];
        }

        $response['currencies'] = Settings::get('currency', 'list')->sortBy('sort_order');
        $response['currency_main'] = $response['currencies']->where('main', 1)->first();

        return $response;
    }

}
