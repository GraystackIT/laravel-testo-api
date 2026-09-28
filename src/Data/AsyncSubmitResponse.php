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
    /**
     * @param  string  $rawStatus  The status string exactly as Testo returned it — the only
     *                             reliable way to read a status that doesn't map to a known
     *                             {@see AsyncRequestStatus} case (surfaced as ::Unknown).
     */
    public function __construct(
        public readonly string             $requestUuid,
        public readonly AsyncRequestStatus $status,
        public readonly string             $rawStatus = '',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $uuid      = $data['request_uuid'] ?? $data['requestUuid'] ?? '';
        $rawStatus = (string) ($data['status'] ?? 'submitted');

        return new self(
            requestUuid: (string) $uuid,
            status: AsyncRequestStatus::fromApiValue($rawStatus),
            rawStatus: $rawStatus,
        );
    }
}
