<?php

namespace App\Support;

use RuntimeException;

class FilesystemSemaphore
{
    /** @var string */
    private $directory;

    /** @var int */
    private $slots;

    /** @var int */
    private $retryDelayMicroseconds;

    public function __construct(string $directory, int $slots, int $retryDelayMicroseconds = 10000)
    {
        if ($slots < 1) {
            throw new \InvalidArgumentException('A semaphore requires at least one slot.');
        }

        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $this->slots = $slots;
        $this->retryDelayMicroseconds = max(1000, $retryDelayMicroseconds);
    }

    /**
     * Acquire a slot, returning null when all slots remain occupied until the
     * timeout expires. Infrastructure failures throw so callers can fail closed.
     */
    public function acquire(int $timeoutMilliseconds): ?FilesystemSemaphoreLease
    {
        $this->ensureDirectoryExists();

        $timeoutMilliseconds = max(0, $timeoutMilliseconds);
        $deadline = microtime(true) + ($timeoutMilliseconds / 1000);

        do {
            $openedFiles = 0;
            $lockFailures = 0;
            $startSlot = abs((int) getmypid()) % $this->slots;

            for ($offset = 0; $offset < $this->slots; $offset++) {
                $slot = ($startSlot + $offset) % $this->slots;
                $path = $this->directory.DIRECTORY_SEPARATOR.sprintf('slot-%03d.lock', $slot + 1);
                $handle = @fopen($path, 'c+');

                if (! is_resource($handle)) {
                    continue;
                }

                $openedFiles++;

                $wouldBlock = 0;

                if (@flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
                    return new FilesystemSemaphoreLease($handle);
                }

                if (! $wouldBlock) {
                    $lockFailures++;
                }

                @fclose($handle);
            }

            if ($openedFiles === 0) {
                throw new RuntimeException('Unable to open any filesystem semaphore slot.');
            }

            if ($lockFailures > 0) {
                throw new RuntimeException('Filesystem locking is unavailable for a semaphore slot.');
            }

            $remainingMicroseconds = (int) (($deadline - microtime(true)) * 1000000);

            if ($remainingMicroseconds > 0) {
                usleep(min($this->retryDelayMicroseconds, $remainingMicroseconds));
            }
        } while (microtime(true) < $deadline);

        return null;
    }

    private function ensureDirectoryExists(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (! @mkdir($this->directory, 0775, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Unable to create the filesystem semaphore directory.');
        }
    }
}
