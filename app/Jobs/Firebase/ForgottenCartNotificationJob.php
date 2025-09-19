<?php

namespace App\Jobs\Firebase;

use App\Models\Cart;
use App\Services\Firebase\FirebaseService;

class ForgottenCartNotificationJob extends BaseFirebaseJob
{
    protected $eTitle = 'Failed to send forgotten cart';


    public function handle(FirebaseService $firebaseService): void
    {
        $forgottenCarts = Cart::with(['user'])
                                        ->whereHas('products')
                                        ->get();
        foreach ($forgottenCarts as $cart) {
            $fcm_token = $cart->user->fcm_token ?? null;
            if ($fcm_token) {
                    $result = $firebaseService->sendToDevice(
                    $fcm_token,
                        [
                            'title' => $this->subject,
                            'body'  => $this->message,
                        ]
                    );

                    if (!$result['success']) { 
                         $this->logError( $cart->user, $result['error']);
                    }
               
            }
        }
    }
}