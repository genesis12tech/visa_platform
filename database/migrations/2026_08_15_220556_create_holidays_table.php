<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained('service_locations')->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('description', 150);
            $table->timestamp('created_at')->nullable();

            $table->unique(['location_id', 'holiday_date'], 'uq_holidays');
            $table->index('holiday_date', 'idx_holidays_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
