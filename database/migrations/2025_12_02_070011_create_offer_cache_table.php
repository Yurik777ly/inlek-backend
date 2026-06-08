<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Слой загрузки из evo_offers; для API используйте product_pharmacy_cache (view evo_product_pharmacy_view).
        Schema::create('offer_cache', function (Blueprint $table) {

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('pharmacy_id');

            $table->string('pharmacy_name')->nullable();
            $table->string('pharmacy_alias')->nullable();
            $table->string('product_name')->nullable();
            $table->string('address')->nullable();
            $table->string('coordinates', 100)->nullable();
            $table->json('schedule')->nullable();

            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('price_old', 12, 2)->nullable();

            $table->integer('stock_count')->default(0);

            $table->date('expiration_date')->nullable();

            $table->timestamp('updated_at')->nullable();

            $table->primary([
                'product_id',
                'pharmacy_id'
            ]);

            $table->index('pharmacy_id');

            $table->index([
                'product_id',
                'stock_count'
            ]);

            $table->index([
                'product_id',
                'price'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_cache');
    }
};
