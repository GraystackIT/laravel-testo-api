<?php

declare(strict_types=1);

use Carbon\Carbon;
use GraystackIT\TestoCloud\Connectors\TestoDataConnector;
use GraystackIT\TestoCloud\Data\MeasurementStatusResponse;
use GraystackIT\TestoCloud\Data\MeasurementSubmitResponse;
use GraystackIT\TestoCloud\Enums\AsyncRequestStatus;
use GraystackIT\TestoCloud\Exceptions\TestoApiException;
use GraystackIT\TestoCloud\Requests\CheckMeasurementStatusRequest;
use GraystackIT\TestoCloud\Requests\SubmitMeasurementRequest;
use GraystackIT\TestoCloud\TestoCloudClient;
use GraystackIT\TestoCloud\TestoDataFileDownloader;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeConnector(): TestoDataConnector
{
    return new TestoDataConnector('test-api-key', 'eu');
}

function makeClientWithMock(MockClient $mockClient): TestoCloudClient
{
    $connector = makeConnector();
    $connector->withMockClient($mockClient);

    return new TestoCloudClient($connector, new TestoDataFileDownloader());
}

// ──────────────────────────────────────────────────────────────────────────────
// Container binding
// ──────────────────────────────────────────────────────────────────────────────

it('is resolved from the container', function () {
    expect(app(TestoCloudClient::class))->toBeInstanceOf(TestoCloudClient::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// submitMeasurementRequest
// ──────────────────────────────────────────────────────────────────────────────

it('submits a measurement request and returns a MeasurementSubmitResponse', function () {
    $mockClient = new MockClient([
        SubmitMeasurementRequest::class => MockResponse::make(['request_uuid' => 'uuid-123', 'status' => 'submitted'], 200),
    ]);

    $client   = makeClientWithMock($mockClient);
    $response = $client->submitMeasurementRequest(Carbon::parse('2024-01-01'), Carbon::parse('2024-01-02'));

    expect($response)->toBeInstanceOf(MeasurementSubmitResponse::class)
        ->and($response->requestUuid)->toBe('uuid-123')
        ->and($response->status)->toBe('submitted');

    $mockClient->assertSent(SubmitMeasurementRequest::class);
});

it('throws TestoApiException when submit measurement returns 401', function () {
    $mockClient = new MockClient([
        SubmitMeasurementRequest::class => MockResponse::make(['error' => 'Unauthorized'], 401),
    ]);

    expect(fn () => makeClientWithMock($mockClient)->submitMeasurementRequest(
        Carbon::parse('2024-01-01'),
        Carbon::parse('2024-01-02')
    ))->toThrow(TestoApiException::class);
});

it('throws TestoApiException when submit response is missing request_uuid', function () {
    $mockClient = new MockClient([
        SubmitMeasurementRequest::class => MockResponse::make(['status' => 'submitted'], 200),
    ]);

    expect(fn () => makeClientWithMock($mockClient)->submitMeasurementRequest(
        Carbon::parse('2024-01-01'),
        Carbon::parse('2024-01-02')
    ))->toThrow(TestoApiException::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// checkRequestStatus
// ──────────────────────────────────────────────────────────────────────────────

it('checks request status and returns a MeasurementStatusResponse', function () {
    $mockClient = new MockClient([
        CheckMeasurementStatusRequest::class => MockResponse::make([
            'status'       => 'completed',
            'data_urls'    => ['https://s3.example.com/data.json.gz'],
            'metadata_url' => 'https://s3.example.com/meta.json',
        ], 200),
    ]);

    $response = makeClientWithMock($mockClient)->checkRequestStatus('uuid-123');

    expect($response)->toBeInstanceOf(MeasurementStatusResponse::class)
        ->and($response->status)->toBe(AsyncRequestStatus::Completed)
        ->and($response->isCompleted())->toBeTrue()
        ->and($response->isProcessing())->toBeFalse()
        ->and($response->isFailed())->toBeFalse()
        ->and($response->dataUrls)->toHaveCount(1)
        ->and($response->metadataUrl)->toBe('https://s3.example.com/meta.json');
});

it('returns processing status correctly', function () {
    $mockClient = new MockClient([
        CheckMeasurementStatusRequest::class => MockResponse::make(['status' => 'processing'], 200),
    ]);

    $response = makeClientWithMock($mockClient)->checkRequestStatus('uuid-456');

    expect($response->isProcessing())->toBeTrue()
        ->and($response->isCompleted())->toBeFalse()
        ->and($response->isFailed())->toBeFalse();
});

it('normalises capitalized API status values (e.g. "Completed" from real Testo API)', function () {
    $mockClient = new MockClient([
        CheckMeasurementStatusRequest::class => MockResponse::make([
            'status'    => 'Completed',
            'data_urls' => ['https://s3.example.com/data.json.gz'],
        ], 200),
    ]);

    $response = makeClientWithMock($mockClient)->checkRequestStatus('uuid-789');

    expect($response->isCompleted())->toBeTrue()
        ->and($response->isProcessing())->toBeFalse()
        ->and($response->isFailed())->toBeFalse();
});

it('normalises "In Progress" API status to isProcessing', function () {
    $mockClient = new MockClient([
        CheckMeasurementStatusRequest::class => MockResponse::make(['status' => 'In Progress'], 200),
    ]);

    $response = makeClientWithMock($mockClient)->checkRequestStatus('uuid-in-progress');

    expect($response->isProcessing())->toBeTrue()
        ->and($response->isCompleted())->toBeFalse();
});

it('throws TestoApiException when check status returns 404', function () {
    $mockClient = new MockClient([
        CheckMeasurementStatusRequest::class => MockResponse::make(['error' => 'Not found'], 404),
    ]);

    expect(fn () => makeClientWithMock($mockClient)->checkRequestStatus('missing-uuid'))
        ->toThrow(TestoApiException::class);
});
