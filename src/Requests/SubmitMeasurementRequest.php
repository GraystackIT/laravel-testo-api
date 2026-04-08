<?php

declare(strict_types=1);

namespace Graystack\TestoCloud\Requests;

use Carbon\Carbon;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class SubmitMeasurementRequest extends Request implements HasBody
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
        return '/v1/measurements';
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
