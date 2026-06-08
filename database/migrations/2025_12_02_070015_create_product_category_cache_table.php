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
        Schema::create('product_category_cache', function (Blueprint $table) {

            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('product_id');

            $table->string('category_name')->nullable();
            $table->string('category_image')->nullable();

            $table->primary([
                'category_id',
                'product_id'
            ]);

            $table->index('product_id');
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_category_cache');
    }
};
