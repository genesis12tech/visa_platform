<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();
            $table->string('code', 64)->unique('uq_doc_types_code');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->json('allowed_mime_types');
            $table->json('allowed_extensions');
            $table->unsignedBigInteger('max_size_bytes')->default(10_485_760);
            $table->unsignedSmallInteger('max_pages')->nullable();
            $table->unsignedSmallInteger('min_width_px')->nullable();
            $table->unsignedSmallInteger('min_height_px')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE document_types ADD CONSTRAINT chk_doc_types_size CHECK (max_size_bytes BETWEEN 1024 AND 52428800)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
