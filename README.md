# graystackit/laravel-testo-cloud

A Laravel package for the **Testo Smart Connect API**, built on [Saloon 4](https://docs.saloon.dev/).

Retrieve historical measurements, alarm events, device properties, device health status, measuring object configurations, and location hierarchies from Testo Smart Connect — all with a clean, Laravel-native interface.

## Requirements

- PHP 8.3+
- Laravel 11, 12, or 13

## Installation

```bash
composer require graystackit/laravel-testo-api
```

Laravel auto-discovers the service provider. Then publish the config file:

```bash
php artisan vendor:publish --tag=testo-config
```

## Configuration

Add the following to your `.env` file:

```env
TESTO_API_KEY=your-api-key

# Optional — defaults shown
TESTO_REGION=eu                # eu | am | ap
TESTO_HTTP_TIMEOUT=30
TESTO_DOWNLOAD_TIMEOUT=120

# Store fetched measurements in the database (default: true)
TESTO_STORE_MEASUREMENTS=true

# Polling behaviour for testo:fetch-measurements
TESTO_POLL_INTERVAL=5          # seconds between status checks
TESTO_POLL_MAX_ATTEMPTS=60     # give up after this many checks (5 min total by default)
TESTO_DEFAULT_FROM_DAYS=7      # days to look back when --from is omitted
```

Generate your API key from the Smart Connect home page. Keys are valid for up to one year.

## Async Workflow

All data-export endpoints use the same two-step async pattern:

1. **Submit** a POST request → receive a `request_uuid`
2. **Poll** a GET request with that UUID until status is `Completed`
3. **Download** each URL in `dataUrls` using `downloadDataFile()`

```
submit() → Submitted → Processing → Completed
                                 ↓
                               Failed
```

An unrecognized status string from Testo (e.g. a future state not listed above) maps to
`AsyncRequestStatus::Unknown` rather than being silently treated as still-processing — check
`$response->rawStatus` for the exact string Testo returned in that case.

## Usage

Resolve `TestoCloudClient` from the container or inject via constructor.

```php
use GraystackIT\TestoCloud\TestoCloudClient;

$client = app(TestoCloudClient::class);
```

---

### Measurements  `POST /v2/measurements`  •  `GET /v2/measurements/{uuid}`

```php
use Carbon\Carbon;

// 1. Submit
$submit = $client->submitMeasurementRequest(
    from:   Carbon::parse('2025-01-01'),
    to:     Carbon::parse('2025-01-31'),
    format: 'JSON',  // optional, default 'JSON'
    odata:  ['$filter' => "serial_no eq '12345678'"],  // optional — see OData filtering below
);

echo $submit->requestUuid;

// 2. Poll
$status = $client->checkRequestStatus($submit->requestUuid);

if ($status->isCompleted()) {
    foreach ($status->dataUrls as $url) {
        $content = $client->downloadDataFile($url);
        // parse with MeasurementNdjsonParser...
    }
}
```

> `$from` must be strictly before `$to` — an `\InvalidArgumentException` is thrown otherwise.

#### Parse NDJSON measurement data

```php
use GraystackIT\TestoCloud\Parsers\MeasurementNdjsonParser;

$measurements = (new MeasurementNdjsonParser())->parse($content);
// [['timestamp' => '...', 'temperature' => 21.5, 'humidity' => 55.0,
//   'temperature_unit' => '°C', 'humidity_unit' => '%RH',
//   'temperature_extension' => null, 'humidity_extension' => null], ...]
```

> `physical_extension` disambiguates rows that share the same `physical_property_name` (e.g. an
> external-probe temperature reading vs. the logger's own internal sensor) — read
> `temperature_extension`/`humidity_extension` when a logger reports more than one of each.

#### Measurements of one logger

`/v2/measurements` returns the readings of **every** logger of the account, and the parser merges
rows by timestamp — so without narrowing, readings of different loggers at the same moment
overwrite each other. Measurement rows name the **sensor** (`sensor_uuid`, and the sensor's
`serial_no`), never the device. Get a logger's sensors from Device Properties and pass them:

```php
$devices = $client->parseDeviceList($devicePropertiesContent);   // LoggerDevice[]
$logger  = $devices[0];
$logger->sensorUuids;      // ['sen-...', ...] — one per channel's sensor
$logger->sensorSerialNos;  // matching serial numbers

$measurements = (new MeasurementNdjsonParser())->parse($content, sensorUuids: $logger->sensorUuids);
```

The older `$deviceUuid` argument is still honoured for rows that carry a `device_uuid`, but Testo's
measurement rows don't. When a device filter is given and cannot be applied, every row is kept and
a warning is logged (`device filter could not be applied`) — pass the sensor UUIDs instead. To
avoid downloading other loggers' data at all, filter the request itself with OData, e.g.
`['$filter' => "serial_no eq '12345678'"]`; each submit counts against the monthly limit, though.

---

### Alarms  `POST /v3/alarms`  •  `GET /v3/alarms/{uuid}`

Retrieve historical alarm events for the configured account.

```php
use Carbon\Carbon;

// 1. Submit
$submit = $client->submitAlarmRequest(
    from: Carbon::parse('2025-01-01'),
    to:   Carbon::parse('2025-01-31'),
);

// 2. Poll
$status = $client->checkAlarmStatus($submit->requestUuid);

if ($status->isCompleted()) {
    foreach ($status->dataUrls as $url) {
        $content = $client->downloadDataFile($url);
    }
}
```

---

### Tasks (deprecated)  `POST /v3/tasks`  •  `GET /v3/tasks/{uuid}`

> ⚠️ **Deprecated — this endpoint does not exist.** Testo's OpenAPI reference
> (`smart-connect-api/api_reference/swagger.json`) lists exactly six endpoint pairs — device
> properties, device status, measurements, alarms, locations and measuring objects — and its
> changelog never added tasks. `submitTaskRequest()`/`checkTaskStatus()` are kept only so existing
> callers keep compiling and will be removed in the next major version. Don't call them.

---

### Device Properties  `POST /v3/devices/properties`  •  `GET /v3/devices/properties/{uuid}`

Retrieve device metadata including serial numbers, model codes, firmware versions,
calibration status, and equipment relationships.

> No date range required — returns current configuration.

Device properties hold one row per **channel**, with `device_uuid` and `sensor_uuid` — the only
place that links a logger to the sensors its measurements and alarms are reported under.
`parseDeviceList()` returns one `LoggerDevice` per device with all its `sensorUuids`.

```php
$submit = $client->submitEquipmentRequest(format: 'JSON');  // format optional

$status = $client->checkEquipmentStatus($submit->requestUuid);

if ($status->isCompleted()) {
    foreach ($status->dataUrls as $url) {
        $content = $client->downloadDataFile($url);
    }
}
```

---

### Device Status  `POST /v4/devices/status`  •  `GET /v4/devices/status/{uuid}`

One row per device: `serial_no`, `model_code`, `production_date`, `connection_type`
(`WIFI`/`ETHERNET`/`UNKNOWN`), `radio_level_percent`, `battery_level_percent`,
`battery_temperature`, `is_powersupply_on`, `last_communication_time`, `next_communication_time`
and `status_time`.

> No date range required — returns current status snapshot.
>
> v4 is the only version in Testo's API reference; it replaced v3. Compared with v3 it renames
> `last_communication`/`next_communication` to `..._time` and drops `fw_code`/`fw_version`, which
> Device Properties still returns.

```php
$submit = $client->submitSensorStatusRequest();

$status = $client->checkSensorStatus($submit->requestUuid);

if ($status->isCompleted()) {
    $content = $client->downloadDataFile($status->dataUrls[0]);
}
```

---

### Measuring Objects  `POST /v1/measuring-objects`  •  `GET /v1/measuring-objects/{uuid}`

Retrieve measuring-object configurations including `customer_uuid`,
`customer_site`, `product_family_id`, measurement settings, and channel assignments.

> No date range required — returns current configuration.

```php
$submit = $client->submitMeasuringObjectRequest();

$status = $client->checkMeasuringObjectStatus($submit->requestUuid);
```

---

### Locations  `POST /v1/locations`  •  `GET /v1/locations/{uuid}`

Retrieve the location hierarchy (sites, zones, rooms) associated with the account.

> No date range required — returns current location structure.

```php
$submit = $client->submitLocationRequest(format: 'JSON');  // format optional

$status = $client->checkLocationStatus($submit->requestUuid);

if ($status->isCompleted()) {
    foreach ($status->dataUrls as $url) {
        $content = $client->downloadDataFile($url);
    }

    // Optional metadata file
    if ($status->metadataUrl) {
        $meta = $client->downloadDataFile($status->metadataUrl);
    }
}
```

---

## Data Objects

### Submission response — `AsyncSubmitResponse`

Returned by every `submit*()` method.

| Property | Type | Description |
|---|---|---|
| `requestUuid` | `string` | UUID to use when polling status |
| `status` | `AsyncRequestStatus` | Enum — `Submitted` on initial response |
| `rawStatus` | `string` | The status string exactly as Testo returned it |

### Status response — `AsyncStatusResponse`

Returned by every `check*Status()` method.

| Property | Type | Description |
|---|---|---|
| `status` | `AsyncRequestStatus` | Current state (see enum below) |
| `dataUrls` | `string[]` | Download URLs (populated when completed) |
| `metadataUrl` | `?string` | Metadata file URL (populated when completed) |
| `error` | `?string` | Error message (populated when failed) |
| `rawStatus` | `string` | The status string exactly as Testo returned it |

Helper methods: `isCompleted()`, `isProcessing()`, `isFailed()`

`error` is read from Testo's `message`, falling back to an `error` key.

### `AsyncRequestStatus` enum

```php
use GraystackIT\TestoCloud\Enums\AsyncRequestStatus;

AsyncRequestStatus::Submitted   // initial acknowledgment
AsyncRequestStatus::Processing  // API is preparing data ("In Progress" normalised)
AsyncRequestStatus::Completed   // data ready for download
AsyncRequestStatus::Failed      // Testo's "Error" — see $response->error
AsyncRequestStatus::Unknown     // any status string Testo returns that isn't one of the above
```

The enum normalises all API status strings: Testo answers `"Completed"`, `"In Progress"` (→
`Processing`) or `"Error"` (→ `Failed`). An
unrecognized string never falls back to `Processing` — it maps to `Unknown` instead, so a
future new terminal status can't get stuck looking like it's still in progress. Read
`$response->rawStatus` to see the original string in that case.

### Measurement-specific objects

Used by `submitMeasurementRequest()`/`checkRequestStatus()` (measurements only — kept as their
own classes since measurements has its own endpoint/status vocabulary separate from the shared
Alarms/Tasks/Equipment/Sensors/Measuring-Objects modules, but shaped the same way):

| Class | Properties |
|---|---|
| `MeasurementSubmitResponse` | `requestUuid`, `status` (`AsyncRequestStatus`), `rawStatus` |
| `MeasurementStatusResponse` | `status`, `dataUrls[]`, `metadataUrl`, `error`, `rawStatus`, helpers |

---

## Error Handling

All client methods throw `GraystackIT\TestoCloud\Exceptions\TestoApiException` on failure.

```php
use GraystackIT\TestoCloud\Exceptions\TestoApiException;

try {
    $submit = $client->submitAlarmRequest(Carbon::parse('2025-01-01'), Carbon::parse('2025-02-01'));
} catch (TestoApiException $e) {
    // HTTP errors, unexpected API responses
    logger()->error($e->getMessage(), ['code' => $e->getCode(), 'detail' => $e->detail]);

    if ($e->isQuotaExceeded()) {
        // The account used its 10,000 monthly POST requests; access returns on the 1st (UTC).
    }
}
```

Testo answers errors as `application/problem+json`; the exception carries its `problemType`
(e.g. `ACCESS_DENIED`), `problemTitle` and `detail`.

### Fair usage limit

Each account may send **10,000 API requests per calendar month** (UTC). Only `submit*()` (POST)
calls count — polling with `check*Status()` and downloading result files do not. Prefer one wide
time range over many small, repeated requests, and fetch device metadata about once a day.

`submit*Request()` methods that accept a date range additionally throw `\InvalidArgumentException` when `$from >= $to`,
and every `submit*Request()` throws it for an OData option other than `$filter`, `$select` or `$orderby`.

### OData filtering

Every `submit*Request()` takes an optional `odata` array, sent as the request's `odata` object:

```php
$client->submitAlarmRequest($from, $to, odata: ['$filter' => "alarm_status eq 'Alarm'"]);
$client->submitEquipmentRequest(odata: ['$select' => 'device_uuid,sensor_uuid,sensor_serial_no']);
```

Field names differ per endpoint — the sensor's serial number is `serial_no` on measurements and
alarms but `sensor_serial_no` on device properties — and an unknown field is answered with
400 Bad Request.

If `TESTO_API_KEY` is not configured, a `\RuntimeException` is thrown on container resolution.

---

## API Endpoint Reference

| Module | Submit | Check Status |
|---|---|---|
| Measurements | `POST /v2/measurements` | `GET /v2/measurements/{uuid}` |
| Alarms | `POST /v3/alarms` | `GET /v3/alarms/{uuid}` |
| Tasks (deprecated — not part of the API) | `POST /v3/tasks` | `GET /v3/tasks/{uuid}` |
| Device Properties | `POST /v3/devices/properties` | `GET /v3/devices/properties/{uuid}` |
| Device Status | `POST /v4/devices/status` | `GET /v4/devices/status/{uuid}` |
| Measuring Objects | `POST /v1/measuring-objects` | `GET /v1/measuring-objects/{uuid}` |
| Locations | `POST /v1/locations` | `GET /v1/locations/{uuid}` |

Base URL: `https://data-api.{region}.smartconnect.testo.com`

---

## Database Storage

### Migration

Run the migration to create the `testo_measurements` table:

```bash
php artisan migrate
```

To customise the migration before running it, publish it first:

```bash
php artisan vendor:publish --tag=testo-migrations
```

#### `testo_measurements` schema

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Auto-increment primary key |
| `logger_uuid` | string\|null | Logger device UUID (populated when available) |
| `measured_at` | timestamp | Timestamp of the measurement |
| `temperature` | decimal(8,4)\|null | Temperature reading |
| `humidity` | decimal(8,4)\|null | Humidity reading |
| `created_at` | timestamp | Record insertion time |
| `updated_at` | timestamp | Record update time |

### Model

```php
use GraystackIT\TestoCloud\Models\TestoMeasurement;

$recent = TestoMeasurement::where('measured_at', '>=', now()->subDay())->get();

foreach ($recent as $row) {
    echo $row->measured_at;   // Carbon instance
    echo $row->temperature;   // float|null
    echo $row->humidity;      // float|null
}
```

### Disabling automatic storage

Set `TESTO_STORE_MEASUREMENTS=false` in your `.env` (or `store_measurements => false` in `config/testo.php`) to fetch and parse data without writing to the database.

---

## Artisan Command

```bash
php artisan testo:fetch-measurements
```

Fetches historical measurements from the Testo API, parses the NDJSON response, and — when storage is enabled — persists every row to `testo_measurements`.

### Options

| Option | Default | Description |
|---|---|---|
| `--from=` | `default_from_days` ago | Start date (`Y-m-d`) |
| `--to=` | today | End date (`Y-m-d`) |
| `--format=` | `JSON` | Export format (`JSON` or `CSV`) |
| `--logger-uuid=` | _(all loggers)_ | Filter results to a single device UUID |

### Examples

```bash
# Last 7 days (default)
php artisan testo:fetch-measurements

# Specific date range
php artisan testo:fetch-measurements --from=2025-01-01 --to=2025-01-31

# Single device only
php artisan testo:fetch-measurements --from=2025-03-01 --to=2025-03-31 --logger-uuid=abc-123
```

### Console output

```
Fetching measurements from 2025-01-01 to 2025-01-31...
Request submitted. UUID: 9f4a1b2c-...
Polling for completion (max 60 attempts, 5s interval)...
  [1/60] Status: submitted — waiting 5s...
  [2/60] Status: processing — waiting 5s...
Status: completed.
Downloading 2 data file(s)...
  [1/2] Downloaded.
  [2/2] Downloaded.
Parsed 2880 measurement(s) across 2 file(s).

Total measurements parsed: 2880
Stored in database: 2880
```

---

## Testing

```bash
TESTO_API_KEY=test-key composer test
```

Tests use Saloon's `MockClient` — no real API calls are made. The service provider refuses to boot
without a key, so any placeholder value is needed.

## License

MIT
