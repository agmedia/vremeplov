<?php

namespace App\Http\Controllers\Back\Settings;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Back\Settings\History;
use App\Models\Back\Settings\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = (new History())->newQuery();

        if ($request->has('trazi')) {
            if ($request->input('trazi') == Helper::categoryGroupPath(true)) {
                $query->where('target', 'product');

                if ($request->has('pojam') && $request->input('pojam') != '') {
                    $query->whereHas('product', function ($query) use ($request) {
                        $query->where('name', 'like', '%' . $request->input('pojam') . '%')
                            ->orWhere('isbn', 'like', '%' . $request->input('pojam') . '%')
                              ->orWhere('sku', 'like', '%' . $request->input('pojam') . '%');
                    });
                }
            }
        }

        $history = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('back.settings.history.index', compact('history'));
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Author $author
     *
     * @return \Illuminate\Http\Response
     */
    public function show(History $history)
    {
        $user = $history->user();
        $target = $history->getTargetModel();
        $old = json_decode($history->old_model, true);
        $new = json_decode($history->new_model, true);

        return view('back.settings.history.show', compact('history', 'user', 'target', 'old', 'new'));
    }
}
