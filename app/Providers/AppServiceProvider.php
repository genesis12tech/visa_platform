<?php

namespace App\Providers;

use App\Models\Agent;
use App\Models\Applicant;
use App\Models\Staff;
use App\Policies\UserPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
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
        $this->registerGuardScopedUserPolicies();
    }

    /**
     * Applicant/Agent/Staff are guard-provider views of the same `users`
     * table (Backend_schema.md §11.1), not independent sensitive models —
     * they share UserPolicy rather than each needing their own.
     */
    private function registerGuardScopedUserPolicies(): void
    {
        Gate::policy(Applicant::class, UserPolicy::class);
        Gate::policy(Agent::class, UserPolicy::class);
        Gate::policy(Staff::class, UserPolicy::class);
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
