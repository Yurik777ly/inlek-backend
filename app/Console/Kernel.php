<?php

namespace App\Console;

use App\Console\Commands\Firebase\CheckForgottenCartCommand;
use App\Console\Commands\Firebase\CheckUpdatedOrderStatusCommand;
use App\Console\Commands\Firebase\NotifyUserProductCommand;
use App\Console\Commands\Firebase\RememberFailedRegistationCommand;
use App\Console\Commands\Firebase\SendNewsCommand;
use App\Console\Commands\Firebase\SendPromotionsCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
         $schedule->command(CheckForgottenCartCommand::class)->weekly();
         $schedule->command(SendNewsCommand::class)->everyFiveMinutes();
         $schedule->command(SendPromotionsCommand::class)->everyFiveMinutes();
         $schedule->command(RememberFailedRegistationCommand::class)->daily();
         $schedule->command(NotifyUserProductCommand::class)->hourly();
         $schedule->command(CheckUpdatedOrderStatusCommand::class)->everyThreeMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
