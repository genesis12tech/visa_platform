<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_type', 16)->default('system');
            $table->foreignId('on_behalf_of_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 100);
            $table->string('auditable_type', 191)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->unsignedBigInteger('application_id')->nullable();
            $table->binary('ip_address', length: 16)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'idx_audit_auditable');
            $table->index(['actor_user_id', 'created_at'], 'idx_audit_actor');
            $table->index(['action', 'created_at'], 'idx_audit_action');
            $table->index(['application_id', 'created_at'], 'idx_audit_app');
            $table->index(['created_at'], 'idx_audit_created');
        });

        // Backend_schema.md §4.12/§8.1 — audit_logs is append-only under PRD
        // BR-02: legal defensibility requires that a compliance log can never
        // be edited or removed after the fact, only ever appended to.
        DB::unprepared('
            CREATE TRIGGER trg_audit_logs_no_update BEFORE UPDATE ON audit_logs
            FOR EACH ROW BEGIN
                SIGNAL SQLSTATE \'45000\'
                    SET MESSAGE_TEXT = \'audit_logs is append-only: rows cannot be updated\';
            END
        ');

        DB::unprepared('
            CREATE TRIGGER trg_audit_logs_no_delete BEFORE DELETE ON audit_logs
            FOR EACH ROW BEGIN
                SIGNAL SQLSTATE \'45000\'
                    SET MESSAGE_TEXT = \'audit_logs is append-only: rows cannot be deleted\';
            END
        ');
    }

    public function down(): void
    {
        // Dropping the table also drops its triggers (MySQL/MariaDB both
        // remove a table's triggers automatically on DROP TABLE).
        Schema::dropIfExists('audit_logs');
    }
};
