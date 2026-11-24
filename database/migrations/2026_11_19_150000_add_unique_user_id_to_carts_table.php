<?php

use App\Services\Cart\CartUniqueIndexGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Уникальный индекс carts.user_id.
 *
 * Перед запуском на данных с возможными дублями:
 * 1. php artisan cart:diagnose-parents
 * 2. php artisan cart:cleanup-empty-parents
 *    без --apply показывает план и ничего не удаляет;
 * 3. php artisan cart:cleanup-empty-parents --apply
 *    удаляет только пустые лишние корзины.
 *
 * Корзины с товарами команда не удаляет и не объединяет.
 * Если после очистки дубли с товарами остались, миграция завершится ошибкой
 * и не изменит данные. Индекс в этом случае добавлять нельзя, пока такие
 * корзины не разобраны отдельно.
 */
return new class extends Migration
{
    public function up(): void
    {
        CartUniqueIndexGuard::assertNoDuplicateParentCarts();

        Schema::table('carts', function (Blueprint $table) {
            $table->unique('user_id', 'carts_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropUnique('carts_user_id_unique');
        });
    }
};
