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
        Schema::create('product_action_json_cache', function (Blueprint $table) {

            $table->unsignedBigInteger('product_id');

            $table->longText('action_json')->nullable();

            $table->timestamp('updated_at')->nullable();

            $table->primary('product_id');

            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_action_json_cache');
    }
};
