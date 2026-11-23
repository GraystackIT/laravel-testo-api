<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Data;

/**
 * A physical Testo logger device identified by its cloud UUID, serial number, and display name.
 *
 * A device carries one or more sensors. Measurements and alarms name the
 * sensor (`sensor_uuid`, and the sensor's `serial_no`), never the device, so
 * `$sensorUuids` is what links a device to its measurement rows.
 */
class LoggerDevice
{
    /**
     * @param  string  $uuid             Cloud UUID that uniquely identifies this device.
     * @param  string  $serialNo         Hardware serial number of the device.
     * @param  string  $name             Human-readable display name as set in Testo Cloud.
     * @param  array<int, string>  $sensorUuids      UUIDs of the device's sensors.
     * @param  array<int, string>  $sensorSerialNos  Serial numbers of the device's sensors.
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $serialNo,
        public readonly string $name,
        public readonly array $sensorUuids = [],
        public readonly array $sensorSerialNos = [],
    ) {}

    /**
     * Construct a LoggerDevice from a Testo API response row.
     *
     * Accepts the actual API field names (`device_serial_no`, `device_display_name`)
     * and several alternative aliases for forward compatibility. A device-properties
     * row describes one channel, so it carries at most one sensor.
     *
     * @param  array<string, mixed>  $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['device_uuid'] ?? $data['uuid'] ?? $data['id'] ?? '',
            serialNo: $data['device_serial_no'] ?? $data['serial_no'] ?? $data['serialNo'] ?? $data['serial_number'] ?? $data['serialNumber'] ?? $data['serial'] ?? '',
            name: $data['device_display_name'] ?? $data['display_name'] ?? $data['name'] ?? $data['serial_no'] ?? $data['device_uuid'] ?? $data['uuid'] ?? '',
            sensorUuids: self::nonEmpty($data['sensor_uuid'] ?? null),
            sensorSerialNos: self::nonEmpty($data['sensor_serial_no'] ?? null),
        );
    }

    /**
     * This device with the sensors of another row of the same device added.
     *
     * @param  self  $other  Another row of the same device (another channel).
     * @return self
     */
    public function withSensorsOf(self $other): self
    {
        return new self(
            uuid: $this->uuid,
            serialNo: $this->serialNo,
            name: $this->name,
            sensorUuids: array_values(array_unique([...$this->sensorUuids, ...$other->sensorUuids])),
            sensorSerialNos: array_values(array_unique([...$this->sensorSerialNos, ...$other->sensorSerialNos])),
        );
    }

    /**
     * A single scalar as a one-element list, or an empty list.
     *
     * @param  mixed  $value  A value from a response row.
     * @return array<int, string>
     */
    private static function nonEmpty(mixed $value): array
    {
        return is_scalar($value) && (string) $value !== '' ? [(string) $value] : [];
    }
}
