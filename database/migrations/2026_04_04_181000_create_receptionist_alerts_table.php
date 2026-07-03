<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receptionist_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // transfer_review|cash_followup|payment_expired
            $table->string('status')->default('pending');
            $table->string('title');
            $table->text('body');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receptionist_alerts');
    }
};
