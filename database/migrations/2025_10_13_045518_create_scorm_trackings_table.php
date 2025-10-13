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
            $table->enum('status', ['not_attempted', 'incomplete', 'completed', 'passed', 'failed'])->default('not_attempted');
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->string('success_status')->nullable(); // passed / failed
            $table->string('completion_status')->nullable(); // completed / incomplete / unknown
            $table->string('suspend_data')->nullable();    // JSON for resume
            $table->time('session_time')->nullable();      // HH:MM:SS
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
