<?php

namespace App\Support\Money;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Immutable minor-unit money value object. All arithmetic goes through bcmath —
 * never floats (PRD BR-08). A "minor unit" is currency-agnostic: for USD it's
 * cents, for JPY (zero-decimal) it's the yen itself. This class never assumes
 * a decimal exponent; toDecimalString() takes it explicitly because the
 * correct value comes from the `currencies` table, not a hardcoded guess.
 */
final class Money implements JsonSerializable
{
    private function __construct(
        private readonly string $minorAmount,
        private readonly string $currency,
    ) {}

    public static function of(int|string $minorAmount, string $currency): self
    {
        if (! preg_match('/^[A-Za-z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Invalid currency code: {$currency}");
        }

        return new self((string) (int) $minorAmount, strtoupper($currency));
    }

    public static function zero(string $currency): self
    {
        return self::of(0, $currency);
    }

    public function minorAmount(): int
    {
        return (int) $this->minorAmount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(bcadd($this->minorAmount, $other->minorAmount, 0), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(bcsub($this->minorAmount, $other->minorAmount, 0), $this->currency);
    }

    public function negate(): self
    {
        return new self(bcmul($this->minorAmount, '-1', 0), $this->currency);
    }

    public function multipliedBy(int|string $factor): self
    {
        return new self(bcmul($this->minorAmount, (string) $factor, 0), $this->currency);
    }

    public function isZero(): bool
    {
        return bccomp($this->minorAmount, '0', 0) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->minorAmount, '0', 0) === 1;
    }

    public function isNegative(): bool
    {
        return bccomp($this->minorAmount, '0', 0) === -1;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency
            && bccomp($this->minorAmount, $other->minorAmount, 0) === 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return bccomp($this->minorAmount, $other->minorAmount, 0) === 1;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return bccomp($this->minorAmount, $other->minorAmount, 0) === -1;
    }

    /**
     * Splits this amount across the given non-negative integer ratios, distributing
     * any remainder minor unit one at a time to the earliest ratios in the list —
     * deterministic, and every share always sums back to exactly this amount.
     *
     * Only defined for non-negative amounts: allocation is for splitting a payment
     * or refund total across line items, not for arbitrary signed arithmetic.
     *
     * @param  array<int, mixed>  $ratios
     * @return array<int, self>
     */
    public function allocate(array $ratios): array
    {
        if ($this->isNegative()) {
            throw new InvalidArgumentException('Cannot allocate a negative amount.');
        }

        if ($ratios === []) {
            throw new InvalidArgumentException('Cannot allocate across zero shares.');
        }

        foreach ($ratios as $ratio) {
            if (! is_int($ratio) || $ratio < 0) {
                throw new InvalidArgumentException('Allocation ratios must be non-negative integers.');
            }
        }

        $total = array_sum($ratios);

        if ($total <= 0) {
            throw new InvalidArgumentException('Sum of allocation ratios must be positive.');
        }

        $totalStr = (string) $total;
        $shares = [];
        $allocated = '0';

        foreach ($ratios as $ratio) {
            $share = bcdiv(bcmul($this->minorAmount, (string) $ratio, 0), $totalStr, 0);
            $shares[] = $share;
            $allocated = bcadd($allocated, $share, 0);
        }

        $remainder = (int) bcsub($this->minorAmount, $allocated, 0);

        for ($i = 0; $i < $remainder; $i++) {
            $shares[$i] = bcadd($shares[$i], '1', 0);
        }

        return array_map(fn (string $share) => new self($share, $this->currency), $shares);
    }

    /**
     * Renders as a decimal string using the given exponent — which must come from
     * the currency's actual `minor_unit_exponent` (currencies table), never assumed.
     */
    public function toDecimalString(int $exponent): string
    {
        return bcdiv($this->minorAmount, bcpow('10', (string) $exponent, 0), $exponent);
    }

    /**
     * @return array{minor_amount: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'minor_amount' => $this->minorAmount(),
            'currency' => $this->currency,
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatchException::forCurrencies($this->currency, $other->currency);
        }
    }
}
