<?php

declare(strict_types=1);

use GraystackIT\TestoCloud\Connectors\TestoDataConnector;
use GraystackIT\TestoCloud\Data\AsyncStatusResponse;
use GraystackIT\TestoCloud\Data\AsyncSubmitResponse;
use GraystackIT\TestoCloud\Enums\AsyncRequestStatus;
use GraystackIT\TestoCloud\Exceptions\TestoApiException;
use GraystackIT\TestoCloud\Requests\CheckMeasuringObjectStatusRequest;
use GraystackIT\TestoCloud\Requests\SubmitMeasuringObjectRequest;
use GraystackIT\TestoCloud\TestoCloudClient;
use GraystackIT\TestoCloud\TestoDataFileDownloader;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeMeasuringObjectClient(MockClient $mockClient): TestoCloudClient
{
    $connector = new TestoDataConnector(config('testo.api_key'), config('testo.region'));
    $connector->withMockClient($mockClient);

    return new TestoCloudClient($connector, new TestoDataFileDownloader());
}

// ──────────────────────────────────────────────────────────────────────────────
// submitMeasuringObjectRequest
// ──────────────────────────────────────────────────────────────────────────────

it('submits a measuring object request and returns AsyncSubmitResponse', function () {
    $mockClient = new MockClient([
        SubmitMeasuringObjectRequest::class => MockResponse::make(['request_uuid' => 'mo-uuid-1', 'status' => 'submitted'], 200),
    ]);

    $response = makeMeasuringObjectClient($mockClient)->submitMeasuringObjectRequest();

    expect($response)->toBeInstanceOf(AsyncSubmitResponse::class)
        ->and($response->requestUuid)->toBe('mo-uuid-1')
        ->and($response->status)->toBe(AsyncRequestStatus::Submitted);

    $mockClient->assertSent(SubmitMeasuringObjectRequest::class);
});

it('submits a measuring object request with custom format', function () {
    $mockClient = new MockClient([
        SubmitMeasuringObjectRequest::class => MockResponse::make(['request_uuid' => 'mo-uuid-csv', 'status' => 'submitted'], 200),
    ]);

    $response = makeMeasuringObjectClient($mockClient)->submitMeasuringObjectRequest('CSV');

    expect($response->requestUuid)->toBe('mo-uuid-csv');
});

it('throws TestoApiException when measuring object submit returns 401', function () {
    $mockClient = new MockClient([
        SubmitMeasuringObjectRequest::class => MockResponse::make(['error' => 'Unauthorized'], 401),
    ]);

    expect(fn () => makeMeasuringObjectClient($mockClient)->submitMeasuringObjectRequest())
        ->toThrow(TestoApiException::class);
});

it('throws TestoApiException when measuring object submit response is missing request_uuid', function () {
    $mockClient = new MockClient([
        SubmitMeasuringObjectRequest::class => MockResponse::make(['status' => 'submitted'], 200),
    ]);

    expect(fn () => makeMeasuringObjectClient($mockClient)->submitMeasuringObjectRequest())
        ->toThrow(TestoApiException::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// checkMeasuringObjectStatus
// ──────────────────────────────────────────────────────────────────────────────

it('checks measuring object status and returns AsyncStatusResponse', function () {
    $mockClient = new MockClient([
        CheckMeasuringObjectStatusRequest::class => MockResponse::make([
            'status'       => 'Completed',
            'data_urls'    => ['https://s3.example.com/mo.json.gz'],
            'metadata_url' => 'https://s3.example.com/mo-meta.json',
        ], 200),
    ]);

    $response = makeMeasuringObjectClient($mockClient)->checkMeasuringObjectStatus('mo-uuid-1');

    expect($response)->toBeInstanceOf(AsyncStatusResponse::class)
        ->and($response->status)->toBe(AsyncRequestStatus::Completed)
        ->and($response->isCompleted())->toBeTrue()
        ->and($response->dataUrls)->toHaveCount(1)
        ->and($response->metadataUrl)->toBe('https://s3.example.com/mo-meta.json');
});

it('returns processing status for measuring object status check', function () {
    $mockClient = new MockClient([
        CheckMeasuringObjectStatusRequest::class => MockResponse::make(['status' => 'Submitted'], 200),
    ]);

    $response = makeMeasuringObjectClient($mockClient)->checkMeasuringObjectStatus('mo-uuid-proc');

    expect($response->isProcessing())->toBeTrue();
});

it('throws TestoApiException when measuring object status check returns 404', function () {
    $mockClient = new MockClient([
        CheckMeasuringObjectStatusRequest::class => MockResponse::make(['error' => 'Not found'], 404),
    ]);

    expect(fn () => makeMeasuringObjectClient($mockClient)->checkMeasuringObjectStatus('missing'))
        ->toThrow(TestoApiException::class);
});
