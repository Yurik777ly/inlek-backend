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
        Schema::create('actions_notifications', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('action_id')->nullable();
        $table->integer( 'pub_date')->nullable();
        $table->smallInteger('sent');
        $table->unique('action_id');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actions_notifications');
    }
};
