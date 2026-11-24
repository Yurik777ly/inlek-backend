<?php

namespace App\Console\Commands\Cart;

use App\Services\Cart\CartDuplicateService;
use Illuminate\Console\Command;

class DiagnoseParentCartsCommand extends Command
{
    protected $signature = 'cart:diagnose-parents';

    protected $description = 'Показать пользователей с несколькими корзинами';

    public function handle(CartDuplicateService $duplicates): int
    {
        $rows = $duplicates->diagnoseParents();

        if ($rows === []) {
            $this->info('Дублей родительских корзин не найдено.');

            return self::SUCCESS;
        }

        foreach ($rows as $user) {
            $this->warn(sprintf(
                'user_id=%d корзин=%d пустые=[%s] с товарами=[%s] настройки различаются=%s',
                $user['user_id'],
                $user['cart_count'],
                implode(',', $user['empty_cart_ids']) ?: '—',
                implode(',', $user['carts_with_products']) ?: '—',
                $user['settings_differ'] ? 'да' : 'нет'
            ));

            $this->table(
                ['id', 'product_rows', 'created_at', 'updated_at', 'promo', 'pharmacy_id', 'promocodes', 'delivery_zone', 'geo_lat', 'geo_long', 'empty'],
                array_map(static fn (array $cart) => [
                    $cart['id'],
                    $cart['product_rows'],
                    $cart['created_at'],
                    $cart['updated_at'],
                    $cart['promo'],
                    $cart['pharmacy_id'],
                    $cart['promocodes'],
                    $cart['delivery_zone'],
                    $cart['geo_lat'],
                    $cart['geo_long'],
                    $cart['empty'] ? 'да' : 'нет',
                ], $user['carts'])
            );
        }

        $this->line('Корзины с товарами автоматически не удаляются и не объединяются.');

        return self::SUCCESS;
    }
}
