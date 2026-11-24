<?php

namespace App\Console\Commands\Cart;

use App\Services\Cart\CartDuplicateService;
use Illuminate\Console\Command;

class CleanupPivotDuplicatesCommand extends Command
{
    protected $signature = 'cart:cleanup-pivots {--apply : Удалить дубли строк, оставив максимальный id. Без флага только отчёт}';

    protected $description = 'Контролируемо удалить дублирующиеся строки товаров в корзине';

    public function handle(CartDuplicateService $duplicates): int
    {
        $apply = (bool) $this->option('apply');
        $result = $duplicates->cleanupPivotDuplicates($apply);

        if (!$apply) {
            $groups = $result['groups'] ?? [];
            if ($groups === []) {
                $this->info('Дублей строк корзины не найдено.');

                return self::SUCCESS;
            }

            $this->comment('Режим просмотра. Данные не изменены. Для удаления добавьте --apply.');
            foreach ($groups as $group) {
                $this->line(sprintf(
                    'cart_id=%d product_id=%d оставить id=%d quantity=%s удалить=[%s]',
                    $group['cart_id'],
                    $group['evo_site_content_id'],
                    $group['keep_id'],
                    $this->quantityOf($group['lines'], $group['keep_id']),
                    implode(',', $group['delete_ids'])
                ));
            }

            return self::SUCCESS;
        }

        $carts = $result['carts'] ?? [];
        if ($carts === []) {
            $this->info('Дублей строк корзины не найдено.');

            return self::SUCCESS;
        }

        foreach ($carts as $cart) {
            if (!empty($cart['skipped'])) {
                $this->warn(sprintf('cart_id=%d пропущено: %s', $cart['cart_id'], $cart['skipped']));
                continue;
            }

            foreach ($cart['kept'] as $kept) {
                $this->info(sprintf(
                    'cart_id=%d product_id=%d оставлена строка id=%d quantity=%d',
                    $cart['cart_id'],
                    $kept['evo_site_content_id'],
                    $kept['id'],
                    $kept['quantity']
                ));
            }

            foreach ($cart['deleted'] as $deleted) {
                $this->line(sprintf(
                    'cart_id=%d product_id=%d удалена строка id=%d quantity=%d',
                    $cart['cart_id'],
                    $deleted['evo_site_content_id'],
                    $deleted['id'],
                    $deleted['quantity']
                ));
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param list<array{id: int, quantity: int}> $lines
     */
    private function quantityOf(array $lines, int $id): string
    {
        foreach ($lines as $line) {
            if ($line['id'] === $id) {
                return (string) $line['quantity'];
            }
        }

        return '—';
    }
}
