<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Parsers;

use Illuminate\Support\Facades\Log;

class MeasurementNdjsonParser
{
    /**
     * Parse newline-delimited JSON (NDJSON) measurement data from the Testo API.
     *
     * Each line contains a JSON object with fields:
     *   - timestamp (string, ISO 8601)
     *   - measurement (float)
     *   - physical_property_name (string, e.g. "Temperature", "Humidity")
     *   - physical_unit (string, optional — e.g. "°C", "°F", "%RH")
     *   - physical_extension (string, optional — disambiguates e.g. an external probe
     *     reading from the logger's own internal sensor when both report the same
     *     physical_property_name)
     *   - device_uuid (string, optional — present when account has multiple loggers)
     *
     * When $deviceUuid is provided, lines whose device_uuid does not match are
     * silently dropped. This is critical for accounts with multiple loggers: the
     * Testo /v2/measurements endpoint returns ALL devices' data for the credentials
     * used, so without filtering, measurements from other devices corrupt the output.
     *
     * $deviceUuid filtering assumes measurement rows carry `device_uuid`. Testo's own
     * migration guide instead documents `serial_no` as the sensor identifier on
     * measurement values — unconfirmed against a live payload either way. Lines with
     * no `device_uuid` key are never filtered out regardless, so this degrades safely
     * (passes everything through) rather than silently dropping data if the field name
     * turns out to be wrong.
     *
     * Lines with the same timestamp are merged into a single entry.
     *
     * @return array<int, array{
     *     timestamp: string,
     *     temperature: ?float,
     *     humidity: ?float,
     *     temperature_unit: ?string,
     *     humidity_unit: ?string,
     *     temperature_extension: ?string,
     *     humidity_extension: ?string,
     * }>
     */
    public function parse(string $ndjson, ?string $deviceUuid = null): array
    {
        $lines = array_filter(
            explode("\n", $ndjson),
            static fn (string $line) => trim($line) !== ''
        );

        if (empty($lines)) {
            Log::warning('MeasurementNdjsonParser: no data lines found in input');

            return [];
        }

        /**
         * @var array<string, array{
         *     timestamp: string,
         *     temperature: ?float,
         *     humidity: ?float,
         *     temperature_unit: ?string,
         *     humidity_unit: ?string,
         *     temperature_extension: ?string,
         *     humidity_extension: ?string,
         * }> $grouped
         */
        $grouped = [];
        $skippedWrongDevice = 0;

        foreach ($lines as $line) {
            $decoded = json_decode(trim($line), true);

            if (
                ! is_array($decoded)
                || ! isset($decoded['timestamp'], $decoded['measurement'], $decoded['physical_property_name'])
            ) {
                Log::debug('MeasurementNdjsonParser: skipping invalid line', [
                    'line' => substr($line, 0, 100),
                ]);

                continue;
            }

            if (
                $deviceUuid !== null
                && isset($decoded['device_uuid'])
                && $decoded['device_uuid'] !== $deviceUuid
            ) {
                $skippedWrongDevice++;

                continue;
            }

            $timestamp    = (string) $decoded['timestamp'];
            $value        = (float) $decoded['measurement'];
            $propertyName = (string) $decoded['physical_property_name'];
            $unit         = isset($decoded['physical_unit']) ? (string) $decoded['physical_unit'] : null;
            $extension    = isset($decoded['physical_extension']) ? (string) $decoded['physical_extension'] : null;

            if (! isset($grouped[$timestamp])) {
                $grouped[$timestamp] = [
                    'timestamp'   => $timestamp,
                    'temperature' => null,
                    'humidity'    => null,
                    'temperature_unit' => null,
                    'humidity_unit' => null,
                    'temperature_extension' => null,
                    'humidity_extension' => null,
                ];
            }

            if (stripos($propertyName, 'Temperature') !== false) {
                $grouped[$timestamp]['temperature'] = $value;
                $grouped[$timestamp]['temperature_unit'] = $unit;
                $grouped[$timestamp]['temperature_extension'] = $extension;
            } elseif (stripos($propertyName, 'Humidity') !== false) {
                $grouped[$timestamp]['humidity'] = $value;
                $grouped[$timestamp]['humidity_unit'] = $unit;
                $grouped[$timestamp]['humidity_extension'] = $extension;
            }
        }

        $result = array_values($grouped);

        Log::info('MeasurementNdjsonParser: parsed measurements', [
            'total_lines'         => count($lines),
            'unique_timestamps'   => count($result),
            'skipped_wrong_device' => $skippedWrongDevice,
            'device_uuid_filter'  => $deviceUuid,
        ]);

        return $result;
    }
}
