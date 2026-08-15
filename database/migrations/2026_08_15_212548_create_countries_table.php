<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('iso2', 2)->charset('ascii')->collation('ascii_bin')->unique('uq_countries_iso2');
            $table->char('iso3', 3)->charset('ascii')->collation('ascii_bin')->unique('uq_countries_iso3');
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);

            $table->index(['is_active', 'name'], 'idx_countries_active_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
