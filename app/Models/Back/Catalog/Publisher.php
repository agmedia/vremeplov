<?php

namespace App\Models\Back\Catalog;

use App\Helpers\ImageHelper;
use App\Models\Back\Catalog\Product\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Publisher extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'publishers';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @var Request
     */
    protected $request;


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'publisher_id', 'id');
    }


    /**
     * Validate new category Request.
     *
     * @param Request $request
     *
     * @return $this
     */
    public function validateRequest(Request $request)
    {
        $request->validate([
            'title' => 'required'
        ]);

        $this->request = $request;

        return $this;
    }


    /**
     * Store new category.
     *
     * @return false
     */
    public function create()
    {
        $id = $this->insertGetId($this->getModelArray());

        if ($id) {
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
        $id = $this->update($this->getModelArray(false));

        if ($id) {
            return $this;
        }

        return false;
    }


    /**
     * @param bool $insert
     *
     * @return array
     */
    private function getModelArray(bool $insert = true): array
    {
        $slug = isset($this->request->slug) ? Str::slug($this->request->slug) : Str::slug($this->request->title);

        $response = [
            'title'            => $this->request->title,
            'description'      => $this->request->description,
            'meta_title'       => $this->request->meta_title,
            'meta_description' => $this->request->meta_description,
            'lang'             => 'hr',
            'sort_order'       => 0,
            'status'           => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'featured'         => (isset($this->request->featured) and $this->request->featured == 'on') ? 1 : 0,
            'slug'             => $slug,
            'url'              => config('settings.publisher_path') . '/' . $slug,
            'updated_at'       => Carbon::now()
        ];

        if ($insert) {
            $response['created_at'] = Carbon::now();
        }

        return $response;
    }


    /**
     * @param Category $category
     *
     * @return bool
     */
    public function resolveImage(Publisher $publisher)
    {
        if ($this->request->hasFile('image')) {
            $path = ImageHelper::makeImageSet($this->request->image, 'publisher', $publisher->title);

            return $publisher->update([
                'image' => $path
            ]);
        }

        return false;
    }

    /*******************************************************************************
    *                                Copyright : AGmedia                           *
    *                              email: filip@agmedia.hr                         *
    *******************************************************************************/

    /**
     * @return int
     */
    public static function checkStatuses_CRON()
    {
        $log_start = microtime(true);

        $total = Publisher::query()->pluck('id');

        $pub_with = Publisher::query()->whereHas('products', function ($query) {
            $query->where('status', 1);
        })->pluck('id');

        $pub_without = $total->diff($pub_with);

        Publisher::query()->whereIn('id', $pub_with)->update(['status' => 1]);
        Publisher::query()->whereIn('id', $pub_without)->update(['status' => 0]);

        $log_end = microtime(true);
        Log::info('__Check Publisher Statuses - Total Execution Time: ' . number_format(($log_end - $log_start), 2, ',', '.') . ' sec.');

        return 1;
    }
}
