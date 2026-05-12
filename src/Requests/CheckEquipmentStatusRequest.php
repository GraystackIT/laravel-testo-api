<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v3/devices/properties/{requestUuid} — poll the status of a submitted device-properties request.
 */
class CheckEquipmentStatusRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $requestUuid,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/v3/devices/properties/{$this->requestUuid}";
    }
}
