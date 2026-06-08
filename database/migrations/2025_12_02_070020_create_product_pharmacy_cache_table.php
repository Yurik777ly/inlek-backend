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
        Schema::create('product_pharmacy_cache', function (Blueprint $table) {

            $table->unsignedBigInteger('product_id');

            $table->unsignedBigInteger('pharmacy_id');

            $table->string('product_name')->nullable();

            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('price_old', 10, 2)->nullable();

            $table->integer('stock_count')->default(0);

            $table->date('expiration_date')->nullable();

            $table->string('recipe')->nullable();
            $table->boolean('is_recipe')->default(false);

            $table->string('is_alcohol')->nullable();

            $table->string('pharmacy_name')->nullable();
            $table->string('pharmacy_alias')->nullable();

            $table->string('pharmacy_delivery')->nullable();

            $table->text('address')->nullable();

            $table->string('coordinates')->nullable();

            $table->text('schedule')->nullable();

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
                'pharmacy_id',
                'stock_count'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_pharmacy_cache');
    }
};
