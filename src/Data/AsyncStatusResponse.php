<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Data;

use GraystackIT\TestoCloud\Enums\AsyncRequestStatus;

/**
 * Returned by every async status-check (GET /{request_uuid}) endpoint across
 * all modules (Alarms, Tasks, Equipment, Sensors, Measuring Objects).
 */
class AsyncStatusResponse
{
    /**
     * @param  string[]  $dataUrls
     */
    public function __construct(
        public readonly AsyncRequestStatus $status,
        public readonly array              $dataUrls,
        public readonly ?string            $metadataUrl,
        public readonly ?string            $error,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status:      AsyncRequestStatus::fromApiValue((string) ($data['status'] ?? 'processing')),
            dataUrls:    (array) ($data['data_urls'] ?? []),
            metadataUrl: isset($data['metadata_url']) ? (string) $data['metadata_url'] : null,
            error:       isset($data['error']) ? (string) $data['error'] : null,
        );
    }

    public function isCompleted(): bool
    {
        return $this->status->isCompleted();
    }

    public function isProcessing(): bool
    {
        return $this->status->isProcessing();
    }

    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }
}
