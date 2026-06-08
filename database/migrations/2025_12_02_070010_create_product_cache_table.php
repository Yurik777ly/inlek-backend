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
        Schema::create('product_cache', function (Blueprint $table) {

            $table->unsignedBigInteger('product_id')->primary();

            $table->string('pagetitle')->nullable();
            $table->string('alias')->nullable();

            $table->longText('content')->nullable();
            $table->string('menutitle')->nullable();

            $table->unsignedBigInteger('parent')->nullable();

            $table->timestamp('create_dttm')->nullable();
            $table->timestamp('published_dttm')->nullable();
            $table->timestamp('edited_dttm')->nullable();

            $table->boolean('published')->default(true);

            $table->longText('product_description')->nullable();
            $table->longText('instruction')->nullable();

            $table->string('mnn')->nullable();
            $table->string('mnn_lat')->nullable();
            $table->string('code')->nullable();

            $table->string('brand')->nullable();
            $table->string('country')->nullable();

            $table->string('form')->nullable();
            $table->string('release_form')->nullable();

            $table->string('termin')->nullable();
            $table->string('temperature')->nullable();

            $table->string('image')->nullable();

            $table->string('dose')->nullable();

            $table->string('recipe')->nullable();

            $table->boolean('is_recipe')->default(false);

            $table->string('product_insert')->nullable();
            $table->string('product_time_register')->nullable();
            $table->string('product_register')->nullable();
            $table->string('product_date_register')->nullable();
            $table->string('product_trademark')->nullable();

            $table->decimal('product_price_from', 10, 2)->nullable();
            $table->decimal('product_price_from_old', 10, 2)->nullable();

            $table->integer('product_price_from_percent')->nullable();

            $table->string('product_sticker')->nullable();

            $table->boolean('is_alcohol')->default(false);

            $table->string('delivery')->nullable();

            $table->longText('product_charachters_json')->nullable();

            $table->boolean('is_available')->default(false);

            $table->integer('pub_date')->nullable();

            $table->integer('create_dttm_raw')->nullable();

            $table->integer('published_dttm_raw')->nullable();
            $table->integer('edited_dttm_raw')->nullable();

            $table->timestamps();

            $table->index('parent');
            $table->index('published');

            $table->index('brand');
            $table->index('country');

            $table->index('form');
            $table->index('release_form');

            $table->index('is_recipe');
            $table->index('is_available');

            $table->index('product_price_from');

            $table->index([
                'is_available',
                'product_price_from'
            ], 'idx_available_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_cache');
    }
};
