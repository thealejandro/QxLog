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
        Schema::table('procedures', function (Blueprint $table) {
            $table->foreignId('payout_batch_id')->nullable()->constrained('payout_batches')->nullOnDelete();

            $table->index(['payout_batch_id', 'status'], 'payout_batch_id_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropForeign(['payout_batch_id']);
            $table->dropColumn(['payout_batch_id']);
        });
    }
};
