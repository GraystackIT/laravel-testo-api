# Changelog

All notable changes to `graystackit/laravel-testo-api` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
