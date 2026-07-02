<?php

namespace App\Console\Commands\Firebase;

use App\Models\OrderStatusChange;
use App\Models\OrderStatusNotification;
use App\Services\Firebase\FirebaseService;
use App\Services\Order\OrderStatusChangeService;

class CheckUpdatedOrderStatusCommand extends FirebaseCommand
{
    protected $signature = 'app:check-order-status';

    protected $description = 'Проверить, изменились ли статусы у заказов. Если да, отправить уведомление.';

    const TITLE_MSG = 'Inlek. Информация о заказе';
    const DEFAULT_MSG = 'Ваш заказ в работе.';
    const AVAITING_DAYS = 1;

    public function __construct(
        FirebaseService $firebaseService,
        private readonly OrderStatusChangeService $orderStatusChangeService,
    ) {
        parent::__construct($firebaseService);
    }

    public function handle()
    {
        $synced = $this->orderStatusChangeService->syncFromOrderHistory();
        if ($synced > 0) {
            $this->info("Synced {$synced} status changes from order history.");
        }

        $pendingChanges = OrderStatusChange::with('user')
            ->whereNull('updated_at')
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('id')
            ->get();

        $sentCount = 0;

        foreach ($pendingChanges as $change) {
            $fcmToken = $this->orderStatusChangeService->resolveFcmToken($change->user);

            if (!$fcmToken) {
                $this->warn("Skip order #{$change->order_id}: FCM token not found for user #{$change->user_id}");
                $change->updated_at = now();
                $change->save();
                continue;
            }

            $notification = OrderStatusNotification::query()
                ->where('status_id', $change->new_status_id)
                ->value('text');

            if (empty($notification)) {
                $this->warn("Skip order #{$change->order_id}: no push text for status {$change->new_status_id}");
                $change->updated_at = now();
                $change->save();
                continue;
            }

            if ((int) $change->new_status_id === 3) {
                $body = $notification . ' ' . now()->addDays(self::AVAITING_DAYS)->format('d.m.Y');
            } else {
                $body = $notification;
            }

            $result = $this->firebaseService->sendToDevice(
                $fcmToken,
                [
                    'title' => self::TITLE_MSG,
                    'body' => $body,
                ],
                [
                    'order_id' => (string) $change->order_id,
                    'status_id' => (string) $change->new_status_id,
                    'type' => 'order_status',
                ]
            );

            if ($result['success']) {
                $change->updated_at = now();
                $change->save();
                $sentCount++;
                $this->info("Sent push for order #{$change->order_id}, status {$change->new_status_id}");
            } else {
                $phone = $change->user?->phone ?? 'unknown';
                $this->error("Push failed for {$phone}: " . ($result['error'] ?? 'unknown error'));
            }
        }

        $this->info("Sent {$sentCount} status notifications.");
    }
}
