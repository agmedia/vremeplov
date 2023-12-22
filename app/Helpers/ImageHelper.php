<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class ImageHelper
{

    public static function makeImageSet($image, string $disk, string $title, string $folder = null): string
    {
        $path_jpg = Str::slug($title) . '-' . Str::random(4) . '.jpg';

        if ($folder) {
            $path_jpg = $folder . '/' . $path_jpg;
        }

        $img = Image::make($image)->resize(800, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        Storage::disk($disk)->put($path_jpg, $img->encode('jpg'));

        $path_webp = str_replace('.jpg', '.webp', $path_jpg);
        Storage::disk($disk)->put($path_webp, $img->encode('webp'));

        // THUMB
        $img = $img->resize(300, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        $path_thumb = str_replace('.jpg', '-thumb.webp', $path_jpg);
        Storage::disk($disk)->put($path_thumb, $img->encode('webp'));

        return config('filesystems.disks.' . $disk . '.url') . $path_jpg;
    }
}
