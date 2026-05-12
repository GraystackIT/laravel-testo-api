<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v1/measuring-objects/{requestUuid} — poll the status of a submitted measuring-object request.
 */
class CheckMeasuringObjectStatusRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $requestUuid,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/v1/measuring-objects/{$this->requestUuid}";
    }
}
