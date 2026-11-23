<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Exceptions;

use RuntimeException;
use Saloon\Exceptions\Request\RequestException;

/**
 * A Testo Smart Connect API call that failed.
 *
 * For an HTTP error the code is the status. Testo answers errors such as the
 * monthly request limit (10,000 POST requests per account; 403 with type
 * `ACCESS_DENIED`) as `application/problem+json`; its `type`, `title` and
 * `detail` travel along, and a plain `message` body is kept as `$detail`.
 */
class TestoApiException extends RuntimeException
{
    /**
     * @param  string  $message  The exception message.
     * @param  int  $code  The HTTP status, or 0 when the API was not reached.
     * @param  \Throwable|null  $previous  The underlying failure.
     * @param  string|null  $problemType  The problem `type`, e.g. `ACCESS_DENIED`.
     * @param  string|null  $problemTitle  The problem `title`.
     * @param  string|null  $detail  The problem `detail`, or the body's `message`.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $problemType = null,
        public readonly ?string $problemTitle = null,
        public readonly ?string $detail = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Build the exception for an HTTP error the API answered with.
     *
     * @param  RequestException  $e  The failed request.
     * @param  string  $message  The exception message.
     * @return self
     */
    public static function fromRequestException(RequestException $e, string $message): self
    {
        $body = json_decode($e->getResponse()->body(), true);
        $body = is_array($body) ? $body : [];

        $text = static fn (string $key): ?string => isset($body[$key]) && is_scalar($body[$key]) && (string) $body[$key] !== ''
            ? (string) $body[$key]
            : null;

        return new self(
            $message,
            $e->getResponse()->status(),
            $e,
            problemType: $text('type'),
            problemTitle: $text('title'),
            detail: $text('detail') ?? $text('message'),
        );
    }

    /**
     * Whether the account used up its monthly request limit.
     *
     * Access returns at the start of the next calendar month (UTC). Only POST
     * (submit) requests count; polling and downloading results do not.
     *
     * @return bool
     */
    public function isQuotaExceeded(): bool
    {
        return $this->getCode() === 403
            && $this->problemType === 'ACCESS_DENIED'
            && $this->detail !== null
            && stripos($this->detail, 'maximum allowed number of API calls') !== false;
    }
}
