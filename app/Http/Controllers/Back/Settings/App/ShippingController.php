<?php

namespace App\Http\Controllers\Back\Settings\App;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Back\Settings\Faq;
use App\Models\Back\Settings\Settings;
use App\Services\Shipping\BoxNowSettingsService;
use Bouncer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ShippingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(BoxNowSettingsService $boxNowSettingsService)
    {
        $this->checkForNewFiles();

        $shippings = Settings::getList('shipping', 'list.%', false);
        $geo_zones = Settings::getList('geo_zone', 'list', false);
        $canManageBoxNow = $this->canManageBoxNow();
        $boxNowSettings = $canManageBoxNow
            ? $boxNowSettingsService->adminValues()
            : null;

        //dd($geo_zones);

        return view('back.settings.app.shipping.shipping', compact(
            'shippings',
            'geo_zones',
            'boxNowSettings',
            'canManageBoxNow'
        ));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, BoxNowSettingsService $boxNowSettingsService)
    {
        $code = (string) $request->input('data.code');
        $active = filter_var($request->input('data.status'), FILTER_VALIDATE_BOOLEAN);

        if ($code === 'boxnow') {
            abort_unless($this->canManageBoxNow($request), 403);
        }

        if ($code === 'boxnow' && $active) {
            $missing = $boxNowSettingsService->missingConfiguration();

            if ($missing !== []) {
                return response()->json([
                    'message' => 'Prije uključivanja Box Now dostave dopunite API postavke: ' . implode(', ', $missing) . '.',
                ], 422);
            }

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
                    return response()->json([
                        'message' => 'Prije uključivanja Box Now dostave izvršite priloženi live SQL.',
                    ], 422);
                }
            }
        }

        $updated = Settings::setListItem('shipping', 'list.' . $request->data['code'], $request->data);

        if ($updated) {
            Helper::resolveCache('set')->forget('cart.public.v2');
            Helper::resolveCache('set')->forget('cart');

            return response()->json(['success' => 'Način dostave je uspješno snimljen.']);
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
    public function destroy(Request $request, Faq $faq)
    {
        $destroyed = Faq::destroy($faq->id);

        if ($destroyed) {
            return redirect()->route('faqs')->with(['success' => 'Faq was succesfully deleted!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error deleting the faq.']);
    }


    /**
     * Check for new files in ..payment/modals directory.
     * Install payment if new files exist.
     */
    private function checkForNewFiles(): void
    {
        $files = new \DirectoryIterator(resource_path('views/back/settings/app/shipping/modals'));

        foreach ($files as $file) {
            if (strpos($file, 'blade.php') !== false) {
                $filename = str_replace('.blade.php', '', $file);
                $exist = false;

                $shipping = collect(Settings::get('shipping', 'list.' . $filename));

                if ($shipping) {
                    $exist = $shipping->where('code', $filename)->first();
                }

                if ( ! $exist) {
                    $default_value = [
                        'title' => $filename,
                        'code' => $filename,
                        'data' => [
                            'price' => 0
                        ],
                        'geo_zone' => '0',
                        'sort_order' => 0,
                        'status' => false
                    ];

                    Settings::set('shipping', 'list.' . $filename, $default_value);
                }

            }
        }
    }


    private function canManageBoxNow(?Request $request = null): bool
    {
        $user = ($request ?: request())->user();

        return (bool) ($user && (
            Bouncer::is($user)->an('master')
            || Bouncer::is($user)->an('admin')
        ));
    }
}
