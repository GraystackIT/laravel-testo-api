<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Data;

use GraystackIT\TestoCloud\Enums\AsyncRequestStatus;

/**
 * Returned by POST /v2/measurements. Kept as its own class (measurements has its own status
 * vocabulary/endpoint separate from the shared Alarms/Tasks/Equipment/Sensors/Measuring-Objects
 * modules), but mirrors {@see AsyncSubmitResponse}'s shape for consistency.
 */
class MeasurementSubmitResponse
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
        $rawStatus = (string) ($data['status'] ?? 'submitted');

        return new self(
            requestUuid: (string) ($data['request_uuid'] ?? $data['requestUuid'] ?? ''),
            status: AsyncRequestStatus::fromApiValue($rawStatus),
            rawStatus: $rawStatus,
        );
    }
}
