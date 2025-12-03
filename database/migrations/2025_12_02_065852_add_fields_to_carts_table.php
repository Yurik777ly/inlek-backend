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
        Schema::table('carts', function (Blueprint $table) {
            $table->integer('pharmacy_id')->nullable();
            $table->string('promocodes',400)->nullable();
            $table->string('delivery_zone',100)->nullable();
            $table->string('geo_lat',20)->nullable();
            $table->string('geo_long',20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn([
                'pharmacy_id', 
                'promocodes', 
                'delivery_zone', 
                'geo_lat',
                'geo_long']);
        });
    }
};
