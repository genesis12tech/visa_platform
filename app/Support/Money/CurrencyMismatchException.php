<?php

namespace App\Support\Money;

use InvalidArgumentException;

class CurrencyMismatchException extends InvalidArgumentException
{
    public static function forCurrencies(string $left, string $right): self
    {
        return new self("Cannot operate on {$left} and {$right} — money amounts must share a currency.");
    }
}
