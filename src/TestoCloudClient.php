<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud;

use Carbon\Carbon;
use GraystackIT\TestoCloud\Connectors\TestoDataConnector;
use GraystackIT\TestoCloud\Data\AsyncStatusResponse;
use GraystackIT\TestoCloud\Data\AsyncSubmitResponse;
use GraystackIT\TestoCloud\Data\MeasurementStatusResponse;
use GraystackIT\TestoCloud\Data\MeasurementSubmitResponse;
use GraystackIT\TestoCloud\Exceptions\TestoApiException;
use GraystackIT\TestoCloud\Requests\CheckAlarmStatusRequest;
use GraystackIT\TestoCloud\Requests\CheckEquipmentStatusRequest;
use GraystackIT\TestoCloud\Requests\CheckLocationStatusRequest;
use GraystackIT\TestoCloud\Requests\CheckMeasurementStatusRequest;
use GraystackIT\TestoCloud\Requests\CheckMeasuringObjectStatusRequest;
use GraystackIT\TestoCloud\Requests\CheckSensorStatusRequest;
use GraystackIT\TestoCloud\Requests\CheckTaskStatusRequest;
use GraystackIT\TestoCloud\Requests\SubmitAlarmRequest;
use GraystackIT\TestoCloud\Requests\SubmitEquipmentRequest;
use GraystackIT\TestoCloud\Requests\SubmitLocationRequest;
use GraystackIT\TestoCloud\Requests\SubmitMeasurementRequest;
use GraystackIT\TestoCloud\Requests\SubmitMeasuringObjectRequest;
use GraystackIT\TestoCloud\Requests\SubmitSensorStatusRequest;
use GraystackIT\TestoCloud\Requests\SubmitTaskRequest;
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
    // Measurements  POST /v2/measurements  •  GET /v2/measurements/{uuid}
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Submit an asynchronous measurement data request.
     *
     * @throws \InvalidArgumentException  when $from is not before $to
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
     * Download and (if gzipped) decompress a signed data file.
     *
     * @throws TestoApiException
     */
    public function downloadDataFile(string $url): string
    {
        return $this->downloader->download($url);
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

        $data = $this->sendAsyncSubmit(new SubmitAlarmRequest($from, $to, $format), 'alarm');

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
        return $this->sendAsyncStatusCheck(new CheckAlarmStatusRequest($requestUuid), 'alarm', $requestUuid);
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

        $data = $this->sendAsyncSubmit(new SubmitTaskRequest($from, $to, $format), 'task');

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
        return $this->sendAsyncStatusCheck(new CheckTaskStatusRequest($requestUuid), 'task', $requestUuid);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Device Properties  POST /v3/devices/properties  •  GET /v3/devices/properties/{uuid}
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Initiate an asynchronous device-properties export.
     *
     * Returns device metadata including serial numbers, model codes, firmware versions,
     * calibration status, and equipment relationships.
     *
     * @throws TestoApiException
     */
    public function submitEquipmentRequest(string $format = 'JSON'): AsyncSubmitResponse
    {
        Log::info('TestoCloudClient: submitting device properties request', ['format' => $format]);

        $data = $this->sendAsyncSubmit(new SubmitEquipmentRequest($format), 'device properties');

        Log::info('TestoCloudClient: device properties request submitted', ['request_uuid' => $data['request_uuid']]);

        return AsyncSubmitResponse::fromArray($data);
    }

    /**
     * Poll the status of a previously submitted device-properties request.
     *
     * @throws TestoApiException
     */
    public function checkEquipmentStatus(string $requestUuid): AsyncStatusResponse
    {
        return $this->sendAsyncStatusCheck(new CheckEquipmentStatusRequest($requestUuid), 'device properties', $requestUuid);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Device Status  POST /v3/devices/status  •  GET /v3/devices/status/{uuid}
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Initiate an asynchronous device health/connectivity status export.
     *
     * Returns battery level, signal strength, last communication timestamp,
     * firmware version, and serial numbers for all devices.
     *
     * @throws TestoApiException
     */
    public function submitSensorStatusRequest(string $format = 'JSON'): AsyncSubmitResponse
    {
        Log::info('TestoCloudClient: submitting device status request', ['format' => $format]);

        $data = $this->sendAsyncSubmit(new SubmitSensorStatusRequest($format), 'device status');

        Log::info('TestoCloudClient: device status request submitted', ['request_uuid' => $data['request_uuid']]);

        return AsyncSubmitResponse::fromArray($data);
    }

    /**
     * Poll the status of a previously submitted device-status request.
     *
     * @throws TestoApiException
     */
    public function checkSensorStatus(string $requestUuid): AsyncStatusResponse
    {
        return $this->sendAsyncStatusCheck(new CheckSensorStatusRequest($requestUuid), 'device status', $requestUuid);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Measuring Objects  POST /v1/measuring-objects  •  GET /v1/measuring-objects/{uuid}
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

        $data = $this->sendAsyncSubmit(new SubmitMeasuringObjectRequest($format), 'measuring object');

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
        return $this->sendAsyncStatusCheck(new CheckMeasuringObjectStatusRequest($requestUuid), 'measuring object', $requestUuid);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Locations  POST /v1/locations  •  GET /v1/locations/{uuid}
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Initiate an asynchronous location hierarchy export.
     *
     * Returns the location structure (sites, zones, rooms) associated with the account.
     *
     * @throws TestoApiException
     */
    public function submitLocationRequest(string $format = 'JSON'): AsyncSubmitResponse
    {
        Log::info('TestoCloudClient: submitting location request', ['format' => $format]);

        $data = $this->sendAsyncSubmit(new SubmitLocationRequest($format), 'location');

        Log::info('TestoCloudClient: location request submitted', ['request_uuid' => $data['request_uuid']]);

        return AsyncSubmitResponse::fromArray($data);
    }

    /**
     * Poll the status of a previously submitted location request.
     *
     * @throws TestoApiException
     */
    public function checkLocationStatus(string $requestUuid): AsyncStatusResponse
    {
        return $this->sendAsyncStatusCheck(new CheckLocationStatusRequest($requestUuid), 'location', $requestUuid);
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
}
