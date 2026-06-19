<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Parsers;

use GraystackIT\TestoCloud\Data\LoggerDevice;
use Illuminate\Support\Facades\Log;

/**
 * Parses newline-delimited JSON (NDJSON) device data from the Testo Cloud API.
 *
 * Multi-channel devices produce one row per channel in the raw file; this parser
 * deduplicates by device_uuid so callers receive one LoggerDevice per physical device.
 */
class DeviceNdjsonParser
{
    /**
     * Parse NDJSON content into a map of LoggerDevice objects keyed by device_uuid.
     *
     * @param  string  $ndjson  Raw NDJSON content from a Testo device-properties download URL.
     * @return array<string, LoggerDevice>
     */
    public function parse(string $ndjson): array
    {
        $trimmed = trim($ndjson);

        if ($trimmed === '') {
            Log::warning('DeviceNdjsonParser: empty input');

            return [];
        }

        // Fast-path: single JSON array for forward compatibility with possible format changes
        if (str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);

            if (is_array($decoded)) {
                return $this->indexByUuid(array_map(
                    static fn (array $row) => LoggerDevice::fromArray($row),
                    $decoded
                ));
            }
        }

        $lines = array_filter(
            explode("\n", $ndjson),
            static fn (string $line) => trim($line) !== ''
        );

        /** @var array<string, LoggerDevice> $devices */
        $devices = [];

        foreach ($lines as $line) {
            $decoded = json_decode(trim($line), true);

            if (! is_array($decoded)) {
                Log::debug('DeviceNdjsonParser: skipping invalid line', ['line' => substr($line, 0, 100)]);

                continue;
            }

            $device = LoggerDevice::fromArray($decoded);

            if ($device->uuid === '') {
                Log::debug('DeviceNdjsonParser: skipping device with empty uuid');

                continue;
            }

            // First occurrence wins — all channels share the same device metadata
            if (! isset($devices[$device->uuid])) {
                $devices[$device->uuid] = $device;
            }
        }

        Log::info('DeviceNdjsonParser: parsed devices', [
            'lines'   => count($lines),
            'devices' => count($devices),
        ]);

        return $devices;
    }

    /**
     * Index an array of LoggerDevice objects by uuid; first occurrence wins.
     *
     * @param  array<int, LoggerDevice>  $devices
     * @return array<string, LoggerDevice>
     */
    private function indexByUuid(array $devices): array
    {
        $indexed = [];

        foreach ($devices as $device) {
            if ($device->uuid !== '' && ! isset($indexed[$device->uuid])) {
                $indexed[$device->uuid] = $device;
            }
        }

        return $indexed;
    }
}
