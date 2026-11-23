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
     * @param  string    $rawStatus  The status string exactly as Testo returned it — the only
     *                               reliable way to read a status that doesn't map to a known
     *                               {@see AsyncRequestStatus} case (surfaced as ::Unknown).
     */
    public function __construct(
        public readonly AsyncRequestStatus $status,
        public readonly array              $dataUrls,
        public readonly ?string            $metadataUrl,
        public readonly ?string            $error,
        public readonly string             $rawStatus = '',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rawStatus = (string) ($data['status'] ?? 'processing');

        return new self(
            status:      AsyncRequestStatus::fromApiValue($rawStatus),
            dataUrls:    (array) ($data['data_urls'] ?? []),
            metadataUrl: isset($data['metadata_url']) ? (string) $data['metadata_url'] : null,
            error:       self::errorText($data),
            rawStatus:   $rawStatus,
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

    /**
     * The error text of a failed request: Testo's `message`, or a legacy `error` key.
     *
     * @param  array<string, mixed>  $data  The decoded status response.
     * @return string|null
     */
    private static function errorText(array $data): ?string
    {
        foreach (['message', 'error'] as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && (string) $data[$key] !== '') {
                return (string) $data[$key];
            }
        }

        return null;
    }
}
