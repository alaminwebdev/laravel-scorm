<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // In your migration
        Schema::create('scorm_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('scorm_sco_id')->constrained()->onDelete('cascade');

            // Core Status Fields (SCORM Standard)
            $table->string('cmi_core_lesson_status')->default('not attempted');
            $table->string('cmi_core_lesson_location')->nullable();
            $table->string('cmi_core_entry')->default('ab-initio');

            // Scoring
            $table->decimal('cmi_core_score_raw', 5, 2)->nullable();
            $table->decimal('cmi_core_score_min', 5, 2)->nullable();
            $table->decimal('cmi_core_score_max', 5, 2)->nullable();
            $table->string('cmi_core_score_scaled')->nullable();

            // Time Tracking (in seconds)
            $table->integer('cmi_core_total_time')->default(0);
            $table->integer('cmi_core_session_time')->default(0);
            $table->string('cmi_core_exit')->nullable();

            // Data Storage
            $table->text('suspend_data')->nullable();
            $table->text('launch_data')->nullable();

            // Your existing fields (keep them)
            $table->enum('status', ['not_attempted', 'incomplete', 'completed', 'passed', 'failed'])->default('not_attempted');
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->string('success_status')->nullable();
            $table->string('completion_status')->nullable();
            $table->time('session_time')->nullable();

            $table->timestamps();
            $table->unique(['user_id', 'scorm_sco_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_trackings');
    }
};
