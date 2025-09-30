<?php

namespace App\Console\Commands\Firebase;

use App\Models\OrderStatusChange;
use App\Models\OrderStatusNotification;


class CheckUpdatedOrderStatusCommand extends FirebaseCommand
{
    protected $signature = 'app:check-order-status';

    protected $description = 'Проверить, изменились ли статусы у заказов. Если да, отправить уведомление.';

    const TITLE_MSG = 'Inlek. Инфорамция о заказе'; 
    const DEFAULT_MSG = 'Ваш заказ в работе.';

  
    public function handle()
    { 
        $updatedOrders = OrderStatusChange::with('user')
            ->get(['order_id', 'new_status_id', 'old_status_id', 'id', 'user_id']);

        $sentCount = 0;
      
        if ($updatedOrders->count() > 0) {

            foreach ($updatedOrders as $order) {
                if($order->user) {
                    $notification = OrderStatusNotification::where('id', '=', $order->new_status_id)
                                                           ->get('text')->first();
                    $result = $this->firebaseService->sendToDevice(
                           $order->user->fcm_token,
                        [
                            'title' => self::TITLE_MSG,
                            'body'  => $notification ? $notification->text : self::DEFAULT_MSG,
                        ]
                    );
                        if ($result['success']) {
                            $order->delete();
                            $sentCount++;
                        } else {
                            $this->info('Error for number '. $order->user->phone .' ' . $result['error']); 
                        }
                }
            }
            
        }

        $this->info("Sent {$sentCount} status notifications.");
       
    }
}
