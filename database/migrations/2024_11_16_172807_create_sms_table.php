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
        if (!Schema::hasTable('sms')) {
            Schema::create('sms', function (Blueprint $table) {
                $table->id();
                $table->string('phone');
                $table->timestamp('last_sms_requested_at')->nullable();
                $table->integer('sms_requested_qty')->default(0);
                $table->integer('code');

                //$table->foreign('phone')->references('phone')->on('users');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms');
    }
};
