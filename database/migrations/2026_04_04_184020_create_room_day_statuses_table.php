<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_day_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status')->default('libre');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'date']);
            $table->index(['hotel_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_day_statuses');
    }
};
