<?php

namespace App\Http\Controllers\Back\Settings\App;

use App\Http\Controllers\Controller;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
use App\Models\Back\Settings\Settings;
use Illuminate\Http\Request;

class OrderStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $statuses = Settings::get('order', 'statuses')->sortBy('sort_order');

        return view('back.settings.app.order_status', compact('statuses'));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->data;
        $data['id'] = (int) ($data['id'] ?? 0);
        $data['title'] = trim($data['title'] ?? '');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['color'] = isset($data['color']) && $data['color'] ? $data['color'] : 'primary';

        if ( ! $data['title']) {
            return response()->json(['message' => 'Naziv statusa je obavezan.']);
        }

        $setting = Settings::where('code', 'order')->where('key', 'statuses')->first();

        $values = collect();

        if ($setting) {
            $values = collect(json_decode($setting->value));
        }

        if ( ! $data['id']) {
            $data['id'] = ((int) $values->max('id')) + 1;
            $values->push($data);
        }
        else {
            $values->where('id', $data['id'])->map(function ($item) use ($data) {
                $item->title = $data['title'];
                $item->sort_order = $data['sort_order'];
                $item->color = $data['color'];

                return $item;
            });
        }

        if ( ! $setting) {
            $stored = Settings::insert('order', 'statuses', $values->toJson(), true);
        } else {
            $stored = Settings::edit($setting->id, 'order', 'statuses', $values->toJson(), true);
        }

        if ($stored) {
            return response()->json(['success' => 'Status narudžbe je uspješno snimljen.']);
        }

        return response()->json(['message' => 'Server error! Pokušajte ponovo ili kontaktirajte administratora!']);
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
        $stored = false;

        if ($data['id']) {
            $id = (int) $data['id'];
            $used_on_orders = Order::query()->where('order_status_id', $id)->exists();
            $used_on_history = OrderHistory::query()->where('status', $id)->exists();

            if ($used_on_orders || $used_on_history) {
                return response()->json(['message' => 'Status se ne može obrisati jer postoje narudžbe ili povijest narudžbi s tim statusom.']);
            }

            $setting = Settings::where('code', 'order')->where('key', 'statuses')->first();

            if ( ! $setting) {
                return response()->json(['message' => 'Lista statusa nije pronađena.']);
            }

            $values = collect(json_decode($setting->value));

            $new_values = $values->reject(function ($item) use ($id) {
                return $item->id == $id;
            });

            $stored = Settings::edit($setting->id, 'order', 'statuses', $new_values->toJson(), true);
        }

        if ($stored) {
            return response()->json(['success' => 'Status narudžbe je uspješno obrisan.']);
        }

        return response()->json(['message' => 'Server error! Pokušajte ponovo ili kontaktirajte administratora!']);
    }
}
