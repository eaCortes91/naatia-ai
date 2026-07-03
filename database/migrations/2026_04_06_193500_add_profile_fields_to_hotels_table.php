<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('address_line')->nullable()->after('email');
            $table->string('neighborhood')->nullable()->after('address_line');
            $table->string('city')->nullable()->after('neighborhood');
            $table->string('state')->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('state');
            $table->decimal('latitude', 11, 8)->nullable()->after('postal_code');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->string('check_in_time', 20)->nullable()->after('longitude');
            $table->string('check_out_time', 20)->nullable()->after('check_in_time');
            $table->boolean('pet_friendly')->default(false)->after('check_out_time');
            $table->text('amenities_text')->nullable()->after('pet_friendly');
            $table->text('policies_text')->nullable()->after('amenities_text');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn([
                'address_line',
                'neighborhood',
                'city',
                'state',
                'postal_code',
                'latitude',
                'longitude',
                'check_in_time',
                'check_out_time',
                'pet_friendly',
                'amenities_text',
                'policies_text',
            ]);
        });
    }
};
