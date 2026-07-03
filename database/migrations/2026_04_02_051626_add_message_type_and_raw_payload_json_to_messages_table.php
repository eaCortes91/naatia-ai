<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'message_type')) {
                $table->string('message_type')->default('text')->after('external_id');
            }

            if (! Schema::hasColumn('messages', 'raw_payload_json')) {
                $table->longText('raw_payload_json')->nullable()->after('message_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'raw_payload_json')) {
                $table->dropColumn('raw_payload_json');
            }

            if (Schema::hasColumn('messages', 'message_type')) {
                $table->dropColumn('message_type');
            }
        });
    }
};
