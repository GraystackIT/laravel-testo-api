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
     * Base URL pattern: https://data-api.{region}.smartconnect.testo.com
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $region = 'eu',
    ) {}

    public function resolveBaseUrl(): string
    {
        return "https://data-api.{$this->region}.smartconnect.testo.com";
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'x-custom-api-key' => $this->apiKey,
        ];
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getRegion(): string
    {
        return $this->region;
    }
}
