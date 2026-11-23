<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v4/devices/status/{requestUuid} — poll the status of a submitted device-status request.
 *
 * See {@see SubmitSensorStatusRequest} for the v4 row fields.
 */
class CheckSensorStatusRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $requestUuid,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/v4/devices/status/{$this->requestUuid}";
    }
}
