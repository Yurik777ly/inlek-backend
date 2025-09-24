<?php

namespace App\Jobs\Firebase;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Firebase\FirebaseService;


class BaseFirebaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $eTitle;
    public function __construct(
        public string $subject,
        public string $message, 
    ) {
      
    }

    public function handle(FirebaseService $firebaseService): void
    {
       
    }

    protected function logError(User $user, string $eMessage): void
    {
        \Log::error($this->eTitle, [
            'user_id' => $user->id,
            'phone' => $user->phone,
            'error' => $eMessage,
            'attempted_at' => now()->toDateTimeString()
        ]);
    }
}