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
        Schema::create('product_promocode_cache', function (Blueprint $table) {

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('promocode_id');

            $table->string('promocode');

            $table->decimal('discount', 12, 2)->nullable();

            $table->dateTime('begin_date')->nullable();
            $table->dateTime('end_date')->nullable();

            $table->primary([
                'product_id',
                'promocode_id'
            ]);

            $table->index('product_id');

            $table->index([
                'begin_date',
                'end_date'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_promocode_cache');
    }
};
