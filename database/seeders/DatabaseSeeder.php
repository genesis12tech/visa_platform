<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Deliberately no WithoutModelEvents: HasUlid generates its ulid in a
    // `creating` model event, which that trait would silently suppress for
    // every seeded model, including nested seeders called from run().

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);
        $this->call(RolePermissionSeeder::class);
        $this->call(NotificationTemplateSeeder::class);
    }
}
