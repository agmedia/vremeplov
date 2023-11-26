<?php

/**
 *
 */

use Illuminate\Support\Facades\Log;

if ( ! function_exists('group')) {
    /**
     * Function that returns category group based on
     * settings.php "group_path" key value. Returns it as is or
     * as a slug if the $slug parameter is true.
     *
     * @param bool $slug
     *
     * @return string
     */
    function group(bool $slug = false): string
    {
        if ($slug) {
            return \Illuminate\Support\Str::slug(config('settings.group_path'));
        }

        return config('settings.group_path');
    }
}

/**
 *
 */
if ( ! function_exists('run_query')) {
    /**
     *
     * @param bool $slug
     *
     * @return string
     */
    function run_query(string $query = null)
    {
        if ($query) {
            return \Illuminate\Support\Facades\DB::statement(
                \Illuminate\Support\Facades\DB::raw($query)
            );
        }

        return false;
    }
}

/**
 *
 */
if ( ! function_exists('logiraj_vrijeme')) {
    /**
     *
     * @param bool $slug
     *
     * @return string
     */
    function logiraj_vrijeme($code, string $log_text = '')
    {
        $log_start1 = microtime(true);

        $code();

        $log_end1 = microtime(true);
        $sec1 = number_format(($log_end1 - $log_start1), 2, ',', '.');
        Log::info($log_text . ' --- Time: ' . $sec1 . ' sec.');
    }
}
