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
     *   - sensor_uuid / serial_no (the sensor the reading came from)
     *
     * The Testo /v2/measurements endpoint returns the data of every logger of the
     * account, so readings must be narrowed to one logger before use — otherwise
     * readings of different loggers at the same timestamp overwrite each other.
     * Measurement rows name the **sensor**, never the device: Testo's FAQ states
     * that measurements have no `device_uuid`. To narrow to one logger, pass its
     * sensors in `$sensorUuids` (from {@see \GraystackIT\TestoCloud\Data\LoggerDevice::$sensorUuids},
     * read off /v3/devices/properties).
     *
     * `$deviceUuid` is still honoured for rows that do carry `device_uuid`. A row
     * without it cannot be attributed to a device and is kept; when that happens a
     * warning is logged, because the result may then hold other loggers' readings.
     *
     * Lines with the same timestamp are merged into a single entry, so a logger
     * whose temperature and humidity come from separate sensors still yields one
     * row per timestamp.
     *
     * @param  string  $ndjson  Raw NDJSON content of a measurements result file.
     * @param  string|null  $deviceUuid  Keep only rows of this device, where rows carry `device_uuid`.
     * @param  array<int, string>  $sensorUuids  Keep only rows of these sensors (`sensor_uuid`).
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
    public function parse(string $ndjson, ?string $deviceUuid = null, array $sensorUuids = []): array
    {
        $lines = array_filter(
            explode("\n", $ndjson),
            static fn (string $line) => trim($line) !== ''
        );

        if (empty($lines)) {
            Log::warning('MeasurementNdjsonParser: no data lines found in input');

            return [];
        }

        $sensorFilter = array_fill_keys(array_map('strval', $sensorUuids), true);

        $grouped = [];
        $skippedWrongDevice = 0;
        $skippedWrongSensor = 0;
        $unattributable = 0;

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

            if ($sensorFilter !== []) {
                if (! isset($decoded['sensor_uuid']) || ! isset($sensorFilter[(string) $decoded['sensor_uuid']])) {
                    $skippedWrongSensor++;

                    continue;
                }
            } elseif ($deviceUuid !== null) {
                if (! isset($decoded['device_uuid'])) {
                    $unattributable++;
                } elseif ($decoded['device_uuid'] !== $deviceUuid) {
                    $skippedWrongDevice++;

                    continue;
                }
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

        if ($unattributable > 0) {
            Log::warning('MeasurementNdjsonParser: device filter could not be applied — rows carry no device_uuid; pass the device\'s sensor UUIDs instead', [
                'device_uuid_filter' => $deviceUuid,
                'unattributable_rows' => $unattributable,
            ]);
        }

        $result = array_values($grouped);

        Log::info('MeasurementNdjsonParser: parsed measurements', [
            'total_lines'          => count($lines),
            'unique_timestamps'    => count($result),
            'skipped_wrong_device' => $skippedWrongDevice,
            'skipped_wrong_sensor' => $skippedWrongSensor,
            'unattributable_rows'  => $unattributable,
            'device_uuid_filter'   => $deviceUuid,
            'sensor_uuid_filter'   => array_keys($sensorFilter),
        ]);

        return $result;
    }
}
