<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_type_document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_type_id')->constrained('visa_types')->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->boolean('is_required')->default(true);
            $table->json('condition_rules')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['visa_type_id', 'document_type_id'], 'uq_vtdr');
            $table->index(['visa_type_id', 'sort_order'], 'idx_vtdr_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_type_document_requirements');
    }
};
