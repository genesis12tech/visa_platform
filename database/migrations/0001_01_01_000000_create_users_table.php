<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();
            $table->string('name', 191);
            $table->string('email', 191);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone', 32)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('user_type', 16)->default('applicant');
            $table->string('locale', 10)->default('en');
            $table->timestamp('last_login_at')->nullable();
            $table->binary('last_login_ip', length: 16)->nullable();
            $table->timestamp('mfa_enabled_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('suspension_reason')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('email', 'uq_users_email');
            $table->index(['status', 'user_type'], 'idx_users_status_type');
            $table->index('last_login_at', 'idx_users_last_login');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_status CHECK (status IN ('pending','active','suspended'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_type CHECK (user_type IN ('applicant','agent','staff'))");
        DB::statement('ALTER TABLE users ADD CONSTRAINT chk_users_suspension CHECK ((status <> \'suspended\') OR (suspended_at IS NOT NULL AND suspension_reason IS NOT NULL))');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
