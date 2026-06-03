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
        Schema::create('product_action_cache', function (Blueprint $table) {

            $table->unsignedBigInteger('product_id');

            $table->unsignedBigInteger('promotion_id');

            $table->string('promotion_text')->nullable();

            $table->boolean('promotion_flg')->default(true);

            $table->boolean('published')->default(true);

            $table->integer('create_dttm_raw')->nullable();
            $table->integer('edited_dttm_raw')->nullable();
            $table->integer('published_dttm_raw')->nullable();

            $table->dateTime('create_dttm')->nullable();
            $table->dateTime('edited_dttm')->nullable();
            $table->dateTime('published_dttm')->nullable();

            $table->primary([
                'product_id',
                'promotion_id'
            ]);

            $table->index('product_id');
            $table->index('promotion_id');
            $table->index('published');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_action_cache');
    }
};
