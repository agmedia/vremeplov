<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class ImageHelper
{

    /**
     * @param             $image
     * @param string      $disk
     * @param string      $title
     * @param string|null $folder
     *
     * @return string
     */
    public static function makeImageSet($image, string $disk, string $title, string $folder = null, int $thumb_width = null, int $thumb_height = null): string
    {
        $path_jpg = Str::slug($title) . '-' . Str::random(4) . '.jpg';

        if ( ! $thumb_width) {
            $thumb_width = 300;
        }

        if ($folder) {
            $path_jpg = $folder . '/' . $path_jpg;
        }

        $img = Image::make($image);

        if ($img->getWidth() > 800) {
            $img = Image::make($image)->resize(800, null, function ($constraint) {
                $constraint->aspectRatio();
            });
        }

        Storage::disk($disk)->put($path_jpg, $img->encode('jpg'));

        $path_webp = str_replace('.jpg', '.webp', $path_jpg);
        Storage::disk($disk)->put($path_webp, $img->encode('webp'));

        // THUMB
        $img = $img->resize($thumb_width, $thumb_height, function ($constraint) {
            $constraint->aspectRatio();
        });

        $path_thumb = str_replace('.jpg', '-thumb.webp', $path_jpg);
        Storage::disk($disk)->put($path_thumb, $img->encode('webp'));

        return config('filesystems.disks.' . $disk . '.url') . $path_jpg;
    }

}
