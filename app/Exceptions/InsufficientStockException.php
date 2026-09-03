<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    private $productId;
    private $available;
    private $requested;

    public function __construct(int $productId, string $productName, int $available, int $requested)
    {
        $this->productId = $productId;
        $this->available = $available;
        $this->requested = $requested;

        parent::__construct(sprintf(
            'Knjiga "%s" više nije dostupna u traženoj količini (dostupno: %d).',
            $productName,
            max(0, $available)
        ), 409);
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function available(): int
    {
        return $this->available;
    }

    public function requested(): int
    {
        return $this->requested;
    }
}
