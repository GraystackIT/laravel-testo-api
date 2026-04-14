<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud;

use GraystackIT\TestoCloud\Exceptions\TestoApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestoDataFileDownloader
{
    public function __construct(private readonly int $timeoutSeconds = 120) {}

    /**
     * Download a measurement data file from a signed URL and return the raw content.
     * Files may be gzip-compressed; this method decompresses them automatically.
     *
     * @throws TestoApiException
     */
    public function download(string $url): string
    {
        Log::info('TestoDataFileDownloader: downloading data file', ['url' => $url]);

        $response = Http::timeout($this->timeoutSeconds)->get($url);

        if ($response->failed()) {
            Log::error('TestoDataFileDownloader: download failed', [
                'url'    => $url,
                'status' => $response->status(),
            ]);

            throw new TestoApiException(
                "Testo data file download returned HTTP {$response->status()} for URL: {$url}",
                $response->status()
            );
        }

        $content = $response->body();

        return $this->decompressIfGzipped($content, $url);
    }

    /**
     * Decompress content if it is gzip-encoded (magic bytes 0x1F 0x8B).
     *
     * @throws TestoApiException
     */
    private function decompressIfGzipped(string $content, string $url): string
    {
        if (strlen($content) < 2) {
            return $content;
        }

        $isGzipped = ord($content[0]) === 0x1F && ord($content[1]) === 0x8B;

        if (! $isGzipped) {
            return $content;
        }

        set_error_handler(static function (): bool { return true; });
        $decompressed = gzdecode($content);
        restore_error_handler();

        if ($decompressed === false) {
            Log::error('TestoDataFileDownloader: failed to decompress gzipped content', [
                'url'            => $url,
                'content_length' => strlen($content),
            ]);

            throw new TestoApiException("Failed to decompress gzipped data file from URL: {$url}");
        }

        Log::info('TestoDataFileDownloader: decompressed gzipped data file', [
            'compressed_size'   => strlen($content),
            'decompressed_size' => strlen($decompressed),
        ]);

        return $decompressed;
    }
}
