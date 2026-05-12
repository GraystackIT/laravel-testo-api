<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /v3/devices/properties — initiate an asynchronous device-properties export.
 *
 * Returns device metadata including serial numbers, model codes, firmware versions,
 * calibration status, and equipment relationships.
 */
class SubmitEquipmentRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $format = 'JSON',
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v3/devices/properties';
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
