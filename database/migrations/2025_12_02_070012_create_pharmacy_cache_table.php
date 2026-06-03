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
        Schema::create('pharmacy_cache', function (Blueprint $table) {

            $table->unsignedBigInteger('pharmacy_id')->primary();

            $table->string('pagetitle')->nullable();
            $table->string('alias')->nullable();

            $table->longText('content')->nullable();

            $table->string('address')->nullable();

            $table->string('coordinates')->nullable();

            $table->string('image', 1000)->nullable();

            $table->longText('schedule')->nullable();

            $table->boolean('published')->default(true);

            $table->unsignedInteger('create_dttm_raw')->nullable();
            $table->string('create_dttm')->nullable();

            $table->unsignedInteger('published_dttm_raw')->nullable();
            $table->string('published_dttm')->nullable();

            $table->unsignedInteger('edited_dttm_raw')->nullable();
            $table->string('edited_dttm')->nullable();

            $table->index('published');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pharmacy_cache');
    }
};
