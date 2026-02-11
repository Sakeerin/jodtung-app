<?php

namespace App\Providers;

use App\Services\ConnectionService;
use App\Services\Line\FlexMessageBuilder;
use App\Services\Line\LineService;
use App\Services\Line\MessageParser;
use App\Services\Line\RichMenuService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register LINE services as singletons
        $this->app->singleton(LineService::class, function ($app) {
            return new LineService();
        });

        $this->app->singleton(MessageParser::class, function ($app) {
            return new MessageParser();
        });

        $this->app->singleton(FlexMessageBuilder::class, function ($app) {
            return new FlexMessageBuilder();
        });

        $this->app->singleton(RichMenuService::class, function ($app) {
            return new RichMenuService();
        });

        $this->app->singleton(ConnectionService::class, function ($app) {
            return new ConnectionService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
