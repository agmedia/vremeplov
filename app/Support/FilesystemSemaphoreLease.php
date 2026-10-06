<?php

namespace App\Support;

class FilesystemSemaphoreLease
{
    /** @var resource|null */
    private $handle;

    /**
     * @param resource $handle
     */
    public function __construct($handle)
    {
        if (! is_resource($handle)) {
            throw new \InvalidArgumentException('A semaphore lease requires an open file handle.');
        }

        $this->handle = $handle;
    }

    public function release(): void
    {
        if (! is_resource($this->handle)) {
            return;
        }

        @flock($this->handle, LOCK_UN);
        @fclose($this->handle);
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
