<?php

namespace App\Models\Back\Widget;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class WidgetGroup extends Model
{

    /**
     * @var string
     */
    protected $table = 'widget_groups';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @var Request
     */
    private $request;


    /**
     * @param $query
     *
     * @return mixed
     */
    public function widgets()
    {
        return $this->hasMany(Widget::class, 'group_id', 'id')->where('status', 1);
    }


    /**
     * All widgets are needed in the admin so a disabled item can be edited
     * and enabled again. The storefront continues to use widgets().
     */
    public function allWidgets()
    {
        return $this->hasMany(Widget::class, 'group_id', 'id')->orderBy('sort_order');
    }


    /**
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getSectionsList()
    {
        $dir = './../resources/views/front/layouts/widget';
        $blades   = new \DirectoryIterator($dir);
        $response = [];

        foreach ($blades as $file) {
            if (strpos($file, 'blade.php') !== false) {
                $data = $this->getWidgetInfo($dir . '/' . $file);

                $filename = str_replace('.blade.php', '', $file);

                $response[] = [
                    'id'    => str_replace('widget_', '', $filename),
                    'title' => $data['title'],
                    'description' => $data['description']
                ];
            }
        }

        return $response;
    }


    /**
     * @param Request $request
     *
     * @return $this
     */
    public function validateRequest(Request $request)
    {
        $request->validate([
            'template' => 'required',
            'title'    => 'required'
        ]);

        $this->setRequest($request);

        return $this;
    }


    /**
     * @return mixed
     */
    public function store()
    {
        $id = $this->insertGetId([
            'template'   => $this->request->template,
            'type'       => null,
            'title'      => $this->request->title,
            'slug'       => Str::slug($this->request->title),
            'width'      => $this->request->width ?: 12,
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
        $ok = $this->where('id', $id)->update([
            'template'   => $this->request->template,
            'type'       => null,
            'title'      => $this->request->title,
            'width'      => $this->request->width ?: 12,
            'status'     => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'updated_at' => Carbon::now()
        ]);

        if ($ok) {
            return $this->find($id);
        }

        return false;
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
     * @param string $file
     *
     * @return array
     */
    private function getWidgetInfo(string $file): array
    {
        $data = file_get_contents($file);

        $from = strpos($data, '<!-- ', 0) + 5;
        $to = strpos($data, ' -->', $from);

        return json_decode(substr($data, $from, $to - $from), true);
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

}
