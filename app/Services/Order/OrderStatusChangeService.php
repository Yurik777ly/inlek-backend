<?php

namespace App\Services\Order;

use App\Models\EVO\EvoCommerceOrderHistory;
use App\Models\SMS;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderStatusChangeService
{
    /**
     * Переносит записи из evo_commerce_order_history (notify=1) в очередь push-уведомлений.
     */
    public function syncFromOrderHistory(): int
    {
        $since = now()->subDay();

        $pendingHistories = DB::table('evo_commerce_order_history as h')
            ->join('evo_commerce_orders as o', 'o.id', '=', 'h.order_id')
            ->leftJoin('order_status_changes as osc', 'osc.history_id', '=', 'h.id')
            ->where('h.notify', 1)
            ->where('h.created_at', '>=', $since)
            ->whereNull('osc.id')
            ->where('o.customer_id', '>', 0)
            ->orderBy('h.id')
            ->get([
                'h.id as history_id',
                'h.order_id',
                'h.status_id',
                'h.created_at',
                'o.customer_id',
            ]);

        $synced = 0;

        foreach ($pendingHistories as $history) {
            $oldStatusId = EvoCommerceOrderHistory::query()
                ->where('order_id', $history->order_id)
                ->where('id', '<', $history->history_id)
                ->orderByDesc('id')
                ->value('status_id');

            if ($oldStatusId === null) {
                $oldStatusId = $history->status_id;
            }

            if ((int) $oldStatusId === (int) $history->status_id) {
                continue;
            }

            DB::table('order_status_changes')->insert([
                'history_id' => $history->history_id,
                'order_id' => $history->order_id,
                'user_id' => $history->customer_id,
                'old_status_id' => (string) $oldStatusId,
                'new_status_id' => (string) $history->status_id,
                'created_at' => $history->created_at ?? now(),
                'updated_at' => null,
            ]);

            $synced++;
        }

        if ($synced > 0) {
            Log::info('Order status changes synced from history', ['count' => $synced]);
        }

        return $synced;
    }

    public function resolveFcmToken(?User $user): ?string
    {
        if (!$user || !$user->status_notifications) {
            return null;
        }

        if (!empty($user->fcm_token)) {
            return $user->fcm_token;
        }

        return SMS::query()
            ->where('phone', $user->phone)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->orderByDesc('id')
            ->value('fcm_token');
    }
}
