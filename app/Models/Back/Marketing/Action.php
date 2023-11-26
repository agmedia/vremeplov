<?php

namespace App\Models\Back\Marketing;

use App\Helpers\Helper;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Product\ProductCategory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Action extends Model
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
     * @var Request
     */
    protected $request;


    /**
     * Validate new action Request.
     *
     * @param Request $request
     *
     * @return $this
     */
    public function validateRequest(Request $request)
    {
        $request->validate([
            'title'    => 'required',
            'type'     => 'required',
            'group'    => 'required',
            'discount' => 'required'
        ]);

        $this->request = $request;

        if ($this->listRequired()) {
            $request->validate([
                'action_list' => 'required'
            ]);
        }

        return $this;
    }


    /**
     * Store new category.
     *
     * @return false
     */
    public function create()
    {
        $data = $this->getRequestData();
        $id   = $this->insertGetId($this->getModelArray());

        if ($id) {
            if ($this->shouldUpdateProducts($data)) {
                $this->updateProducts($this->resolveTarget($data['links']), $id, $data['start'], $data['end']);
            }

            return $this->find($id);
        }

        return false;
    }


    /**
     * @param Category $category
     *
     * @return false
     */
    public function edit()
    {
        $data    = $this->getRequestData();
        $updated = $this->update($this->getModelArray(false));

        if ($updated) {
            if ($this->shouldUpdateProducts($data)) {
                $this->updateProducts($this->resolveTarget($data['links']), $this->id, $data['start'], $data['end']);
            }

            return $this;
        }

        return false;
    }


    /**
     * @return bool
     */
    public function isValid(string $coupon = ''): bool
    {
        $is_valid = false;

        $from = now()->subDay();
        $to = now()->addDay();

        if ($this->date_start && $this->date_start != '0000-00-00 00:00:00') {
            $from = Carbon::make($this->date_start);
        }
        if ($this->date_end && $this->date_end != '0000-00-00 00:00:00') {
            $to = Carbon::make($this->date_end);
        }

        if ($from <= now() && now() <= $to) {
            $is_valid = true;
        }

        if ($is_valid) {
            $is_valid = false;

            if ($this->coupon && $coupon != '' && $coupon == $this->coupon) {
                $is_valid = true;
            }

            if ( ! $this->coupon) {
                $is_valid = true;
            }
        }

        return $is_valid;
    }


    /**
     * @param bool $insert
     *
     * @return array
     */
    private function getModelArray(bool $insert = true): array
    {
        $data = $this->getRequestData();

        $response = [
            'title'      => $this->request->title,
            'type'       => $this->request->type,
            'discount'   => $this->request->discount,
            'group'      => $this->request->group,
            'links'      => $data['links']->flatten()->toJson(),
            'date_start' => $data['start'],
            'date_end'   => $data['end'],
            'coupon'     => $this->request->coupon,
            'status'     => $data['status'],
            'updated_at' => Carbon::now()
        ];

        if ($insert) {
            $response['created_at'] = Carbon::now();
        }

        return $response;
    }


    /**
     * @return array
     */
    private function getRequestData(): array
    {
        $links = collect([$this->request->group]);

        if ($this->request->action_list) {
            $links = collect($this->request->action_list);
        }

        return [
            'links'  => $links,
            'status' => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'start'  => $this->request->date_start ? Carbon::make($this->request->date_start) : null,
            'end'    => $this->request->date_end ? Carbon::make($this->request->date_end) : null,
        ];
    }


    /**
     * @param array $data
     *
     * @return bool
     */
    private function shouldUpdateProducts(array $data): bool
    {
        if ($this->request->group == 'total') {
            return false;
        }

        if ($data['status']) {
            return true;
        }

        return false;
    }


    /**
     * @return bool
     */
    private function listRequired(): bool
    {
        if (in_array($this->request->group, ['all', 'total'])) {
            return false;
        }

        return true;
    }


    /**
     * @param $links
     *
     * @return mixed
     */
    private function resolveTarget($links)
    {
        if ($this->request->group == 'product') {
            return $links;
        }

        if ($this->request->group == 'category') {
            return ProductCategory::whereIn('category_id', $links)->pluck('product_id')->unique();
        }

        if ($this->request->group == 'author') {
            return Product::whereIn('author_id', $links)->pluck('id')->unique();
        }

        if ($this->request->group == 'publisher') {
            return Product::whereIn('publisher_id', $links)->pluck('id')->unique();
        }

        return $this->request->group;
    }


    /**
     * @param     $target
     * @param int $id
     * @param     $start
     * @param     $end
     */
    private function updateProducts($target, int $id, $start, $end): void
    {
        $query = [];

        if ($target == 'all') {
            $products = Product::pluck('price', 'id');
        } else {
            $products = Product::whereIn('id', $target)->pluck('price', 'id');
        }

        foreach ($products->all() as $k_id => $price) {
            $query[] = [
                'product_id' => $k_id,
                'special'    => Helper::calculateDiscountPrice($price, $this->request->discount, $this->request->type)
            ];
        }

        $start = $start ?: 'null';
        $end   = $end ?: 'null';

        DB::table('temp_table')->truncate();

        foreach (array_chunk($query, 500) as $chunk) {
            DB::table('temp_table')->insert($chunk);
        }

        DB::select(DB::raw("UPDATE products p INNER JOIN temp_table tt ON p.id = tt.product_id SET p.special = tt.special, p.action_id = " . $id . ", p.special_from = '" . $start . "', p.special_to = '" . $end . "';"));

        DB::table('temp_table')->truncate();
    }


    /**
     * @return mixed
     */
    private function truncateProducts()
    {
        return Product::where('action_id', $this->id)->update([
            'action_id'    => 0,
            'special'      => null,
            'special_from' => null,
            'special_to'   => null,
        ]);
    }
}
