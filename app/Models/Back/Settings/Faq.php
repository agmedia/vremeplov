<?php

namespace App\Models\Back\Settings;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Faq extends Model
{

    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'faq';

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
            'title'       => 'required',
            'description' => 'required'
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
        $id = $this->insertGetId([
            'title'       => $this->request->title,
            'category'    => 'default',
            'description' => $this->request->description,
            'lang'        => 'hr',
            'sort_order' => 0,
            'status'      => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now()
        ]);

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
        $id = $this->update([
            'title'       => $this->request->title,
            'category'    => 'default',
            'description' => $this->request->description,
            'lang'        => 'hr',
            'sort_order' => 0,
            'status'      => (isset($this->request->status) and $this->request->status == 'on') ? 1 : 0,
            'updated_at'  => Carbon::now()
        ]);

        if ($id) {
            return $this->find($id);
        }

        return false;
    }
}
