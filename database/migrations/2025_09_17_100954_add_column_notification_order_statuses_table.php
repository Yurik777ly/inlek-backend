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
        Schema::table('evo_commerce_order_statuses', function (Blueprint $table) {
            $table->text('notification_body')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evo_commerce_order_statuses', function (Blueprint $table) {
            $table->dropColumn('notification_body');
        });
    }
};
