<?php

declare(strict_types=1);

namespace Graystack\TestoCloud;

use Carbon\Carbon;
use Graystack\TestoCloud\Connectors\TestoDataConnector;
use Graystack\TestoCloud\Data\LoggerDevice;
use Graystack\TestoCloud\Data\MeasurementStatusResponse;
use Graystack\TestoCloud\Data\MeasurementSubmitResponse;
use Graystack\TestoCloud\Exceptions\TestoApiException;
use Graystack\TestoCloud\Requests\CheckMeasurementStatusRequest;
use Graystack\TestoCloud\Requests\GetLoggersRequest;
use Graystack\TestoCloud\Requests\GetTokenRequest;
use Graystack\TestoCloud\Requests\SubmitMeasurementRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\RequestException;

class TestoCloudClient
{
    public function __construct(
        private readonly TestoDataConnector $connector,
        private readonly TestoDataFileDownloader $downloader,
    ) {}

    /**
     * Submit an asynchronous measurement data request.
     *
     * @throws TestoApiException
     */
    public function submitMeasurementRequest(Carbon $from, Carbon $to, string $format = 'JSON'): MeasurementSubmitResponse
    {
        if ($from->greaterThanOrEqualTo($to)) {
            throw new \InvalidArgumentException(
                "The \$from date ({$from->toIso8601String()}) must be before the \$to date ({$to->toIso8601String()})."
            );
        }

        Log::info('TestoCloudClient: submitting measurement request', [
            'from'   => $from->toIso8601String(),
            'to'     => $to->toIso8601String(),
            'format' => $format,
        ]);

        try {
            $this->applyBearerToken();

            $response = $this->connector->send(new SubmitMeasurementRequest($from, $to, $format));
        } catch (RequestException $e) {
            Log::error('TestoCloudClient: submit measurement request failed', [
                'status' => $e->getResponse()->status(),
                'body'   => substr($e->getResponse()->body(), 0, 500),
            ]);

            throw new TestoApiException(
                "Testo API returned HTTP {$e->getResponse()->status()} when submitting measurement request",
                $e->getResponse()->status(),
                $e
            );
        } catch (\Throwable $e) {
            Log::error('TestoCloudClient: unexpected error submitting measurement request', ['message' => $e->getMessage()]);

            throw new TestoApiException("Testo API measurement submission failed: {$e->getMessage()}", 0, $e);
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['request_uuid'])) {
            throw new TestoApiException('Testo API measurement submission response missing request_uuid');
        }

        Log::info('TestoCloudClient: measurement request submitted', ['request_uuid' => $data['request_uuid']]);

