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
        Schema::create('applicant_profiles', function (Blueprint $table) {
            $table->id();
            $table->publicUlid();
            $table->foreignId('user_id')->unique('uq_profiles_user')->constrained('users')->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->date('date_of_birth');
            $table->string('place_of_birth', 191)->nullable();
            $table->string('sex', 20)->nullable();
            $table->foreignId('nationality_country_id')->constrained('countries')->restrictOnDelete();

            // Laravel's `encrypted` cast (AES-256-CBC, APP_KEY) — see ApplicantProfile
            // model. The hash is a peppered blind index (Backend_schema.md §13.2):
            // encrypted values are non-deterministic, so this is the only way to
            // detect duplicate passport numbers without decrypting every row.
            $table->text('passport_number_encrypted')->nullable();
            $table->char('passport_number_hash', 64)->charset('ascii')->collation('ascii_bin')->nullable();

            $table->date('passport_issued_at')->nullable();
            $table->date('passport_expires_at')->nullable();
            $table->foreignId('passport_issuing_country_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->string('address_line1', 191)->nullable();
            $table->string('address_line2', 191)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->foreignId('residence_country_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('passport_number_hash', 'idx_profiles_passport_hash');
            $table->index('nationality_country_id', 'idx_profiles_nationality');
            $table->index('date_of_birth', 'idx_profiles_dob');
        });

        $this->ensureCheckConstraintsSupported();

        DB::statement('ALTER TABLE applicant_profiles ADD CONSTRAINT chk_profiles_passport_dates CHECK (passport_issued_at IS NULL OR passport_expires_at IS NULL OR passport_expires_at > passport_issued_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_profiles');
    }
};
