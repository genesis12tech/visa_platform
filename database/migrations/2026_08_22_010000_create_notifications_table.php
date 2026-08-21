<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            // Laravel's own UUID convention for this table (Backend_schema.md §4.10).
            $table->uuid('id')->primary();
            $table->string('type', 191);
            $table->string('notifiable_type', 191);
            $table->unsignedBigInteger('notifiable_id');
            $table->json('data');
            // No FK yet — visa_applications doesn't exist until Stage 3 (same
            // deliberate gap as audit_logs.application_id; add the
            // fk_notifications_app constraint once that table exists).
            $table->unsignedBigInteger('application_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Composite index includes read_at (unlike Laravel's default
            // ->morphs() index, which only covers type+id) — this is the
            // exact index the "unread notifications" query needs.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'idx_notifications_notifiable');
            $table->index('application_id', 'idx_notifications_app');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
