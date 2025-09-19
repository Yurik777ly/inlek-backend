<?php

namespace App\Console\Commands\Firebase;


use App\Console\Commands\Firebase\FirebaseCommand;
use App\Jobs\Firebase\SendNewsJob;
use App\Models\NewsView;


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
        ->get()
        ->first();

        if ($news) {
            $message = $news->pagetitle ?? self::DEFAULT_MSG;
            $job = new SendNewsJob(self::TITLE_MSG, $message);
            dispatch($job);
        }
       
        $this->info("Sent news.");
    }
}
