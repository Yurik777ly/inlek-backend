<?php

namespace App\Console\Commands\Catalog;

use App\Services\Catalog\CatalogCacheRefresher;
use Illuminate\Console\Command;

class RefreshCatalogCacheCommand extends Command
{
    protected $signature = 'catalog:refresh-cache
                            {--only=* : Шаги: pharmacies, products, offers, product_pharmacies, categories, category_json, promocodes, actions, relations, product_characters}
                            {--incremental : Инкрементально обновить offers и promocodes (остальные шаги — полностью)}
                            {--no-truncate : Не очищать таблицы перед вставкой (только для полного режима)}';

    protected $description = 'Наполнить кэш-таблицы каталога из MODX/1С (запускать после обмена с 1С)';

    public function handle(CatalogCacheRefresher $refresher): int
    {
        $only = $this->option('only');
        $only = is_array($only) && $only !== [] ? $only : null;

        if ($only !== null) {
            $unknown = array_diff($only, $this->availableSteps());

            if ($unknown !== []) {
                $this->error('Неизвестные шаги: ' . implode(', ', $unknown));
                $this->line('Доступные: ' . implode(', ', $this->availableSteps()));

                return self::FAILURE;
            }
        }

        $incremental = (bool) $this->option('incremental');
        $truncate = !$this->option('no-truncate');

        if ($incremental && $this->option('no-truncate')) {
            $this->warn('Флаг --no-truncate игнорируется в режиме --incremental.');
        }

        $mode = $incremental ? 'инкрементально' : 'полностью';
        $scope = $only ? implode(', ', $only) : 'полный цикл';
        $this->info("Обновление кэша каталога ({$mode}, {$scope})...");
        $this->newLine();

        $started = microtime(true);
        $counts = $refresher->refresh(
            $only,
            $truncate,
            $incremental,
            fn (string $message) => $this->line($message),
        );
        $elapsed = round(microtime(true) - $started, 2);

        $this->newLine();

        $rows = [];

        foreach ($counts as $step => $count) {
            $rows[] = [$step, $count];
        }

        $this->table(['Шаг', 'Затронуто строк'], $rows);
        $this->info("Готово за {$elapsed} с.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function availableSteps(): array
    {
        return [
            'pharmacies',
            'products',
            'offers',
            'product_pharmacies',
            'categories',
            'category_json',
            'promocodes',
            'actions',
            'relations',
            'product_characters',
        ];
    }
}
