<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /v1/measuring-objects — initiate an asynchronous measuring-object configuration export.
 *
 * Returns customer_uuid, customer_site, product_family_id, measurement
 * configurations, and channel assignments.
 */
class SubmitMeasuringObjectRequest extends Request implements HasBody
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
        return '/v1/measuring-objects';
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
