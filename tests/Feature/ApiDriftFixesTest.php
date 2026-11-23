<?php

declare(strict_types=1);

use Carbon\Carbon;
use GraystackIT\TestoCloud\Connectors\TestoDataConnector;
use GraystackIT\TestoCloud\Data\AsyncStatusResponse;
use GraystackIT\TestoCloud\Data\MeasurementStatusResponse;
use GraystackIT\TestoCloud\Enums\AsyncRequestStatus;
use GraystackIT\TestoCloud\Exceptions\TestoApiException;
use GraystackIT\TestoCloud\Parsers\DeviceNdjsonParser;
use GraystackIT\TestoCloud\Parsers\MeasurementNdjsonParser;
use GraystackIT\TestoCloud\Requests\CheckSensorStatusRequest;
use GraystackIT\TestoCloud\Requests\SubmitAlarmRequest;
use GraystackIT\TestoCloud\Requests\SubmitEquipmentRequest;
use GraystackIT\TestoCloud\Requests\SubmitMeasurementRequest;
use GraystackIT\TestoCloud\Requests\SubmitSensorStatusRequest;
use GraystackIT\TestoCloud\TestoCloudClient;
use GraystackIT\TestoCloud\TestoDataFileDownloader;
use Illuminate\Support\Facades\Log;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/**
 * A client whose connector answers through the given mock.
 */
function driftClient(MockClient $mockClient): TestoCloudClient
{
    $connector = new TestoDataConnector(config('testo.api_key'), config('testo.region'));
    $connector->withMockClient($mockClient);

    return new TestoCloudClient($connector, new TestoDataFileDownloader());
}

/**
 * One measurement row the way Testo's /v2/measurements result file carries it.
 *
 * @param  array<string, mixed>  $overrides
 */
function measurementRow(string $sensorUuid, string $property, float $value, string $timestamp = '2026-06-28T10:00:00Z', array $overrides = []): string
{
    return json_encode([
        'uuid'                   => bin2hex(random_bytes(8)),
        'sensor_uuid'            => $sensorUuid,
        'channel_no'             => 1,
        'serial_no'              => 'SN-'.$sensorUuid,
        'timestamp'              => $timestamp,
        'measurement'            => $value,
        'physical_property_name' => $property,
        'physical_unit'          => $property === 'Temperature' ? '°C' : '%RH',
        ...$overrides,
    ]);
}

// ─── Status ───────────────────────────────────────────────────────────────────

it('maps Testo\'s Error status to Failed', function (): void {
    expect(AsyncRequestStatus::fromApiValue('Error'))->toBe(AsyncRequestStatus::Failed)
        ->and(AsyncRequestStatus::fromApiValue('In Progress'))->toBe(AsyncRequestStatus::Processing)
        ->and(AsyncRequestStatus::fromApiValue('Completed'))->toBe(AsyncRequestStatus::Completed);
});

it('reports a request Testo answers with Error as failed, with its message', function (): void {
    $status = MeasurementStatusResponse::fromArray(['status' => 'Error', 'message' => 'Data preparation failed']);

    expect($status->isFailed())->toBeTrue()
        ->and($status->status->value)->toBe('failed')
        ->and($status->rawStatus)->toBe('Error')
        ->and($status->error)->toBe('Data preparation failed');
});

it('still reads a legacy error key', function (): void {
    expect(AsyncStatusResponse::fromArray(['status' => 'Error', 'error' => 'boom'])->error)->toBe('boom');
});

// ─── Device → sensors ─────────────────────────────────────────────────────────

it('collects the sensors of every channel of a device', function (): void {
    $ndjson = implode("\n", [
        json_encode(['device_uuid' => 'dev-1', 'device_serial_no' => 'D1', 'device_display_name' => 'Fridge', 'sensor_uuid' => 'sen-t', 'sensor_serial_no' => 'S-T', 'channel_no' => 1]),
        json_encode(['device_uuid' => 'dev-1', 'device_serial_no' => 'D1', 'device_display_name' => 'Fridge', 'sensor_uuid' => 'sen-h', 'sensor_serial_no' => 'S-H', 'channel_no' => 2]),
        json_encode(['device_uuid' => 'dev-1', 'device_serial_no' => 'D1', 'device_display_name' => 'Fridge', 'sensor_uuid' => 'sen-h', 'sensor_serial_no' => 'S-H', 'channel_no' => 3]),
        json_encode(['device_uuid' => 'dev-2', 'device_serial_no' => 'D2', 'device_display_name' => 'Freezer', 'sensor_uuid' => 'sen-x', 'channel_no' => 1]),
    ]);

    $devices = (new DeviceNdjsonParser())->parse($ndjson);

    expect($devices['dev-1']->name)->toBe('Fridge')
        ->and($devices['dev-1']->sensorUuids)->toBe(['sen-t', 'sen-h'])
        ->and($devices['dev-1']->sensorSerialNos)->toBe(['S-T', 'S-H'])
        ->and($devices['dev-2']->sensorUuids)->toBe(['sen-x']);
});

// ─── Measurements of one logger ───────────────────────────────────────────────

it('keeps only the readings of the given sensors, merging a logger\'s temperature and humidity', function (): void {
    $ndjson = implode("\n", [
        measurementRow('sen-t', 'Temperature', 4.0),
        measurementRow('sen-h', 'Humidity', 60.0),
        measurementRow('other', 'Temperature', -18.0),
    ]);

    $rows = (new MeasurementNdjsonParser())->parse($ndjson, 'dev-1', ['sen-t', 'sen-h']);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['temperature'])->toBe(4.0)
        ->and($rows[0]['humidity'])->toBe(60.0);
});

