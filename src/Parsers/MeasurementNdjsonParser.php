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
     *
     * Lines with the same timestamp are merged into a single entry.
     *
     * @return array<int, array{timestamp: string, temperature: ?float, humidity: ?float}>
     */
    public function parse(string $ndjson): array
    {
        $lines = array_filter(
            explode("\n", $ndjson),
            static fn (string $line) => trim($line) !== ''
        );

        if (empty($lines)) {
            Log::warning('MeasurementNdjsonParser: no data lines found in input');

            return [];
        }

        /** @var array<string, array{timestamp: string, temperature: ?float, humidity: ?float}> $grouped */
        $grouped = [];

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

            $timestamp    = (string) $decoded['timestamp'];
            $value        = (float) $decoded['measurement'];
            $propertyName = (string) $decoded['physical_property_name'];

            if (! isset($grouped[$timestamp])) {
                $grouped[$timestamp] = [
                    'timestamp'   => $timestamp,
                    'temperature' => null,
                    'humidity'    => null,
                ];
            }

            if (stripos($propertyName, 'Temperature') !== false) {
                $grouped[$timestamp]['temperature'] = $value;
            } elseif (stripos($propertyName, 'Humidity') !== false) {
                $grouped[$timestamp]['humidity'] = $value;
            }
        }

        $result = array_values($grouped);

        Log::info('MeasurementNdjsonParser: parsed measurements', [
            'total_lines'       => count($lines),
            'unique_timestamps' => count($result),
        ]);

        return $result;
    }
}
