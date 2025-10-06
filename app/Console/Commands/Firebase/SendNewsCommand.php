<?php

namespace App\Console\Commands\Firebase;


use App\Console\Commands\Firebase\FirebaseCommand;
use App\Jobs\Firebase\SendNewsJob;
use App\Models\NewsView;
use Illuminate\Support\Facades\Cache;


class SendNewsCommand extends FirebaseCommand
{
    protected $signature = 'app:send-news';

 
    protected $description = 'Проверить, есть ли свежие новости. Если есть, отправить первую попавшуюся.';

    const TITLE_MSG = 'Inlek. Новости.'; 
    const DEFAULT_MSG = 'Подробности в приложении и на сайте аптеки Inlek.';
    const LIMIT_DAYS = 8;

  
    public function handle()
    { 
        $news = NewsView::where('create_dttm','>=', now()->subDays(self::LIMIT_DAYS))
        ->where('published', 1)
        ->inRandomOrder()
        ->get(['published', 'pagetitle', 'create_dttm', 'contentid'])
        ->first();

        if ($news) {
            $sendedNews = 0;
            if (!Cache::has($news->contentid)) {
                Cache::put($news->contentid, 1, 30 * 24 * 60);
            } else {
                $sendedNews = Cache::get($news->contentid);
            }
        
            if (!$sendedNews) {
                $message = $news->pagetitle ?? self::DEFAULT_MSG;
                $job = new SendNewsJob(self::TITLE_MSG, $message);
                dispatch($job);
            }
        }
       
        $this->info("Sent news.");
    }
}
