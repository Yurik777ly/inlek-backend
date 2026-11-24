<?php

use App\Services\Cart\CartUniqueIndexGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Уникальный индекс cart_evo_site_content (cart_id, evo_site_content_id).
 *
 * Перед запуском:
 * 1. php artisan cart:diagnose-pivots
 * 2. php artisan cart:cleanup-pivots
 *    без --apply показывает, какая строка будет оставлена;
 * 3. php artisan cart:cleanup-pivots --apply
 *    оставляет строку с максимальным id и удаляет остальные.
 *    quantity не суммируется и MAX(quantity) не используется.
 *
 * Если дубли ещё есть, миграция завершится ошибкой и не удалит строки.
 * Представление evo_carts_detailed группирует дубли через MAX(quantity).
 * После очистки видимое количество может стать меньше. Это ожидаемо.
 */
return new class extends Migration
{
    public function up(): void
    {
        CartUniqueIndexGuard::assertNoDuplicatePivotRows();

        Schema::table('cart_evo_site_content', function (Blueprint $table) {
            $table->unique(
                ['cart_id', 'evo_site_content_id'],
                'cart_evo_site_content_cart_product_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('cart_evo_site_content', function (Blueprint $table) {
            $table->dropUnique('cart_evo_site_content_cart_product_unique');
        });
    }
};
