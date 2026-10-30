<?php

namespace App\Marketing\Repositories\Contracts;

interface SegmentRepositoryInterface
{
    public function findIdByExternalId(string $externalId): ?int;

    public function exists(string $externalId): bool;

    /**
     * @param string[] $externalIds
     */
    public function preload(array $externalIds): void;

    public function clearCache(): void;

    public function requireIdByExternalId(string $externalId): int;

    /**
     * Возвращает товары сегмента.
     *
     * @return int[]
     */
    public function getProductIdsByExternalId(
        string $externalId
    ): array;

    /**
     * Возвращает товары сегмента по внутреннему ID.
     *
     * @return int[]
     */
    public function getProductIdsById(int $segmentId): array;

    /**
     * Проверяет принадлежность товара сегменту.
     */
    public function containsProduct(
        string $externalId,
        int $productId
    ): bool;

    /**
     * Проверяет принадлежность товара сегменту по внутреннему ID.
     */
    public function containsProductById(
        int $segmentId,
        int $productId
    ): bool;
}
