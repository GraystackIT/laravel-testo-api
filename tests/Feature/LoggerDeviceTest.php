<?php

declare(strict_types=1);

use GraystackIT\TestoCloud\Data\LoggerDevice;

it('constructs with all properties', function (): void {
    $device = new LoggerDevice(
        uuid:     'uuid-abc',
        serialNo: 'SN-123',
        name:     'Fridge Logger A',
    );

    expect($device->uuid)->toBe('uuid-abc')
        ->and($device->serialNo)->toBe('SN-123')
        ->and($device->name)->toBe('Fridge Logger A');
});

it('maps device_serial_no and device_display_name from API response', function (): void {
    $device = LoggerDevice::fromArray([
        'device_uuid'         => 'dev-uuid-1',
        'device_serial_no'    => 'SN-456',
        'device_display_name' => 'Cold Store Sensor',
    ]);

    expect($device->uuid)->toBe('dev-uuid-1')
        ->and($device->serialNo)->toBe('SN-456')
        ->and($device->name)->toBe('Cold Store Sensor');
});

it('falls back to legacy serial_no when device_serial_no is absent', function (): void {
    $device = LoggerDevice::fromArray([
        'device_uuid' => 'dev-uuid-2',
        'serial_no'   => 'SN-LEGACY',
    ]);

    expect($device->serialNo)->toBe('SN-LEGACY');
});

it('falls back to uuid as name when no display name is available', function (): void {
    $device = LoggerDevice::fromArray([
        'device_uuid' => 'dev-uuid-3',
    ]);

    expect($device->name)->toBe('dev-uuid-3');
});

it('returns empty strings for missing uuid and serial fields', function (): void {
    $device = LoggerDevice::fromArray([]);

    expect($device->uuid)->toBe('')
        ->and($device->serialNo)->toBe('')
        ->and($device->name)->toBe('');
});
