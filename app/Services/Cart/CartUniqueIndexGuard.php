<?php

namespace App\Services\Cart;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class CartUniqueIndexGuard
{
    public static function assertNoDuplicateParentCarts(): void
    {
        $userIds = DB::table('carts')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return;
        }

        throw new RuntimeException(
            'Нельзя добавить уникальный индекс carts.user_id: у пользователей есть несколько корзин ('
            . $userIds->implode(', ')
            . '). Сначала выполните `php artisan cart:diagnose-parents`. '
            . 'Пустые дубли можно убрать командой `php artisan cart:cleanup-empty-parents --apply`. '
            . 'Корзины с товарами эта команда не удаляет и не объединяет. Миграция данные не удаляет.'
        );
    }

    public static function assertNoDuplicatePivotRows(): void
    {
        $groups = DB::table('cart_evo_site_content')
            ->select('cart_id', 'evo_site_content_id')
            ->groupBy('cart_id', 'evo_site_content_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($groups->isEmpty()) {
            return;
        }

        $sample = $groups->take(20)->map(function ($row) {
            return $row->cart_id . ':' . $row->evo_site_content_id;
        })->implode(', ');

        throw new RuntimeException(
            'Нельзя добавить уникальный индекс cart_evo_site_content (cart_id, evo_site_content_id): '
            . 'найдены повторяющиеся строки (' . $groups->count() . '; примеры cart_id:product_id — ' . $sample . '). '
            . 'Сначала выполните `php artisan cart:diagnose-pivots`, затем `php artisan cart:cleanup-pivots --apply`. '
            . 'Очистка оставляет строку с максимальным id и не суммирует quantity. Миграция данные не удаляет.'
        );
    }
}
