<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Data;

/** A physical Testo logger device identified by its cloud UUID, serial number, and display name. */
class LoggerDevice
{
    /**
     * @param  string  $uuid      Cloud UUID that uniquely identifies this device.
     * @param  string  $serialNo  Hardware serial number.
     * @param  string  $name      Human-readable display name as set in Testo Cloud.
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $serialNo,
        public readonly string $name,
    ) {}

    /**
     * Construct a LoggerDevice from a Testo API response row.
     *
     * Accepts the actual API field names (`device_serial_no`, `device_display_name`)
     * and several alternative aliases for forward compatibility.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['device_uuid'] ?? $data['uuid'] ?? $data['id'] ?? '',
            serialNo: $data['device_serial_no'] ?? $data['serial_no'] ?? $data['serialNo'] ?? $data['serial_number'] ?? $data['serialNumber'] ?? $data['serial'] ?? '',
            name: $data['device_display_name'] ?? $data['display_name'] ?? $data['name'] ?? $data['serial_no'] ?? $data['device_uuid'] ?? $data['uuid'] ?? '',
        );
    }
}
