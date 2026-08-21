<?php

use App\Support\Concerns\EnsuresCheckConstraintSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use EnsuresCheckConstraintSupport;

    public function up(): void
    {
        Schema::create('visa_types', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();
            $table->foreignId('country_id')->constrained('countries')->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('processing_days_min');
            $table->unsignedSmallInteger('processing_days_max');
            $table->unsignedSmallInteger('sla_business_hours')->default(240);
            $table->boolean('requires_biometrics')->default(false);
            $table->boolean('requires_interview')->default(false);
            $table->boolean('requires_four_eyes')->default(false);
            $table->unsignedTinyInteger('max_reschedules')->default(2);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['country_id', 'code'], 'uq_visa_types_country_code');
            $table->index(['is_active', 'country_id'], 'idx_visa_types_active');
        });

        $this->ensureCheckConstraintsSupported();

        DB::statement('ALTER TABLE visa_types ADD CONSTRAINT chk_visa_types_processing CHECK (processing_days_max >= processing_days_min)');
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_types');
    }
};
