<?php

namespace App\Support;

use Illuminate\Http\Request;

class DatabaseCapacityLeaseManager
{
    private const ATTRIBUTE = 'db_capacity_semaphore_lease';

    public function hold(Request $request, FilesystemSemaphoreLease $lease): void
    {
        $existing = $request->attributes->get(self::ATTRIBUTE);

        if ($existing instanceof FilesystemSemaphoreLease) {
            $existing->release();
        }

        $request->attributes->set(self::ATTRIBUTE, $lease);
    }

    public function hasLease(Request $request): bool
    {
        return $request->attributes->get(self::ATTRIBUTE) instanceof FilesystemSemaphoreLease;
    }

    public function release(Request $request): void
    {
        $lease = $request->attributes->get(self::ATTRIBUTE);
        $request->attributes->remove(self::ATTRIBUTE);

        if ($lease instanceof FilesystemSemaphoreLease) {
            $lease->release();
        }
    }
}
