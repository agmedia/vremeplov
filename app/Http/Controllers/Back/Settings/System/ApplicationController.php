<?php

namespace App\Http\Controllers\Back\Settings\System;

use App\Http\Controllers\Controller;
use App\Models\Back\Apartment\ApartmentDetail;
use App\Models\Back\Settings\Application;
use App\Models\Back\Settings\Faq;
use App\Models\Back\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $items = Settings::query()->where('code', 'app')->get();

        $data = Application::setAdminIndexData($items);

        return view('back.settings.system.application', compact('items', 'data'));
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function basicInfoStore(Request $request): JsonResponse
    {
        $is_valid = Validator::make($request->toArray(), [
            'title' => 'required',
            'address' => 'required',
            'zip' => 'required',
            'city' => 'required',
            'state' => 'required',
            'phone' => 'required',
            'email' => 'required'
        ]);

        if ($is_valid->fails()) {
            return response()->json($is_valid->errors());
        }

        $set = Settings::reset('app', 'basic', $request->toArray());

        if ($set) {
            return response()->json(['success' => 'Osnovni info aplikacije je snimljen...']);
        }

        return response()->json(['error' => 'Whoops.!! Pokušajte ponovo ili kontaktirajte administratora!']);
    }


    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function storeGoogleMapsApiKey(Request $request): JsonResponse
    {
        if ($request->has('data')) {
            $set = Settings::reset('app', 'google.maps', $request->input('data'));

            if ($set) {
                return response()->json(['success' => __('back/app.save_success')]);
            }
        }

        return response()->json(['error' => __('back/app.save_failure')]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *val
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->data;

        $setting = Settings::where('code', 'amenity')->where('key', 'list')->first();

        $values = collect();

        if ($setting) {
            $values = collect(json_decode($setting->value));
        }

        if ( ! $data['id']) {
            $group_title = $values->where('group', $data['group'])->first()->group_title;

            $data['id'] = $values->count() + 1;
            $data['group_title'] = $group_title;
            $data['featured'] = 0;
            $data['status'] = 0;
            $values->push($data);

        } else {
            $group_title = $values->where('group', $data['group'])->first()->group_title;

            $values->where('id', $data['id'])->map(function ($item) use ($data, $group_title) {
                $item->title = $data['title'];
                $item->group = $data['group'];
                $item->group_title = $group_title;
                $item->icon = $data['icon'];
                $item->featured = $data['featured'];
                $item->status = 0;

                return $item;
            });
        }

        if ( ! $setting) {
            $stored = Settings::insert('amenity', 'list', $values->toJson(), true);
        } else {
            $stored = Settings::edit($setting->id, 'amenity', 'list', $values->toJson(), true);
        }

        if ($stored) {
            //$this->clearCache();

            return response()->json(['success' => 'Pogodnost je uspješno snimljena.']);
        }

        return response()->json(['message' => 'Whoops.!! Pokušajte ponovo ili kontaktirajte administratora!']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $data = $request->data;

        if ($data['id']) {
            $setting = Settings::where('code', 'amenity')->where('key', 'list')->first();

            $values = collect(json_decode($setting->value));

            $new_values = $values->reject(function ($item) use ($data) {
                return $item->id == $data['id'];
            });

            $stored = Settings::edit($setting->id, 'amenity', 'list', $new_values->toJson(), true);
            ApartmentDetail::where('group', $data['id'])->delete();
        }

        if ($stored) {
            return response()->json(['success' => 'Pogodnost je uspješno obrisana.']);
        }

        return response()->json(['message' => 'Whoops.!! Pokušajte ponovo ili kontaktirajte administratora!']);
    }

}
