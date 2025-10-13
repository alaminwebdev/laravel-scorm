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
        Schema::create('scorm_packages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('identifier')->nullable(); // from imsmanifest.xml
            $table->string('version')->nullable();
            $table->text('description')->nullable();
            $table->string('entry_point')->nullable(); // index.html inside zip
            $table->string('file_path'); // path to extracted folder
            $table->timestamps();

            $table->unique('identifier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_packages');
    }
};
