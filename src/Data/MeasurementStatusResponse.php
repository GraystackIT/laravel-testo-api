<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Data;

class MeasurementStatusResponse
{
    /**
     * @param  string[]  $dataUrls
     */
    public function __construct(
        public readonly string $status,
        public readonly array $dataUrls,
        public readonly ?string $metadataUrl,
        public readonly ?string $error,
    ) {}

    /**
     * @param  array{status: string, data_urls?: string[], metadata_url?: string, error?: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] ?? 'processing',
            dataUrls: $data['data_urls'] ?? [],
            metadataUrl: $data['metadata_url'] ?? null,
            error: $data['error'] ?? null,
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['submitted', 'processing'], true);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
