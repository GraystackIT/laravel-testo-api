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
    use Concerns\SendsODataOptions;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $format = 'JSON',
        private readonly array $odata = [],
    ) {
        $this->assertValidOData($this->odata);
    }

    public function resolveEndpoint(): string
    {
        return '/v3/devices/properties';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->withOData([
            'options' => [
                'result_file_format' => $this->format,
            ],
        ], $this->odata);
    }
}
