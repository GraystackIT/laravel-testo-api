<?php

declare(strict_types=1);

use Carbon\Carbon;
use GraystackIT\TestoCloud\Connectors\TestoDataConnector;
use GraystackIT\TestoCloud\Data\LoggerDevice;
use GraystackIT\TestoCloud\Data\MeasurementStatusResponse;
use GraystackIT\TestoCloud\Data\MeasurementSubmitResponse;
use GraystackIT\TestoCloud\Enums\AsyncRequestStatus;
use GraystackIT\TestoCloud\Exceptions\TestoApiException;
use GraystackIT\TestoCloud\Requests\CheckMeasurementStatusRequest;
use GraystackIT\TestoCloud\Requests\GetLoggersRequest;
use GraystackIT\TestoCloud\Requests\GetTokenRequest;
use GraystackIT\TestoCloud\Requests\SubmitMeasurementRequest;
use GraystackIT\TestoCloud\TestoCloudClient;
use GraystackIT\TestoCloud\TestoDataFileDownloader;
use Illuminate\Support\Facades\Cache;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeConnector(): TestoDataConnector
{
    return new TestoDataConnector('test-id', 'test-secret', 'eu', 'p');
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
        GetTokenRequest::class          => MockResponse::make(['IdToken' => 'tok-abc', 'expires_in' => 86400], 200),
        SubmitMeasurementRequest::class => MockResponse::make(['request_uuid' => 'uuid-123', 'status' => 'submitted'], 200),
    ]);

    $client   = makeClientWithMock($mockClient);
    $response = $client->submitMeasurementRequest(Carbon::parse('2024-01-01'), Carbon::parse('2024-01-02'));

    expect($response)->toBeInstanceOf(MeasurementSubmitResponse::class)
        ->and($response->requestUuid)->toBe('uuid-123')
        ->and($response->status)->toBe('submitted');

    $mockClient->assertSent(GetTokenRequest::class);
    $mockClient->assertSent(SubmitMeasurementRequest::class);
});

it('throws TestoApiException when submit measurement returns 401', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class          => MockResponse::make(['IdToken' => 'tok-abc', 'expires_in' => 86400], 200),
        SubmitMeasurementRequest::class => MockResponse::make(['error' => 'Unauthorized'], 401),
    ]);

    expect(fn () => makeClientWithMock($mockClient)->submitMeasurementRequest(
        Carbon::parse('2024-01-01'),
        Carbon::parse('2024-01-02')
    ))->toThrow(TestoApiException::class);
});

it('throws TestoApiException when submit response is missing request_uuid', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class          => MockResponse::make(['IdToken' => 'tok-abc', 'expires_in' => 86400], 200),
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
        GetTokenRequest::class              => MockResponse::make(['IdToken' => 'tok-abc', 'expires_in' => 86400], 200),
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
        GetTokenRequest::class              => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        CheckMeasurementStatusRequest::class => MockResponse::make(['status' => 'processing'], 200),
    ]);

    $response = makeClientWithMock($mockClient)->checkRequestStatus('uuid-456');

    expect($response->isProcessing())->toBeTrue()
        ->and($response->isCompleted())->toBeFalse()
        ->and($response->isFailed())->toBeFalse();
});

it('normalises capitalized API status values (e.g. "Completed" from real Testo API)', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class              => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
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
        GetTokenRequest::class              => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        CheckMeasurementStatusRequest::class => MockResponse::make(['status' => 'In Progress'], 200),
    ]);

    $response = makeClientWithMock($mockClient)->checkRequestStatus('uuid-in-progress');

    expect($response->isProcessing())->toBeTrue()
        ->and($response->isCompleted())->toBeFalse();
});

it('throws TestoApiException when check status returns 404', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class              => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        CheckMeasurementStatusRequest::class => MockResponse::make(['error' => 'Not found'], 404),
    ]);

    expect(fn () => makeClientWithMock($mockClient)->checkRequestStatus('missing-uuid'))
        ->toThrow(TestoApiException::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// getAllLoggers
// ──────────────────────────────────────────────────────────────────────────────

it('returns a list of LoggerDevice objects', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        GetLoggersRequest::class => MockResponse::make([
            'loggers' => [
                ['uuid' => 'logger-uuid-1', 'serial_no' => 'SN001'],
                ['uuid' => 'logger-uuid-2', 'serial_no' => 'SN002'],
            ],
        ], 200),
    ]);

    $loggers = makeClientWithMock($mockClient)->getAllLoggers();

    expect($loggers)->toHaveCount(2)
        ->and($loggers[0])->toBeInstanceOf(LoggerDevice::class)
        ->and($loggers[0]->uuid)->toBe('logger-uuid-1')
        ->and($loggers[0]->serialNo)->toBe('SN001')
        ->and($loggers[1]->uuid)->toBe('logger-uuid-2');
});

it('returns an empty array when loggers list is absent', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class   => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        GetLoggersRequest::class => MockResponse::make([], 200),
    ]);

    expect(makeClientWithMock($mockClient)->getAllLoggers())->toBe([]);
});

it('throws TestoApiException when get loggers returns 500', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class   => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        GetLoggersRequest::class => MockResponse::make(['error' => 'Internal Server Error'], 500),
    ]);

    expect(fn () => makeClientWithMock($mockClient)->getAllLoggers())
        ->toThrow(TestoApiException::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Token caching
// ──────────────────────────────────────────────────────────────────────────────

it('caches the access token and does not re-authenticate on second call', function () {
    Cache::flush();

    $mockClient = new MockClient([
        GetTokenRequest::class   => MockResponse::make(['IdToken' => 'cached-tok', 'expires_in' => 86400], 200),
        GetLoggersRequest::class => MockResponse::make(['loggers' => []], 200),
    ]);

    $connector = makeConnector();
    $connector->withMockClient($mockClient);
    $client = new TestoCloudClient($connector, new TestoDataFileDownloader());

    $client->getAllLoggers();
    $client->getAllLoggers();

    // Token request sent only once; second call uses the cache
    $mockClient->assertSentCount(3); // 1 token + 2 logger requests
});

it('throws TestoApiException when authentication returns 401', function () {
    Cache::flush();

    $mockClient = new MockClient([
        GetTokenRequest::class => MockResponse::make(['error' => 'Unauthorized'], 401),
    ]);

    expect(fn () => makeClientWithMock($mockClient)->getAllLoggers())
        ->toThrow(TestoApiException::class);
});

it('throws TestoApiException when authentication response has no token field', function () {
    Cache::flush();

    $mockClient = new MockClient([
        GetTokenRequest::class   => MockResponse::make(['some_other_field' => 'value'], 200),
        GetLoggersRequest::class => MockResponse::make(['loggers' => []], 200),
    ]);

    expect(fn () => makeClientWithMock($mockClient)->getAllLoggers())
        ->toThrow(TestoApiException::class);
});
