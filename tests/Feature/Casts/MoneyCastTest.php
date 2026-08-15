<?php

use App\Casts\MoneyCast;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A throwaway model/table exercising MoneyCast the way a real one (e.g.
 * visa_applications.fee_total_minor + fee_currency) would use it.
 */
class MoneyCastTestModel extends Model
{
    protected $table = 'money_cast_test_models';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fee_total_minor' => MoneyCast::class.':fee_currency',
        ];
    }
}

beforeEach(function () {
    Schema::create('money_cast_test_models', function ($table) {
        $table->id();
        $table->bigInteger('fee_total_minor')->nullable();
        $table->char('fee_currency', 3)->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('money_cast_test_models');
});

test('reading a model returns a Money instance built from the paired minor/currency columns', function () {
    MoneyCastTestModel::query()->insert(['fee_total_minor' => 16000, 'fee_currency' => 'USD']);

    $model = MoneyCastTestModel::query()->first();

    expect($model->fee_total_minor)->toBeInstanceOf(Money::class)
        ->and($model->fee_total_minor->minorAmount())->toBe(16000)
        ->and($model->fee_total_minor->currency())->toBe('USD');
});

test('assigning a Money instance writes both the minor amount and currency columns', function () {
    $model = new MoneyCastTestModel;
    $model->fee_total_minor = Money::of(24000, 'EUR');
    $model->save();

    $raw = DB::table('money_cast_test_models')->first();

    expect($raw->fee_total_minor)->toBe(24000)
        ->and($raw->fee_currency)->toBe('EUR');
});

test('a null money value is stored and read back as null in both columns', function () {
    $model = new MoneyCastTestModel;
    $model->fee_total_minor = null;
    $model->save();

    $fresh = MoneyCastTestModel::query()->first();

    expect($fresh->fee_total_minor)->toBeNull();

    $raw = DB::table('money_cast_test_models')->first();
    expect($raw->fee_total_minor)->toBeNull()
        ->and($raw->fee_currency)->toBeNull();
});

test('assigning a non-Money, non-null value throws', function () {
    $model = new MoneyCastTestModel;
    $model->fee_total_minor = 16000;
    $model->save();
})->throws(InvalidArgumentException::class);

test('a round trip through save and reload preserves the exact amount and currency', function () {
    $model = new MoneyCastTestModel;
    $model->fee_total_minor = Money::of(1, 'JPY');
    $model->save();

    $fresh = MoneyCastTestModel::query()->find($model->id);

    expect($fresh->fee_total_minor->equals(Money::of(1, 'JPY')))->toBeTrue();
});
