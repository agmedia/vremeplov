<?php

namespace App\Models\Back\Widget;

use App\Helpers\ImageHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Widget extends Model
{

    /**
     * @var string
     */
    protected $table = 'widgets';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @var string[]
     */
    protected $appends = ['webp','thumb'];

    /**
     * @var Request
     */
    private $request;

    /**
     * @var
     */
    private $url;


    /**
     * @param $value
     *
     * @return array|string|string[]
     */
    public function getWebpAttribute($value)
    {
        return config('settings.images_domain') . str_replace('.jpg', '.webp', $this->image);
    }


    /**
     * @param $value
     *
     * @return array|string|string[]
     */
    public function getThumbAttribute($value)
    {
        return config('settings.images_domain') . str_replace('.jpg', '-thumb.webp', $this->image);
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function group()
    {
        return $this->hasOne(WidgetGroup::class, 'id', 'group_id');
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeGroups($query)
    {
        return $query->groupBy('group_id');
    }


    /**
     * @param Request $request
     *
     * @return $this
     */
    public function validateRequest(Request $request)
    {
        // Validate the request.
        $request->validate([
            'group_template' => 'required',
            'title' => 'required'
        ]);

        // Set Product Model request variable
        $this->setRequest($request);

        return $this;
    }


    /**
     * @return $this
     */
    public function setUrl()
    {
        $this->url = $this->request->url;

        if ( ! $this->url && $this->request->link && $this->request->link_id) {
            $this->url = Url::set($this->request->link, $this->request->link_id);
        }

        return $this;
    }


    /**
     * @return mixed
     */
    public function store()
    {
        $data = null;

        if ($this->request->has('group_template')) {
            $group = WidgetGroup::where('id', $this->request->group_id)->first();
            $group_id = $group->id;

            $arr = $this->request->toArray();
            unset($arr['_token']);
            unset($arr['_method']);
            unset($arr['image']);
            unset($arr['image_long']);

            if ($this->request->has('action_list')) {
                $arr['list'] = $this->request->input('action_list');
                unset($arr['action_list']);
            }

            $data = serialize($arr);
        }

        $id = $this->insertGetId([
            'group_id'   => $group_id,
            'title'      => $this->request->title,
            'subtitle'   => $this->request->subtitle,
            'description' => $this->request->description ?: null,
            'data'       => $data,
            'link'       => $this->request->link ?: null,
            'link_id'    => $this->request->link_id ?: null,
            'url'        => $this->url ?: '/',
            'badge'      => $this->request->badge ?: null,
            'width'      => $this->request->width ?: null,
            'sort_order' => $this->request->sort_order ?: 0,
            'status'     => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        return $this->find($id);
    }


    /**
     * @param $id
     *
     * @return false
     */
    public function edit($id)
    {
        if ($this->request->has('group_template')) {
            $group = WidgetGroup::where('id', $this->request->group_id)->first();
            $group_id = $group->id;

            $arr = $this->request->toArray();
            unset($arr['_token']);
            unset($arr['_method']);
            unset($arr['image']);
            unset($arr['image_long']);

            if ($this->request->has('action_list')) {
                $arr['list'] = $this->request->input('action_list');
                unset($arr['action_list']);
            }

            $data = serialize($arr);
        }

        $ok = $this->where('id', $id)->update([
            'group_id'   => $group_id,
            'title'      => $this->request->title,
            'subtitle'   => $this->request->subtitle,
            'description' => $this->request->description ?: null,
            'data'       => $data,
            'link'       => $this->request->link ?: null,
            'link_id'    => $this->request->link_id ?: null,
            'url'        => $this->url ?: '/',
            'badge'      => $this->request->badge ?: null,
            'width'      => $this->request->width ?: null,
            'sort_order' => $this->request->sort_order ?: 0,
            'status'     => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'updated_at' => Carbon::now()
        ]);

        if ($ok) {
            return $this->find($id);
        }

        return false;
    }


    /**
     * @param $request
     *
     * @return bool
     */
    public function resolveImage($request)
    {
        if ($request->image) {
            $data = json_decode($request->image);
        }
        if ($request->image_long) {
            $data = json_decode($request->image_long);
        }

        $group = $this->group()->first();

        if ($group->template == 'custom' && str_contains($group->slug, 'slider')) {
            $path = ImageHelper::makeImageSet($data->output->image, 'widget', $this->title, strval($this->id), 500, 500);
        } else {
            $path = ImageHelper::makeImageSet($data->output->image, 'widget', $this->title, strval($this->id));
        }

        return $this->update([
            'image' => $path
        ]);
    }


    /**
     * @return array[]
     */
    public function sizes()
    {
        return [
            [
                'value' => 12,
                'title' => '1:1 - Puna širina'
            ],
            [
                'value' => 6,
                'title' => '1:2 - Pola širine'
            ],
            [
                'value' => 4,
                'title' => '1:3 - Trećina širine'
            ],
            [
                'value' => 8,
                'title' => '2:3 - 2 trećine širine'
            ],
        ];
    }


    /**
     * Set Product Model request variable.
     *
     * @param $request
     */
    private function setRequest($request)
    {
        $this->request = $request;
    }


    /**
     * @param $request
     *
     * @return bool
     */
    public static function hasImage($request)
    {
        if ($request->has('image') && $request->input('image')) {
            return true;
        }
        if ($request->has('image_long') && $request->input('image_long')) {
            return true;
        }

        return false;
    }
}
