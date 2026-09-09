<?php

namespace App\Models\Back\Marketing;

use App\Helpers\ImageHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class Blog extends Model
{

    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'pages';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @var Request
     */
    protected $request;


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
            'title' => ['required', 'string', 'max:191'],
            'related_slider_mode' => ['nullable', Rule::in(['none', 'books', 'author'])],
            'related_slider_title' => ['nullable', 'string', 'max:191'],
            'related_slider_author_id' => [
                Rule::requiredIf($request->input('related_slider_mode') === 'author'),
                'nullable',
                'integer',
                'exists:authors,id',
            ],
            'related_slider_products' => [
                Rule::requiredIf($request->input('related_slider_mode') === 'books'),
                'nullable',
                'array',
                'min:1',
                'max:15',
            ],
            'related_slider_products.*' => [
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where('group', 'knjige'),
            ],
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
            return $this->find($this->id);
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
        $response = [
            'category_id'       => null,
            'group'             => 'blog',
            'title'             => $this->request->title,
            'short_description' => $this->request->short_description,
            'description'       => $this->request->description,
            'meta_title'        => $this->request->meta_title,
            'meta_description'  => $this->request->meta_description,
            'slug'              => isset($this->request->slug) ? Str::slug($this->request->slug) : Str::slug($this->request->title),
            'keywords'          => false,
            'related_slider'    => $this->relatedSliderPayload(),
            'publish_date'      => $this->request->publish_date ? Carbon::make($this->request->publish_date) : null,
            'status'            => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'updated_at'        => Carbon::now()
        ];

        if ($insert) {
            $response['created_at'] = Carbon::now();
        }

        return $response;
    }


    /**
     * Decode the optional related-products configuration safely.
     */
    public function getRelatedSliderAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }


    private function relatedSliderPayload(): ?string
    {
        $mode = (string) $this->request->input('related_slider_mode', 'none');

        if (! in_array($mode, ['books', 'author'], true)) {
            return null;
        }

        $payload = [
            'mode' => $mode,
            'title' => trim((string) $this->request->input('related_slider_title')),
        ];

        if ($mode === 'author') {
            $payload['author_id'] = (int) $this->request->input('related_slider_author_id');
        } else {
            $payload['product_ids'] = collect($this->request->input('related_slider_products', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->take(15)
                ->values()
                ->all();
        }

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }


    /**
     * @param Category $category
     *
     * @return bool
     */
    public function resolveImage(Blog $blog)
    {
        if ($this->request->hasFile('image')) {
            $path = ImageHelper::makeImageSet($this->request->image, 'blog', $blog->title, strval($blog->id));

            return $blog->update([
                'image' => $path
            ]);
        }

        return false;
    }
}
