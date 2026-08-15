<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_locations', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();
            $table->string('name', 150);
            $table->foreignId('country_id')->constrained('countries')->restrictOnDelete();
            $table->string('address_line1', 191);
            $table->string('address_line2', 191)->nullable();
            $table->string('city', 100);
            $table->string('postal_code', 32)->nullable();
            $table->string('timezone', 64);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('operating_hours');
            $table->string('contact_phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'country_id'], 'idx_locations_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_locations');
    }
};
