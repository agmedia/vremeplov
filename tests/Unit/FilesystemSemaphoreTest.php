<?php

namespace Tests\Unit;

use App\Support\FilesystemSemaphore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FilesystemSemaphoreTest extends TestCase
{
    /** @var string */
    private $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vremeplov-semaphore-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            foreach (new \FilesystemIterator($this->directory) as $file) {
                @unlink($file->getPathname());
            }

            @rmdir($this->directory);
        } elseif (is_file($this->directory)) {
            @unlink($this->directory);
        }

        parent::tearDown();
    }

    public function test_instances_share_the_configured_number_of_slots(): void
    {
        $firstProcess = new FilesystemSemaphore($this->directory, 2);
        $secondProcess = new FilesystemSemaphore($this->directory, 2);

        $firstLease = $firstProcess->acquire(0);
        $secondLease = $secondProcess->acquire(0);

        $this->assertNotNull($firstLease);
        $this->assertNotNull($secondLease);
        $this->assertNull($firstProcess->acquire(0));

        $firstLease->release();
        $replacementLease = $secondProcess->acquire(0);

        $this->assertNotNull($replacementLease);

        $replacementLease->release();
        $secondLease->release();
    }

    public function test_lease_destructor_releases_a_slot(): void
    {
        $semaphore = new FilesystemSemaphore($this->directory, 1);
        $lease = $semaphore->acquire(0);

        $this->assertNotNull($lease);
        $this->assertNull($semaphore->acquire(0));

        unset($lease);
        gc_collect_cycles();

        $replacementLease = $semaphore->acquire(0);
        $this->assertNotNull($replacementLease);
        $replacementLease->release();
    }

    public function test_unusable_directory_is_reported_as_infrastructure_failure(): void
    {
        touch($this->directory);

        $this->expectException(RuntimeException::class);

        (new FilesystemSemaphore($this->directory, 1))->acquire(0);
    }
}
