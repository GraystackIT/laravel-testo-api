<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /v4/equipments — initiate an asynchronous equipment-configuration export.
 *
 * Returns equipment hierarchies, sensor mappings, measurement thresholds,
 * and physical_value / physical_extension fields for channel alignment.
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
        return '/v4/equipments';
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
