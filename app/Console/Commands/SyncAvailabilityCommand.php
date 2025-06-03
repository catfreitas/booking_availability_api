<?php

namespace App\Console\Commands;

use App\DataTransferObjects\AvailabilityIngestionDTO;
use App\Services\AvailabilityIngestionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
// use Illuminate\Validation\ValidationException; // Not directly thrown/caught here unless you add Validator::make()
use Throwable;

class SyncAvailabilityCommand extends Command
{
    protected $signature = 'app:sync-availability';
    protected $description = 'Fetches and syncs availability data from an external JSON feed.'; // Updated description
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
            return Command::FAILURE; // Error message already printed by getFeedUrl
        }

        try {
            $feedData = $this->_fetchAndParseFeed($feedUrl);

            if (!$feedData) {
                return Command::FAILURE; // Error message already printed by _fetchAndParseFeed
            }

            if (!$this->_validateFeedDataStructure($feedData, $feedUrl, Http::getLastResponse())) { // Pass last response for logging if needed
                return Command::FAILURE; // Error message already printed by _validateFeedDataStructure
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

            $this->_clearCache();

            return Command::SUCCESS;

        } catch (Throwable $e) {
            $this->error('An unexpected error occurred during availability sync: ' . $e->getMessage());
            Log::error('SyncAvailabilityCommand: An unexpected error occurred.', [
                'url' => $feedUrl, // $feedUrl is defined in this scope
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
    private function _fetchAndParseFeed(string $feedUrl): ?array
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
    private function _validateFeedDataStructure(array $data, string $feedUrl, $httpResponseForLog = null): bool
    {
        if (empty($data) || !isset($data['property_id']) || !isset($data['rooms']) || !is_array($data['rooms'])) {
            $this->error('Fetched data is empty or has a malformed structure.');
            Log::warning('SyncAvailabilityCommand: Fetched data is empty or malformed.', [
                'url' => $feedUrl,
                'data_snippet' => substr(json_encode($data), 0, 500), // Log snippet of data
                // 'response_body_if_available' => $httpResponseForLog ? substr($httpResponseForLog->body() ?? '', 0, 500) : null
            ]);
            return false;
        }
        return true;
    }

    /**
     * Clears the relevant application cache.
     */
    private function _clearCache(): void
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
                Cache::flush(); // Clears data from the default cache store
                $this->info('Application data cache flushed (non-Redis default: ' . $currentCacheDriver . ').');
                Log::info('Application data cache flushed (non-Redis default: ' . $currentCacheDriver . ').');
            }
        } catch (Throwable $e) {
            $this->error('Error during cache flush: ' . $e->getMessage());
            Log::error('SyncAvailabilityCommand: Error during cache flush.', ['error_message' => $e->getMessage()]);
            // Decide if this should make the command fail or just log a warning.
            // For now, it logs and the command might still return SUCCESS if ingestion was okay.
        }
    }
}
