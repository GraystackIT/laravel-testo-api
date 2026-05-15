<?php

declare(strict_types=1);

use Carbon\Carbon;
use GraystackIT\TestoCloud\Connectors\TestoDataConnector;
use GraystackIT\TestoCloud\Data\AsyncStatusResponse;
use GraystackIT\TestoCloud\Data\AsyncSubmitResponse;
use GraystackIT\TestoCloud\Enums\AsyncRequestStatus;
use GraystackIT\TestoCloud\Exceptions\TestoApiException;
use GraystackIT\TestoCloud\Requests\CheckTaskStatusRequest;
use GraystackIT\TestoCloud\Requests\SubmitTaskRequest;
use GraystackIT\TestoCloud\TestoCloudClient;
use GraystackIT\TestoCloud\TestoDataFileDownloader;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeTaskClient(MockClient $mockClient): TestoCloudClient
{
    $connector = new TestoDataConnector(config('testo.api_key'), config('testo.region'));
    $connector->withMockClient($mockClient);

    return new TestoCloudClient($connector, new TestoDataFileDownloader());
}

// ──────────────────────────────────────────────────────────────────────────────
// submitTaskRequest
// ──────────────────────────────────────────────────────────────────────────────

it('submits a task request and returns AsyncSubmitResponse', function () {
    $mockClient = new MockClient([
        SubmitTaskRequest::class => MockResponse::make(['request_uuid' => 'task-uuid-1', 'status' => 'submitted'], 200),
    ]);

    $response = makeTaskClient($mockClient)->submitTaskRequest(
        Carbon::parse('2024-06-01'),
        Carbon::parse('2024-06-30')
    );

    expect($response)->toBeInstanceOf(AsyncSubmitResponse::class)
        ->and($response->requestUuid)->toBe('task-uuid-1')
        ->and($response->status)->toBe(AsyncRequestStatus::Submitted);

    $mockClient->assertSent(SubmitTaskRequest::class);
});

it('throws InvalidArgumentException when task from >= to', function () {
    $mockClient = new MockClient([]);

    expect(fn () => makeTaskClient($mockClient)->submitTaskRequest(
        Carbon::parse('2024-06-30'),
        Carbon::parse('2024-06-01')
    ))->toThrow(\InvalidArgumentException::class);
});

it('throws TestoApiException when task submit returns 403', function () {
    $mockClient = new MockClient([
        SubmitTaskRequest::class => MockResponse::make(['error' => 'Forbidden'], 403),
    ]);

    expect(fn () => makeTaskClient($mockClient)->submitTaskRequest(
        Carbon::parse('2024-06-01'),
        Carbon::parse('2024-06-30')
    ))->toThrow(TestoApiException::class);
});

it('throws TestoApiException when task submit response is missing request_uuid', function () {
    $mockClient = new MockClient([
        SubmitTaskRequest::class => MockResponse::make(['status' => 'submitted'], 200),
    ]);

    expect(fn () => makeTaskClient($mockClient)->submitTaskRequest(
        Carbon::parse('2024-06-01'),
        Carbon::parse('2024-06-30')
    ))->toThrow(TestoApiException::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// checkTaskStatus
// ──────────────────────────────────────────────────────────────────────────────

it('checks task status and returns AsyncStatusResponse', function () {
    $mockClient = new MockClient([
        CheckTaskStatusRequest::class => MockResponse::make([
            'status'    => 'completed',
            'data_urls' => ['https://s3.example.com/tasks.json.gz'],
        ], 200),
    ]);

    $response = makeTaskClient($mockClient)->checkTaskStatus('task-uuid-1');

    expect($response)->toBeInstanceOf(AsyncStatusResponse::class)
        ->and($response->status)->toBe(AsyncRequestStatus::Completed)
        ->and($response->isCompleted())->toBeTrue()
        ->and($response->dataUrls)->toHaveCount(1);
});

it('returns failed status when task request fails', function () {
    $mockClient = new MockClient([
        CheckTaskStatusRequest::class => MockResponse::make([
            'status' => 'failed',
            'error'  => 'Internal processing error',
        ], 200),
    ]);

    $response = makeTaskClient($mockClient)->checkTaskStatus('task-uuid-fail');

    expect($response->isFailed())->toBeTrue()
        ->and($response->error)->toBe('Internal processing error');
});

it('throws TestoApiException when task status check returns 500', function () {
    $mockClient = new MockClient([
        CheckTaskStatusRequest::class => MockResponse::make(['error' => 'Server Error'], 500),
    ]);

    expect(fn () => makeTaskClient($mockClient)->checkTaskStatus('task-uuid-1'))
        ->toThrow(TestoApiException::class);
});
