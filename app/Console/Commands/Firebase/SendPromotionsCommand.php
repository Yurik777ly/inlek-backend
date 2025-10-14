<?php

namespace App\Console\Commands\Firebase;


use App\Models\ActionNotification;
use App\Console\Commands\Firebase\FirebaseCommand;
use App\Jobs\Firebase\SendPromotionsJob;
use Illuminate\Support\Facades\DB;


class SendPromotionsCommand extends FirebaseCommand
{
    protected $signature = 'app:send-promotions';

 
    protected $description = 'Проверить, есть ли свежие акции. Если есть, отправить первую попавшуюся.';

    const TITLE_MSG = 'Inlek. Новая акция.'; 
    const DEFAULT_MSG = 'Скидки на определенные товары. Подробности в приложении и на сайте аптеки Inlek.';
    const LIMIT_DAYS = 7;

  
    public function handle()
    { 
        $newPromotions = ActionNotification::where('sent', 0)
        ->with(['action' => function($query) {
            $query->select('action_id', 'pagetitle', 'pub_date', 'published');
        }])
        ->get();

        foreach ($newPromotions as $newPromotion) {
            $promotion = $newPromotion->action;
            if ($promotion) {
                $message = $promotion->pagetitle ?? self::DEFAULT_MSG;
                $job = new SendPromotionsJob(self::TITLE_MSG, $message);
                dispatch($job);
            }
        }
        DB::table('actions_notifications')
        ->where('sent', 0)
        ->update(['sent' => 1]);

        $this->info("Sent pomotions.");
    }
}
