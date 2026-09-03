<?php

namespace App\Http\Controllers\Back;

use App\Helpers\Country;
use App\Helpers\OrderHelper;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Mail\OrderStatusChanged;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
use App\Models\Back\Settings\Settings;
use App\Models\Front\Checkout\Shipping\Gls;
use App\Services\Shipping\BoxNowOrderPolicy;
use App\Services\Shipping\BoxNowService;
use App\Services\Shipping\BoxNowSettingsService;
use App\Services\Shipping\OrderTrackingService;
use App\Services\Inventory\OrderInventoryService;
use Bouncer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class OrderController extends Controller
{
    private const BOXNOW_SHIPMENT_LOCK_SECONDS = 180;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, Order $order, BoxNowOrderPolicy $boxNowPolicy)
    {
        $orders = $order->filter($request)
                        ->paginate(config('settings.pagination.back'))
                        ->appends(request()->query());;

        $statuses = Settings::get('order', 'statuses');

        $canManageBoxNow = $this->canManageBoxNow();

        return view('back.order.index', compact('orders', 'statuses', 'boxNowPolicy', 'canManageBoxNow'));
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
        if (strtolower(trim((string) $request->input('shipping'))) === BoxNowService::CARRIER) {
            return redirect()->back()->withInput()->with([
                'error' => 'BOX NOW narudžbu kreirajte kroz checkout kako bi kupac odabrao paketomat.',
            ]);
        }

        $order = new Order();

        try {
            $stored = DB::transaction(function () use ($order, $request) {
                $stored = $order->validateRequest($request)->store();

                if ($stored) {
                    return app(OrderInventoryService::class)->commit($stored, 'admin_order_created');
                }

                return false;
            });
        } catch (InsufficientStockException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

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
    public function show(Order $order, BoxNowOrderPolicy $boxNowPolicy)
    {
        $statuses = Settings::get('order', 'statuses');
        $canManageBoxNow = $this->canManageBoxNow();

        return view('back.order.show', compact('order', 'statuses', 'boxNowPolicy', 'canManageBoxNow'));
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Order $order
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(
        Order $order,
        OrderTrackingService $trackingService,
        BoxNowOrderPolicy $boxNowPolicy,
        BoxNowSettingsService $boxNowSettings
    )
    {
        $countries = Country::list();
        $statuses = Settings::get('order', 'statuses');
        $shippings = Settings::getList('shipping');
        $payments = Settings::getList('payment');
        $isBoxNowOrder = $trackingService->isBoxNowOrder($order);

        if (! $isBoxNowOrder) {
            $shippings = collect($shippings)->reject(function ($shipping) {
                return strtolower(trim((string) ($shipping->code ?? ''))) === BoxNowService::CARRIER;
            });
        } else {
            $codEnabled = (bool) $boxNowSettings->get()['cod_enabled'];
            $payments = collect($payments)->reject(function ($payment) use ($codEnabled) {
                $code = strtolower(trim((string) ($payment->code ?? '')));

                return $code === 'pickup' || ($code === 'cod' && ! $codEnabled);
            });
        }

        $boxNowShipmentLocked = $isBoxNowOrder && (
            $boxNowPolicy->hasShipment($order)
            || trim((string) $order->tracking_code) !== ''
            || (bool) $order->printed
        );

        return view('back.order.edit', compact(
            'order',
            'countries',
            'statuses',
            'shippings',
            'payments',
            'isBoxNowOrder',
            'boxNowShipmentLocked'
        ));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Order                    $order
     *
     * @return \Illuminate\Http\Response
     */
    public function update(
        Request $request,
        Order $order,
        OrderTrackingService $trackingService,
        BoxNowOrderPolicy $boxNowPolicy,
        BoxNowSettingsService $boxNowSettings
    )
    {
        $isBoxNowOrder = $trackingService->isBoxNowOrder($order);
        $requestedBoxNow = strtolower(trim((string) $request->input('shipping'))) === BoxNowService::CARRIER;

        if (! $isBoxNowOrder && $requestedBoxNow) {
            return redirect()->back()->withInput()->with([
                'error' => 'Dostavu postojeće narudžbe nije moguće promijeniti na BOX NOW bez odabira paketomata.',
            ]);
        }

        $lock = null;

        if ($isBoxNowOrder) {
            $lock = Cache::lock(
                'boxnow-shipment-create:' . $order->id,
                self::BOXNOW_SHIPMENT_LOCK_SECONDS
            );

            if (! $lock->get()) {
                return redirect()->back()->withInput()->with([
                    'error' => 'BOX NOW pošiljka se upravo obrađuje. Pričekajte i pokušajte ponovno.',
                ]);
            }
        }

        try {
            if ($lock) {
                $order->refresh();
                $isBoxNowOrder = $trackingService->isBoxNowOrder($order);
            }

            if (! $isBoxNowOrder && $requestedBoxNow) {
                return redirect()->back()->withInput()->with([
                    'error' => 'Dostavu postojeće narudžbe nije moguće promijeniti na BOX NOW bez odabira paketomata.',
                ]);
            }

            $paymentCode = strtolower(trim((string) $request->input('payment')));
            $currentPaymentCode = strtolower(trim((string) $order->payment_code));
            $hasBoxNowShipment = $boxNowPolicy->hasShipment($order)
                || ($isBoxNowOrder && trim((string) $order->tracking_code) !== '')
                || ($isBoxNowOrder && (bool) $order->printed);
            $invalidBoxNowPayment = $paymentCode === 'pickup'
                || ($paymentCode === 'cod' && ! (bool) $boxNowSettings->get()['cod_enabled']);

            if ($requestedBoxNow
                && $invalidBoxNowPayment
                && ! ($hasBoxNowShipment && $paymentCode === $currentPaymentCode)) {
                return redirect()->back()->withInput()->with([
                    'error' => 'Odabrano plaćanje nije dopušteno za BOX NOW.',
                ]);
            }

            if ($isBoxNowOrder && ! $requestedBoxNow && $hasBoxNowShipment) {
                return redirect()->back()->withInput()->with([
                    'error' => 'Način dostave nije moguće promijeniti nakon kreiranja BOX NOW pošiljke.',
                ]);
            }

            if ($hasBoxNowShipment && $paymentCode !== $currentPaymentCode) {
                return redirect()->back()->withInput()->with([
                    'error' => 'Način plaćanja nije moguće promijeniti nakon kreiranja BOX NOW pošiljke.',
                ]);
            }

            try {
                $updated = DB::transaction(function () use ($order, $request) {
                    $lockedOrder = Order::query()->where('id', $order->id)->lockForUpdate()->firstOrFail();

                    if ($lockedOrder->payment_attempt_started_at !== null
                        && $lockedOrder->inventory_committed_at === null) {
                        throw new \RuntimeException(
                            'Narudžbu nije moguće mijenjati dok je vanjsko plaćanje u tijeku. Otkažite je i izradite novu.',
                            409
                        );
                    }

                    $updated = $order->validateRequest($request)->store($order->id);

                    if ($updated) {
                        $inventory = app(OrderInventoryService::class);

                        if ($inventory->isActive($updated)) {
                            return $inventory->synchronize(
                                $updated,
                                $updated->inventory_committed_at === null
                                    ? $updated->inventory_reservation_expires_at
                                    : null,
                                $updated->inventory_committed_at !== null,
                                'admin_order_updated'
                            );
                        }

                        return $updated;
                    }

                    return false;
                });
            } catch (InsufficientStockException $exception) {
                return redirect()->back()->withInput()->with('error', $exception->getMessage());
            } catch (\RuntimeException $exception) {
                if ($exception->getCode() === 409) {
                    return redirect()->back()->withInput()->with('error', $exception->getMessage());
                }

                throw $exception;
            }

            if ($updated) {
                if ($isBoxNowOrder && ! $requestedBoxNow) {
                    $this->clearBoxNowShipmentData($updated);
                }

                return redirect()->route('orders.edit', ['order' => $updated])->with(['success' => 'Narudžba je snimljena!']);
            }

            return redirect()->back()->with(['error' => 'Oops..! Dogodila se greška prilikom snimanja.']);
        } finally {
            if ($lock) {
                $lock->release();
            }
        }
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
            $orders = $this->parseOrderIds($request->input('orders'));
            $status = (int) $request->input('selected');

            try {
                DB::transaction(function () use ($orders, $status) {
                    foreach ($orders as $order_id) {
                        $this->changeOrderStatus($order_id, $status);
                    }
                });
            } catch (\RuntimeException $exception) {
                if ($exception->getCode() === 409) {
                    return response()->json(['error' => $exception->getMessage()], 409);
                }

                throw $exception;
            }

            return response()->json(['message' => 'Statusi su uspješno promijenjeni..!']);
        }

        if ($request->has('order_id')) {
            if ($request->has('status') && $request->input('status')) {
                $status   = (int) $request->input('status');
                $order_id = (int) $request->input('order_id');

                try {
                    $order = DB::transaction(function () use ($order_id, $status) {
                        return $this->changeOrderStatus($order_id, $status);
                    });
                } catch (\RuntimeException $exception) {
                    if ($exception->getCode() === 409) {
                        return response()->json(['error' => $exception->getMessage()], 409);
                    }

                    throw $exception;
                }

                if ($order && $order->payment_email) {
                    Mail::to($order->payment_email)->send(
                        new OrderStatusChanged($order, $order->status($status), $request->input('comment'))
                    );
                }
            }

            OrderHistory::store($request->input('order_id'), $request);

            return response()->json(['message' => 'Status je uspješno promijenjen..!']);
        }

        return response()->json(['error' => 'Greška..! Molimo pokušajte ponovo ili kontaktirajte administratora..']);
    }


    /**
     * @param string $orders
     *
     * @return array
     */
    private function parseOrderIds(string $orders): array
    {
        return array_values(array_filter(array_map('intval', explode(',', trim($orders, '[]')))));
    }


    /**
     * @param int $order_id
     * @param int $status
     *
     * @return Order|null
     */
    private function changeOrderStatus(int $order_id, int $status): ?Order
    {
        $order = Order::query()
                      ->where('id', $order_id)
                      ->lockForUpdate()
                      ->first();

        if ( ! $order) {
            return null;
        }

        $previous_status = (int) $order->order_status_id;

        if ($previous_status === $status) {
            return $order;
        }

        $shipmentLock = null;

        if (app(OrderTrackingService::class)->isBoxNowOrder($order)) {
            $shipmentLock = Cache::lock(
                'boxnow-shipment-create:' . $order->id,
                self::BOXNOW_SHIPMENT_LOCK_SECONDS
            );

            if (! $shipmentLock->get()) {
                throw new \RuntimeException(
                    'Status nije promijenjen jer se BOX NOW pošiljka upravo obrađuje.',
                    409
                );
            }
        }

        try {
            $order->update([
                'order_status_id' => $status
            ]);

            app(OrderInventoryService::class)->applyStatusTransition(
                $order,
                $previous_status,
                $status,
                sprintf('admin_status:%d_to_%d', $previous_status, $status)
            );
        } finally {
            if ($shipmentLock) {
                $shipmentLock->release();
            }
        }

        return $order;
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function api_send_gls(Request $request)
    {
        $request->validate(['order_id' => 'required|integer']);

        $order = Order::query()->with('products')->find($request->input('order_id'));

        if (! $order) {
            return response()->json(['error' => 'Narudžba nije pronađena.'], 404);
        }

        // Stari admin bookmark ili gumb ne smije Box Now narudžbu poslati GLS-u.
        if (app(OrderTrackingService::class)->isBoxNowOrder($order)) {
            return response()->json([
                'error' => 'Box Now narudžba ne može se poslati kroz GLS endpoint.',
            ], 422);
        }

        $gls = new Gls($order);
        $label = $gls->resolve();

        if (isset($label['ParcelIdList'])) {
            return response()->json(['message' => 'GLS je uspješno poslan sa ID: ' . $label['ParcelIdList'][0]]);
        }

        return response()->json(['error' => 'Greška..! Molimo pokušajte ponovo ili kontaktirajte administratora..']);
    }


    /**
     * Kreira Box Now pošiljku i sprema sve tracking podatke na narudžbu.
     */
    public function api_send_boxnow(
        Request $request,
        BoxNowService $boxNow,
        OrderTrackingService $trackingService,
        BoxNowOrderPolicy $boxNowPolicy
    ) {
        $request->validate(['order_id' => 'required|integer']);

        $order = Order::query()->with('products')->find($request->input('order_id'));

        if (! $order) {
            return response()->json(['error' => 'Narudžba nije pronađena.'], 404);
        }

        if (! $trackingService->isBoxNowOrder($order)) {
            return response()->json(['error' => 'Narudžba nema odabranu Box Now dostavu.'], 422);
        }

        return $this->sendBoxNowShipment($order, $boxNow, $trackingService, $boxNowPolicy);
    }


    /**
     * Ručno osvježava Box Now status iz admina.
     */
    public function api_refresh_boxnow_tracking(Request $request, OrderTrackingService $trackingService)
    {
        $request->validate(['order_id' => 'required|integer']);
        $order = Order::query()->find($request->input('order_id'));

        if (! $order) {
            return response()->json(['error' => 'Narudžba nije pronađena.'], 404);
        }

        if (! $trackingService->isBoxNowOrder($order)) {
            return response()->json(['error' => 'Narudžba nema odabranu Box Now dostavu.'], 422);
        }

        if (! $this->boxNowTrackingColumnsExist()) {
            return response()->json([
                'error' => 'Box Now SQL za tracking polja još nije izvršen na bazi.',
            ], 503);
        }

        try {
            $result = $trackingService->refreshBoxNow($order);

            return response()->json(['message' => $result['message']]);
        } catch (\Throwable $exception) {
            Log::warning('Box Now tracking refresh failed.', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'Greška..! ' . $exception->getMessage()], 422);
        }
    }


    /**
     * Preuzima službenu BOX NOW PDF adresnicu za već kreiranu pošiljku.
     */
    public function boxnow_label(
        Order $order,
        BoxNowService $boxNow,
        OrderTrackingService $trackingService
    ) {
        if (! $trackingService->isBoxNowOrder($order)) {
            return redirect()->back()->with('error', 'Narudžba nema odabranu Box Now dostavu.');
        }

        try {
            $label = $boxNow->label($order);

            return response($label['contents'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $label['filename'] . '"',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Box Now label download failed.', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()->back()->with('error', $exception->getMessage());
        }
    }


    private function sendBoxNowShipment(
        Order $order,
        BoxNowService $boxNow,
        OrderTrackingService $trackingService,
        BoxNowOrderPolicy $boxNowPolicy
    ) {
        if (! $this->boxNowTrackingColumnsExist()) {
            return response()->json([
                'error' => 'Box Now SQL za tracking polja još nije izvršen na bazi.',
            ], 503);
        }

        if ($reason = $boxNowPolicy->dispatchBlockedReason($order)) {
            return response()->json(['error' => $reason], 422);
        }

        $lock = Cache::lock(
            'boxnow-shipment-create:' . $order->id,
            self::BOXNOW_SHIPMENT_LOCK_SECONDS
        );

        if (! $lock->get()) {
            return response()->json([
                'error' => 'Kreiranje Box Now pošiljke za ovu narudžbu već je u tijeku.',
            ], 409);
        }

        try {
            // Narudžba je možda učitana prije nego što je drugi zahtjev završio.
            $order->refresh();
            $order->load('products');

            if (! $trackingService->isBoxNowOrder($order)) {
                return response()->json([
                    'error' => 'Narudžba više nema odabranu Box Now dostavu.',
                ], 422);
            }

            // Status se mora ponovno provjeriti nakon dobivanja locka i
            // osvježavanja modela kako promjena statusa ne bi zaobišla guard.
            if ($reason = $boxNowPolicy->dispatchBlockedReason($order)) {
                return response()->json(['error' => $reason], 422);
            }

            if ($boxNowPolicy->hasShipment($order)) {
                $shipmentId = $boxNowPolicy->parcelId($order);

                return response()->json([
                    'message' => $shipmentId
                        ? 'Box Now pošiljka je već kreirana: ' . $shipmentId
                        : 'Box Now pošiljka je već kreirana za ovu narudžbu.',
                ]);
            }

            $tracking = $boxNow->createDeliveryRequest($order);
            $trackingService->apply($order, $tracking);
            $message = ! empty($tracking['recovered'])
                ? 'Postojeća Box Now pošiljka pronađena je i spremljena s ID-em: ' . $tracking['parcel_id']
                : 'Box Now pošiljka uspješno je kreirana s ID-em: ' . $tracking['parcel_id'];

            return response()->json(['message' => $message]);
        } catch (\Throwable $exception) {
            Log::error('Box Now shipment failed.', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'Greška..! ' . $exception->getMessage()], 422);
        } finally {
            $lock->release();
        }
    }


    private function boxNowTrackingColumnsExist(): bool
    {
        foreach ([
            'commentp',
            'shipping_carrier',
            'shipping_parcel_id',
            'shipping_tracking_url',
            'shipping_tracking_status_code',
            'shipping_tracking_status',
            'shipping_tracking_updated_at',
            'shipping_tracking_attempted_at',
            'shipping_tracking_payload',
        ] as $column) {
            if (! Schema::hasColumn('orders', $column)) {
                return false;
            }
        }

        return true;
    }


    private function canManageBoxNow(): bool
    {
        $user = request()->user();

        return (bool) ($user && (
            Bouncer::is($user)->an('master')
            || Bouncer::is($user)->an('admin')
        ));
    }


    private function clearBoxNowShipmentData(Order $order): void
    {
        $values = [
            'commentp' => null,
            'shipping_carrier' => null,
            'shipping_parcel_id' => null,
            'shipping_tracking_url' => null,
            'shipping_tracking_status_code' => null,
            'shipping_tracking_status' => null,
            'shipping_tracking_updated_at' => null,
            'shipping_tracking_attempted_at' => null,
            'shipping_tracking_payload' => null,
            'tracking_code' => null,
            'printed' => false,
            'shipped' => false,
        ];

        $existingValues = collect($values)->filter(function ($value, $column) {
            return Schema::hasColumn('orders', $column);
        })->all();

        if ($existingValues !== []) {
            $order->forceFill($existingValues)->save();
        }
    }
}
