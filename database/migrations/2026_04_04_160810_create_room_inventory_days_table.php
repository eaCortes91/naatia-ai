<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_inventory_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->unsignedInteger('total_units')->default(0);
            $table->unsignedInteger('reserved_units')->default(0);
            $table->unsignedInteger('blocked_units')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'fecha']);
            $table->index(['hotel_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_inventory_days');
    }
};
