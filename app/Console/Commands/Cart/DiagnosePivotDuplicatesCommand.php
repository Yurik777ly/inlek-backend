<?php

namespace App\Console\Commands\Cart;

use App\Services\Cart\CartDuplicateService;
use Illuminate\Console\Command;

class DiagnosePivotDuplicatesCommand extends Command
{
    protected $signature = 'cart:diagnose-pivots';

    protected $description = 'Показать повторяющиеся строки cart_id + evo_site_content_id';

    public function handle(CartDuplicateService $duplicates): int
    {
        $groups = $duplicates->diagnosePivots();

        if ($groups === []) {
            $this->info('Дублей строк корзины не найдено.');

            return self::SUCCESS;
        }

        foreach ($groups as $group) {
            $this->warn(sprintf(
                'cart_id=%d product_id=%d строк=%d оставить id=%d удалить=[%s]',
                $group['cart_id'],
                $group['evo_site_content_id'],
                $group['row_count'],
                $group['keep_id'],
                implode(',', $group['delete_ids'])
            ));

            $this->table(
                ['id', 'quantity'],
                array_map(static fn (array $line) => [$line['id'], $line['quantity']], $group['lines'])
            );
        }

        $this->line('Очистка оставляет строку с максимальным id. quantity не суммируется.');

        return self::SUCCESS;
    }
}
