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
        Schema::create('visa_fees', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();
            $table->foreignId('visa_type_id')->constrained('visa_types')->restrictOnDelete();
            $table->foreignId('nationality_country_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->char('currency', 3)->charset('ascii')->collation('ascii_bin');
            $table->bigInteger('base_fee_minor');
            $table->bigInteger('service_fee_minor')->default(0);
            $table->bigInteger('priority_fee_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(
                ['visa_type_id', 'nationality_country_id', 'is_active', 'valid_from', 'valid_until'],
                'idx_fees_resolution'
            );
        });

        $this->ensureCheckConstraintsSupported();

        DB::statement('ALTER TABLE visa_fees ADD CONSTRAINT chk_fees_nonneg CHECK (base_fee_minor >= 0 AND service_fee_minor >= 0 AND priority_fee_minor >= 0 AND tax_minor >= 0)');
        DB::statement('ALTER TABLE visa_fees ADD CONSTRAINT chk_fees_window CHECK (valid_until IS NULL OR valid_until > valid_from)');
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_fees');
    }
};
