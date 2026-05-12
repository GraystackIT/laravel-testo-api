<?php

declare(strict_types=1);

use GraystackIT\TestoCloud\Connectors\TestoDataConnector;
use GraystackIT\TestoCloud\Data\AsyncStatusResponse;
use GraystackIT\TestoCloud\Data\AsyncSubmitResponse;
use GraystackIT\TestoCloud\Enums\AsyncRequestStatus;
use GraystackIT\TestoCloud\Exceptions\TestoApiException;
use GraystackIT\TestoCloud\Requests\CheckLocationStatusRequest;
use GraystackIT\TestoCloud\Requests\SubmitLocationRequest;
use GraystackIT\TestoCloud\TestoCloudClient;
use GraystackIT\TestoCloud\TestoDataFileDownloader;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeLocationClient(MockClient $mockClient): TestoCloudClient
{
    $connector = new TestoDataConnector('test-api-key', 'eu');
    $connector->withMockClient($mockClient);

    return new TestoCloudClient($connector, new TestoDataFileDownloader());
}

// ──────────────────────────────────────────────────────────────────────────────
// submitLocationRequest
// ──────────────────────────────────────────────────────────────────────────────

it('submits a location request and returns AsyncSubmitResponse', function () {
    $mockClient = new MockClient([
        SubmitLocationRequest::class => MockResponse::make(['request_uuid' => 'loc-uuid-1', 'status' => 'submitted'], 200),
    ]);

    $response = makeLocationClient($mockClient)->submitLocationRequest();

    expect($response)->toBeInstanceOf(AsyncSubmitResponse::class)
        ->and($response->requestUuid)->toBe('loc-uuid-1')
        ->and($response->status)->toBe(AsyncRequestStatus::Submitted);

    $mockClient->assertSent(SubmitLocationRequest::class);
});

it('submits a location request with custom format', function () {
    $mockClient = new MockClient([
        SubmitLocationRequest::class => MockResponse::make(['request_uuid' => 'loc-uuid-csv', 'status' => 'submitted'], 200),
    ]);

    $response = makeLocationClient($mockClient)->submitLocationRequest('CSV');

    expect($response->requestUuid)->toBe('loc-uuid-csv');
});

it('throws TestoApiException when location submit returns 401', function () {
    $mockClient = new MockClient([
        SubmitLocationRequest::class => MockResponse::make(['error' => 'Unauthorized'], 401),
    ]);

    expect(fn () => makeLocationClient($mockClient)->submitLocationRequest())
        ->toThrow(TestoApiException::class);
});

it('throws TestoApiException when location submit response is missing request_uuid', function () {
    $mockClient = new MockClient([
        SubmitLocationRequest::class => MockResponse::make(['status' => 'submitted'], 200),
    ]);

    expect(fn () => makeLocationClient($mockClient)->submitLocationRequest())
        ->toThrow(TestoApiException::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// checkLocationStatus
// ──────────────────────────────────────────────────────────────────────────────

it('checks location status and returns AsyncStatusResponse', function () {
    $mockClient = new MockClient([
        CheckLocationStatusRequest::class => MockResponse::make([
            'status'       => 'Completed',
            'data_urls'    => ['https://s3.example.com/locations.json.gz'],
            'metadata_url' => 'https://s3.example.com/locations-meta.json',
        ], 200),
    ]);

    $response = makeLocationClient($mockClient)->checkLocationStatus('loc-uuid-1');

    expect($response)->toBeInstanceOf(AsyncStatusResponse::class)
        ->and($response->status)->toBe(AsyncRequestStatus::Completed)
        ->and($response->isCompleted())->toBeTrue()
        ->and($response->dataUrls)->toHaveCount(1)
        ->and($response->metadataUrl)->toBe('https://s3.example.com/locations-meta.json');
});

it('returns processing status for location status check', function () {
    $mockClient = new MockClient([
        CheckLocationStatusRequest::class => MockResponse::make(['status' => 'In Progress'], 200),
    ]);

    $response = makeLocationClient($mockClient)->checkLocationStatus('loc-uuid-proc');

    expect($response->isProcessing())->toBeTrue()
        ->and($response->isCompleted())->toBeFalse()
        ->and($response->isFailed())->toBeFalse();
});

it('throws TestoApiException when location status check returns 404', function () {
    $mockClient = new MockClient([
        CheckLocationStatusRequest::class => MockResponse::make(['error' => 'Not found'], 404),
    ]);

    expect(fn () => makeLocationClient($mockClient)->checkLocationStatus('missing'))
        ->toThrow(TestoApiException::class);
});
