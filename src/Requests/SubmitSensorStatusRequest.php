<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /v3/devices/status — initiate an asynchronous device health/connectivity status export.
 *
 * Returns battery level, signal strength, last communication timestamp,
 * firmware version, and serial numbers for all devices.
 *
 * NEEDS VERIFICATION (2026-09-28): Testo's changelog records `/v4/devices/status` being added
 * on top of this v3 path, with `last_communication`/`next_communication` renamed to
 * `..._time`, `fw_code`/`fw_version` removed (now under `/v3/devices/properties`, which
 * Equipment already calls), and `production_date`/`battery_temperature`/`status_time` added.
 * The changelog never states v3 is deprecated, but the docs' own endpoint-overview page still
 * only lists v3 — the two sources disagree, so this wasn't changed pending confirmation
 * whichever is actually current. This package doesn't parse any device-status-specific fields
 * into a typed DTO today (only the generic async wrapper), so the version gap is lower-risk
 * than it looks, but should still move to v4 once confirmed live.
 */
class SubmitSensorStatusRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $format = 'JSON',
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v3/devices/status';
    }

    /**
     * @return array{options: array{result_file_format: string}}
     */
    protected function defaultBody(): array
    {
        return [
            'options' => [
                'result_file_format' => $this->format,
            ],
        ];
    }
}
