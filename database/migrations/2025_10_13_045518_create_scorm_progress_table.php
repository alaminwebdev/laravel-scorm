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
        Schema::create('scorm_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('scorm_sco_id')->constrained()->onDelete('cascade');

            // SCORM 1.2 Core elements
            $table->string('cmi_core_lesson_status')->default('not attempted');
            $table->string('cmi_core_lesson_location')->nullable();
            $table->decimal('cmi_core_score_raw', 5, 2)->nullable();
            $table->decimal('cmi_core_score_min', 5, 2)->default(0);
            $table->decimal('cmi_core_score_max', 5, 2)->default(100);
            $table->integer('cmi_core_total_time')->default(0); // in seconds
            $table->integer('cmi_core_session_time')->default(0); // in seconds
            $table->string('cmi_core_entry')->default('ab-initio');
            $table->string('cmi_core_exit')->nullable();

            // SCORM 2004 elements
            $table->string('completion_status')->nullable(); // completed, incomplete, not attempted, unknown
            $table->string('success_status')->nullable(); // passed, failed, unknown
            $table->decimal('score_scaled', 5, 4)->nullable(); // -1 to 1
            $table->decimal('score_raw', 5, 2)->nullable();
            $table->decimal('score_min', 5, 2)->nullable();
            $table->decimal('score_max', 5, 2)->nullable();
            $table->integer('total_time')->default(0); // in seconds
            $table->string('entry')->default('ab-initio');

            // Common fields
            $table->text('suspend_data')->nullable();
            $table->text('launch_data')->nullable();
            $table->text('comments')->nullable();
            $table->text('comments_from_lms')->nullable();

            // Progress tracking
            $table->decimal('progress_measure', 5, 4)->nullable(); // 0 to 1
            $table->decimal('scaled_passing_score', 5, 4)->nullable(); // -1 to 1

            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->unique(['user_id', 'scorm_sco_id']);
            $table->index(['user_id', 'cmi_core_lesson_status']);
        });

        // Separate table for interactions (questions)
        Schema::create('scorm_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorm_tracking_id')->constrained()->onDelete('cascade');
            $table->string('interaction_id');
            $table->string('type'); // true-false, choice, fill-in, matching, performance, sequencing, likert, numeric
            $table->text('description')->nullable();
            $table->text('learner_response')->nullable();
            $table->text('correct_response')->nullable();
            $table->string('result')->nullable(); // correct, incorrect, unanticipated, neutral
            $table->decimal('weighting', 5, 2)->nullable();
            $table->decimal('latency', 8, 2)->nullable(); // in seconds
            $table->timestamp('timestamp');
            $table->timestamps();

            $table->index(['scorm_tracking_id', 'interaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_trackings');
        Schema::dropIfExists('scorm_interactions');
    }
};
