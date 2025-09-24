<?php

namespace App\Jobs\Firebase;

use App\Models\User;
use App\Services\Firebase\FirebaseService;

class SendNewsJob extends BaseFirebaseJob
{

    protected $eTitle = 'Failed to send news';


    public function handle(FirebaseService $firebaseService): void
    {
        $users = $this->getTargetUsers();

        foreach ($users as $user) {
                $result = $firebaseService->sendToDevice(
                    $user->fcm_token,
                        [
                            'title' => $this->subject,
                            'body'  => $this->message,
                        ]
                );
                if (!$result['success']) { 
                        $this->logError( $user, $result['error']);
                }
        }
    }

    protected function getTargetUsers()
    {
        $query = User::where('status_notifications', 1)
            ->whereNotNull('fcm_token');

        return $query->get();
    }
}