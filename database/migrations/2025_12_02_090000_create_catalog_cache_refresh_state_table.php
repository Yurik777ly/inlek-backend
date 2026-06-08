<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_cache_refresh_state', function (Blueprint $table) {
            $table->string('step', 64)->primary();
            $table->timestamp('refreshed_at');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_cache_refresh_state');
    }
};
