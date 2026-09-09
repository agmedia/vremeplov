<?php

namespace App\Http\Controllers\Back\Settings;

use App\Http\Controllers\Controller;
use App\Models\Back\Settings\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->has('search') && ! empty($request->search)) {
            $pages = Page::where('group', 'page')->where('title', 'like', '%' . $request->search . '%')->paginate(12);
        } else {
            $pages = Page::where('group', 'page')->paginate(12);
        }

        return view('back.settings.pages.index', compact('pages'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $groups = Page::subgroups()->pluck('subgroup');

        return view('back.settings.pages.edit', compact('groups'));
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
        $page = new Page();

        $stored = $page->validateRequest($request)->create();

        if ($stored) {
            $page->resolveImage($stored);

            $this->flush($stored);

            return redirect()->route('pages.edit', ['page' => $stored])->with(['success' => 'Page was succesfully saved!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error saving the page.']);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Author $author
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Page $page)
    {
        $groups = $page->subgroups()->pluck('subgroup');

        return view('back.settings.pages.edit', compact('page', 'groups'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Author                   $author
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Page $page)
    {
        $updated = $page->validateRequest($request)->edit();

        if ($updated) {
            $page->resolveImage($updated);

            $this->flush($updated);

            return redirect()->route('pages.edit', ['page' => $updated])->with(['success' => 'Page was succesfully saved!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error saving the page.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Page $page)
    {
        $destroyed = Page::destroy($page->id);

        if ($destroyed) {
            return redirect()->route('pages')->with(['success' => 'Page was succesfully deleted!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error deleting the page.']);
    }


    /**
     * Store an image inserted through the info-page rich text editor.
     */
    public function uploadPageImage(Request $request)
    {
        $validated = $request->validate([
            'upload' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'page_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $image = $validated['upload'];
        $name = Str::random(18) . '.' . strtolower($image->extension());
        $path = ! empty($validated['page_id']) ? $validated['page_id'] . '/' : '';

        Storage::disk('page')->putFileAs($path, $image, $name);

        return response()->json([
            'fileName' => $name,
            'uploaded' => true,
            'url' => url(config('filesystems.disks.page.url') . $path . $name),
        ]);
    }


    /**
     * @param Page $page
     */
    private function flush(Page $page): void
    {
        Cache::forget('page.' . $page->id);
        Cache::forget('page.' . $page->slug);

        if ($page->slug === 'homepage') {
            Cache::forget('page.homepage');
        }
    }
}
