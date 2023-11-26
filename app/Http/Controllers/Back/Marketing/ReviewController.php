<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Marketing\Action\Action;
use App\Models\Back\Marketing\Review;
use App\Models\Back\Settings\Settings;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $reviews = Review::paginate(12);

        return view('back.marketing.review.index', compact('reviews'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //dd('$$$$$');
        return view('back.marketing.review.edit');
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
        $review = new Review();

        $stored = $review->validateRequest($request)->create();

        if ($stored) {
            return redirect()->route('reviews.edit', ['review' => $stored])->with(['success' => 'Review was succesfully saved!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error saving the action.']);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Author $author
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Review $review)
    {
        //dd($review->toArray());
        return view('back.marketing.review.edit', compact('review'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Author                   $author
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Review $review)
    {
        $updated = $review->validateRequest($request)->edit();

        if ($updated) {
            return redirect()->route('reviews.edit', ['review' => $updated])->with(['success' => 'Review was succesfully saved!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error saving the review.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Review $review)
    {
        $destroyed = Review::destroy($review->id);

        if ($destroyed) {
            return redirect()->route('reviews')->with(['success' => 'Review je uspjšeno izbrisana!']);
        }

        return redirect()->back()->with(['error' => 'Oops..! Greška prilikom brisanja.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroyApi(Request $request)
    {
        if ($request->has('id')) {
            $review = Review::find($request->input('id'));
            $destroyed = $review->delete();

            if ($destroyed) {
                return response()->json(['success' => 200]);
            }
        }

        return response()->json(['error' => 300]);
    }
}
