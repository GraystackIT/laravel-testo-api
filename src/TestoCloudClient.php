<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud;

use Carbon\Carbon;
use GraystackIT\TestoCloud\Connectors\TestoDataConnector;
use GraystackIT\TestoCloud\Data\AsyncStatusResponse;
use GraystackIT\TestoCloud\Data\AsyncSubmitResponse;
use GraystackIT\TestoCloud\Data\LoggerDevice;
use GraystackIT\TestoCloud\Data\MeasurementStatusResponse;
use GraystackIT\TestoCloud\Data\MeasurementSubmitResponse;
use GraystackIT\TestoCloud\Exceptions\TestoApiException;
use GraystackIT\TestoCloud\Requests\CheckAlarmStatusRequest;
use GraystackIT\TestoCloud\Requests\CheckEquipmentStatusRequest;
use GraystackIT\TestoCloud\Requests\CheckMeasurementStatusRequest;
use GraystackIT\TestoCloud\Requests\CheckMeasuringObjectStatusRequest;
use GraystackIT\TestoCloud\Requests\CheckSensorStatusRequest;
use GraystackIT\TestoCloud\Requests\CheckTaskStatusRequest;
use GraystackIT\TestoCloud\Requests\GetLoggersRequest;
use GraystackIT\TestoCloud\Requests\GetTokenRequest;
use GraystackIT\TestoCloud\Requests\SubmitAlarmRequest;
use GraystackIT\TestoCloud\Requests\SubmitEquipmentRequest;
use GraystackIT\TestoCloud\Requests\SubmitMeasurementRequest;
use GraystackIT\TestoCloud\Requests\SubmitMeasuringObjectRequest;
use GraystackIT\TestoCloud\Requests\SubmitSensorStatusRequest;
use GraystackIT\TestoCloud\Requests\SubmitTaskRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Request as SaloonRequest;

