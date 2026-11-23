<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * POST /v4/devices/status — initiate an asynchronous device health/connectivity status export.
 *
 * One row per device: serial_no, model_code, production_date, connection_type,
 * radio_level_percent, battery_level_percent, battery_temperature, is_powersupply_on,
 * last_communication_time, next_communication_time and status_time. v4 is the only
 * version in Testo's API reference; it replaced v3, renaming last_communication /
 * next_communication to ..._time and dropping fw_code / fw_version, which
 * /v3/devices/properties (Equipment) still returns.
 */
class SubmitSensorStatusRequest extends Request implements HasBody
{
    use HasJsonBody;
    use Concerns\SendsODataOptions;

    protected Method $method = Method::POST;

    /**
     * @param  string  $format  Result file format: CSV, JSON, PARQUET, ORC, AVRO or TEXTFILE.
     * @param  array{'$filter'?: string, '$select'?: string, '$orderby'?: string}  $odata  OData query options.
     */
    public function __construct(
        private readonly string $format = 'JSON',
        private readonly array $odata = [],
    ) {
        $this->assertValidOData($this->odata);
    }

    public function resolveEndpoint(): string
    {
        return '/v4/devices/status';
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
