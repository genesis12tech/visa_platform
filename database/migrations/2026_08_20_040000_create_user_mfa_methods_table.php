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
        Schema::create('user_mfa_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16)->default('totp');
            $table->text('secret_encrypted');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'type'], 'uq_mfa_user_type');
        });

        $this->ensureCheckConstraintsSupported();

        DB::statement("ALTER TABLE user_mfa_methods ADD CONSTRAINT chk_mfa_type CHECK (type IN ('totp'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mfa_methods');
    }
};
