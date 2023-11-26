<?php

namespace App\Http\Controllers\Back\Widget;

use App\Models\Back\Widget\Widget;
use App\Models\Back\Widget\WidgetGroup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WidgetGroupController extends Controller
{

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $wg = new WidgetGroup();
        $sizes = $wg->sizes();
        $sections = $wg->getSectionsList();

        return view('back.widget.edit-group', compact('sizes', 'sections'));
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
        $wg = new WidgetGroup();
        $stored = $wg->validateRequest($request)->store();

        if ($stored) {
            $this->flush();

            return redirect()->back()->with(['success' => 'Widget grupa je uspješno snimljena!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! Desila se greška sa snimanjem widget grupe.']);
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
        $widget = WidgetGroup::where('id', $id)->first();

        if ( ! $widget) {
            abort(401);
        }

        $wg = new WidgetGroup();
        $sizes = $wg->sizes();
        $sections = $wg->getSectionsList();

        return view('back.widget.edit-group', compact('widget', 'sections', 'sizes'));
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
        $wg = new WidgetGroup();
        $updated = $wg->validateRequest($request)->edit($id);

        if ($updated) {
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
            /*if ($request->input('type') == 'category') {
                return response()->json(Category::getList());
            }
            if ($request->input('type') == 'manufacturer') {
                return response()->json(Manufacturer::list());
            }
            if ($request->input('type') == 'product') {
                return response()->json(Product::getMenu());
            }
            if ($request->input('type') == 'page') {
                return response()->json(Blog::published()->pluck('title', 'id'));
            }*/
        }

        return response()->json([
            'id' => 0,
            'text' => 'Molimo odaberite tip linka..'
        ]);
    }
}
