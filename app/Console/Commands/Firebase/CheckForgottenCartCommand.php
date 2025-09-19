<?php

namespace App\Console\Commands\Firebase;


use App\Jobs\Firebase\ForgottenCartNotificationJob;


class CheckForgottenCartCommand extends FirebaseCommand
{
    protected $signature = 'app:check-forgotten-cart';

    protected $description = 'Напомнить про забытые корзины.';

    const TITLE_MSG = 'Inlek. Напоминание'; 
    const DEFAULT_MSG = 'Недавно вы выбирали товары в аптеке Inlek. Завершите офрмление заказа, скоро корзина очистится.';

  
    public function handle()
    {
        $job = new ForgottenCartNotificationJob(self::TITLE_MSG, self::DEFAULT_MSG);
        dispatch($job);
        $this->info("Sent forgotten cart notifications.");
    }
}
