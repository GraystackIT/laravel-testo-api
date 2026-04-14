<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Connectors;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

class TestoDataConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;

    /**
     * Base URL pattern: https://data-api.{region}.{environment}.savr.saveris.net
     */
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $region = 'eu',
        private readonly string $environment = 'p',
    ) {}

    public function resolveBaseUrl(): string
    {
        return "https://data-api.{$this->region}.{$this->environment}.savr.saveris.net";
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }
}
