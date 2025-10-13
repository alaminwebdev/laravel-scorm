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
        Schema::create('scorm_scos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorm_package_id')->constrained()->onDelete('cascade');
            $table->string('identifier');
            $table->string('title');
            $table->string('launch')->nullable(); // launch file relative path
            $table->integer('sort_order')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('scorm_scos')->onDelete('cascade');
            $table->boolean('is_launchable')->default(false);
            $table->timestamps();
            $table->unique(['scorm_package_id', 'identifier']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_scos');
    }
};
