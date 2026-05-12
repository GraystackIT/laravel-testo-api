<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud;

use GraystackIT\TestoCloud\Commands\FetchMeasurementsCommand;
use GraystackIT\TestoCloud\Connectors\TestoDataConnector;
use Illuminate\Support\ServiceProvider;

class TestoCloudServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/testo-cloud.php', 'testo-cloud');
        $this->mergeConfigFrom(__DIR__.'/../config/testo.php', 'testo');

        $this->app->singleton(TestoDataConnector::class, function () {
            $apiKey = (string) config('testo-cloud.api_key', '');

            if (empty($apiKey)) {
                throw new \RuntimeException(
                    'Testo API key is not configured. Set TESTO_API_KEY in your .env file.'
                );
            }

            return new TestoDataConnector(
                apiKey: $apiKey,
                region: (string) config('testo-cloud.region', 'eu'),
            );
        });

        $this->app->singleton(TestoDataFileDownloader::class, fn () => new TestoDataFileDownloader(
            timeoutSeconds: (int) config('testo-cloud.download_timeout', 120),
        ));

        $this->app->singleton(TestoCloudClient::class, fn ($app) => new TestoCloudClient(
            connector: $app->make(TestoDataConnector::class),
            downloader: $app->make(TestoDataFileDownloader::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/testo-cloud.php' => config_path('testo-cloud.php'),
            ], 'testo-cloud-config');

            $this->publishes([
                __DIR__.'/../config/testo.php' => config_path('testo.php'),
            ], 'testo-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'testo-migrations');

            $this->commands([
                FetchMeasurementsCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
