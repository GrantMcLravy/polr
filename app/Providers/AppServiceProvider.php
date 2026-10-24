<?php

namespace App\Providers;

use App\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Validation rules as Polr 2.x (Lumen 5.1) applied them.
        ValidatorFacade::resolver(function ($translator, $data, $rules, $messages, $attributes) {
            return new Validator($translator, $data, $rules, $messages, $attributes);
        });
    }
}
