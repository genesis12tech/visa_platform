<?php

use App\Support\Money\CurrencyMismatchException;
use App\Support\Money\Money;

test('of() stores the minor amount and currency, uppercasing the currency code', function () {
    $money = Money::of(16000, 'usd');

    expect($money->minorAmount())->toBe(16000)
        ->and($money->currency())->toBe('USD');
});

test('of() rejects a currency code that is not three letters', function () {
    Money::of(100, 'US');
})->throws(InvalidArgumentException::class);

test('zero() creates a zero-value amount in the given currency', function () {
    $money = Money::zero('EUR');

    expect($money->minorAmount())->toBe(0)
        ->and($money->isZero())->toBeTrue();
});

test('add() sums two amounts in the same currency', function () {
    $result = Money::of(10000, 'USD')->add(Money::of(2500, 'USD'));

    expect($result->minorAmount())->toBe(12500)
        ->and($result->currency())->toBe('USD');
});

test('add() throws when currencies differ', function () {
    Money::of(100, 'USD')->add(Money::of(100, 'EUR'));
})->throws(CurrencyMismatchException::class);

test('subtract() finds the difference between two amounts', function () {
    $result = Money::of(10000, 'USD')->subtract(Money::of(2500, 'USD'));

    expect($result->minorAmount())->toBe(7500);
});

test('subtract() can produce a negative amount', function () {
    $result = Money::of(1000, 'USD')->subtract(Money::of(2500, 'USD'));

    expect($result->minorAmount())->toBe(-1500)
        ->and($result->isNegative())->toBeTrue();
});

test('negate() flips the sign', function () {
    expect(Money::of(1000, 'USD')->negate()->minorAmount())->toBe(-1000)
        ->and(Money::of(-1000, 'USD')->negate()->minorAmount())->toBe(1000);
});

test('multipliedBy() scales the amount', function () {
    $result = Money::of(1500, 'USD')->multipliedBy(3);

    expect($result->minorAmount())->toBe(4500);
});

test('operations do not mutate the original instance', function () {
    $original = Money::of(1000, 'USD');
    $original->add(Money::of(500, 'USD'));

    expect($original->minorAmount())->toBe(1000);
});

test('isZero, isPositive, and isNegative report sign correctly', function () {
    expect(Money::of(0, 'USD')->isZero())->toBeTrue()
        ->and(Money::of(0, 'USD')->isPositive())->toBeFalse()
        ->and(Money::of(0, 'USD')->isNegative())->toBeFalse()
        ->and(Money::of(1, 'USD')->isPositive())->toBeTrue()
        ->and(Money::of(-1, 'USD')->isNegative())->toBeTrue();
});

test('equals() requires both the same amount and the same currency', function () {
    expect(Money::of(1000, 'USD')->equals(Money::of(1000, 'USD')))->toBeTrue()
        ->and(Money::of(1000, 'USD')->equals(Money::of(1000, 'EUR')))->toBeFalse()
        ->and(Money::of(1000, 'USD')->equals(Money::of(999, 'USD')))->toBeFalse();
});

test('greaterThan() and lessThan() compare same-currency amounts', function () {
    expect(Money::of(1000, 'USD')->greaterThan(Money::of(500, 'USD')))->toBeTrue()
        ->and(Money::of(500, 'USD')->lessThan(Money::of(1000, 'USD')))->toBeTrue()
        ->and(Money::of(1000, 'USD')->greaterThan(Money::of(1000, 'USD')))->toBeFalse();
});

test('greaterThan() throws on a currency mismatch', function () {
    Money::of(1000, 'USD')->greaterThan(Money::of(500, 'EUR'));
})->throws(CurrencyMismatchException::class);

test('toDecimalString() renders a two-decimal currency correctly', function () {
    expect(Money::of(16000, 'USD')->toDecimalString(2))->toBe('160.00')
        ->and(Money::of(99, 'USD')->toDecimalString(2))->toBe('0.99')
        ->and(Money::of(5, 'USD')->toDecimalString(2))->toBe('0.05');
});

test('toDecimalString() renders a zero-decimal currency (JPY) without a decimal point', function () {
    // This is the exact failure mode Backend_schema.md calls out: naive money code
    // assumes every currency has 2 decimal places and divides by 100 regardless,
    // turning ¥12,400 into "124.00" instead of "12400".
    expect(Money::of(12400, 'JPY')->toDecimalString(0))->toBe('12400');
});

test('toDecimalString() renders a negative amount correctly', function () {
    expect(Money::of(-1550, 'USD')->toDecimalString(2))->toBe('-15.50');
});

test('allocate() splits an amount evenly when it divides cleanly', function () {
    $shares = Money::of(9000, 'USD')->allocate([1, 1, 1]);

    expect($shares)->toHaveCount(3)
        ->and($shares[0]->minorAmount())->toBe(3000)
        ->and($shares[1]->minorAmount())->toBe(3000)
        ->and($shares[2]->minorAmount())->toBe(3000);
});

test('allocate() distributes the remainder deterministically without losing a single minor unit', function () {
    // 100 split three ways: 34/33/33, and every run must produce the identical result.
    $shares = Money::of(100, 'USD')->allocate([1, 1, 1]);

    expect($shares[0]->minorAmount())->toBe(34)
        ->and($shares[1]->minorAmount())->toBe(33)
        ->and($shares[2]->minorAmount())->toBe(33);

    $total = array_reduce($shares, fn ($carry, $share) => $carry + $share->minorAmount(), 0);
    expect($total)->toBe(100);
});

test('allocate() respects unequal ratios', function () {
    $shares = Money::of(10000, 'USD')->allocate([70, 30]);

    expect($shares[0]->minorAmount())->toBe(7000)
        ->and($shares[1]->minorAmount())->toBe(3000);
});

test('allocate() preserves the currency on every share', function () {
    $shares = Money::of(100, 'JPY')->allocate([1, 1]);

    expect($shares[0]->currency())->toBe('JPY')
        ->and($shares[1]->currency())->toBe('JPY');
});

test('allocate() throws on an empty ratio list', function () {
    Money::of(100, 'USD')->allocate([]);
})->throws(InvalidArgumentException::class);

test('allocate() throws when ratios sum to zero', function () {
    Money::of(100, 'USD')->allocate([0, 0]);
})->throws(InvalidArgumentException::class);

test('allocate() throws on a negative amount', function () {
    Money::of(-100, 'USD')->allocate([1, 1]);
})->throws(InvalidArgumentException::class);

test('allocate() is repeatable — the same input always produces the same output', function () {
    $first = Money::of(9999, 'USD')->allocate([1, 1, 1, 1, 1, 1, 1]);
    $second = Money::of(9999, 'USD')->allocate([1, 1, 1, 1, 1, 1, 1]);

    foreach ($first as $i => $share) {
        expect($share->minorAmount())->toBe($second[$i]->minorAmount());
    }
});
