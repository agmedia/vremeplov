<?php


namespace App\Helpers;


use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Publisher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Query
{

    /**
     * @param string $author
     *
     * @return array
     */
    public static function mountAuthor(string $author): array
    {
        $response = [];

        if (strpos($author, ',') !== false) {
            $arr = explode(',', $author);

            foreach ($arr as $item) {
                $_author = Author::where('slug', $item)->first();
                $response[$_author->id] = $item;
            }
        } else {
            $_author = Author::where('slug', $author)->first();
            $response[$_author->id] = $author;
        }

        return $response;
    }


    /**
     * @param string $publisher
     *
     * @return array
     */
    public static function mountPublisher(string $publisher): array
    {
        $response = [];

        if (strpos($publisher, ',') !== false) {
            $arr = explode(',', $publisher);

            foreach ($arr as $item) {
                $_publisher = Publisher::where('slug', $item)->first();
                $response[$_publisher->id] = $item;
            }
        } else {
            $_publisher = Publisher::where('slug', $publisher)->first();
            $response[$_publisher->id] = $publisher;
        }

        return $response;
    }


    /**
     * @param array $data
     *
     * @return string
     */
    public static function resolve(array $data): string
    {
        $response = '';

        foreach ($data as $item) {
            if ($item) {
                $response .= $item . ',';
            }
        }

        if ( ! $data) {
            $response = '';
        } else {
            $response = substr($response, 0, -1);
        }

        return $response;
    }


    /**
     * @param array $data
     *
     * @return array
     */
    public static function unset(array $data): array
    {
        foreach ($data as $key => $item) {
            if ( ! $item) {
                unset($data[$key]);
            }
        }

        return $data;
    }


    /**
     * @param string|null $query
     *
     * @return bool
     */
    public static function run(string $query = null)
    {
        if ($query) {
            return DB::statement(DB::raw($query));
        }

        return false;
    }

}