        return MeasurementSubmitResponse::fromArray($data);
    }

    /**
     * Poll the status of a previously submitted async measurement request.
     *
     * @throws TestoApiException
     */
    public function checkRequestStatus(string $requestUuid): MeasurementStatusResponse
    {
        Log::info('TestoCloudClient: checking measurement request status', ['request_uuid' => $requestUuid]);

        try {
            $this->applyBearerToken();

            $response = $this->connector->send(new CheckMeasurementStatusRequest($requestUuid));
        } catch (RequestException $e) {
            Log::error('TestoCloudClient: check request status failed', [
                'request_uuid' => $requestUuid,
                'status'       => $e->getResponse()->status(),
                'body'         => substr($e->getResponse()->body(), 0, 500),
            ]);

            throw new TestoApiException(
                "Testo API returned HTTP {$e->getResponse()->status()} checking status for {$requestUuid}",
                $e->getResponse()->status(),
                $e
            );
        } catch (\Throwable $e) {
            Log::error('TestoCloudClient: unexpected error checking request status', ['message' => $e->getMessage()]);

            throw new TestoApiException("Testo API status check failed: {$e->getMessage()}", 0, $e);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new TestoApiException('Testo API status response was not a JSON object');
        }

        return MeasurementStatusResponse::fromArray($data);
    }

    /**
     * Download and (if gzipped) decompress a signed measurement data file.
     *
     * @throws TestoApiException
     */
    public function downloadDataFile(string $url): string
    {
        return $this->downloader->download($url);
    }

    /**
     * Retrieve all logger devices associated with this API account.
     *
     * @return LoggerDevice[]
     *
     * @throws TestoApiException
     */
    public function getAllLoggers(): array
    {
        Log::info('TestoCloudClient: fetching logger list');

        try {
            $this->applyBearerToken();

            $response = $this->connector->send(new GetLoggersRequest());
        } catch (RequestException $e) {
            Log::error('TestoCloudClient: get loggers request failed', [
                'status' => $e->getResponse()->status(),
                'body'   => substr($e->getResponse()->body(), 0, 500),
            ]);

            throw new TestoApiException(
                "Testo API returned HTTP {$e->getResponse()->status()} when fetching loggers",
                $e->getResponse()->status(),
                $e
            );
        } catch (\Throwable $e) {
            Log::error('TestoCloudClient: unexpected error fetching loggers', ['message' => $e->getMessage()]);

            throw new TestoApiException("Testo API loggers fetch failed: {$e->getMessage()}", 0, $e);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new TestoApiException('Testo API loggers response was not valid JSON');
        }

        $loggers = $this->parseLoggersList($data);

        Log::info('TestoCloudClient: loggers fetched', ['count' => count($loggers)]);

        return $loggers;
    }

    /**
     * Acquire and cache an access token, then set it as Bearer auth on the connector.
     *
     * @throws TestoApiException
     */
    private function applyBearerToken(): void
    {
        $token = $this->getAccessToken();
        $this->connector->withTokenAuth($token);
    }

    /**
     * Fetch (or return cached) the API access token.
     *
     * @throws TestoApiException
     */
    private function getAccessToken(): string
    {
        $cacheKey = sprintf(
            'testo_token_%s_%s_%s',
            $this->connector->getClientId(),
            $this->connector->getRegion(),
            $this->connector->getEnvironment()
        );

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        Log::info('TestoCloudClient: acquiring new access token');

        try {
            $response = $this->connector->send(
                new GetTokenRequest(
                    $this->connector->getClientId(),
                    $this->connector->getClientSecret()
                )
            );
        } catch (RequestException $e) {
            Log::error('TestoCloudClient: authentication failed', [
                'status' => $e->getResponse()->status(),
                'body'   => substr($e->getResponse()->body(), 0, 500),
            ]);

            throw new TestoApiException(
                "Testo API authentication returned HTTP {$e->getResponse()->status()}",
                $e->getResponse()->status(),
                $e
            );
        } catch (\Throwable $e) {
            Log::error('TestoCloudClient: unexpected authentication error', ['message' => $e->getMessage()]);

            throw new TestoApiException("Testo API authentication failed: {$e->getMessage()}", 0, $e);
        }

        $data = $response->json();
        $token = $data['token'] ?? $data['access_token'] ?? $data['IdToken'] ?? null;

        if (! $token) {
            throw new TestoApiException('Testo API authentication response missing token field (expected IdToken, token, or access_token)');
        }

        $buffer   = config('testo-cloud.token_cache_ttl_buffer_seconds', 60);
        $ttl      = ($data['expires_in'] ?? 86400) - $buffer;

        Cache::put($cacheKey, $token, now()->addSeconds(max($ttl, 60)));

        Log::info('TestoCloudClient: access token cached', ['expires_in' => $data['expires_in'] ?? 86400]);

        return $token;
    }

    /**
     * Normalise loggers list from the various response shapes the Testo API may return.
     *
     * @param  array<string, mixed>  $data
     * @return LoggerDevice[]
     */
    private function parseLoggersList(array $data): array
    {
        $raw = $data['devices_status'] ?? $data['loggers'] ?? $data['data'] ?? $data;

        if (! is_array($raw)) {
            return [];
        }

        $devices = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $devices[] = LoggerDevice::fromArray($item);
        }

        return $devices;
    }
}
