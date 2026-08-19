<?php

use App\Casts\IpAddressCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A throwaway model/table exercising IpAddressCast the way login_attempts.ip_address
 * and users.last_login_ip (both VARBINARY(16), storing INET6_ATON-equivalent bytes) do.
 */
class IpAddressCastTestModel extends Model
{
    protected $table = 'ip_address_cast_test_models';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ip_address' => IpAddressCast::class,
        ];
    }
}

beforeEach(function () {
    Schema::create('ip_address_cast_test_models', function ($table) {
        $table->id();
        $table->binary('ip_address', length: 16)->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('ip_address_cast_test_models');
});

test('an IPv4 address round-trips through binary storage', function () {
    $model = new IpAddressCastTestModel;
    $model->ip_address = '203.0.113.42';
    $model->save();

    $fresh = IpAddressCastTestModel::query()->find($model->id);

    expect($fresh->ip_address)->toBe('203.0.113.42');
});

test('an IPv6 address round-trips through binary storage', function () {
    $model = new IpAddressCastTestModel;
    $model->ip_address = '2001:db8::1';
    $model->save();

    $fresh = IpAddressCastTestModel::query()->find($model->id);

    expect($fresh->ip_address)->toBe('2001:db8::1');
});

test('the stored value is genuinely packed binary, not the readable string', function () {
    $model = new IpAddressCastTestModel;
    $model->ip_address = '203.0.113.42';
    $model->save();

    $raw = DB::table('ip_address_cast_test_models')->where('id', $model->id)->value('ip_address');

    expect($raw)->not->toBe('203.0.113.42')
        ->and(strlen($raw))->toBe(4); // packed IPv4 is 4 bytes, not 16 — VARBINARY allows shorter values
});

test('a null ip address is stored and read back as null', function () {
    $model = new IpAddressCastTestModel;
    $model->ip_address = null;
    $model->save();

    $fresh = IpAddressCastTestModel::query()->find($model->id);

    expect($fresh->ip_address)->toBeNull();
});

test('an invalid IP string throws rather than silently storing garbage', function () {
    $model = new IpAddressCastTestModel;
    $model->ip_address = 'not-an-ip';
    $model->save();
})->throws(InvalidArgumentException::class);
