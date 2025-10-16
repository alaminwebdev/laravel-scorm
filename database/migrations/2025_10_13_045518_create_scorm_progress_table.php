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

            // SCORM 1.2 Core
            $table->string('cmi_core_lesson_status')->default('not attempted');
            $table->string('cmi_core_lesson_location')->nullable();
            $table->decimal('cmi_core_score_raw', 5, 2)->nullable();
            $table->decimal('cmi_core_score_min', 5, 2)->default(0);
            $table->decimal('cmi_core_score_max', 5, 2)->default(100);
            $table->integer('cmi_core_total_time')->default(0);
            $table->string('cmi_core_entry')->default('ab-initio');
            $table->string('cmi_core_exit')->nullable();

            // SCORM 2004
            $table->string('completion_status')->nullable();
            $table->string('success_status')->nullable();
            $table->decimal('score_scaled', 5, 4)->nullable();
            $table->decimal('score_raw', 5, 2)->nullable();
            $table->decimal('score_min', 5, 2)->nullable();
            $table->decimal('score_max', 5, 2)->nullable();
            $table->integer('total_time')->default(0);
            $table->string('entry')->nullable();

            // Progress & Analytics (calculated fields)
            $table->integer('interactions_count')->default(0);
            $table->integer('correct_interactions_count')->default(0);
            $table->decimal('score_percentage', 5, 2)->nullable();
            $table->text('suspend_data')->nullable();

            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            // Unique constraint - one row per user per SCO
            $table->unique(['user_id', 'scorm_sco_id']);
        });

        // Separate table for interactions (questions)
        Schema::create('scorm_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorm_tracking_id')->constrained()->onDelete('cascade');
            $table->string('interaction_id'); // Unique ID for the question
            $table->string('type'); // true-false, choice, fill-in, etc.
            $table->text('description')->nullable(); // Question text
            $table->text('learner_response'); // User's answer
            $table->text('correct_response')->nullable(); // Correct answer
            $table->string('result'); // correct, incorrect, neutral
            $table->decimal('weighting', 5, 2)->default(1.0);
            $table->decimal('latency', 8, 2)->nullable(); // Response time
            $table->timestamp('timestamp');
            $table->timestamps();

            // Index for performance
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
