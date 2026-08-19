<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * MySQL only enforces CHECK constraints from 8.0.16 (Backend_schema.md
 * §1509) — below that they parse and silently no-op. MariaDB enforces them
 * from 10.2.1, well below this project's verified-production 11.8.8, so any
 * MariaDB instance passes unconditionally. Tests asserting a CHECK
 * violation throws should ->skip(fn () => ! databaseEnforcesCheckConstraints(), ...)
 * rather than fail outright when run against a non-enforcing local engine.
 */
function databaseEnforcesCheckConstraints(): bool
{
    $version = DB::selectOne('SELECT VERSION() as version')->version;

    if (stripos($version, 'mariadb') !== false) {
        return true;
    }

    return version_compare($version, '8.0.16', '>=');
}
