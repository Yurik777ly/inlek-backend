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

            if (!Schema::hasColumn('carts', 'pharmacy_id')) {
                $table->integer('pharmacy_id')->nullable();
            }

            if (!Schema::hasColumn('carts', 'promocodes')) {
                $table->string('promocodes', 400)->nullable();
            }

            if (!Schema::hasColumn('carts', 'delivery_zone')) {
                $table->string('delivery_zone', 100)->nullable();
            }

            if (!Schema::hasColumn('carts', 'geo_lat')) {
                $table->string('geo_lat', 20)->nullable();
            }

            if (!Schema::hasColumn('carts', 'geo_long')) {
                $table->string('geo_long', 20)->nullable();
            }

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
