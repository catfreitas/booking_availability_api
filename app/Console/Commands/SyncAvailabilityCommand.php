<?php

namespace App\Console\Commands;

use App\DataTransferObjects\AvailabilityIngestionDTO;
use App\Services\AvailabilityIngestionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncAvailabilityCommand extends Command
{
    protected $signature = 'app:sync-availability';
    protected $description = 'Fetches and syncs availability data from an external JSON feed.';
    protected AvailabilityIngestionService $ingestionService;

    public function __construct(AvailabilityIngestionService $ingestionService)
    {
        parent::__construct();
        $this->ingestionService = $ingestionService;
    }

    public function handle(): int
    {
        $this->info('Starting availability sync...');
        $feedUrl = $this->getFeedUrl();

        if (!$feedUrl) {
            return Command::FAILURE;
        }

        try {
            $feedData = $this->fetchAndParseFeed($feedUrl);

            if (!$feedData) {
                return Command::FAILURE;
            }

            if (!$this->validateFeedDataStructure($feedData, $feedUrl)) {
                return Command::FAILURE;
            }

            // Create DTO from the feed data
            $ingestionDto = new AvailabilityIngestionDTO(
                property_id: $feedData['property_id'],
                name: $feedData['name'],
                roomsData: $feedData['rooms']
            );

            // Ingest data using the service
            $this->ingestionService->ingestData($ingestionDto);

            $this->info('Availability data synced successfully from ' . $feedUrl);
            Log::info('SyncAvailabilityCommand: Availability data synced successfully.', ['url' => $feedUrl]);

            $this->clearCache();

            return Command::SUCCESS;

        } catch (Throwable $e) {
            $this->error('An unexpected error occurred during availability sync: ' . $e->getMessage());
            Log::error('SyncAvailabilityCommand: An unexpected error occurred.', [
                'url' => $feedUrl,
                'error_message' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 1000) // Limit trace length
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Retrieves and validates the feed URL from configuration.
     */
    private function getFeedUrl(): ?string
    {
        $feedUrl = config('services.availability_feed.url');
        if (!$feedUrl) {
            $this->error('Availability feed URL is not configured in config/services.php or .env file.');
            Log::error('SyncAvailabilityCommand: Availability feed URL is not configured.');
        }
        return $feedUrl;
    }

    /**
     * Fetches and parses data from the external feed URL.
     */
    private function fetchAndParseFeed(string $feedUrl): ?array
    {
        $response = Http::timeout(30)->get($feedUrl);

        if (!$response->successful()) {
            $this->error('Failed to fetch availability data from feed. HTTP Status: ' . $response->status());
            Log::error('SyncAvailabilityCommand: Failed to fetch availability data from feed.', [
                'url' => $feedUrl,
                'status' => $response->status(),
                'response_body' => substr($response->body() ?? '', 0, 500) // Log a snippet of the body
            ]);
            return null;
        }
        return $response->json();
    }

    /**
     * Validates the basic structure of the fetched feed data.
     */
    private function validateFeedDataStructure(array $data, string $feedUrl, $httpResponseForLog = null): bool
    {
        if (empty($data) || !isset($data['property_id']) || !isset($data['rooms']) || !is_array($data['rooms'])) {
            $this->error('Fetched data is empty or has a malformed structure.');
            Log::warning('SyncAvailabilityCommand: Fetched data is empty or malformed.', [
                'url' => $feedUrl,
                'data_snippet' => substr(json_encode($data), 0, 500),
            ]);
            return false;
        }
        return true;
    }

    /**
     * Clears the relevant application cache.
     */
    private function clearCache(): void
    {
        $currentCacheDriver = config('cache.default');
        $this->info('SyncAvailabilityCommand: About to flush cache. Detected default cache driver: ' . $currentCacheDriver);
        Log::debug('SyncAvailabilityCommand: About to flush cache. Detected default cache driver: ' . $currentCacheDriver);

        try {
            if ($currentCacheDriver === 'redis') {
                Cache::tags('availability')->flush();
                $this->info('Availability cache tags flushed (Redis).');
                Log::info('Availability cache tags flushed (Redis).');
            } else {
                Cache::flush();
                $this->info('Application data cache flushed (non-Redis default: ' . $currentCacheDriver . ').');
                Log::info('Application data cache flushed (non-Redis default: ' . $currentCacheDriver . ').');
            }
        } catch (Throwable $e) {
            $this->error('Error during cache flush: ' . $e->getMessage());
            Log::error('SyncAvailabilityCommand: Error during cache flush.', ['error_message' => $e->getMessage()]);
        }
    }
}
