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
        Schema::create('cart_evo_site_content', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_id');
            $table->unsignedInteger('evo_site_content_id');
            $table->unsignedTinyInteger('quantity');
            $table->timestamps();

            $table->foreign('cart_id')
                ->references('id')
                ->on('carts')
                ->cascadeOnDelete();

            $table->foreign('evo_site_content_id')
                ->references('id')
                ->on('evo_site_content')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('basket_product');
    }
};
