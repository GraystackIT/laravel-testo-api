# Changelog

All notable changes to `graystackit/laravel-testo-api` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Second audit (2026-11-23), against Testo's OpenAPI reference

Audited against `smart-connect-api/api_reference/swagger.json`, the Smart Connect changelog and
FAQ. The spec settles both questions the first audit left open: device status exists only as v4,
and there is no tasks endpoint.

#### Fixed
- **A failed Testo request is reported as failed.** Testo's poll endpoints answer `Completed`,
  `In Progress` or `Error`; `AsyncRequestStatus::fromApiValue()` mapped `failed` but not `error`,
  so a failed request came back `Unknown` and `testo:fetch-measurements` (and any caller checking
  `isFailed()`) kept polling until its attempts ran out. `Error` now maps to `Failed`. The status
  DTOs read the error text from Testo's `message`, falling back to `error`.
- **Measurements of one logger can be narrowed for real.** Testo's measurement rows carry no
  `device_uuid` — they name the sensor (`sensor_uuid`, `serial_no`) — so
  `MeasurementNdjsonParser::parse($ndjson, $deviceUuid)` never filtered, and readings of several
  loggers at the same timestamp overwrote each other. `parse()` takes a new
  `array $sensorUuids` and keeps only those sensors' rows. `LoggerDevice` gains `$sensorUuids` and
  `$sensorSerialNos`, which `DeviceNdjsonParser` now collects from every channel row of a device
  instead of keeping only the first. When `$deviceUuid` is given but cannot be applied, all rows
  are still returned, as before, and a warning is logged.
- Device status moved to `/v4/devices/status`, the only version in the API reference. v4 renames
  `last_communication`/`next_communication` to `..._time` and drops `fw_code`/`fw_version`
  (still on `/v3/devices/properties`).

#### Deprecated
- `submitTaskRequest()`, `checkTaskStatus()`, `SubmitTaskRequest`, `CheckTaskStatusRequest`:
  Testo has no tasks endpoint; the requests cannot succeed. Removed in the next major version.

#### Added
- OData options on every `submit*Request()` method and request, as a trailing
  `array $odata` (`$filter`, `$select`, `$orderby`), sent as the body's `odata` object; anything
  else throws `InvalidArgumentException`. New concern `Requests\Concerns\SendsODataOptions`.
- `TestoApiException` carries Testo's `application/problem+json` error: `problemType`,
  `problemTitle`, `detail` (or a plain `message`), with `isQuotaExceeded()` for the monthly limit
  of 10,000 POST requests per account. New `TestoApiException::fromRequestException()`; the
  message and code are unchanged, and the three-argument constructor still works.

#### Not acted on
- Measuring objects gained `back_reference` (Testo, 2026-07-13). The package passes measuring-object
  rows through unparsed, so nothing changes.

### First audit (2026-09-28)

Audited against Testo's Smart Connect API docs (`developers.testo.dev/smart-connect-api/`) —
endpoint-examples overview, OData-filtering page, and changelog.

### Fixed
- `FetchMeasurementsCommand`'s poll-loop log line string-interpolated `$status->status`
  directly — an `AsyncRequestStatus` enum *instance*, not a string. PHP does not implicitly
  stringify enum instances, so this threw `Error: Object of class ... could not be converted to
  string` on every poll iteration that didn't complete on the very first status check (i.e.
  almost always in practice), crashing `testo:fetch-measurements` mid-run. Now uses the new
  `$status->rawStatus` string property instead.
- `MeasurementSubmitResponse` was the only submit-response DTO in the package that kept
  `status` as a raw string instead of the shared `AsyncRequestStatus` enum every other endpoint
  uses, and read `request_uuid` without a safe fallback. Aligned with `AsyncSubmitResponse`'s
  shape. **Breaking:** `MeasurementSubmitResponse::$status` is now `AsyncRequestStatus`, not
  `string` — read `$response->rawStatus` for the original string.
- `AsyncRequestStatus::fromApiValue()` silently mapped any unrecognized status string to
  `Processing`. A genuinely new terminal status from Testo (e.g. a future `cancelled` state)
  would have been treated as "still processing" forever, spinning `FetchMeasurementsCommand`'s
  poll loop until `poll_max_attempts` was exhausted instead of surfacing the real state. Added
  an `AsyncRequestStatus::Unknown` case as the fallback instead. **Breaking:** code matching
  exhaustively on `AsyncRequestStatus` cases needs an `Unknown` arm.
- `config('testo.http_timeout')` was declared and documented but never actually applied to any
  HTTP request — now wired into `TestoDataConnector` via Saloon's `defaultConfig()`.

### Added
- `AsyncSubmitResponse`, `AsyncStatusResponse`, `MeasurementStatusResponse`, and
  `MeasurementSubmitResponse` all gained `$rawStatus` — the status string exactly as Testo
  returned it, since `AsyncRequestStatus::Unknown` alone can't distinguish *which* unrecognized
  status was received.
- `MeasurementNdjsonParser` now captures `physical_unit` and `physical_extension` per row
  (`temperature_unit`, `humidity_unit`, `temperature_extension`, `humidity_extension`) —
  confirmed real fields per Testo's migration guide, previously discarded. Not yet persisted by
  `FetchMeasurementsCommand`/`TestoMeasurement` (would need a migration) — available in the
  parsed array for callers who want them.

### Flagged — unverified, deliberately left unchanged pending confirmation
- **Tasks (`/v3/tasks`):** this path does not appear anywhere in Testo's current Smart Connect
  API reference (checked three separate official pages, all of which list the same fixed six
  endpoint pairs with no tasks entry). A "Task Execution API" concept is referenced elsewhere
  but its actual path could not be confirmed. See the docblocks on `SubmitTaskRequest`/
  `CheckTaskStatusRequest`.
- **Device/Sensor Status (`/v3/devices/status`):** Testo's changelog records a `/v4/devices/status`
  being added, with `last_communication`/`next_communication` renamed to `..._time`,
  `fw_code`/`fw_version` removed, and `production_date`/`battery_temperature`/`status_time`
  added — but the docs' own endpoint-overview page still only lists v3, and the changelog never
  states v3 is deprecated. See the docblocks on `SubmitSensorStatusRequest`/
  `CheckSensorStatusRequest`.

## [1.0.0] - 2025-04-07

### Added
- Initial release
- `TestoCloudClient` with `submitMeasurementRequest()`, `checkRequestStatus()`, `downloadDataFile()`, and `getAllLoggers()` methods
- Automatic gzip decompression for downloaded measurement files
- Token caching with configurable TTL buffer
- `MeasurementSubmitResponse`, `MeasurementStatusResponse`, and `LoggerDevice` data objects
- `MeasurementNdjsonParser` for parsing newline-delimited JSON measurement data
- Support for EU, Americas, and Asia-Pacific API regions
- Laravel service provider with auto-discovery
- Input validation: `$from` must be before `$to` in `submitMeasurementRequest()`
- Config validation: clear `RuntimeException` when `TESTO_CLIENT_ID` or `TESTO_CLIENT_SECRET` is missing
