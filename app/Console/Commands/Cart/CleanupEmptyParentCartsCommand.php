<?php

namespace App\Console\Commands\Cart;

use App\Services\Cart\CartDuplicateService;
use Illuminate\Console\Command;

class CleanupEmptyParentCartsCommand extends Command
{
    protected $signature = 'cart:cleanup-empty-parents {--apply : Удалить пустые дубли. Без флага только отчёт}';

    protected $description = 'Контролируемо удалить пустые дублирующиеся корзины';

    public function handle(CartDuplicateService $duplicates): int
    {
        $apply = (bool) $this->option('apply');
        $rows = $duplicates->cleanupEmptyParents($apply);

        if ($rows === []) {
            $this->info('Пустых дублей родительских корзин не найдено.');

            return self::SUCCESS;
        }

        if (!$apply) {
            $this->comment('Режим просмотра. Данные не изменены. Для удаления добавьте --apply.');
        }

        foreach ($rows as $row) {
            $deleted = $row['deleted'] ?? $row['delete_ids'] ?? [];
            $this->line(sprintf(
                'user_id=%d %s удалить=[%s] оставлено=[%s]%s',
                $row['user_id'],
                $apply ? 'выполнено' : 'план',
                implode(',', $deleted) ?: '—',
                isset($row['kept']) ? (implode(',', $row['kept']) ?: '—') : 'см. диагностику',
                !empty($row['canonical_changed']) ? ' выбранная корзина сменилась, потому что пустая корзина с наименьшим id удалена' : ''
            ));

            foreach ($row['skipped'] ?? [] as $skipped) {
                $id = isset($skipped['id']) ? ' id=' . $skipped['id'] : '';
                $this->warn('пропущено' . $id . ': ' . ($skipped['reason'] ?? ''));
            }
        }

        if ($apply) {
            $this->info('Корзины с товарами не удалялись и не объединялись.');
        }

        return self::SUCCESS;
    }
}
