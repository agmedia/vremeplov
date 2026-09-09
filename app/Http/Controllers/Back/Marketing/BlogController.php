<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Back\Marketing\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class BlogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $blogs = Blog::where('group', 'blog')
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('title', 'like', '%' . $request->search . '%');
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('back.marketing.blog.index', compact('blogs'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('back.marketing.blog.edit');
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
        $blog = new Blog();

        $stored = $blog->validateRequest($request)->create();

        if ($stored) {
            $blog->resolveImage($stored);

            return redirect()->route('blogs.edit', ['blog' => $stored])->with(['success' => 'Blog was succesfully saved!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error saving the blog.']);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Author $author
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Blog $blog)
    {
        return view('back.marketing.blog.edit', compact('blog'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Author                   $author
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Blog $blog)
    {
        $updated = $blog->validateRequest($request)->edit();

        if ($updated) {
            $blog->resolveImage($updated);

            return redirect()->route('blogs.edit', ['blog' => $updated])->with(['success' => 'Blog was succesfully saved!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error saving the blog.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Blog $blog)
    {
        $destroyed = Blog::destroy($blog->id);

        if ($destroyed) {
            return redirect()->route('blogs')->with(['success' => 'Blog was succesfully deleted!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error deleting the blog.']);
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
            $destroyed = Blog::destroy($request->input('id'));

            if ($destroyed) {
                return response()->json(['success' => 200]);
            }
        }

        return response()->json(['error' => 300]);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadBlogImage(Request $request)
    {
        if ( ! $request->hasFile('upload')) {
            return response()->json(['uploaded' => false]);
        }

        $blog_id = $request->input('blog_id');
        $img = $request->file('upload');
        $name = Str::random(9) . '_' . $img->getClientOriginalName();

        $path = '';

        if ($blog_id) {
            $path = $blog_id . '/';
        }

        Storage::disk('blog')->putFileAs($path, $img, $name);

        return response()->json(['fileName' => $name, 'uploaded' => true, 'url' => url(config('filesystems.disks.blog.url') . $path . $name)]);
    }
}
