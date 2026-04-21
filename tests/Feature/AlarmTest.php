<?php

declare(strict_types=1);

use Carbon\Carbon;
use GraystackIT\TestoCloud\Connectors\TestoDataConnector;
use GraystackIT\TestoCloud\Data\AsyncStatusResponse;
use GraystackIT\TestoCloud\Data\AsyncSubmitResponse;
use GraystackIT\TestoCloud\Enums\AsyncRequestStatus;
use GraystackIT\TestoCloud\Exceptions\TestoApiException;
use GraystackIT\TestoCloud\Requests\CheckAlarmStatusRequest;
use GraystackIT\TestoCloud\Requests\GetTokenRequest;
use GraystackIT\TestoCloud\Requests\SubmitAlarmRequest;
use GraystackIT\TestoCloud\TestoCloudClient;
use GraystackIT\TestoCloud\TestoDataFileDownloader;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeAlarmClient(MockClient $mockClient): TestoCloudClient
{
    $connector = new TestoDataConnector('test-id', 'test-secret', 'eu', 'p');
    $connector->withMockClient($mockClient);

    return new TestoCloudClient($connector, new TestoDataFileDownloader());
}

// ──────────────────────────────────────────────────────────────────────────────
// submitAlarmRequest
// ──────────────────────────────────────────────────────────────────────────────

it('submits an alarm request and returns AsyncSubmitResponse', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class   => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        SubmitAlarmRequest::class => MockResponse::make(['request_uuid' => 'alarm-uuid-1', 'status' => 'submitted'], 200),
    ]);

    $response = makeAlarmClient($mockClient)->submitAlarmRequest(
        Carbon::parse('2024-01-01'),
        Carbon::parse('2024-01-02')
    );

    expect($response)->toBeInstanceOf(AsyncSubmitResponse::class)
        ->and($response->requestUuid)->toBe('alarm-uuid-1')
        ->and($response->status)->toBe(AsyncRequestStatus::Submitted);

    $mockClient->assertSent(SubmitAlarmRequest::class);
});

it('throws InvalidArgumentException when alarm from >= to', function () {
    $mockClient = new MockClient([]);

    expect(fn () => makeAlarmClient($mockClient)->submitAlarmRequest(
        Carbon::parse('2024-01-02'),
        Carbon::parse('2024-01-01')
    ))->toThrow(\InvalidArgumentException::class);
});

it('throws TestoApiException when alarm submit returns 401', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class    => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        SubmitAlarmRequest::class => MockResponse::make(['error' => 'Unauthorized'], 401),
    ]);

    expect(fn () => makeAlarmClient($mockClient)->submitAlarmRequest(
        Carbon::parse('2024-01-01'),
        Carbon::parse('2024-01-02')
    ))->toThrow(TestoApiException::class);
});

it('throws TestoApiException when alarm submit response is missing request_uuid', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class    => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        SubmitAlarmRequest::class => MockResponse::make(['status' => 'submitted'], 200),
    ]);

    expect(fn () => makeAlarmClient($mockClient)->submitAlarmRequest(
        Carbon::parse('2024-01-01'),
        Carbon::parse('2024-01-02')
    ))->toThrow(TestoApiException::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// checkAlarmStatus
// ──────────────────────────────────────────────────────────────────────────────

it('checks alarm status and returns AsyncStatusResponse', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class       => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        CheckAlarmStatusRequest::class => MockResponse::make([
            'status'       => 'Completed',
            'data_urls'    => ['https://s3.example.com/alarms.json.gz'],
            'metadata_url' => 'https://s3.example.com/alarms-meta.json',
        ], 200),
    ]);

    $response = makeAlarmClient($mockClient)->checkAlarmStatus('alarm-uuid-1');

    expect($response)->toBeInstanceOf(AsyncStatusResponse::class)
        ->and($response->status)->toBe(AsyncRequestStatus::Completed)
        ->and($response->isCompleted())->toBeTrue()
        ->and($response->isProcessing())->toBeFalse()
        ->and($response->isFailed())->toBeFalse()
        ->and($response->dataUrls)->toHaveCount(1)
        ->and($response->metadataUrl)->toBe('https://s3.example.com/alarms-meta.json');
});

it('normalises "In Progress" status to processing enum case', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class         => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        CheckAlarmStatusRequest::class => MockResponse::make(['status' => 'In Progress'], 200),
    ]);

    $response = makeAlarmClient($mockClient)->checkAlarmStatus('alarm-uuid-2');

    expect($response->isProcessing())->toBeTrue()
        ->and($response->isCompleted())->toBeFalse();
});

it('throws TestoApiException when alarm status check returns 404', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class         => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        CheckAlarmStatusRequest::class => MockResponse::make(['error' => 'Not found'], 404),
    ]);

    expect(fn () => makeAlarmClient($mockClient)->checkAlarmStatus('missing-uuid'))
        ->toThrow(TestoApiException::class);
});
