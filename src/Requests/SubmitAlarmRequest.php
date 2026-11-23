<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Carbon\Carbon;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /v3/alarms — initiate an asynchronous historical alarm export.
 *
 * Request structure mirrors the measurements endpoint.
 */
class SubmitAlarmRequest extends Request implements HasBody
{
    use HasJsonBody;
    use Concerns\SendsODataOptions;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly Carbon $from,
        private readonly Carbon $to,
        private readonly string $format = 'JSON',
        private readonly array $odata = [],
    ) {
        $this->assertValidOData($this->odata);
    }

    public function resolveEndpoint(): string
    {
        return '/v3/alarms';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->withOData([
            'date_time_from'  => $this->from->toIso8601String(),
            'date_time_until' => $this->to->toIso8601String(),
            'options'         => [
                'result_file_format' => $this->format,
            ],
        ], $this->odata);
    }
}
