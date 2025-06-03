# Hijiffy - Booking Availability API Challenge

## 1. Project Overview

* Briefly state the goal: To build a Laravel-based Booking Microservice that integrates with a Dialogflow chatbot. This service allows users to request property availability, ask FAQs, and query real-time data from a secure, rate-limited, and cached API.

## 2. System Architecture & Design Decisions

### 2.1. Technology Stack
* **Backend Framework:** Laravel 11 (using PHP 8.2+)
* **Database:** MySQL (as configured for development)
* **API Authentication:** Laravel Sanctum (for token-based authentication of the public query API)
* **Caching:** Redis (configured as the primary cache driver, utilizing Predis)
* **Job Queue (Implicit):** Laravel's default queue driver would be used if any jobs were dispatched (though not explicitly used in the current scope beyond scheduled commands).
* **NLU/Chatbot:** Google Dialogflow ES

### 2.2. Core Components & Flow
* **Ingestion API (`POST /api/availability-ingest`):**
    * Receives JSON payloads detailing property, room, and daily availability (price, max_guests).
    * Uses a Service-Repository pattern: `AvailabilityController` -> `AvailabilityIngestionService` -> (`PropertyRepository`, `RoomRepository`, `RoomAvailabilityRepository`) -> Eloquent Models.
    * Employs `updateOrCreate` for idempotent data synchronization.
* **Scheduled Data Sync (`php artisan app:sync-availability`):**
    * An Artisan command designed to fetch availability data from an external JSON feed.
    * Reuses the `AvailabilityIngestionService` for data processing and persistence.
    * Scheduled to run periodically (e.g., daily) via Laravel's scheduler (configured in `routes/console.php`).
    * Invalidates relevant caches upon successful data sync.
* **Public Query API (`GET /api/availability`):**
    * Allows clients to query for room availability based on `property_id` (can be external ID or name), `check_in` date, `check_out` date, and number of `guests`.
    * Secured using Laravel Sanctum (bearer token authentication).
    * Rate-limited per API token.
    * Uses a Service-Repository pattern: `AvailabilityController` -> `AvailabilitySearchService` -> Repositories -> Eloquent Models.
    * Results are cached using Redis to optimize performance.
    * Responses are formatted using Laravel API Resources (`PropertyAvailabilityResource`).
* **Dialogflow Agent:**
    * Provides a conversational interface.
    * Static Intents: `CheckInTime`, `CheckOutTime`, `ParkingAvailability` provide direct text responses.
    * `CheckAvailability` Intent: Extracts parameters (`property_id`, dates, guests) from user utterances and uses webhook fulfillment.
    * Webhook (`POST /api/agent-webhook`): A Laravel endpoint that receives data from Dialogflow, calls the `AvailabilitySearchService`, and formats a `fulfillmentText` response.

### 2.3. Key Design Decisions
* **Service-Repository Pattern:** Implemented to separate business logic (services) from data access concerns (repositories). This promotes cleaner controllers, enhances testability (mocks for repositories), allows for easier changes to data sources, and adheres to the Single Responsibility Principle.
* **Laravel Sanctum:** Chosen for API authentication due to its lightweight nature, suitability for token-based authentication (SPAs, mobile apps, third-party services), and seamless integration with Laravel.
* **Named Rate Limiters:** Distinct rate limiters are defined (`api` for user queries, `ingestApi` for data ingestion) to apply appropriate traffic control based on endpoint characteristics (user-facing vs. system-to-system) and security considerations.
* **Redis for Caching:** Selected as the caching backend for its performance and support for advanced features like cache tagging. This enables efficient caching of availability query results and allows for more granular cache invalidation strategies.
* **Tag-Based Cache Invalidation:** The caching strategy is designed around tags (e.g., `'availability'`, `'property:<id>'`). When data is updated by the sync command, relevant tags are flushed from Redis, ensuring data freshness. This is more efficient than flushing the entire cache.
* **Form Requests for Validation:** Laravel's Form Requests (`StoreAvailabilityIngestionRequest`, `GetAvailabilityRequest`) are used to encapsulate validation logic for incoming API requests, keeping controllers lean and providing automatic error responses.
* **API Resources for Output:** Laravel API Resources (`PropertyAvailabilityResource`) are used to transform and standardize the JSON responses for the public query API, ensuring a consistent data contract.
* **Idempotent Data Ingestion:** The use of `updateOrCreate` in the repositories ensures that the data ingestion process is idempotent – resending the same data will update existing records or create new ones without duplication.
* **Property Lookup Flexibility (Dialogflow):** The system is designed to allow property lookup by name (more natural for chatbot users) which then resolves to the internal `external_property_id`, demonstrating a user-centric approach.

## 3. How to Run

