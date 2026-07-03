<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('room_type_id')->nullable()->after('hotel_id')->constrained('room_types')->nullOnDelete();
            $table->decimal('weekday_rate', 10, 2)->default(0)->after('inventario_total');
            $table->decimal('weekend_rate', 10, 2)->default(0)->after('weekday_rate');
            $table->string('base_status')->default('libre')->after('weekend_rate');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_type_id');
            $table->dropColumn(['weekday_rate', 'weekend_rate', 'base_status']);
        });
    }
};
