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
        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('instrumentist_id')->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by_id')->constrained('users')->nullOnDelete(); //Persona que liquida

            $table->dateTime('paid_at'); //automatico now()
            $table->decimal('total_amount', 10, 2)->default(0);

            $table->string('status')->default('active'); // active | void
            $table->string('void_reason')->nullable();

            $table->index(['instrumentist_id', 'paid_at'], 'instrumentist_paid_at_idx');
            $table->index(['paid_by_id', 'paid_at'], 'paid_by_paid_at_idx');
            $table->index('status', 'payout_batches_status_idx');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_batches');
    }
};
