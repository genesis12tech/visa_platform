<?php

use App\Support\Concerns\EnsuresCheckConstraintSupport;
use Illuminate\Support\Facades\DB;

function migrationUsingCheckConstraintGuard(): object
{
    return new class
    {
        use EnsuresCheckConstraintSupport;

        public function guard(): void
        {
            $this->ensureCheckConstraintsSupported();
        }
    };
}

test('MySQL 8.0.16 or later is accepted', function () {
    DB::shouldReceive('selectOne')->once()->andReturn((object) ['version' => '8.0.16']);

    expect(fn () => migrationUsingCheckConstraintGuard()->guard())->not->toThrow(RuntimeException::class);
});

test('a MySQL version newer than 8.0.16 is accepted', function () {
    DB::shouldReceive('selectOne')->once()->andReturn((object) ['version' => '8.4.2']);

    expect(fn () => migrationUsingCheckConstraintGuard()->guard())->not->toThrow(RuntimeException::class);
});

test('MySQL below 8.0.16 is rejected — CHECK parses and is silently ignored below that version', function () {
    DB::shouldReceive('selectOne')->once()->andReturn((object) ['version' => '8.0.15']);

    migrationUsingCheckConstraintGuard()->guard();
})->throws(RuntimeException::class);

test('an old MySQL major version is rejected', function () {
    DB::shouldReceive('selectOne')->once()->andReturn((object) ['version' => '5.7.44']);

    migrationUsingCheckConstraintGuard()->guard();
})->throws(RuntimeException::class);

test('any MariaDB version is accepted — it enforces CHECK from 10.2.1, long before this project targets', function () {
    DB::shouldReceive('selectOne')->once()->andReturn((object) ['version' => '10.3.0-MariaDB']);

    expect(fn () => migrationUsingCheckConstraintGuard()->guard())->not->toThrow(RuntimeException::class);
});

test('the production MariaDB version string is accepted', function () {
    DB::shouldReceive('selectOne')->once()->andReturn((object) ['version' => '11.8.8-MariaDB']);

    expect(fn () => migrationUsingCheckConstraintGuard()->guard())->not->toThrow(RuntimeException::class);
});
