<?php

namespace App\Console\Commands\Firebase;


use Illuminate\Console\Command;
use App\Services\Firebase\FirebaseService;


class FirebaseCommand extends Command
{
     protected $signature = 'app:firebase-command';

    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
        parent::__construct();
    }

  
    public function handle()
    { 
    }
}
