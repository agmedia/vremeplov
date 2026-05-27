<?php

namespace App\Models\Back\Orders;

use App\Models\Back\Chart;
use App\Models\Back\Product\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Bouncer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderStats extends Model
{

    /**
     * Get and return sorted transactions data
     * for chart view.
     *
     * @param Request $request
     *
     * @return array
     */
    public static function getChartData(Request $request)
    {
        $user         = auth()->user();
        $chart        = new Chart($request);
        $query_params = $chart->setQueryParams();

        $query = (new Order())->newQuery();

        if (Bouncer::is($user)->an('editor')) {
            $query->where('client_id', $user->clientId());
        }

        $orders = $query->select('total', 'created_at')
            ->whereBetween('created_at', [$query_params['from'], $query_params['to']])
            ->orderBy('created_at')
            ->get()
            ->groupBy(function ($val) use ($query_params) {
                return Carbon::parse($val->created_at)->format($query_params['group']);
            });

        return $chart->returnQueryData($query_params['iterator'], $orders);
    }


    /**
     * Get and set transactions data
     * for chart view.
     *
     * @param Request $request
     *
     * @return array
     */
    public static function getStatusPieChartData(Request $request, $column)
    {
        $user    = auth()->user();
        $labels  = [];
        $data    = [];
        $widgets = [];
        $chart   = new Chart($request);
        $query   = (new Order())->newQuery();

        if (Bouncer::is($user)->an('editor')) {
            $query->where('client_id', $user->clientId());
        }

        $response = $query->select('*',
            DB::raw('count(*) as count'))->with('status')->groupBy('order_status_id')->get();

        foreach ($response as $item) {
            array_push($labels, $item->status->title ?? $item->status->name ?? 'Nepoznat status');
            array_push($data, $item->count);
        }

        return [
            'labels'  => $labels,
            'data'    => $data,
            'colors'  => array_slice($chart->pie_colors, 0, count($response) + 1),
            'widgets' => $widgets
        ];
    }


    /**
     * @return array
     */
    public static function count()
    {
        $user          = auth()->user();
        $products      = Product::clients()->pluck('id');
        $query_product = (new OrderProduct())->newQuery();

        if (Bouncer::is($user)->an('editor')) {
            $query_product->whereIn('product_id', $products);
        }

        $query = (new Order())->newQuery();

        $query->whereIn('id', $query_product->pluck('order_id'));

        return [
            'qty'   => $query->count(),
            'href'  => route('orders'),
            'label' => 'Narudžbe',
            'icon'  => 'si si-basket-loaded'
        ];
    }
}
