<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_status_changes', function (Blueprint $table) {
            if (!Schema::hasColumn('order_status_changes', 'history_id')) {
                $table->unsignedBigInteger('history_id')->nullable()->unique()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_status_changes', function (Blueprint $table) {
            if (Schema::hasColumn('order_status_changes', 'history_id')) {
                $table->dropUnique(['history_id']);
                $table->dropColumn('history_id');
            }
        });
    }
};
