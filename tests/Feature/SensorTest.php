<?php

declare(strict_types=1);

use GraystackIT\TestoCloud\Connectors\TestoDataConnector;
use GraystackIT\TestoCloud\Data\AsyncStatusResponse;
use GraystackIT\TestoCloud\Data\AsyncSubmitResponse;
use GraystackIT\TestoCloud\Enums\AsyncRequestStatus;
use GraystackIT\TestoCloud\Exceptions\TestoApiException;
use GraystackIT\TestoCloud\Requests\CheckSensorStatusRequest;
use GraystackIT\TestoCloud\Requests\SubmitSensorStatusRequest;
use GraystackIT\TestoCloud\TestoCloudClient;
use GraystackIT\TestoCloud\TestoDataFileDownloader;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeSensorClient(MockClient $mockClient): TestoCloudClient
{
    $connector = new TestoDataConnector('test-api-key', 'eu');
    $connector->withMockClient($mockClient);

    return new TestoCloudClient($connector, new TestoDataFileDownloader());
}

// ──────────────────────────────────────────────────────────────────────────────
// submitSensorStatusRequest
// ──────────────────────────────────────────────────────────────────────────────

it('submits a device status request and returns AsyncSubmitResponse', function () {
    $mockClient = new MockClient([
        SubmitSensorStatusRequest::class => MockResponse::make(['request_uuid' => 'sensor-uuid-1', 'status' => 'submitted'], 200),
    ]);

    $response = makeSensorClient($mockClient)->submitSensorStatusRequest();

    expect($response)->toBeInstanceOf(AsyncSubmitResponse::class)
        ->and($response->requestUuid)->toBe('sensor-uuid-1')
        ->and($response->status)->toBe(AsyncRequestStatus::Submitted);

    $mockClient->assertSent(SubmitSensorStatusRequest::class);
});

it('throws TestoApiException when device status submit returns 401', function () {
    $mockClient = new MockClient([
        SubmitSensorStatusRequest::class => MockResponse::make(['error' => 'Unauthorized'], 401),
    ]);

    expect(fn () => makeSensorClient($mockClient)->submitSensorStatusRequest())
        ->toThrow(TestoApiException::class);
});

it('throws TestoApiException when device status submit response is missing request_uuid', function () {
    $mockClient = new MockClient([
        SubmitSensorStatusRequest::class => MockResponse::make(['status' => 'submitted'], 200),
    ]);

    expect(fn () => makeSensorClient($mockClient)->submitSensorStatusRequest())
        ->toThrow(TestoApiException::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// checkSensorStatus
// ──────────────────────────────────────────────────────────────────────────────

it('checks device status and returns AsyncStatusResponse', function () {
    $mockClient = new MockClient([
        CheckSensorStatusRequest::class => MockResponse::make([
            'status'    => 'completed',
            'data_urls' => [
                'https://s3.example.com/sensors.json.gz',
                'https://s3.example.com/sensors-2.json.gz',
            ],
        ], 200),
    ]);

    $response = makeSensorClient($mockClient)->checkSensorStatus('sensor-uuid-1');

    expect($response)->toBeInstanceOf(AsyncStatusResponse::class)
        ->and($response->status)->toBe(AsyncRequestStatus::Completed)
        ->and($response->isCompleted())->toBeTrue()
        ->and($response->dataUrls)->toHaveCount(2)
        ->and($response->metadataUrl)->toBeNull()
        ->and($response->error)->toBeNull();
});

it('returns processing status for device status check', function () {
    $mockClient = new MockClient([
        CheckSensorStatusRequest::class => MockResponse::make(['status' => 'In Progress'], 200),
    ]);

    $response = makeSensorClient($mockClient)->checkSensorStatus('sensor-uuid-proc');

    expect($response->isProcessing())->toBeTrue()
        ->and($response->isCompleted())->toBeFalse()
        ->and($response->isFailed())->toBeFalse();
});

it('throws TestoApiException when device status check returns 500', function () {
    $mockClient = new MockClient([
        CheckSensorStatusRequest::class => MockResponse::make(['error' => 'Server Error'], 500),
    ]);

    expect(fn () => makeSensorClient($mockClient)->checkSensorStatus('sensor-uuid-1'))
        ->toThrow(TestoApiException::class);
});
