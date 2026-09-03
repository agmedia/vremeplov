<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use ReflectionProperty;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetEloquentGuardableColumnCache();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            $this->resetEloquentGuardableColumnCache();
        }
    }

    /**
     * Feature tests use multiple temporary schemas for the same model classes.
     * Laravel caches guardable columns statically by model class, not connection.
     */
    private function resetEloquentGuardableColumnCache(): void
    {
        $property = new ReflectionProperty(Model::class, 'guardableColumns');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }
}
