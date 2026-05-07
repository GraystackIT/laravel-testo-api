<?php

declare(strict_types=1);

namespace GraystackIT\TestoCloud\Commands;

use Carbon\Carbon;
use GraystackIT\TestoCloud\Exceptions\TestoApiException;
use GraystackIT\TestoCloud\Models\TestoMeasurement;
use GraystackIT\TestoCloud\Parsers\MeasurementNdjsonParser;
use GraystackIT\TestoCloud\TestoCloudClient;
use Illuminate\Console\Command;

class FetchMeasurementsCommand extends Command
{
    protected $signature = 'testo:fetch-measurements
                            {--from= : Start date (Y-m-d). Defaults to configured default_from_days ago}
                            {--to=   : End date (Y-m-d). Defaults to today}
                            {--format=JSON : Export file format (JSON or CSV)}
                            {--logger-uuid= : Filter measurements to a single logger device UUID}';

    protected $description = 'Fetch historical measurement data from the Testo Saveris API';

    public function handle(TestoCloudClient $client, MeasurementNdjsonParser $parser): int
    {
        $from = $this->option('from')
            ? Carbon::parse((string) $this->option('from'))->startOfDay()
            : Carbon::now()->subDays((int) config('testo.default_from_days', 7))->startOfDay();

        $to = $this->option('to')
            ? Carbon::parse((string) $this->option('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $format     = strtoupper((string) ($this->option('format') ?? 'JSON'));
        $loggerUuid = $this->option('logger-uuid') ? (string) $this->option('logger-uuid') : null;

        if ($from->greaterThanOrEqualTo($to)) {
            $this->error('The --from date must be strictly before the --to date.');

            return self::FAILURE;
        }

        $this->info("Fetching measurements from {$from->toDateString()} to {$to->toDateString()}...");

        // 1. Submit async request
        try {
            $submit = $client->submitMeasurementRequest($from, $to, $format);
        } catch (TestoApiException $e) {
            $this->error("Failed to submit measurement request: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Request submitted. UUID: {$submit->requestUuid}");

        // 2. Poll until completed
        $maxAttempts = (int) config('testo.poll_max_attempts', 60);
        $interval    = (int) config('testo.poll_interval_seconds', 5);

        $this->info("Polling for completion (max {$maxAttempts} attempts, {$interval}s interval)...");

        $status  = null;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $status = $client->checkRequestStatus($submit->requestUuid);
            } catch (TestoApiException $e) {
                $this->error("Status check failed: {$e->getMessage()}");

                return self::FAILURE;
            }

            if ($status->isCompleted()) {
                $this->info("Status: completed.");
                break;
            }

            if ($status->isFailed()) {
                $this->error('Measurement request failed: '.($status->error ?? 'unknown error'));

                return self::FAILURE;
            }

            $this->line("  [{$attempt}/{$maxAttempts}] Status: {$status->status} — waiting {$interval}s...");
            sleep($interval);
        }

        if ($status === null || ! $status->isCompleted()) {
            $this->error("Measurement request did not complete within the allowed attempts ({$maxAttempts} × {$interval}s).");

            return self::FAILURE;
        }

        if (empty($status->dataUrls)) {
            $this->warn('Request completed but no data files were returned.');

            return self::SUCCESS;
        }

        // 3. Download all data files and concatenate before parsing.
        //    The Testo API splits results across multiple S3 URLs (one per Athena
        //    worker). Processing files individually misses cross-file timestamp
        //    deduplication; concatenating first lets the parser merge them correctly.
        $shouldStore = (bool) config('testo.store_measurements', true);
        $fileCount   = count($status->dataUrls);

        $this->info("Downloading {$fileCount} data file(s)...");

        $combinedContent = '';

        foreach ($status->dataUrls as $index => $url) {
            $fileNum = $index + 1;

            try {
                $content = $client->downloadDataFile($url);
            } catch (TestoApiException $e) {
                $this->error("  [{$fileNum}/{$fileCount}] Download failed: {$e->getMessage()}");

                return self::FAILURE;
            }

            $combinedContent .= $content;
            if (! str_ends_with($combinedContent, "\n")) {
                $combinedContent .= "\n";
            }

            $this->line("  [{$fileNum}/{$fileCount}] Downloaded.");
        }

        // Parse the combined content — grouping by timestamp deduplicates entries
        // that appear in more than one file.
        $rows      = $parser->parse($combinedContent, $loggerUuid);
        $totalRows = count($rows);

        // Sort chronologically before storing; the API does not guarantee order
        // across files.
        usort($rows, static fn (array $a, array $b) => strtotime($a['timestamp']) <=> strtotime($b['timestamp']));

        $this->info("Parsed {$totalRows} measurement(s) across {$fileCount} file(s).");

        $storedRows = 0;

        if ($shouldStore && $totalRows > 0) {
            foreach ($rows as $row) {
                TestoMeasurement::firstOrCreate(
                    ['logger_uuid' => $loggerUuid, 'measured_at' => $row['timestamp']],
                    ['temperature' => $row['temperature'] ?? null, 'humidity' => $row['humidity'] ?? null]
                );
                $storedRows++;
            }
        }

        $this->newLine();
        $this->info("Total measurements parsed: {$totalRows}");

        if ($shouldStore) {
            $this->info("Stored in database: {$storedRows}");
        } else {
            $this->line('Database storage is disabled (testo.store_measurements = false). No rows were written.');
        }

        return self::SUCCESS;
    }
}
