<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CartDuplicateService
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    /**
     * Пользователи, у которых больше одной корзины.
     *
     * @return list<array<string, mixed>>
     */
    public function diagnoseParents(): array
    {
        $carts = DB::table('carts')->orderBy('user_id')->orderBy('id')->get();
        $counts = DB::table('cart_evo_site_content')
            ->select('cart_id', DB::raw('COUNT(*) as product_rows'))
            ->groupBy('cart_id')
            ->pluck('product_rows', 'cart_id');

        $byUser = [];
        foreach ($carts as $cart) {
            $byUser[$cart->user_id][] = $cart;
        }

        $report = [];
        foreach ($byUser as $userId => $userCarts) {
            if (count($userCarts) < 2) {
                continue;
            }

            $rows = [];
            $signatures = [];
            $emptyIds = [];
            $withProducts = [];

            foreach ($userCarts as $cart) {
                $productRows = (int) ($counts[$cart->id] ?? 0);
                $signatures[$this->settingsSignature($cart)] = true;

                if ($productRows === 0) {
                    $emptyIds[] = (int) $cart->id;
                } else {
                    $withProducts[] = (int) $cart->id;
                }

                $rows[] = [
                    'id' => (int) $cart->id,
                    'product_rows' => $productRows,
                    'created_at' => $cart->created_at,
                    'updated_at' => $cart->updated_at,
                    'promo' => $cart->promo,
                    'pharmacy_id' => $cart->pharmacy_id,
                    'promocodes' => $cart->promocodes,
                    'delivery_zone' => $cart->delivery_zone,
                    'geo_lat' => $cart->geo_lat,
                    'geo_long' => $cart->geo_long,
                    'empty' => $productRows === 0,
                ];
            }

            $report[] = [
                'user_id' => (int) $userId,
                'cart_count' => count($userCarts),
                'empty_cart_ids' => $emptyIds,
                'carts_with_products' => $withProducts,
                'settings_differ' => count($signatures) > 1,
                'carts' => $rows,
            ];
        }

        return $report;
    }

    /**
     * Удаляет только пустые лишние корзины.
     * Без $apply данные не меняются.
     * Перед удалением блокируется пользователь, затем его корзины по возрастанию id.
     *
     * @return list<array<string, mixed>>
     */
    public function cleanupEmptyParents(bool $apply): array
    {
        $diagnosis = $this->diagnoseParents();

        if (!$apply) {
            return array_map(function (array $user) {
                return [
                    'user_id' => $user['user_id'],
                    'applied' => false,
                    'delete_ids' => $this->planEmptyDeletes($user['empty_cart_ids'], $user['carts_with_products']),
                    'kept_with_products' => $user['carts_with_products'],
                    'settings_differ' => $user['settings_differ'],
                ];
            }, $diagnosis);
        }

        $results = [];
        foreach ($diagnosis as $user) {
            $results[] = $this->deleteEmptyDuplicatesForUser($user['user_id']);
        }

        return $results;
    }

    /**
     * Повторяющиеся пары cart_id + evo_site_content_id.
     * К удалению помечаются все строки, кроме строки с максимальным id.
     *
     * @return list<array<string, mixed>>
     */
    public function diagnosePivots(): array
    {
        return $this->pivotDuplicateGroups();
    }

    /**
     * @return array{applied: bool, groups?: list<array<string, mixed>>, carts?: list<array<string, mixed>>}
     */
    public function cleanupPivotDuplicates(bool $apply): array
    {
        $groups = $this->diagnosePivots();

        if (!$apply) {
            return [
                'applied' => false,
                'groups' => $groups,
            ];
        }

        $cartIds = array_values(array_unique(array_map(
            static fn (array $group) => $group['cart_id'],
            $groups
        )));

        $carts = [];
        foreach ($cartIds as $cartId) {
            try {
                $carts[] = $this->cartService->modifyLockedCart($cartId, function (Cart $cart) {
                    return $this->deletePivotDuplicatesOnLockedCart((int) $cart->id);
                });
            } catch (ModelNotFoundException) {
                $carts[] = [
                    'cart_id' => $cartId,
                    'kept' => [],
                    'deleted' => [],
                    'skipped' => 'корзина не найдена',
                ];
            }
        }

        return [
            'applied' => true,
            'carts' => $carts,
        ];
    }

    /**
     * @param list<int> $emptyIds
     * @param list<int> $withProducts
     * @return list<int>
     */
    private function planEmptyDeletes(array $emptyIds, array $withProducts): array
    {
        sort($emptyIds);

        if ($emptyIds === []) {
            return [];
        }

        if ($withProducts === []) {
            array_shift($emptyIds);
        }

        return array_values($emptyIds);
    }

    /**
     * @return array<string, mixed>
     */
    private function deleteEmptyDuplicatesForUser(int $userId): array
    {
        return DB::transaction(function () use ($userId) {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            if (!$user) {
                return [
                    'user_id' => $userId,
                    'applied' => true,
                    'deleted' => [],
                    'kept' => [],
                    'skipped' => [['reason' => 'пользователь не найден']],
                    'canonical_changed' => false,
                ];
            }

            $carts = Cart::query()
                ->where('user_id', $userId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $lowestId = $carts->isEmpty() ? null : (int) $carts->first()->id;

            if ($carts->count() <= 1) {
                return [
                    'user_id' => $userId,
                    'applied' => true,
                    'deleted' => [],
                    'kept' => $carts->pluck('id')->map(static fn ($id) => (int) $id)->all(),
                    'skipped' => [['reason' => 'единственная корзина не удаляется']],
                    'canonical_changed' => false,
                ];
            }

            $empty = [];
            $nonEmpty = [];
            foreach ($carts as $cart) {
                $rows = (int) DB::table('cart_evo_site_content')->where('cart_id', $cart->id)->count();
                if ($rows === 0) {
                    $empty[] = (int) $cart->id;
                } else {
                    $nonEmpty[] = (int) $cart->id;
                }
            }

            $deleted = [];
            $skipped = [];

            foreach ($this->planEmptyDeletes($empty, $nonEmpty) as $cartId) {
                $rows = (int) DB::table('cart_evo_site_content')->where('cart_id', $cartId)->count();
                if ($rows > 0) {
                    $skipped[] = ['id' => $cartId, 'reason' => 'в корзине появились товары'];
                    continue;
                }

                $remaining = (int) Cart::query()->where('user_id', $userId)->count();
                if ($remaining <= 1) {
                    $skipped[] = ['id' => $cartId, 'reason' => 'нельзя удалить единственную корзину'];
                    continue;
                }

                Cart::query()->whereKey($cartId)->where('user_id', $userId)->delete();
                $deleted[] = $cartId;
            }

            $kept = Cart::query()
                ->where('user_id', $userId)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            return [
                'user_id' => $userId,
                'applied' => true,
                'deleted' => $deleted,
                'kept' => $kept,
                'skipped' => $skipped,
                'canonical_changed' => $lowestId !== null && !in_array($lowestId, $kept, true),
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pivotDuplicateGroups(?int $cartId = null): array
    {
        $query = DB::table('cart_evo_site_content')
            ->orderBy('cart_id')
            ->orderBy('evo_site_content_id')
            ->orderBy('id');

        if ($cartId !== null) {
            $query->where('cart_id', $cartId);
        }

        $grouped = [];
        foreach ($query->get(['id', 'cart_id', 'evo_site_content_id', 'quantity']) as $row) {
            $key = $row->cart_id . ':' . $row->evo_site_content_id;
            $grouped[$key][] = $row;
        }

        $result = [];
        foreach ($grouped as $lines) {
            if (count($lines) < 2) {
                continue;
            }

            $normalized = array_map(static fn ($line) => [
                'id' => (int) $line->id,
                'quantity' => (int) $line->quantity,
            ], $lines);
            $ids = array_column($normalized, 'id');
            $keepId = max($ids);

            $result[] = [
                'cart_id' => (int) $lines[0]->cart_id,
                'evo_site_content_id' => (int) $lines[0]->evo_site_content_id,
                'row_count' => count($normalized),
                'lines' => $normalized,
                'keep_id' => $keepId,
                'delete_ids' => array_values(array_filter($ids, static fn (int $id) => $id !== $keepId)),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function deletePivotDuplicatesOnLockedCart(int $cartId): array
    {
        $groups = $this->pivotDuplicateGroups($cartId);
        $kept = [];
        $deleted = [];

        foreach ($groups as $group) {
            if ($group['delete_ids'] === []) {
                continue;
            }

            DB::table('cart_evo_site_content')->whereIn('id', $group['delete_ids'])->delete();

            $kept[] = [
                'evo_site_content_id' => $group['evo_site_content_id'],
                'id' => $group['keep_id'],
                'quantity' => $this->quantityOf($group['lines'], $group['keep_id']),
            ];

            foreach ($group['lines'] as $line) {
                if ($line['id'] === $group['keep_id']) {
                    continue;
                }
                $deleted[] = [
                    'evo_site_content_id' => $group['evo_site_content_id'],
                    'id' => $line['id'],
                    'quantity' => $line['quantity'],
                ];
            }
        }

        return [
            'cart_id' => $cartId,
            'kept' => $kept,
            'deleted' => $deleted,
        ];
    }

    /**
     * @param list<array{id: int, quantity: int}> $lines
     */
    private function quantityOf(array $lines, int $id): int
    {
        foreach ($lines as $line) {
            if ($line['id'] === $id) {
                return $line['quantity'];
            }
        }

        return 0;
    }

    private function settingsSignature(object $cart): string
    {
        return json_encode([
            'promo' => (string) ($cart->promo ?? ''),
            'pharmacy_id' => (string) ($cart->pharmacy_id ?? ''),
            'promocodes' => (string) ($cart->promocodes ?? ''),
            'delivery_zone' => (string) ($cart->delivery_zone ?? ''),
            'geo_lat' => (string) ($cart->geo_lat ?? ''),
            'geo_long' => (string) ($cart->geo_long ?? ''),
        ], JSON_UNESCAPED_UNICODE);
    }
}
