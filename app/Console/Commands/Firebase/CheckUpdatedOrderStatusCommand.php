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
    const AVAITING_DAYS = 4; 

  
    public function handle()
    { 
        $updatedOrders = OrderStatusChange::with('user')
            ->whereNull('updated_at')
            ->get(['order_id', 'new_status_id', 'old_status_id', 'id', 'user_id', 'updated_at']);

        $sentCount = 0;
      
        if ($updatedOrders->count() > 0) {

            foreach ($updatedOrders as $order) {
                if($order->user) {
                    $notification = OrderStatusNotification::where('status_id', '=', $order->new_status_id)
                                                           ->get('text')->first()->text;
                    if ($order->new_status_id == 3) {
                        $notification = $notification. ' '. now()->addDays(self::AVAITING_DAYS)->format('d.m.Y');
                        $this->info($notification);
                    }
                    $result = $this->firebaseService->sendToDevice(
                           $order->user->fcm_token,
                        [
                            'title' => self::TITLE_MSG,
                            'body'  => $notification ? $notification : self::DEFAULT_MSG,
                        ]
                    );
                        if ($result['success']) {
                            $order->updated_at = now();
                            $order->save();
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
