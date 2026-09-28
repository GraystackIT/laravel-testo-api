<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v3/devices/status/{requestUuid} — poll the status of a submitted device-status request.
 *
 * NEEDS VERIFICATION (2026-09-28): see {@see SubmitSensorStatusRequest} — Testo's changelog
 * records a v4 of this endpoint pair.
 */
class CheckSensorStatusRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $requestUuid,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/v3/devices/status/{$this->requestUuid}";
    }
}
