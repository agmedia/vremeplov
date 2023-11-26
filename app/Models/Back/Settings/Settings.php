<?php

namespace App\Models\Back\Settings;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Settings extends Model
{

    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'settings';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @var Request
     */
    protected $request;

    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * @param string $code
     * @param string $key
     *
     * @return false|Collection
     */
    public static function get(string $code, string $key)
    {
        $styles = Settings::where('code', $code)->where('key', $key)->first();

        if ($styles) {
            if ($styles->json) {
                return collect(json_decode($styles->value));
            }

            return $styles->value;
        }

        return collect();
    }


    /**
     * @param string $code
     * @param string $key
     *
     * @return false|Collection
     */
    public static function getList(string $code, string $key = 'list.%', bool $only_active = true)
    {
        $styles = Settings::where('code', $code)->where('key', 'like', $key)->get();

        if ($styles->count()) {
            $return_styles = collect();

            foreach ($styles as $style) {
                if ($style->json) {
                    $temp_style = collect(json_decode($style->value))->all();

                    foreach ($temp_style as $item) {
                        $return_styles->put($item->title, $item);
                    }
                }
            }

            if ($only_active) {
                return $return_styles->where('status')->sortBy('sort_order');
            }

            return $return_styles->sortBy('sort_order');
        }

        return [];
    }

    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * @param string $code
     * @param string $key
     * @param        $value
     * @param bool   $json
     *
     * @return bool|mixed
     */
    public static function set(string $code, string $key, $value, bool $json = true)
    {
        $setting = Settings::where('code', $code)->where('key', $key)->first();

        if ($setting) {
            if ($json) {
                $values = collect(json_decode($setting->value));

                if ( ! $values->contains($value)) {
                    $values->push($value);
                }

                $value = json_encode($values);
            }

            return self::edit($setting->id, $code, $key, $value, $json);
        }

        if ($json) {
            $values = [$value];

            $value = json_encode($values);
        }

        return self::insert($code, $key, $value, $json);
    }


    /**
     * @param string $code
     * @param string $key
     * @param        $value
     * @param bool   $json
     *
     * @return bool|mixed
     */
    public static function setListItem(string $code, string $key, $value)
    {
        $updated = false;
        $setting = Settings::where('code', $code)->where('key', $key)->first();

        if ($setting) {
            $updated = $setting->update([
                'value' => json_encode([$value])
            ]);
        }

        return $updated ?: false;
    }


    /**
     * @param string $key
     * @param mixed  $value
     * @param bool   $json
     *
     * @return mixed
     */
    public static function setProduct(string $key, $value, bool $json = true)
    {
        $styles = Settings::where('code', 'product')->where('key', $key)->first();

        if ($styles) {
            if ($json) {
                $values = collect(json_decode($styles->value));

                if ( ! $values->contains($value)) {
                    $values->push($value);
                }

                $value = json_encode($values);
            }

            return self::edit($styles->id, 'product', $key, $value, $json);
        }

        if ($json) {
            $values = [$value];

            $value = json_encode($values);
        }

        return self::insert('product', $key, $value, $json);
    }


    /**
     * @param string $code
     * @param string $key
     * @param        $value
     * @param bool   $json
     *
     * @return mixed
     */
    public static function insert(string $code, string $key, $value, bool $json)
    {
        return self::insertGetId([
            'code'       => $code,
            'key'        => $key,
            'value'      => $value,
            'json'       => $json,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }


    /**
     * @param int    $id
     * @param string $code
     * @param string $key
     * @param        $value
     * @param bool   $json
     *
     * @return bool
     */
    public static function edit(int $id, string $code, string $key, $value, bool $json)
    {
        return self::where('id', $id)->update([
            'code'       => $code,
            'key'        => $key,
            'value'      => $value,
            'json'       => $json,
            'updated_at' => Carbon::now()
        ]);
    }


    /**
     * @param string $code
     * @param string $key
     * @param        $value
     * @param bool   $json
     *
     * @return bool|mixed
     */
    public static function reset(string $code, string $key, $value, bool $json = true)
    {
        $setting = Settings::where('code', $code)->where('key', $key)->first();

        if ($json) {
            $value = json_encode([$value]);
        }

        if ($setting) {
            return self::edit($setting->id, $code, $key, $value, $json);
        }

        return self::insert($code, $key, $value, $json);
    }


    /**
     * @param string $code
     * @param string $key
     *
     * @return mixed
     */
    public static function erase(string $code, string $key)
    {
        return self::where('code', $code)->where('key', $key)->delete();
    }
}
