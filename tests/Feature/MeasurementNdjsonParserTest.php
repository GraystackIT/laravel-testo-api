<?php

declare(strict_types=1);

use GraystackIT\TestoCloud\Parsers\MeasurementNdjsonParser;

// ──────────────────────────────────────────────────────────────────────────────
// MeasurementNdjsonParser::parse()
// ──────────────────────────────────────────────────────────────────────────────

it('returns empty array for blank input', function (): void {
    expect((new MeasurementNdjsonParser())->parse(''))->toBe([])
        ->and((new MeasurementNdjsonParser())->parse('   '))->toBe([]);
});

it('groups temperature and humidity rows sharing a timestamp into one entry', function (): void {
    $ndjson = implode("\n", [
        json_encode(['timestamp' => '2024-01-01T00:00:00Z', 'measurement' => 21.5, 'physical_property_name' => 'Temperature']),
        json_encode(['timestamp' => '2024-01-01T00:00:00Z', 'measurement' => 45.0, 'physical_property_name' => 'Humidity']),
    ]);

    $rows = (new MeasurementNdjsonParser())->parse($ndjson);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['timestamp'])->toBe('2024-01-01T00:00:00Z')
        ->and($rows[0]['temperature'])->toBe(21.5)
        ->and($rows[0]['humidity'])->toBe(45.0);
});

it('captures physical_unit and physical_extension per property when present', function (): void {
    $ndjson = implode("\n", [
        json_encode([
            'timestamp' => '2024-01-01T00:00:00Z',
            'measurement' => 21.5,
            'physical_property_name' => 'Temperature',
            'physical_unit' => '°C',
            'physical_extension' => 'external probe',
        ]),
        json_encode([
            'timestamp' => '2024-01-01T00:00:00Z',
            'measurement' => 45.0,
            'physical_property_name' => 'Humidity',
            'physical_unit' => '%RH',
        ]),
    ]);

    $rows = (new MeasurementNdjsonParser())->parse($ndjson);

    expect($rows[0]['temperature_unit'])->toBe('°C')
        ->and($rows[0]['temperature_extension'])->toBe('external probe')
        ->and($rows[0]['humidity_unit'])->toBe('%RH')
        ->and($rows[0]['humidity_extension'])->toBeNull();
});

it('defaults unit and extension to null when absent', function (): void {
    $ndjson = json_encode(['timestamp' => '2024-01-01T00:00:00Z', 'measurement' => 21.5, 'physical_property_name' => 'Temperature']);

    $rows = (new MeasurementNdjsonParser())->parse((string) $ndjson);

    expect($rows[0]['temperature_unit'])->toBeNull()
        ->and($rows[0]['temperature_extension'])->toBeNull();
});

it('filters rows by device_uuid when a filter is provided', function (): void {
    $ndjson = implode("\n", [
        json_encode(['timestamp' => '2024-01-01T00:00:00Z', 'measurement' => 21.5, 'physical_property_name' => 'Temperature', 'device_uuid' => 'device-a']),
        json_encode(['timestamp' => '2024-01-01T00:01:00Z', 'measurement' => 22.0, 'physical_property_name' => 'Temperature', 'device_uuid' => 'device-b']),
    ]);

    $rows = (new MeasurementNdjsonParser())->parse($ndjson, 'device-a');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['timestamp'])->toBe('2024-01-01T00:00:00Z');
});

it('does not filter out rows with no device_uuid key, even when a filter is active', function (): void {
    $ndjson = json_encode(['timestamp' => '2024-01-01T00:00:00Z', 'measurement' => 21.5, 'physical_property_name' => 'Temperature']);

    $rows = (new MeasurementNdjsonParser())->parse((string) $ndjson, 'device-a');

    expect($rows)->toHaveCount(1);
});

it('skips lines missing required fields without throwing', function (): void {
    $ndjson = implode("\n", [
        json_encode(['timestamp' => '2024-01-01T00:00:00Z']), // missing measurement + physical_property_name
        'not-json',
        json_encode(['timestamp' => '2024-01-01T00:00:00Z', 'measurement' => 21.5, 'physical_property_name' => 'Temperature']),
    ]);

    $rows = (new MeasurementNdjsonParser())->parse($ndjson);

    expect($rows)->toHaveCount(1);
});

it('ignores a physical_property_name that is not Temperature or Humidity', function (): void {
    $ndjson = json_encode(['timestamp' => '2024-01-01T00:00:00Z', 'measurement' => 1013.0, 'physical_property_name' => 'Pressure']);

    $rows = (new MeasurementNdjsonParser())->parse((string) $ndjson);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['temperature'])->toBeNull()
        ->and($rows[0]['humidity'])->toBeNull();
});
