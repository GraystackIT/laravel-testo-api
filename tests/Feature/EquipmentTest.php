<?php

declare(strict_types=1);

use GraystackIT\TestoCloud\Connectors\TestoDataConnector;
use GraystackIT\TestoCloud\Data\AsyncStatusResponse;
use GraystackIT\TestoCloud\Data\AsyncSubmitResponse;
use GraystackIT\TestoCloud\Enums\AsyncRequestStatus;
use GraystackIT\TestoCloud\Exceptions\TestoApiException;
use GraystackIT\TestoCloud\Requests\CheckEquipmentStatusRequest;
use GraystackIT\TestoCloud\Requests\GetTokenRequest;
use GraystackIT\TestoCloud\Requests\SubmitEquipmentRequest;
use GraystackIT\TestoCloud\TestoCloudClient;
use GraystackIT\TestoCloud\TestoDataFileDownloader;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeEquipmentClient(MockClient $mockClient): TestoCloudClient
{
    $connector = new TestoDataConnector('test-id', 'test-secret', 'eu', 'p');
    $connector->withMockClient($mockClient);

    return new TestoCloudClient($connector, new TestoDataFileDownloader());
}

// ──────────────────────────────────────────────────────────────────────────────
// submitEquipmentRequest
// ──────────────────────────────────────────────────────────────────────────────

it('submits an equipment request and returns AsyncSubmitResponse', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class      => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        SubmitEquipmentRequest::class => MockResponse::make(['request_uuid' => 'equip-uuid-1', 'status' => 'Submitted'], 200),
    ]);

    $response = makeEquipmentClient($mockClient)->submitEquipmentRequest();

    expect($response)->toBeInstanceOf(AsyncSubmitResponse::class)
        ->and($response->requestUuid)->toBe('equip-uuid-1')
        ->and($response->status)->toBe(AsyncRequestStatus::Submitted);

    $mockClient->assertSent(SubmitEquipmentRequest::class);
});

it('submits an equipment request with custom format', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class        => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        SubmitEquipmentRequest::class => MockResponse::make(['request_uuid' => 'equip-uuid-csv', 'status' => 'submitted'], 200),
    ]);

    $response = makeEquipmentClient($mockClient)->submitEquipmentRequest('CSV');

    expect($response->requestUuid)->toBe('equip-uuid-csv');
});

it('throws TestoApiException when equipment submit returns 401', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class        => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        SubmitEquipmentRequest::class => MockResponse::make(['error' => 'Unauthorized'], 401),
    ]);

    expect(fn () => makeEquipmentClient($mockClient)->submitEquipmentRequest())
        ->toThrow(TestoApiException::class);
});

it('throws TestoApiException when equipment submit response is missing request_uuid', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class        => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        SubmitEquipmentRequest::class => MockResponse::make(['status' => 'submitted'], 200),
    ]);

    expect(fn () => makeEquipmentClient($mockClient)->submitEquipmentRequest())
        ->toThrow(TestoApiException::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// checkEquipmentStatus
// ──────────────────────────────────────────────────────────────────────────────

it('checks equipment status and returns AsyncStatusResponse', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class           => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        CheckEquipmentStatusRequest::class => MockResponse::make([
            'status'       => 'Completed',
            'data_urls'    => ['https://s3.example.com/equipment.json.gz'],
            'metadata_url' => 'https://s3.example.com/equipment-meta.json',
        ], 200),
    ]);

    $response = makeEquipmentClient($mockClient)->checkEquipmentStatus('equip-uuid-1');

    expect($response)->toBeInstanceOf(AsyncStatusResponse::class)
        ->and($response->status)->toBe(AsyncRequestStatus::Completed)
        ->and($response->isCompleted())->toBeTrue()
        ->and($response->dataUrls)->toHaveCount(1)
        ->and($response->metadataUrl)->toBe('https://s3.example.com/equipment-meta.json');
});

it('throws TestoApiException when equipment status check returns 404', function () {
    $mockClient = new MockClient([
        GetTokenRequest::class             => MockResponse::make(['IdToken' => 'tok', 'expires_in' => 86400], 200),
        CheckEquipmentStatusRequest::class => MockResponse::make(['error' => 'Not found'], 404),
    ]);

    expect(fn () => makeEquipmentClient($mockClient)->checkEquipmentStatus('missing'))
        ->toThrow(TestoApiException::class);
});
