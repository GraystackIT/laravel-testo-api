<?php

declare(strict_types=1);

namespace Graystack\TestoCloud\Data;

class LoggerDevice
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $serialNo,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['device_uuid'] ?? $data['uuid'] ?? $data['id'] ?? '',
            serialNo: $data['serial_no'] ?? $data['serialNo'] ?? $data['serial_number'] ?? $data['serialNumber'] ?? $data['serial'] ?? '',
        );
    }
}
