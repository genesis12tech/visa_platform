<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerMigrationMacros();
    }

    /**
     * Schema conventions shared by every migration (Backend_schema.md §1 D-2,
     * §2): a BIGINT auto-increment stays the primary key throughout; this adds
     * the externally-addressable ULID column in the exact charset/collation the
     * schema specifies, wherever a migration calls $table->publicUlid().
     */
    private function registerMigrationMacros(): void
    {
        Blueprint::macro('publicUlid', function (string $column = 'ulid') {
            /** @var Blueprint $this */
            return $this->ulid($column)->charset('ascii')->collation('ascii_bin')->unique();
        });
    }
}
