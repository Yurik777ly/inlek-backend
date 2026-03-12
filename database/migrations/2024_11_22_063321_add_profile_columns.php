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
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable();
            }
            if (!Schema::hasColumn('users', 'gender')) {
                $table->string('gender')->default('male');
            }
            if (!Schema::hasColumn('users', 'birthday')) {
                $table->string('birthday')->nullable();
            }
            if (!Schema::hasColumn('users', 'status_notifications')) {
                $table->boolean('status_notifications')->default(false);
            }
            if (!Schema::hasColumn('users', 'accept_policy')) {
                $table->boolean('accept_policy')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'first_name')) {
                $table->dropColumn('first_name');
            }
            if (Schema::hasColumn('users', 'last_name')) {
                $table->dropColumn('last_name');
            }
            if (Schema::hasColumn('users', 'gender')) {
                $table->dropColumn('gender');
            }
            if (Schema::hasColumn('users', 'birthday')) {
                $table->dropColumn('birthday');
            }
            if (Schema::hasColumn('users', 'status_notifications')) {
                $table->dropColumn('status_notifications');
            }
            if (Schema::hasColumn('users', 'accept_policy')) {
                $table->dropColumn('accept_policy');
            }
        });
    }
};
