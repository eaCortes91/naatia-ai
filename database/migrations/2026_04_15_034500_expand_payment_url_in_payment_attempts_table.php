<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Keep it simple and compatible in MySQL without doctrine/dbal.
        DB::statement('ALTER TABLE payment_attempts MODIFY payment_url TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payment_attempts MODIFY payment_url VARCHAR(255) NULL');
    }
};
