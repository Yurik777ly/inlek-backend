<?php

namespace App\Console\Commands\Firebase;


use App\Models\ActionView;
use App\Console\Commands\Firebase\FirebaseCommand;
use App\Jobs\Firebase\SendPromotionsJob;


class SendPromotionsCommand extends FirebaseCommand
{
    protected $signature = 'app:send-promotions';

 
    protected $description = 'Проверить, есть ли свежие акции. Если есть, отправить первую попавшуюся.';

    const TITLE_MSG = 'Inlek. Новая акция.'; 
    const DEFAULT_MSG = 'Скидки на определенные товары. Подробности в приложении и на сайте аптеки Inlek.';
    const LIMIT_DAYS = 7;

  
    public function handle()
    { 
        $promotion = ActionView::where('create_dttm','>=', now()->subDays(self::LIMIT_DAYS))
        ->where('published', 1)
        ->inRandomOrder()
        ->get()
        ->first();

        if ($promotion) {
            $message = $promotion->pagetitle ?? self::DEFAULT_MSG;
            $job = new SendPromotionsJob(self::TITLE_MSG, $message);
            dispatch($job);
        }
       
        $this->info("Sent pomotions.");
    }
}
