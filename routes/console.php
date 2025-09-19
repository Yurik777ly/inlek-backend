<?php

use App\Console\Commands\Firebase\CheckForgottenCartCommand;
use App\Console\Commands\Firebase\RememberFailedRegistationCommand;
use App\Console\Commands\Firebase\CheckUpdatedOrderStatusCommand;
use App\Console\Commands\Firebase\SendPromotionsCommand;
use App\Console\Commands\Firebase\SendNewsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


Schedule::command(RememberFailedRegistationCommand::class)
    ->timezone('Europe/Moscow')
    ->at('11:00')
    ->appendOutputTo(storage_path('logs/firebase/reminders-24h.log'));

Schedule::command(CheckUpdatedOrderStatusCommand::class)
    ->everyThreeMinutes()
    ->appendOutputTo(storage_path('logs/firebase/order_notifications.log'));

Schedule::command(SendPromotionsCommand::class)
    ->weekly()
    ->appendOutputTo(storage_path('logs/firebase/promotions.log'));

Schedule::command(SendNewsCommand::class)
    ->weekly()
    ->appendOutputTo(storage_path('logs/firebase/news.log'));

Schedule::command(CheckForgottenCartCommand::class)
    ->weekly()
    ->appendOutputTo(storage_path('logs/firebase/forgotten_cart_notifications.log'));
    
