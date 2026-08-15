<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->charset('ascii')->collation('ascii_bin')->unique('uq_currencies_code');
            $table->string('name', 100);
            $table->string('symbol', 8);
            $table->unsignedTinyInteger('minor_unit_exponent')->default(2);
            $table->boolean('is_active')->default(true);
        });

        DB::statement('ALTER TABLE currencies ADD CONSTRAINT chk_currencies_exponent CHECK (minor_unit_exponent <= 4)');
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
