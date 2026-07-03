<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restaurant_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();

            $table->enum('status', [
                'draft',
                'pending_payment',
                'paid',
                'confirmed',
                'preparing',
                'ready',
                'out_for_delivery',
                'delivered',
                'completed',
                'cancelled',
            ])->default('draft');

            $table->enum('fulfillment_type', ['pickup', 'delivery'])->nullable();

            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('delivery_reference')->nullable();

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
            $table->index(['contact_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_orders');
    }
};
