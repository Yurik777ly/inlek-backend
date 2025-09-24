<?php

namespace App\Providers;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Firestore;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Contract\RemoteConfig;
use Kreait\Firebase\Contract\Storage;
use Illuminate\Support\ServiceProvider;

class FirebaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Factory::class, function () {
            $credentials = config('services.firebase.credentials');
            
            return (new Factory)
                ->withServiceAccount($credentials)
                ->withDatabaseUri(config('services.firebase.database_url'));
        });

        $this->app->singleton(Messaging::class, function () {
            return app(Factory::class)->createMessaging();
        });

        $this->app->singleton(Auth::class, function () {
            return app(Factory::class)->createAuth();
        });

        $this->app->singleton(Firestore::class, function () {
            return app(Factory::class)->createFirestore();
        });

        $this->app->singleton(Storage::class, function () {
            return app(Factory::class)->createStorage();
        });
    }

    public function boot()
    {
        
    }
}