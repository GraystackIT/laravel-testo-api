<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /v3/tasks/{requestUuid} — poll the status of a submitted task request.
 *
 * @deprecated Testo's Smart Connect API has no tasks endpoint. Its OpenAPI reference
 *             (api_reference/swagger.json) lists exactly six endpoint pairs — device
 *             properties, device status, measurements, alarms, locations and measuring
 *             objects — and the changelog never added tasks, so this request fails.
 *             Kept only so existing callers keep compiling; removed in the next major version.
 */
class CheckTaskStatusRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $requestUuid,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/v3/tasks/{$this->requestUuid}";
    }
}
