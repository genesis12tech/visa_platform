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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 64);
            $table->string('locale', 10)->default('en');
            $table->string('channel', 16)->default('mail');
            $table->string('subject', 191)->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_key', 'locale', 'channel'], 'uq_templates_event');
        });

        $this->ensureCheckConstraintsSupported();

        DB::statement("ALTER TABLE notification_templates ADD CONSTRAINT chk_notif_channel CHECK (channel IN ('mail','database'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
