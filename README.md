# laravel-testo-cloud

A Laravel package for the **Testo Saveris Data API**, built on [Saloon 4](https://docs.saloon.dev/).

Retrieve historical temperature and humidity measurements from Testo IoT data loggers, list connected devices, and download measurement data files — all with a clean, Laravel-native interface.

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12

## Installation

```bash
composer require graystackit/laravel-testo-api
```

Laravel auto-discovers the service provider. Then publish the config file:

```bash
php artisan vendor:publish --tag=testo-cloud-config
```

## Configuration

Add the following to your `.env` file:

```env
TESTO_CLIENT_ID=your-client-id
TESTO_CLIENT_SECRET=your-client-secret

# Optional — defaults shown
TESTO_REGION=eu           # eu | am | ap
TESTO_ENVIRONMENT=p       # p (production) | i (integration/testing)
TESTO_HTTP_TIMEOUT=30
TESTO_DOWNLOAD_TIMEOUT=120
TESTO_TOKEN_CACHE_TTL_BUFFER=60
```

Obtain your credentials from your [Testo Saveris account](https://www.testo.com/).

## Usage

Resolve `TestoCloudClient` from the container or inject it via dependency injection.

### List all logger devices

```php
use GraystackIT\TestoCloud\TestoCloudClient;

$client = app(TestoCloudClient::class);

$loggers = $client->getAllLoggers();

foreach ($loggers as $logger) {
    echo $logger->uuid;
    echo $logger->serialNo;
}
```

### Submit a measurement data request

Measurement retrieval is asynchronous. First submit a request for a date range, then poll until complete.

```php
use Carbon\Carbon;

$response = $client->submitMeasurementRequest(
    from: Carbon::parse('2025-01-01'),
    to:   Carbon::parse('2025-01-31'),
);

echo $response->requestUuid; // store this for polling
echo $response->status;
```

> `$from` must be strictly before `$to` — an `InvalidArgumentException` is thrown otherwise.

### Poll request status

```php
$status = $client->checkRequestStatus($response->requestUuid);

if ($status->isCompleted()) {
    foreach ($status->dataUrls as $url) {
        $content = $client->downloadDataFile($url);
        // parse NDJSON content...
    }
}

if ($status->isFailed()) {
    echo $status->error;
}
```

Helper methods on `MeasurementStatusResponse`: `isCompleted()`, `isProcessing()`, `isFailed()`.

### Download a measurement data file

Files may be gzip-compressed. The package decompresses them automatically.

```php
$content = $client->downloadDataFile($url);
```

### Parse NDJSON measurement data

```php
use GraystackIT\TestoCloud\Parsers\MeasurementNdjsonParser;

$parser = new MeasurementNdjsonParser();
$measurements = $parser->parse($content);

// [['timestamp' => '...', 'temperature' => 21.5, 'humidity' => 55.0], ...]
```

## Data Objects

| Class | Properties |
|---|---|
| `LoggerDevice` | `uuid`, `serialNo` |
| `MeasurementSubmitResponse` | `requestUuid`, `status` |
| `MeasurementStatusResponse` | `status`, `dataUrls[]`, `metadataUrl`, `error`, `isCompleted()`, `isProcessing()`, `isFailed()` |

## Error Handling

All client methods throw `GraystackIT\TestoCloud\Exceptions\TestoApiException` on failure (extends `RuntimeException`).

```php
use GraystackIT\TestoCloud\Exceptions\TestoApiException;

try {
    $loggers = $client->getAllLoggers();
} catch (TestoApiException $e) {
    // HTTP errors, authentication failures, unexpected API responses
    logger()->error($e->getMessage());
}
```

`submitMeasurementRequest()` additionally throws `\InvalidArgumentException` if `$from >= $to`.

If `TESTO_CLIENT_ID` or `TESTO_CLIENT_SECRET` is not set, a `\RuntimeException` is thrown when the client is first resolved from the container.

## Testing

```bash
composer test
```

Tests use Saloon's `MockClient` — no real API calls are made.

## License

MIT
