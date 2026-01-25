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
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();

            //UX amigable separado
            $table->date('procedure_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            $table->string('patient_name');
            $table->string('procedure_type');
            $table->boolean('is_videosurgery')->default(false);

            $table->foreignId('instrumentist_id')->nullable()->constrained('users')->nullOnDelete(); // Instrumentista
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete(); // Doctor
            $table->foreignId('circulating_id')->nullable()->constrained('users')->nullOnDelete(); // Circulante

            $table->string('instrumentist_name')->nullable();
            $table->string('doctor_name')->nullable();
            $table->string('circulating_name')->nullable();

            $table->timestamp('paid_at')->nullable();

            //Calculo automatico (snapshot)
            $table->decimal('calculated_amount', 10, 2)->default(0);
            $table->json('pricing_snapshot')->nullable();

            //Estados
            $table->string('status')->default('pending');
            $table->string('void_reason')->nullable();

            // indices
            $table->index(['instrumentist_id', 'status'], 'instrumentist_status_idx');
            $table->index(['doctor_id', 'status'], 'doctor_status_idx');
            $table->index(['circulating_id', 'status'], 'circulating_status_idx');
            $table->index('procedure_date', 'procedure_date_idx');
            $table->index(['paid_at', 'status'], 'paid_at_status_idx');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procedures');
    }
};
