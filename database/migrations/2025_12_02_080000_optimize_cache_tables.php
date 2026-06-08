<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_cache', function (Blueprint $table) {
            if (!Schema::hasColumn('product_cache', 'product_charachters_json')) {
                $table->longText('product_charachters_json')->nullable()->after('delivery');
            }
        });

        Schema::table('product_promocode_json_cache', function (Blueprint $table) {
            if (!$this->indexExists('product_promocode_json_cache', 'product_promocode_json_cache_updated_at_index')) {
                $table->index('updated_at');
            }
        });

        Schema::table('product_pharmacy_cache', function (Blueprint $table) {
            if ($this->indexExists('product_pharmacy_cache', 'product_pharmacy_cache_product_id_index')) {
                $table->dropIndex('product_pharmacy_cache_product_id_index');
            }

            if ($this->indexExists('product_pharmacy_cache', 'product_pharmacy_cache_stock_count_index')) {
                $table->dropIndex('product_pharmacy_cache_stock_count_index');
            }
        });

        Schema::table('product_category_cache', function (Blueprint $table) {
            if (!$this->indexExists('product_category_cache', 'product_category_cache_category_id_index')) {
                $table->index('category_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_cache', function (Blueprint $table) {
            if (Schema::hasColumn('product_cache', 'product_charachters_json')) {
                $table->dropColumn('product_charachters_json');
            }
        });

        Schema::table('product_promocode_json_cache', function (Blueprint $table) {
            if ($this->indexExists('product_promocode_json_cache', 'product_promocode_json_cache_updated_at_index')) {
                $table->dropIndex('product_promocode_json_cache_updated_at_index');
            }
        });

        Schema::table('product_pharmacy_cache', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('stock_count');
        });

        Schema::table('product_category_cache', function (Blueprint $table) {
            if ($this->indexExists('product_category_cache', 'product_category_cache_category_id_index')) {
                $table->dropIndex('product_category_cache_category_id_index');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $index]
        );

        return !empty($result);
    }
};
