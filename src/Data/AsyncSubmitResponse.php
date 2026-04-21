<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Data;

use GraystackIT\TestoCloud\Enums\AsyncRequestStatus;

/**
 * Returned by every async-initiation (POST) endpoint across all modules
 * (Alarms, Tasks, Equipment, Sensors, Measuring Objects).
 */
class AsyncSubmitResponse
{
    public function __construct(
        public readonly string             $requestUuid,
        public readonly AsyncRequestStatus $status,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $uuid = $data['request_uuid'] ?? $data['requestUuid'] ?? '';

        return new self(
            requestUuid: (string) $uuid,
            status: AsyncRequestStatus::fromApiValue((string) ($data['status'] ?? 'submitted')),
        );
    }
}
