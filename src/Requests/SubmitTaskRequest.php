<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Carbon\Carbon;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /v3/tasks — initiate an asynchronous quality-management / HACCP task export.
 *
 * UNVERIFIED (2026-09-28): this path does not appear anywhere in Testo's current Smart Connect
 * API reference — checked the endpoint-examples overview, the OData filtering page, and the
 * changelog, all of which enumerate the same fixed set of six endpoint pairs (device
 * properties, device status, measurements, alarms, locations, measuring objects) with no tasks
 * entry. Search results reference a separate "Task Execution API" concept but its actual
 * endpoint path could not be confirmed. Verify against a live API key (or with Testo) before
 * relying on this — it may 404, may live under a different path, or may belong to a different
 * API family entirely.
 */
class SubmitTaskRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly Carbon $from,
        private readonly Carbon $to,
        private readonly string $format = 'JSON',
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v3/tasks';
    }

    /**
     * @return array{date_time_from: string, date_time_until: string, options: array{result_file_format: string}}
     */
    protected function defaultBody(): array
    {
        return [
            'date_time_from'  => $this->from->toIso8601String(),
            'date_time_until' => $this->to->toIso8601String(),
            'options'         => [
                'result_file_format' => $this->format,
            ],
        ];
    }
}
