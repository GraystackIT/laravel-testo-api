<?php

declare(strict_types=1);

use GraystackIT\TestoCloud\Data\LoggerDevice;
use GraystackIT\TestoCloud\Parsers\DeviceNdjsonParser;
use GraystackIT\TestoCloud\TestoCloudClient;
use GraystackIT\TestoCloud\TestoDataFileDownloader;
use GraystackIT\TestoCloud\Connectors\TestoDataConnector;

// ──────────────────────────────────────────────────────────────────────────────
// DeviceNdjsonParser::parse()
// ──────────────────────────────────────────────────────────────────────────────

it('returns empty array for blank input', function (): void {
    expect((new DeviceNdjsonParser())->parse(''))->toBe([])
        ->and((new DeviceNdjsonParser())->parse('   '))->toBe([]);
});

it('parses a single NDJSON line into one LoggerDevice', function (): void {
    $ndjson = json_encode([
        'device_uuid'         => 'uuid-1',
        'device_serial_no'    => 'SN-001',
        'device_display_name' => 'Freezer A',
    ]);

    $devices = (new DeviceNdjsonParser())->parse($ndjson);

    expect($devices)->toHaveCount(1)
        ->and($devices['uuid-1'])->toBeInstanceOf(LoggerDevice::class)
        ->and($devices['uuid-1']->uuid)->toBe('uuid-1')
        ->and($devices['uuid-1']->serialNo)->toBe('SN-001')
        ->and($devices['uuid-1']->name)->toBe('Freezer A');
});

it('deduplicates multi-channel device rows by device_uuid', function (): void {
    $row = ['device_uuid' => 'uuid-multi', 'device_serial_no' => 'SN-M', 'device_display_name' => 'Logger X'];

    $ndjson = implode("\n", [
        json_encode($row),
        json_encode($row + ['channel' => 2]),
        json_encode($row + ['channel' => 3]),
    ]);

    $devices = (new DeviceNdjsonParser())->parse($ndjson);

    expect($devices)->toHaveCount(1)
        ->and($devices['uuid-multi']->uuid)->toBe('uuid-multi');
});

it('parses multiple distinct devices from NDJSON', function (): void {
    $ndjson = implode("\n", [
        json_encode(['device_uuid' => 'uuid-a', 'device_serial_no' => 'SN-A', 'device_display_name' => 'Device A']),
        json_encode(['device_uuid' => 'uuid-b', 'device_serial_no' => 'SN-B', 'device_display_name' => 'Device B']),
    ]);

    $devices = (new DeviceNdjsonParser())->parse($ndjson);

    expect($devices)->toHaveCount(2)
        ->and($devices['uuid-a']->serialNo)->toBe('SN-A')
        ->and($devices['uuid-b']->name)->toBe('Device B');
});

it('skips lines with no device_uuid', function (): void {
    $ndjson = implode("\n", [
        json_encode(['device_serial_no' => 'SN-NOID']),
        json_encode(['device_uuid' => 'uuid-ok', 'device_serial_no' => 'SN-OK']),
    ]);

    $devices = (new DeviceNdjsonParser())->parse($ndjson);

    expect($devices)->toHaveCount(1)
        ->and($devices)->toHaveKey('uuid-ok');
});

it('skips invalid (non-JSON) lines without throwing', function (): void {
    $ndjson = implode("\n", [
        'not-json',
        json_encode(['device_uuid' => 'uuid-valid', 'device_serial_no' => 'SN-V']),
    ]);

    $devices = (new DeviceNdjsonParser())->parse($ndjson);

    expect($devices)->toHaveCount(1)
        ->and($devices)->toHaveKey('uuid-valid');
});

it('fast-paths a single JSON array format', function (): void {
    $json = json_encode([
        ['device_uuid' => 'uuid-arr-1', 'device_serial_no' => 'SN-1', 'device_display_name' => 'Array Device 1'],
        ['device_uuid' => 'uuid-arr-2', 'device_serial_no' => 'SN-2', 'device_display_name' => 'Array Device 2'],
    ]);

    $devices = (new DeviceNdjsonParser())->parse($json);

    expect($devices)->toHaveCount(2)
        ->and($devices['uuid-arr-1']->name)->toBe('Array Device 1')
        ->and($devices['uuid-arr-2']->serialNo)->toBe('SN-2');
});

// ──────────────────────────────────────────────────────────────────────────────
// TestoCloudClient::parseDeviceList()
// ──────────────────────────────────────────────────────────────────────────────

it('parseDeviceList returns an indexed array of LoggerDevice objects', function (): void {
    $connector = new TestoDataConnector('test-key', 'eu');
    $client    = new TestoCloudClient($connector, new TestoDataFileDownloader());

    $ndjson = implode("\n", [
        json_encode(['device_uuid' => 'uuid-c1', 'device_serial_no' => 'SN-C1', 'device_display_name' => 'Client Device 1']),
        json_encode(['device_uuid' => 'uuid-c2', 'device_serial_no' => 'SN-C2', 'device_display_name' => 'Client Device 2']),
    ]);

    $list = $client->parseDeviceList($ndjson);

    expect($list)->toBeArray()
        ->and($list)->toHaveCount(2)
        ->and($list[0])->toBeInstanceOf(LoggerDevice::class)
        ->and($list[1])->toBeInstanceOf(LoggerDevice::class)
        ->and(array_keys($list))->toBe([0, 1]);
});

it('parseDeviceList deduplicates multi-channel rows', function (): void {
    $connector = new TestoDataConnector('test-key', 'eu');
    $client    = new TestoCloudClient($connector, new TestoDataFileDownloader());

    $row    = ['device_uuid' => 'uuid-dup', 'device_serial_no' => 'SN-DUP'];
    $ndjson = implode("\n", [json_encode($row), json_encode($row), json_encode($row)]);

    $list = $client->parseDeviceList($ndjson);

    expect($list)->toHaveCount(1)
        ->and($list[0]->uuid)->toBe('uuid-dup');
});
