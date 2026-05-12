<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class CheckMeasurementStatusRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $requestUuid) {}

    public function resolveEndpoint(): string
    {
        return "/v2/measurements/{$this->requestUuid}";
    }
}
