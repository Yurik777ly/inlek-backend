<?php

namespace App\Console\Commands\Firebase;


use App\Models\ActionView;
use App\Console\Commands\Firebase\FirebaseCommand;
use App\Jobs\Firebase\SendPromotionsJob;
use Illuminate\Support\Facades\Cache;


class SendPromotionsCommand extends FirebaseCommand
{
    protected $signature = 'app:send-promotions';

 
    protected $description = 'Проверить, есть ли свежие акции. Если есть, отправить первую попавшуюся.';

    const TITLE_MSG = 'Inlek. Новая акция.'; 
    const DEFAULT_MSG = 'Скидки на определенные товары. Подробности в приложении и на сайте аптеки Inlek.';
    const LIMIT_DAYS = 7;

  
    public function handle()
    { 
        $promotion = ActionView::where('pub_date','>=', now()->subDays(self::LIMIT_DAYS))
        ->where('published', 1)
        ->inRandomOrder()
        ->get(['pagetitle', 'published', 'create_dttm', 'pub_date', 'action_id'])
        ->first();
        
        if ($promotion) {
            $pubDate = null;
            if (!Cache::has('action'.$promotion->action_id)) {
                Cache::put('action'.$promotion->action_id, $promotion->pub_date, 30 * 24 * 60);
            } else {
                $pubDate = Cache::get('action'.$promotion->action_id);
            }
           
            if ($pubDate !== $promotion->pub_date) {
                $message = $promotion->pagetitle ?? self::DEFAULT_MSG;
                $job = new SendPromotionsJob(self::TITLE_MSG, $message);
                dispatch($job);
            }
        }
       
        $this->info("Sent pomotions.");
    }
}
