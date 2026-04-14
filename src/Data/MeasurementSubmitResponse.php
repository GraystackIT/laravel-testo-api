<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Data;

class MeasurementSubmitResponse
{
    public function __construct(
        public readonly string $requestUuid,
        public readonly string $status,
    ) {}

    /**
     * @param  array{request_uuid: string, status: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            requestUuid: $data['request_uuid'],
            status: $data['status'] ?? 'submitted',
        );
    }
}
