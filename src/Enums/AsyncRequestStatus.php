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
     * Normalise the raw API status string (which may be "In Progress", "Completed", etc.)
     * into a typed enum case.
     */
    public static function fromApiValue(string $value): self
    {
        return match (mb_strtolower(trim(str_replace(' ', '', $value)))) {
            'submitted'              => self::Submitted,
            'inprogress', 'processing' => self::Processing,
            'completed'              => self::Completed,
            'failed'                 => self::Failed,
            default                  => self::Processing,
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
