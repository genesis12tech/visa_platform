<?php

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HasUlidTestModel extends Model
{
    use HasUlid;

    protected $table = 'has_ulid_test_models';

    public $timestamps = false;

    protected $guarded = [];
}

class HasUlidCustomRouteTestModel extends Model
{
    use HasUlid;

    protected $table = 'has_ulid_test_models';

    public $timestamps = false;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'tracking_number';
    }
}

beforeEach(function () {
    Schema::create('has_ulid_test_models', function (Blueprint $table) {
        $table->id();
        $table->publicUlid();
        $table->string('tracking_number')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('has_ulid_test_models');
});

test('the publicUlid migration macro creates a 26-character unique column', function () {
    DB::table('has_ulid_test_models')->insert(['ulid' => str_pad('A', 26, 'A')]);

    expect(fn () => DB::table('has_ulid_test_models')->insert(['ulid' => str_pad('A', 26, 'A')]))
        ->toThrow(QueryException::class);
});

test('creating a model auto-generates a 26-character ulid', function () {
    $model = HasUlidTestModel::query()->create([]);

    expect($model->ulid)->toBeString()
        ->and(strlen($model->ulid))->toBe(26);
});

test('two created models never share a ulid', function () {
    $ulids = collect(range(1, 25))
        ->map(fn () => HasUlidTestModel::query()->create([])->ulid);

    expect($ulids->unique())->toHaveCount(25);
});

test('an explicitly set ulid is not overwritten on create', function () {
    $model = HasUlidTestModel::query()->create(['ulid' => str_pad('CUSTOM', 26, 'X')]);

    expect($model->ulid)->toBe(str_pad('CUSTOM', 26, 'X'));
});

test('getRouteKeyName defaults to ulid', function () {
    expect((new HasUlidTestModel)->getRouteKeyName())->toBe('ulid');
});

test('a model can override getRouteKeyName while still using HasUlid for the ulid column itself', function () {
    $model = HasUlidCustomRouteTestModel::query()->create(['tracking_number' => 'VISA-IND-2026-8K3F2Q']);

    expect($model->getRouteKeyName())->toBe('tracking_number')
        ->and($model->ulid)->toBeString()
        ->and(strlen($model->ulid))->toBe(26);
});
