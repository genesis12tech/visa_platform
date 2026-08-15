<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rejection_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 16);
            $table->string('code', 64);
            $table->string('label', 191);
            $table->text('applicant_text');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['scope', 'code'], 'uq_rejection_scope_code');
        });

        DB::statement("ALTER TABLE rejection_reasons ADD CONSTRAINT chk_rejection_scope CHECK (scope IN ('document','decision'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('rejection_reasons');
    }
};
