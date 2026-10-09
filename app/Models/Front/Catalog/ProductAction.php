<?php

namespace App\Models\Front\Catalog;

use App\Models\Front\Catalog\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ProductAction extends Model
{

    /**
     * @var string
     */
    protected $table = 'product_actions';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }


    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeActive(Builder $query)
    {
        $now = Carbon::now();

        return $query->where('status', 1)
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('date_start')->orWhere('date_start', '<=', $now);
            })
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('date_end')->orWhere('date_end', '>=', $now);
            });
    }
}
