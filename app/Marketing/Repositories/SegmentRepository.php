<?php

namespace App\Marketing\Repositories;

use App\Marketing\Repositories\Contracts\SegmentRepositoryInterface;
use Illuminate\Support\Facades\DB;

class SegmentRepository implements SegmentRepositoryInterface
{
    /**
     * Кэш external_id => id
     */
    protected array $cache = [];

    /**
     * Кэш товаров сегментов.
     *
     * segment_id => product_id[]
     */
    protected array $productsCache = [];

    public function findIdByExternalId(string $externalId): ?int
    {
        $externalId = trim($externalId);

        if ($externalId === '') {
            return null;
        }

        if (array_key_exists($externalId, $this->cache)) {
            return $this->cache[$externalId];
        }

        $id = DB::table('marketing_segments')
            ->where('external_id', $externalId)
            ->value('id');

        $this->cache[$externalId] = $id
            ? (int) $id
            : null;

        return $this->cache[$externalId];
    }

    public function exists(string $externalId): bool
    {
        return $this->findIdByExternalId($externalId) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function preload(array $externalIds): void
    {
        $externalIds = array_unique(
            array_filter(
                array_map('trim', $externalIds)
            )
        );

        if (empty($externalIds)) {
            return;
        }

        $notLoaded = array_diff(
            $externalIds,
            array_keys($this->cache)
        );

        if (empty($notLoaded)) {
            return;
        }

        $rows = DB::table('marketing_segments')
            ->select('external_id', 'id')
            ->whereIn('external_id', $notLoaded)
            ->get();

        foreach ($rows as $row) {
            $this->cache[$row->external_id] = (int)$row->id;
        }

        foreach ($notLoaded as $externalId) {
            $this->cache[$externalId] ??= null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache(): void
    {
        $this->cache = [];

        $this->productsCache = [];
    }

    /**
     * Загрузить товары из MarketingEvent.
     */
    public function preloadFromMarketingEvent(array $event): void
    {
        $ids = [];

        foreach ($event['goods'] ?? [] as $id) {
            $ids[] = $id;
        }

        foreach ($event['conditions_and_values'] ?? [] as $row) {
            if (!empty($row['id_odject'])) {
                $ids[] = $row['id_odject'];
            }
        }

        $this->preload($ids);
    }

    public function requireIdByExternalId(string $externalId): int
    {
        $id = $this->findIdByExternalId($externalId);

        if ($id === null) {
            throw new \RuntimeException(
                sprintf('Сегмент с внешним кодом "%s" не найден.', $externalId)
            );
        }

        return $id;
    }

    /**
     * Возвращает товары сегмента.
     *
     * @return int[]
     */
    public function getProductIdsByExternalId(
        string $externalId
    ): array {

        $segmentId = $this->findIdByExternalId(
            $externalId
        );

        if ($segmentId === null) {
            return [];
        }

        return $this->getProductIdsById($segmentId);
    }

    /**
     * Возвращает товары сегмента по внутреннему ID.
     *
     * @return int[]
     */
    public function getProductIdsById(int $segmentId): array
    {
        if (isset($this->productsCache[$segmentId])) {
            return $this->productsCache[$segmentId];
        }

        $productIds = DB::table('marketing_segment_products')
            ->where('segment_id', $segmentId)
            ->pluck('product_id')
            ->map(fn ($id) => (int)$id)
            ->all();

        $this->productsCache[$segmentId] = $productIds;

        return $productIds;
    }

    /**
     * Проверяет, входит ли товар в сегмент.
     */
    public function containsProduct(
        string $externalId,
        int $productId
    ): bool {

        return in_array(
            $productId,
            $this->getProductIdsByExternalId($externalId),
            true
        );
    }

    /**
     * Проверяет, входит ли товар в сегмент по внутреннему ID.
     */
    public function containsProductById(
        int $segmentId,
        int $productId
    ): bool {

        return in_array(
            $productId,
            $this->getProductIdsById($segmentId),
            true
        );
    }
}
