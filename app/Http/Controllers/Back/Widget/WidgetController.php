<?php

namespace App\Http\Controllers\Back\Widget;

use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Settings\Settings;
use App\Models\Back\Widget\Widget;
use App\Models\Back\Widget\WidgetGroup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WidgetController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = (new WidgetGroup())->newQuery();

        if ($request->has('group')) {
            $query->where('group_id', $request->input('group'));
        }

        $groups = $query->with('widgets')->paginate(config('settings.pagination.items'));

        return view('back.widget.index', compact('groups'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        if ($request->has('group')) {
            $sizes  = (new Widget())->sizes();
            $selected = WidgetGroup::where('id', $request->input('group'))->first();

            if ($selected) {
                return view('back.widget.templates.' . $selected->template, compact('selected', 'sizes'));
            }
        }

        abort(401);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $widget = new Widget();
        $stored = $widget->validateRequest($request)->setUrl()->store();

        if ($stored) {
            if (Widget::hasImage($request)) {
                $stored->resolveImage($request);
            }

            $this->flush();

            return redirect()->route('widgets')->with(['success' => 'Widget je uspješno snimljen!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! Desila se greška sa snimanjem widgeta.']);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $query = (new Widget())->newQuery();

        $widget = $query->where('id', $id)->with('group')->first();
        $sizes  = $widget->sizes();

        if ( ! $widget) {
            abort(401);
        }

        if ($widget->group) {
            $selected = $widget->group;

            $widget->data = unserialize($widget->data);
            $widget->target = isset($widget->data['group']) ? $widget->data['group'] : null;
            $widget->links = collect()->flatten()->toJson();

            if (isset($widget->data['list'])) {
                $widget->links = collect($widget->data['list'])->flatten()->toJson();
            }

            return view('back.widget.templates.' . $widget->group->template, compact('selected', 'sizes', 'widget'));
        }

        $groups = WidgetGroup::where('status', 1)->get();

        return view('back.widget.edit', compact('widget', 'groups', 'sizes'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $widget = new Widget();
        $updated = $widget->validateRequest($request)->setUrl()->edit($id);

        if ($updated) {
            if (Widget::hasImage($request)) {
                $updated->resolveImage($request);
            }

            $this->flush();

            return redirect()->back()->with(['success' => 'Widget je uspješno snimljen!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! Desila se greška sa snimanjem widgeta.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        if (isset($request['data']['id'])) {
            return response()->json(
                Widget::where('id', $request['data']['id'])->delete()
            );
        }
    }


    /**
     * @return void
     */
    private function flush(): void
    {
        Artisan::call('optimize:clear');
    }


    /*******************************************************************************
    *                                Copyright : AGmedia                           *
    *                              email: filip@agmedia.hr                         *
    *******************************************************************************/
    // API ROUTES

    public function getLinks(Request $request)
    {
        if ($request->has('type')) {
            if ($request->input('type') == 'category') {
                return response()->json(Category::getList());
            }
            if ($request->input('type') == 'page') {
                return response()->json(Blog::published()->pluck('title', 'id'));
            }
            if ($request->input('type') == 'publisher') {
                return response()->json(Publisher::getList());
            }
        }

        return response()->json([
            'id' => 0,
            'text' => 'Molimo odaberite tip linka..'
        ]);
    }
}
