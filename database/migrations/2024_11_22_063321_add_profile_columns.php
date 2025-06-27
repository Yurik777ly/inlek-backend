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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('gender')->default('male');
            $table->string('birthday')->nullable();
            $table->boolean('status_notifications')->default(false);
            $table->boolean('accept_policy')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('first_name');
            $table->dropColumn('last_name');
            $table->dropColumn('gender');
            $table->dropColumn('birthday');
            $table->dropColumn('status_notifications');
            $table->dropColumn('accept_policy');
        });
    }
};
