<?php

namespace App\Http\Controllers\Back;

use App\Helpers\Country;
use App\Helpers\OrderHelper;
use App\Helpers\ProductHelper;
use App\Http\Controllers\Controller;
use App\Mail\StatusCanceled;
use App\Mail\StatusPaid;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
use App\Models\Back\Settings\Settings;
use App\Models\Front\Checkout\Shipping\Gls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, Order $order)
    {
        $orders = $order->filter($request)->paginate(config('settings.pagination.back'));

        $statuses = Settings::get('order', 'statuses');

        return view('back.order.index', compact('orders', 'statuses'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('back.order.edit');
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
        $order = new Order();

        $stored = $order->validateRequest($request)->store();

        if ($stored) {
            return redirect()->route('orders.edit', ['order' => $stored])->with(['success' => 'Narudžba je snimljena!']);
        }

        return redirect()->back()->with(['error' => 'Oops..! Dogodila se greška prilikom snimanja.']);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Order $order
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Order $order)
    {
        $statuses = Settings::get('order', 'statuses');

        return view('back.order.show', compact('order', 'statuses'));
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Order $order
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Order $order)
    {
        $countries = Country::list();
        $statuses = Settings::get('order', 'statuses');
        $shippings = Settings::getList('shipping');
        $payments = Settings::getList('payment');

        return view('back.order.edit', compact('order', 'countries', 'statuses', 'shippings', 'payments'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Order                    $order
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Order $order)
    {
        $updated = $order->validateRequest($request)->store($order->id);

        if ($updated) {
            return redirect()->route('orders.edit', ['order' => $updated])->with(['success' => 'Narudžba je snimljena!']);
        }

        return redirect()->back()->with(['error' => 'Oops..! Dogodila se greška prilikom snimanja.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request) {}


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function api_status_change(Request $request)
    {
        if ($request->has('orders')) {
            $orders = explode(',', substr($request->input('orders'), 1, -1));

            Order::whereIn('id', $orders)->update([
                'order_status_id' => $request->input('selected')
            ]);

            if (OrderHelper::isCanceled((int) $request->input('selected'))) {
                $orders = Order::query()->whereIn('id', $orders)->pluck('id');

                foreach ($orders as $order_id) {
                    ProductHelper::makeAvailable($order_id);
                }
            }

            return response()->json(['message' => 'Statusi su uspješno promijenjeni..!']);
        }

        if ($request->has('order_id')) {
            if ($request->has('status') && $request->input('status')) {
                Order::where('id', $request->input('order_id'))->update([
                    'order_status_id' => $request->input('status')
                ]);

                if (OrderHelper::isCanceled((int) $request->input('selected'))) {
                    ProductHelper::makeAvailable($request->input('order_id'));
                }

                if ($request->input('status') == config('settings.order.status.paid')) {
                    $order = Order::find($request->input('order_id'));

                    dispatch(function () use ($order) {
                        Mail::to($order->payment_email)->send(new StatusPaid($order));
                    });
                }

                if ($request->input('status') == config('settings.order.status.canceled')) {
                    $order = Order::find($request->input('order_id'));

                    dispatch(function () use ($order) {
                        Mail::to($order->payment_email)->send(new StatusCanceled($order));
                    });
                }
            }

            OrderHistory::store($request->input('order_id'), $request);

            return response()->json(['message' => 'Status je uspješno promijenjen..!']);
        }

        return response()->json(['error' => 'Greška..! Molimo pokušajte ponovo ili kontaktirajte administratora..']);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function api_send_gls(Request $request)
    {
        $request->validate(['order_id' => 'required']);

        $order = Order::where('id', $request->input('order_id'))->first();

        $gls = new Gls($order);
        $label = $gls->resolve();

        if (isset($label['ParcelIdList'])) {
            return response()->json(['message' => 'GLS je uspješno poslan sa ID: ' . $label['ParcelIdList'][0]]);
        }

        return response()->json(['error' => 'Greška..! Molimo pokušajte ponovo ili kontaktirajte administratora..']);
    }
}
