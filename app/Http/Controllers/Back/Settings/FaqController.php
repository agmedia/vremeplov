<?php

namespace App\Http\Controllers\Back\Settings;

use App\Http\Controllers\Controller;
use App\Models\Back\Settings\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->has('search') && ! empty($request->search)) {
            $faqs = Faq::where('title', 'like', '%' . $request->search . '%')->paginate(12);
        } else {
            $faqs = Faq::paginate(12);
        }

        return view('back.settings.faq.index', compact('faqs'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('back.settings.faq.edit');
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
        $faq = new Faq();

        $stored = $faq->validateRequest($request)->create();

        if ($stored) {
            return redirect()->route('faqs.edit', ['faq' => $stored])->with(['success' => 'Faq was succesfully saved!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error saving the faq.']);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Author $author
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Faq $faq)
    {
        return view('back.settings.faq.edit', compact('faq'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Author                   $author
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Faq $faq)
    {
        $updated = $faq->validateRequest($request)->edit();

        if ($updated) {
            return redirect()->route('faqs.edit', ['faq' => $updated])->with(['success' => 'Faq was succesfully saved!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error saving the faq.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Faq $faq)
    {
        $destroyed = Faq::destroy($faq->id);

        if ($destroyed) {
            return redirect()->route('faqs')->with(['success' => 'Faq was succesfully deleted!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error deleting the faq.']);
    }
}