class TestoCloudClient
{
    public function __construct(
        private readonly TestoDataConnector $connector,
        private readonly TestoDataFileDownloader $downloader,
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // Measurements (existing)
    // ──────────────────────────────────────────────────────────────────────────

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

    // ──────────────────────────────────────────────────────────────────────────
    // Alarms  POST /v3/alarms  •  GET /v3/alarms/{uuid}
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Initiate an asynchronous historical alarm export.
     *
     * @throws \InvalidArgumentException  when $from is not before $to
     * @throws TestoApiException
     */
    public function submitAlarmRequest(Carbon $from, Carbon $to, string $format = 'JSON'): AsyncSubmitResponse
    {
        if ($from->greaterThanOrEqualTo($to)) {
            throw new \InvalidArgumentException(
                "The \$from date ({$from->toIso8601String()}) must be before the \$to date ({$to->toIso8601String()})."
            );
        }

        Log::info('TestoCloudClient: submitting alarm request', [
            'from'   => $from->toIso8601String(),
            'to'     => $to->toIso8601String(),
            'format' => $format,
        ]);

        $data = $this->sendAsyncSubmit(
            new SubmitAlarmRequest($from, $to, $format),
            'alarm'
        );

        Log::info('TestoCloudClient: alarm request submitted', ['request_uuid' => $data['request_uuid']]);

        return AsyncSubmitResponse::fromArray($data);
    }

    /**
     * Poll the status of a previously submitted alarm request.
     *
     * @throws TestoApiException
     */
    public function checkAlarmStatus(string $requestUuid): AsyncStatusResponse
    {
        return $this->sendAsyncStatusCheck(
            new CheckAlarmStatusRequest($requestUuid),
            'alarm',
            $requestUuid
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tasks  POST /v3/tasks  •  GET /v3/tasks/{uuid}
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Initiate an asynchronous quality-management / HACCP task export.
     *
     * @throws \InvalidArgumentException  when $from is not before $to
     * @throws TestoApiException
     */
    public function submitTaskRequest(Carbon $from, Carbon $to, string $format = 'JSON'): AsyncSubmitResponse
    {
        if ($from->greaterThanOrEqualTo($to)) {
            throw new \InvalidArgumentException(
                "The \$from date ({$from->toIso8601String()}) must be before the \$to date ({$to->toIso8601String()})."
            );
        }

        Log::info('TestoCloudClient: submitting task request', [
            'from'   => $from->toIso8601String(),
            'to'     => $to->toIso8601String(),
            'format' => $format,
        ]);

        $data = $this->sendAsyncSubmit(
            new SubmitTaskRequest($from, $to, $format),
            'task'
        );

        Log::info('TestoCloudClient: task request submitted', ['request_uuid' => $data['request_uuid']]);

        return AsyncSubmitResponse::fromArray($data);
    }

    /**
     * Poll the status of a previously submitted task request.
     *
     * @throws TestoApiException
     */
    public function checkTaskStatus(string $requestUuid): AsyncStatusResponse
    {
        return $this->sendAsyncStatusCheck(
            new CheckTaskStatusRequest($requestUuid),
            'task',
            $requestUuid
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Equipment  POST /v4/equipments  •  GET /v4/equipments/{uuid}
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Initiate an asynchronous equipment-configuration export.
     *
     * Returns equipment hierarchies, sensor mappings, measurement thresholds,
     * and physical_value / physical_extension fields for channel alignment.
     *
     * @throws TestoApiException
     */
    public function submitEquipmentRequest(string $format = 'JSON'): AsyncSubmitResponse
    {
        Log::info('TestoCloudClient: submitting equipment request', ['format' => $format]);

        $data = $this->sendAsyncSubmit(
            new SubmitEquipmentRequest($format),
            'equipment'
        );

        Log::info('TestoCloudClient: equipment request submitted', ['request_uuid' => $data['request_uuid']]);

        return AsyncSubmitResponse::fromArray($data);
    }

    /**
     * Poll the status of a previously submitted equipment request.
     *
     * @throws TestoApiException
     */
    public function checkEquipmentStatus(string $requestUuid): AsyncStatusResponse
    {
        return $this->sendAsyncStatusCheck(
            new CheckEquipmentStatusRequest($requestUuid),
            'equipment',
            $requestUuid
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Sensors  POST /v3/sensors/status  •  GET /v3/sensors/status/{uuid}
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Initiate an asynchronous sensor health/battery status export.
     *
     * Returns battery level, signal strength, last communication timestamp,
     * firmware version, and serial numbers for all sensors.
     *
     * @throws TestoApiException
     */
    public function submitSensorStatusRequest(string $format = 'JSON'): AsyncSubmitResponse
    {
        Log::info('TestoCloudClient: submitting sensor status request', ['format' => $format]);

        $data = $this->sendAsyncSubmit(
            new SubmitSensorStatusRequest($format),
            'sensor status'
        );

        Log::info('TestoCloudClient: sensor status request submitted', ['request_uuid' => $data['request_uuid']]);

        return AsyncSubmitResponse::fromArray($data);
    }

    /**
     * Poll the status of a previously submitted sensor-status request.
     *
     * @throws TestoApiException
     */
    public function checkSensorStatus(string $requestUuid): AsyncStatusResponse
    {
        return $this->sendAsyncStatusCheck(
            new CheckSensorStatusRequest($requestUuid),
            'sensor status',
            $requestUuid
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Measuring Objects  POST /v1/measuring_objects  •  GET /v1/measuring_objects/{uuid}
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Initiate an asynchronous measuring-object configuration export.
     *
     * Returns customer_uuid, customer_site, product_family_id, measurement
     * configurations, and channel assignments.
     *
     * @throws TestoApiException
     */
    public function submitMeasuringObjectRequest(string $format = 'JSON'): AsyncSubmitResponse
    {
        Log::info('TestoCloudClient: submitting measuring object request', ['format' => $format]);

        $data = $this->sendAsyncSubmit(
            new SubmitMeasuringObjectRequest($format),
            'measuring object'
        );

        Log::info('TestoCloudClient: measuring object request submitted', ['request_uuid' => $data['request_uuid']]);

        return AsyncSubmitResponse::fromArray($data);
    }

    /**
     * Poll the status of a previously submitted measuring-object request.
     *
     * @throws TestoApiException
     */
    public function checkMeasuringObjectStatus(string $requestUuid): AsyncStatusResponse
    {
        return $this->sendAsyncStatusCheck(
            new CheckMeasuringObjectStatusRequest($requestUuid),
            'measuring object',
            $requestUuid
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Send any async-initiation (POST) request and return the validated response body.
     *
     * @return array<string, mixed>
     *
     * @throws TestoApiException
     */
    private function sendAsyncSubmit(SaloonRequest $request, string $module): array
    {
        try {
            $this->applyBearerToken();

            $response = $this->connector->send($request);
        } catch (RequestException $e) {
            Log::error("TestoCloudClient: {$module} submit request failed", [
                'status' => $e->getResponse()->status(),
                'body'   => substr($e->getResponse()->body(), 0, 500),
            ]);

            throw new TestoApiException(
                "Testo API returned HTTP {$e->getResponse()->status()} when submitting {$module} request",
                $e->getResponse()->status(),
                $e
            );
        } catch (\Throwable $e) {
            Log::error("TestoCloudClient: unexpected error submitting {$module} request", ['message' => $e->getMessage()]);

            throw new TestoApiException("Testo API {$module} submission failed: {$e->getMessage()}", 0, $e);
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['request_uuid'])) {
            throw new TestoApiException("Testo API {$module} submission response missing request_uuid");
        }

        return $data;
    }

    /**
     * Send any async status-check (GET /{uuid}) request and return the response DTO.
     *
     * @throws TestoApiException
     */
    private function sendAsyncStatusCheck(SaloonRequest $request, string $module, string $requestUuid): AsyncStatusResponse
    {
        Log::info("TestoCloudClient: checking {$module} status", ['request_uuid' => $requestUuid]);

        try {
            $this->applyBearerToken();

            $response = $this->connector->send($request);
        } catch (RequestException $e) {
            Log::error("TestoCloudClient: {$module} status check failed", [
                'request_uuid' => $requestUuid,
                'status'       => $e->getResponse()->status(),
                'body'         => substr($e->getResponse()->body(), 0, 500),
            ]);

            throw new TestoApiException(
                "Testo API returned HTTP {$e->getResponse()->status()} checking {$module} status for {$requestUuid}",
                $e->getResponse()->status(),
                $e
            );
        } catch (\Throwable $e) {
            Log::error("TestoCloudClient: unexpected error checking {$module} status", ['message' => $e->getMessage()]);

            throw new TestoApiException("Testo API {$module} status check failed: {$e->getMessage()}", 0, $e);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new TestoApiException("Testo API {$module} status response was not a JSON object");
        }

        return AsyncStatusResponse::fromArray($data);
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

        $data  = $response->json();
        $token = $data['token'] ?? $data['access_token'] ?? $data['IdToken'] ?? null;

        if (! $token) {
            throw new TestoApiException('Testo API authentication response missing token field (expected IdToken, token, or access_token)');
        }

        $buffer = config('testo-cloud.token_cache_ttl_buffer_seconds', 60);
        $ttl    = ($data['expires_in'] ?? 86400) - $buffer;

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
