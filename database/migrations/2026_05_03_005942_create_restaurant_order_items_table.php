<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restaurant_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('restaurant_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('restaurant_items')->nullOnDelete();

            $table->string('name_snapshot');
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('line_total', 10, 2);

            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_order_items');
    }
};
