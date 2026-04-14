<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class GetTokenRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v1/token';
    }

    /**
     * @return array{username: string, password: string}
     */
    protected function defaultBody(): array
    {
        return [
            'username' => $this->clientId,
            'password' => $this->clientSecret,
        ];
    }
}
