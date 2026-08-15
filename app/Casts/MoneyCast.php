<?php

namespace App\Casts;

use App\Support\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts a `*_minor` integer column paired with a currency column into a single
 * Money attribute. Attach to the minor-amount column, passing the currency
 * column's name as the cast argument:
 *
 *   protected function casts(): array
 *   {
 *       return ['fee_total_minor' => MoneyCast::class.':fee_currency'];
 *   }
 *
 * @implements CastsAttributes<Money|null, mixed>
 */
class MoneyCast implements CastsAttributes
{
    public function __construct(private readonly string $currencyColumn) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::of((int) $value, (string) $attributes[$this->currencyColumn]);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [
                $key => null,
                $this->currencyColumn => $attributes[$this->currencyColumn] ?? null,
            ];
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException(
                sprintf('%s can only be assigned a %s instance or null, %s given.', self::class, Money::class, get_debug_type($value))
            );
        }

        return [
            $key => $value->minorAmount(),
            $this->currencyColumn => $value->currency(),
        ];
    }
}
