<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('successful')->default(false);
            $table->string('failure_reason', 32)->nullable();
            $table->binary('ip_address', length: 16)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('guard', 16)->default('web');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['email', 'created_at'], 'idx_login_email_time');
            $table->index(['ip_address', 'created_at'], 'idx_login_ip_time');
            $table->index(['user_id', 'created_at'], 'idx_login_user_time');
        });

        DB::statement("ALTER TABLE login_attempts ADD CONSTRAINT chk_login_failure CHECK (
            (successful = 1 AND failure_reason IS NULL) OR
            (successful = 0 AND failure_reason IN ('bad_credentials','unverified','suspended','mfa_failed','throttled'))
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