### 3.1. Prerequisites
* PHP (e.g., 8.2 or as per `composer.json`)
* Composer 2.x
* MySQL (or other compatible database)
* Redis Server (ensure it's running if `CACHE_DRIVER=redis`)
* Node.js & npm (only if you need to compile frontend assets - not directly required for this backend challenge setup)
* A tunneling service like ngrok or localtunnel (e.g., `localtunnel --port 8000`) for testing Dialogflow webhook integration with your local development server.

### 3.2. Installation & Setup
1.  **Clone Repository:** `git clone <your_repo_url> && cd <project_name>`
2.  **Install Dependencies:** `composer install`
3.  **Environment File:**
    * Copy `.env.example` to `.env`: `cp .env.example .env`
    * Generate application key: `php artisan key:generate`
4.  **Configure `.env`:**
    * `APP_NAME`, `APP_URL` (e.g., `http://localhost:8000`)
    * Database connection: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
    * Cache Driver: `CACHE_DRIVER=redis`
    * Redis Connection: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD=null` (if none), `REDIS_CLIENT=predis` (if you installed Predis)
    * Availability Feed URL for Sync Command: `AVAILABILITY_FEED_URL="URL_TO_YOUR_MOCK_JSON_FEED"`
        * *(Note: Also ensure this is configured in `config/services.php` if your command reads it from there: ` 'availability_feed' => ['url' => env('AVAILABILITY_FEED_URL')], `)*
5.  **Database Migrations:** `php artisan migrate`
6.  **(Optional) Database Seeding:** (If you create seeders) `php artisan db:seed`
7.  **Start Local Development Server:** `php artisan serve` (usually on port 8000)

### 3.3. Running the Scheduler
* The `app:sync-availability` command syncs data from the `AVAILABILITY_FEED_URL`.
* It's scheduled in `routes/console.php` (e.g., daily).
* **To manually run all due scheduled tasks once:** `php artisan schedule:run`
* **To keep the scheduler running locally and executing tasks as they become due (every minute check):** `php artisan schedule:work`
* **Production Cron Entry:**
    ```cron
    * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
    ```

### 3.4. Dialogflow Agent Setup
1.  The exported Dialogflow agent is provided as `[YourAgentName].zip`.
2.  Go to the Dialogflow ES console, select or create an agent.
3.  Navigate to Agent Settings (gear icon ⚙️) -> "Export and Import" tab.
4.  Choose "Import from ZIP" and upload the provided agent file.
5.  **Configure Webhook Fulfillment:**
    * In Dialogflow, go to "Fulfillment" (left menu).
    * Enable "Webhook".
    * Set the URL to `YOUR_TUNNEL_URL/api/agent-webhook` (where `YOUR_TUNNEL_URL` is the HTTPS URL provided by your tunneling service like ngrok or localtunnel, pointing to your local Laravel server, e.g., port 8000).

## 4. API Endpoints Documentation

*(Provide details for each endpoint as previously discussed: method, URL, protection, headers, request body/params, success/error responses).*
* `POST /api/availability-ingest`
* `GET /api/availability` (include how to get a test token)

## 5. Rate Limiting Strategy Explanation

### Justification
Rate limiting is implemented to protect the API from abuse, ensure fair usage, and maintain server stability. Different limits are applied to different types of endpoints based on their expected usage patterns and security considerations.

### Configuration
* **Public Query API (`GET /api/availability`):**
    * Uses the `'api'` named rate limiter.
    * Limit: 100 requests per hour per authenticated API token (identified by user ID).
    * Implementation: Defined in `AppServiceProvider::boot()` using `RateLimiter::for('api', ...)` and applied via `throttle:api` middleware. This ensures that individual users consuming the public API are fairly limited.
* **Ingestion API (`POST /api/availability-ingest`):**
    * Uses the `'ingestApi'` named rate limiter.
    * Limit: Example: 60 requests per hour per IP address.
    * Implementation: Defined in `AppServiceProvider::boot()` using `RateLimiter::for('ingestApi', ...)` and applied via `throttle:ingestApi` middleware. This is suitable for a system-to-system data feed where requests come from a known IP or a limited set of IPs.

## 6. Caching Strategy Explanation

### Justification
Caching is implemented for the `GET /api/availability` endpoint to significantly improve response times for frequently requested availability queries and to reduce the load on the database. The strategy aims for data freshness aligned with the data synchronization schedule.

### Configuration
* **What is Cached:** The JSON response (specifically, the data array generated by `AvailabilitySearchService`) for unique combinations of `property_id`, `check_in`, `check_out`, and `guests`.
* **How:** Using Laravel's `Cache::remember()` method within the `AvailabilitySearchService`.
* **Cache Driver:** Configured to use **Redis** (`CACHE_DRIVER=redis`). Redis is chosen for its high performance and support for **cache tagging**.
* **Cache Key:** Generated dynamically based on a sorted, MD5 hashed string of the validated query parameters to ensure uniqueness and consistency.
* **Time-To-Live (TTL):** 24 hours. This aligns with the daily data sync, ensuring data is served from cache for most of the day.
* **Invalidation Strategy:**
    * Cache entries are tagged with a general `'availability'` tag and a property-specific tag (e.g., `'property:EXTERNAL_PROPERTY_ID'`).
    * When the `app:sync-availability` Artisan command successfully completes its daily data ingestion, it flushes the cache using `Cache::tags('availability')->flush()`. This clears all cached items tagged with 'availability', ensuring that after a data sync, the API will serve fresh data which then gets re-cached.
    * Using tags with Redis allows for potentially more granular cache invalidation if the sync process were to update only specific properties in the future. If the file cache is used (e.g., in a development environment without Redis), the command falls back to `Cache::flush()` to clear the entire default data cache store.

## 7. Running Tests
* To run all PHPUnit tests:
    ```bash
    php artisan test
    ```
* To run tests from a specific file:
    ```bash
    php artisan test tests/Unit/Services/AvailabilityIngestionServiceTest.php
    php artisan test tests/Feature/Api/PublicAvailabilityApiTest.php
    ```
