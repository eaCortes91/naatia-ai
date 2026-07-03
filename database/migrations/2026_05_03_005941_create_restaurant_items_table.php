<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restaurant_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('restaurant_categories')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->enum('stock_status', ['available', 'out_of_stock'])->default('available');
            $table->boolean('active')->default(true);
            $table->string('image_url')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'active']);
            $table->index(['hotel_id', 'stock_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_items');
    }
};
