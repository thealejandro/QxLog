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
        Schema::create('pricing_settings', function (Blueprint $table) {
            $table->id();

            $table->decimal('default_rate', 10, 2)->default(200);

            $table->decimal('video_rate', 10, 2)->default(300);
            $table->decimal('night_rate', 10, 2)->default(350);
            $table->decimal('long_case_rate', 10, 2)->default(350);

            $table->integer('long_case_threshold_minutes')->default(120);

            $table->time('night_start')->default('22:00'); // HH:MM
            $table->time('night_end')->default('06:00');   // HH:MM

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_settings');
    }
};
