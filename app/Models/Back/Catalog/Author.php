<?php

namespace App\Models\Back\Catalog;

use App\Helpers\Helper;
use App\Helpers\ImageHelper;
use App\Models\Back\Catalog\Product\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Author extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'authors';

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
        return $this->hasMany(Product::class, 'author_id', 'id');
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
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
            'letter'           => Helper::resolveFirstLetter($this->request->title),
            'title'            => $this->request->title,
            'description'      => $this->request->description,
            'meta_title'       => $this->request->meta_title,
            'meta_description' => $this->request->meta_description,
            'lang'             => 'hr',
            'sort_order'       => 0,
            'status'           => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'featured'         => (isset($this->request->featured) and $this->request->featured == 'on') ? 1 : 0,
            'slug'             => $slug,
            'url'              => config('settings.author_path') . '/' . $slug,
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
    public function resolveImage(Author $author)
    {
        if ($this->request->hasFile('image')) {
            $path = ImageHelper::makeImageSet($this->request->image, 'author', $author->title);

            return $author->update([
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

        $total = Author::query()->pluck('id');

        $authors_with = Author::query()->whereHas('products', function ($query) {
            $query->where('status', 1);
        })->pluck('id');

        $authors_without = $total->diff($authors_with);

        Author::query()->whereIn('id', $authors_with)->update(['status' => 1]);
        Author::query()->whereIn('id', $authors_without)->update(['status' => 0]);

        $log_end = microtime(true);
        Log::info('__Check Author Statuses - Total Execution Time: ' . number_format(($log_end - $log_start), 2, ',', '.') . ' sec.');

        return 1;
    }
}
