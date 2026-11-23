<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Requests\Concerns;

/**
 * Adds Testo's OData query options to a submit request's body.
 *
 * Every Smart Connect submit endpoint accepts an `odata` object with `$filter`,
 * `$select` and `$orderby`. Field names differ per endpoint (measurements name the
 * sensor as `serial_no`/`sensor_uuid`, devices as `device_serial_no`/`device_uuid`)
 * and an unknown field is answered with 400 Bad Request. Example:
 * `['$filter' => "serial_no eq '12345678'", '$orderby' => 'timestamp desc']`.
 */
trait SendsODataOptions
{
    /**
     * The OData options Testo accepts.
     *
     * @var array<int, string>
     */
    private static array $odataKeys = ['$filter', '$select', '$orderby'];

    /**
     * Reject OData options Testo does not know before calling the API.
     *
     * @param  array<string, mixed>  $odata  The OData options given.
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function assertValidOData(array $odata): void
    {
        foreach ($odata as $key => $value) {
            if (! in_array($key, self::$odataKeys, true)) {
                throw new \InvalidArgumentException("Unknown OData option [{$key}]; use \$filter, \$select or \$orderby.");
            }

            if (! is_string($value) || trim($value) === '') {
                throw new \InvalidArgumentException("OData option [{$key}] must be a non-empty string.");
            }
        }
    }

    /**
     * The body with the OData options added under `odata`, when any are given.
     *
     * @param  array<string, mixed>  $body  The request body.
     * @param  array<string, string>  $odata  The OData options.
     * @return array<string, mixed>
     */
    protected function withOData(array $body, array $odata): array
    {
        if ($odata !== []) {
            $body['odata'] = $odata;
        }

        return $body;
    }
}
