<?php

namespace App\Console\Commands\Firebase;

use App\Console\Commands\Firebase\FirebaseCommand;
use App\Models\SMS;



class RememberFailedRegistationCommand extends FirebaseCommand
{
    protected $signature = 'app:remember-failed-registation 
                            {--hours=24 : Hours after registration started}
                            {--limit=3  : Maximum number of sms_requested_qty}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверить, есть ли пользователи, не завершившие регистрацию, отправить им пуш-уведомление';

    const TITLE_MSG = 'Напоминание';
    const BODY_MSG  = 'Регистрация в приложении Inlek не завершена. Запросите код заново.';

    /**
     * Execute the console command.
     */
    public function handle()
    { 
        $hours = (int)$this->option('hours');
        $limit = (int)$this->option('limit');
        
        $incompletedRegistration = SMS::where('is_confirmed', '=', 0)
            ->where(function ($query) use($hours) {
                $query->where('last_sms_requested_at', '>=', now()->subHours($hours))
                      ->orWhereNull('last_sms_requested_at');
            })
            ->where('sms_requested_qty', '<', $limit)
             ->get(['fcm_token','phone']);

        $sentCount = 0;

        if ($incompletedRegistration->count() > 0) {
            foreach($incompletedRegistration as $unconfirmedUser) {
                if ($unconfirmedUser->fcm_token) {
                    $result = $this->firebaseService->sendToDevice(
                    $unconfirmedUser->fcm_token,
                        [
                            'title' => self::TITLE_MSG,
                            'body'  => self::BODY_MSG,
                        ]
                    );
                    if ($result['success']) {
                        $sentCount++;
                    } else {
                        $this->info('Error for number '. $unconfirmedUser->phone .' ' . $result['error']); 
                    }
                }
            }
        }
       
        $this->info("Sent {$sentCount} registration reminders.");
    }
}
