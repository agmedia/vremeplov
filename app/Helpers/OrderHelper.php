<?php

namespace App\Helpers;


class OrderHelper
{
    /**
     * @param string $key
     * @param array  $fallback
     *
     * @return array
     */
    public static function statusGroup(string $key, array $fallback = []): array
    {
        $statuses = config('settings.order.' . $key, $fallback);

        if ( ! is_array($statuses)) {
            $statuses = [$statuses];
        }

        return array_values(array_unique(array_map('intval', $statuses)));
    }


    /**
     * Statusi koji traže daljnju obradu u adminu.
     *
     * @return array
     */
    public static function processingStatuses(): array
    {
        return static::statusGroup('processing_statuses', [1, 2, 3, 11, 13]);
    }


    /**
     * Završni ili zatvoreni statusi.
     *
     * @return array
     */
    public static function finishedStatuses(): array
    {
        return static::statusGroup('finished_statuses', [4, 5, 6, 7, 9, 10, 12, 14]);
    }


    /**
     * Statusi koji ulaze u prometne grafove.
     *
     * @return array
     */
    public static function turnoverStatuses(): array
    {
        return static::statusGroup('turnover_statuses', [1, 2, 3, 4, 9, 10, 11]);
    }


    /**
     * Statusi koje treba izbaciti iz općih statistika narudžbi.
     *
     * @return array
     */
    public static function statisticsExcludedStatuses(): array
    {
        return static::statusGroup('statistics_excluded_statuses', [5, 6, 7, 8, 12, 14]);
    }


    /**
     * Statusi koji se tretiraju kao plaćeni/realizirani u helperima.
     *
     * @return array
     */
    public static function paidStatuses(): array
    {
        return static::statusGroup('paid_statuses', [3, 4, 9, 10, 11]);
    }


    /**
     * @param int $status
     *
     * @return bool
     */
    public static function isCanceled(int $status): bool
    {
        if (is_array(config('settings.order.canceled_status'))) {
            foreach (config('settings.order.canceled_status') as $value) {
                if ($value == $status) {
                    return true;
                }
            }
        } else {
            if (config('settings.order.canceled_status') == $status) {
                return true;
            }
        }

        return false;
    }


    /**
     * @param int $status
     *
     * @return bool
     */
    public static function isReturned(int $status): bool
    {
        if (is_array(config('settings.order.returned_status'))) {
            foreach (config('settings.order.returned_status') as $value) {
                if ($value == $status) {
                    return true;
                }
            }
        } else {
            if (config('settings.order.returned_status') == $status) {
                return true;
            }
        }

        return false;
    }
}
