<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Enums;

enum AsyncRequestStatus: string
{
    case Submitted = 'submitted';
    case Processing = 'processing';
    case Completed  = 'completed';
    case Failed     = 'failed';

    /**
     * Fallback for any status string Testo returns that isn't one of the cases above.
     *
     * Never silently treated as "still processing": an unrecognized terminal status (e.g. a
     * future "cancelled" state) would otherwise make {@see \GraystackIT\TestoCloud\Commands\FetchMeasurementsCommand}'s
     * poll loop spin until poll_max_attempts is exhausted instead of surfacing the real state.
     * Callers that see this should log the DTO's raw status string rather than assume progress.
     */
    case Unknown = 'unknown';

    /**
     * Normalise the raw API status string into a typed enum case.
     *
     * Testo's poll endpoints answer `Completed`, `In Progress` or `Error`; `Error`
     * is the failed state and maps to {@see self::Failed}.
     *
     * @param  string  $value  The status string Testo returned.
     * @return self
     */
    public static function fromApiValue(string $value): self
    {
        return match (mb_strtolower(trim(str_replace(' ', '', $value)))) {
            'submitted'              => self::Submitted,
            'inprogress', 'processing' => self::Processing,
            'completed'              => self::Completed,
            'error', 'failed'        => self::Failed,
            default                  => self::Unknown,
        };
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    public function isProcessing(): bool
    {
        return $this === self::Submitted || $this === self::Processing;
    }

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }
}
