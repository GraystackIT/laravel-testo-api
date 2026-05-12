<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /v1/locations — initiate an asynchronous location hierarchy export.
 *
 * Returns the location structure (sites, zones, rooms) associated with the account.
 */
class SubmitLocationRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $format = 'JSON',
    ) {}

    public function resolveEndpoint(): string
    {
        return '/v1/locations';
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
