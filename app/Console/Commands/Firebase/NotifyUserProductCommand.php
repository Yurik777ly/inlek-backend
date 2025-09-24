<?php

namespace App\Console\Commands\Firebase;


use App\Models\EVO\EvoSiteContent;
use App\Models\ProductPharmacyView;
use Illuminate\Support\Facades\DB;


class NotifyUserProductCommand extends FirebaseCommand
{
    protected $signature = 'app:notify-user-about-product';

    protected $description = 'Уведомить о поступлении товара.';

    const TITLE_MSG = 'Inlek. Товары снова в ассортименте.'; 
    const BODY_MSG  = 'Подробнее в приложении.';

  
    public function handle()
    {
    // 1. Получаем товары, по которым еще не отправляли уведомления
    $productIds = DB::table('notificate_product_user as npu')
        ->join('users', 'users.id', '=', 'npu.user_id')
        ->whereNotNull('users.fcm_token')
        ->whereNull('users.deleted_at')
        ->where(function ($query) {
            $query->whereNull('npu.notified_at');
        })
        ->distinct()
        ->pluck('npu.product_id')
        ->toArray();

    if (empty($productIds)) {
        $this->info('No products require notification');
        return;
    }

    // 2. Получаем данные товаров
    $products = EvoSiteContent::whereIn('id', $productIds)
        ->select('id', 'pagetitle')
        ->get()
        ->keyBy('id');

    // 3. Получаем количество аптек для товаров
    $pharmacyCounts = ProductPharmacyView::whereIn('product_id', $productIds)
        ->where('stock_count', '>', 0)
        ->selectRaw('product_id, COUNT(*) as pharmacies_count')
        ->groupBy('product_id')
        ->pluck('pharmacies_count', 'product_id')
        ->toArray();

    // 4. Получаем пользователей с подписками
    $usersWithSubscriptions = DB::table('notificate_product_user as npu')
        ->join('users', 'users.id', '=', 'npu.user_id')
        ->whereIn('npu.product_id', $productIds)
        ->whereNotNull('users.fcm_token')
        ->whereNull('users.deleted_at')
        ->where(function ($query) {
            $query->whereNull('npu.notified_at');
        })
        ->select(
            'users.id as user_id',
            'users.fcm_token',
            'npu.product_id',
            'npu.notified_at'
        )
        ->get();

    // 5. Группируем данные по пользователям
    $userNotifications = [];
    $notificationsToUpdate = [];

    foreach ($usersWithSubscriptions as $subscription) {
        $productId = $subscription->product_id;
        $pharmaciesCount = $pharmacyCounts[$productId] ?? 0;

        if ($pharmaciesCount > 0 && isset($products[$productId])) {
            $product = $products[$productId];

            if (!isset($userNotifications[$subscription->user_id])) {
                $userNotifications[$subscription->user_id] = [
                    'fcm_token' => $subscription->fcm_token,
                    'products' => []
                ];
            }

            $userNotifications[$subscription->user_id]['products'][] = [
                'title' => $product->pagetitle,
                'pharmacies_count' => $pharmaciesCount
            ];

            $notificationsToUpdate[] = [
                'user_id' => $subscription->user_id,
                'product_id' => $productId
            ];
        }
    }

    // 6. Отправляем уведомления
    $sentCount = $this->sendNotifications($userNotifications);

    // 7. Обновляем время уведомления в pivot таблице
    if (!empty($notificationsToUpdate)) {
        $this->updateNotificationTimes($notificationsToUpdate);
    }

    $this->info("Sent {$sentCount} notifications");
}

protected function sendNotifications(array $userNotifications): int
{
    $sentCount = 0;

    foreach ($userNotifications as $userId => $userData) {
        if (empty($userData['products'])) {
            continue;
        }

        $message = $this->buildMessage($userData['products']);
        
        try {
            $result = $this->firebaseService->sendToDevice(
                $userData['fcm_token'],
                [
                    'title' => self::TITLE_MSG,
                    'body' => $message ?? self::BODY_MSG,
                ]
            );

            if ($result['success']) {
                $sentCount++;
            } else {
                $this->logError($userId, $result['error']);
            }

        } catch (\Exception $e) {
            $this->logError($userId, $e->getMessage());
        }
    }

    return $sentCount;
}

protected function updateNotificationTimes(array $notificationsToUpdate): void
{
    $now = now();
    
    foreach (array_chunk($notificationsToUpdate, 100) as $chunk) {
        $userIds = [];
        $productIds = [];
        
        foreach ($chunk as $notification) {
            $userIds[] = $notification['user_id'];
            $productIds[] = $notification['product_id'];
        }
        
        DB::table('notificate_product_user')
            ->whereIn('user_id', $userIds)
            ->whereIn('product_id', $productIds)
            ->update([
                'notified_at' => $now,
                'updated_at' => $now
            ]);
    }

 
}
    protected function buildMessage(array $products):string 
    {
        $message = '';
        foreach ($products as $product) {
            $message = $message .'Товар "' . $product['title']. '" доступен в '. $product['pharmacies_count'].' апт.  ';
        }
        return $message;
    }
}