it('warns and keeps every row when a device filter cannot be applied', function (): void {
    Log::spy();

    $ndjson = implode("\n", [
        measurementRow('sen-t', 'Temperature', 4.0),
        measurementRow('other', 'Temperature', -18.0, '2026-06-28T10:01:00Z'),
    ]);

    $rows = (new MeasurementNdjsonParser())->parse($ndjson, 'dev-1');

    expect($rows)->toHaveCount(2);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'device filter could not be applied')
            && $context['unattributable_rows'] === 2)
        ->once();
});

it('does not warn when there is no device filter', function (): void {
    Log::spy();

    (new MeasurementNdjsonParser())->parse(measurementRow('sen-t', 'Temperature', 4.0));

    Log::shouldNotHaveReceived('warning');
});

// ─── Device status v4 ─────────────────────────────────────────────────────────

it('calls the v4 device status endpoints', function (): void {
    expect((new SubmitSensorStatusRequest())->resolveEndpoint())->toBe('/v4/devices/status')
        ->and((new CheckSensorStatusRequest('abc'))->resolveEndpoint())->toBe('/v4/devices/status/abc');
});

// ─── OData ────────────────────────────────────────────────────────────────────

it('sends OData options with a measurements request', function (): void {
    $mockClient = new MockClient([
        SubmitMeasurementRequest::class => MockResponse::make(['status' => 'Submitted', 'request_uuid' => 'req-1'], 200),
    ]);

    driftClient($mockClient)->submitMeasurementRequest(
        Carbon::parse('2026-06-28T00:00:00Z'),
        Carbon::parse('2026-06-29T00:00:00Z'),
        odata: ['$filter' => "serial_no eq '12345678'", '$orderby' => 'timestamp desc'],
    );

    $mockClient->assertSent(fn (SubmitMeasurementRequest $request): bool => $request->body()->all()['odata'] === [
        '$filter'  => "serial_no eq '12345678'",
        '$orderby' => 'timestamp desc',
    ]);
});

it('sends OData options with the other submit requests', function (): void {
    $mockClient = new MockClient([
        SubmitAlarmRequest::class     => MockResponse::make(['status' => 'Submitted', 'request_uuid' => 'req-a'], 200),
        SubmitEquipmentRequest::class => MockResponse::make(['status' => 'Submitted', 'request_uuid' => 'req-e'], 200),
    ]);

    $client = driftClient($mockClient);
    $client->submitAlarmRequest(Carbon::parse('2026-06-28'), Carbon::parse('2026-06-29'), odata: ['$filter' => "alarm_status eq 'Alarm'"]);
    $client->submitEquipmentRequest(odata: ['$select' => 'device_uuid,sensor_uuid']);

    $mockClient->assertSent(fn ($request): bool => $request instanceof SubmitAlarmRequest
        && $request->body()->all()['odata'] === ['$filter' => "alarm_status eq 'Alarm'"]);
    $mockClient->assertSent(fn ($request): bool => $request instanceof SubmitEquipmentRequest
        && $request->body()->all()['odata'] === ['$select' => 'device_uuid,sensor_uuid']);
});

it('leaves odata out of the body when none is given', function (): void {
    expect((new SubmitEquipmentRequest())->body()->all())->toBe(['options' => ['result_file_format' => 'JSON']]);
});

it('rejects an unknown OData option before calling the API', function (): void {
    driftClient(new MockClient([]))->submitMeasurementRequest(
        Carbon::parse('2026-06-28'),
        Carbon::parse('2026-06-29'),
        odata: ['$top' => '10'],
    );
})->throws(InvalidArgumentException::class, 'Unknown OData option [$top]');

// ─── Monthly limit ────────────────────────────────────────────────────────────

it('recognises the monthly request limit', function (): void {
    $mockClient = new MockClient([
        SubmitEquipmentRequest::class => MockResponse::make([
            'type'     => 'ACCESS_DENIED',
            'title'    => 'User is not authorized to access this resource',
            'detail'   => 'You have reached the maximum allowed number of API calls (10000). Your usage counter will reset at the beginning of the next month in UTC.',
            'instance' => 'req-123',
            'status'   => 403,
        ], 403, ['Content-Type' => 'application/problem+json']),
    ]);

    try {
        driftClient($mockClient)->submitEquipmentRequest();
        $this->fail('No exception was thrown.');
    } catch (TestoApiException $e) {
        expect($e->getCode())->toBe(403)
            ->and($e->getMessage())->toBe('Testo API returned HTTP 403 when submitting device properties request')
            ->and($e->problemType)->toBe('ACCESS_DENIED')
            ->and($e->isQuotaExceeded())->toBeTrue();
    }
});

it('does not take any 403 for the monthly limit', function (): void {
    $mockClient = new MockClient([
        SubmitEquipmentRequest::class => MockResponse::make(['message' => 'Forbidden'], 403),
    ]);

    try {
        driftClient($mockClient)->submitEquipmentRequest();
        $this->fail('No exception was thrown.');
    } catch (TestoApiException $e) {
        expect($e->isQuotaExceeded())->toBeFalse()
            ->and($e->detail)->toBe('Forbidden');
    }
});

it('still builds the exception the way callers construct it directly', function (): void {
    $e = new TestoApiException('failed', 500);

    expect($e->problemType)->toBeNull()
        ->and($e->isQuotaExceeded())->toBeFalse();
});
