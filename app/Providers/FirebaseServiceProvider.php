<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;

class FirebaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Auth::class, function ($app) {
            return (new Factory)
                ->withServiceAccount(config('firebase.credentials.file'))
                ->withProjectId(config('firebase.project_id'))
                ->createAuth();
        });
    }
}
