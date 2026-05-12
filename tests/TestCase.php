<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Tests;

use GraystackIT\TestoCloud\TestoCloudServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [TestoCloudServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('testo-cloud.api_key', 'test-api-key');
        $app['config']->set('testo-cloud.region', 'eu');
    }
}
