# Booking Availability API & Chatbot Integration Challenge

## 1. Project Overview

This project is a Laravel-based Booking Microservice designed to manage and provide property availability information. It integrates with a Dialogflow chatbot, allowing users to make availability requests via natural language, ask Frequently Asked Questions (FAQs), and query real-time data from a secure, rate-limited, and cached API.

## 2. System Architecture & Design Decisions

### 2.1. Technology Stack
* **Backend Framework:** Laravel 11 (PHP 8.2+)
* **Database:** MySQL (as configured for development)
* **API Authentication:** Laravel Sanctum (token-based for the public query API)
* **Caching:** Redis (configured as cache driver, utilizing Predis)
* **Job Scheduling:** Laravel's built-in Scheduler for data synchronization.
* **NLU/Chatbot:** Google Dialogflow ES
* **Development Tools:** Composer, Artisan, PHPUnit, localtunnel (for Dialogflow webhook testing).

### 2.2. Core Components & Flow
* **Ingestion API (`POST /api/availability-ingest`):**
    * Receives JSON payloads detailing property (ID and name), rooms, and daily availability (price, max_guests).
    * Uses a Service-Repository pattern (`AvailabilityController` -> `AvailabilityIngestionService` -> Repositories -> Eloquent Models) for c.
    * Data Transfer Objects (`AvailabilityIngestionDTO`, `RoomIngestionDTO`) are used for structured data transfer to the service.
    * Employs `updateOrCreate` in repositories for idempotent data synchronization.
* **Scheduled Data Sync (`php artisan app:sync-availability`):**
    * An Artisan command to fetch availability data from a configured external JSON feed.
    * Reuses the `AvailabilityIngestionService` (with DTOs) for data processing and persistence.
    * Scheduled to run periodically (e.g., daily, configured in `routes/console.php`).
    * Invalidates relevant caches (using Redis tags) upon successful data sync.
* **Public Query API (`GET /api/availability`):**
    * Allows authenticated clients to query for room availability based on `property_id` (can be external ID or name), `check_in` date, `check_out` date, and number of `guests`.
    * Secured using Laravel Sanctum (Bearer token authentication). [cite: 3]
    * Rate-limited per API token. [cite: 3]
    * Uses a Service-Repository pattern (`AvailabilityController` -> `AvailabilitySearchService` -> Repositories -> Eloquent Models).
    * Input parameters are validated by `GetAvailabilityRequest` and structured into an `AvailabilitySearchDTO`.
    * Results are cached using Redis (`Cacheable` Trait, `Cache::tags(...)->remember(...)`) to optimize performance.
    * Responses are formatted using Laravel API Resources (`PropertyAvailabilityResource`) which consumes an `AvailabilityResultDTO`.
* **Dialogflow Agent:**
    * Handles Natural Language Understanding for user queries.
    * Static Intents: `CheckInTime`, `CheckOutTime`, `ParkingAvailability` provide direct text responses.
    * `CheckAvailability` Intent: Extracts parameters (`property_id` (as name or ID), dates, guests) and uses webhook fulfillment.
    * Webhook (`POST /api/agent-webhook`): A Laravel endpoint (`AvailabilityController@handleAvailability` -> `DialogflowWebhookService`) that receives data from Dialogflow, uses `AvailabilitySearchService` (with DTOs and custom exceptions for flow control), and formats a `fulfillmentText` response.

### 2.3. Key Design Decisions
* **Service-Repository Pattern:** Implemented to separate business logic (services) from data access concerns (repositories). This promotes cleaner controllers, enhances testability (mocks for repositories), allows for easier changes to data sources, and adheres to the Single Responsibility Principle.
* **Data Transfer Objects (DTOs):** Used for passing structured data between layers (e.g., Controller to Service, Service to API Resource), ensuring clear contracts and type safety (e.g., `AvailabilityIngestionDTO`, `AvailabilitySearchDTO`, `AvailabilityResultDTO`).
* **Custom Exceptions:** Specific exceptions (e.g., `PropertyNotFoundException`, `InvalidSearchParametersException`, `DialogflowParameterException`, `ConfigurationException`) are used for better error handling and flow control, allowing controllers to return more precise API/webhook responses.
* **BaseApiController:** Created to centralize common API response formatting logic (e.g., for 404, 400, 500, 503 errors, and Dialogflow error responses), keeping specific controllers DRY.
* **Cacheable Trait:** A trait was created to encapsulate the generic caching mechanism (`Cache::tags(...)->remember(...)` with key/tag generation), making it reusable for services requiring similar caching patterns.
* **Laravel Sanctum for API Authentication:** Selected for its lightweight nature and robust token-based authentication for the public query API.
* **Named Rate Limiters:** Distinct rate limiters defined in `AppServiceProvider` (`api` for user queries, `ingestApi` for data ingestion) to apply appropriate traffic control.
* **Redis for Caching:** Chosen as the cache backend for its performance and support for cache tagging, enabling efficient and granular cache invalidation.
* **Form Requests for Validation:** Used for validating incoming HTTP API requests (`StoreAvailabilityIngestionRequest`, `GetAvailabilityRequest`).
* **API Resources for Output:** Laravel API Resources (`PropertyAvailabilityResource`) ensure consistent JSON responses for the public query API.
* **Idempotent Data Ingestion:** The use of `updateOrCreate` in repositories ensures the data synchronization process is idempotent.
* **Dialogflow Property Identification:** The system allows users to query properties by name (e.g., "Hotel Viana") in Dialogflow. The backend (`AvailabilitySearchService`) attempts to resolve this name first, then falls back to checking if the identifier is an `external_property_id`, providing a more user-friendly experience. The `property_id` parameter in Dialogflow for the `CheckAvailability` intent is configured using `@sys.any` to capture these natural names.

## 3. How to Run

### 3.1. Prerequisites
* PHP (e.g., ^8.2)
* Composer 2.x
* MySQL (or another compatible database like MariaDB)
* Redis Server (running on default host/port or as configured in `.env`)
* (Optional) Node.js & npm (if frontend assets were part of the project - not for this BE focus)
* A tunneling service like **localtunnel** or **ngrok** for testing Dialogflow webhook integration with your local development server.

### 3.2. Installation & Setup
1.  **Clone the repository:**
    ```bash
    git clone <your-repo-url>
    cd <project-directory-name>
    ```
2.  **Install PHP dependencies:**
    ```bash
    composer install
    ```
3.  **Install Redis PHP Client (if not using phpredis extension):**
    ```bash
    composer require predis/predis
    ```
4.  **Environment Setup:**
    * Copy `.env.example` to `.env`: `cp .env.example .env`
    * Generate application key: `php artisan key:generate`
5.  **Configure `.env` file:**
    Update the following variables in your `.env` file:
    ```env
    APP_NAME="Challenge Booking API"
    APP_ENV=local
    APP_KEY= (should be filled by key:generate)
    APP_DEBUG=true
    APP_URL=http://localhost:8000 # Or your preferred local URL

    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3308 # Or your MySQL port
    DB_DATABASE=your_database_name # e.g., hijiffy_booking_db
    DB_USERNAME=your_db_user # e.g., root
    DB_PASSWORD=your_db_password

    CACHE_DRIVER=redis
    REDIS_CLIENT=predis # or phpredis if you have the extension
    REDIS_HOST=127.0.0.1
    REDIS_PORT=6379
    REDIS_PASSWORD=null # Or your Redis password
    
    #For the SyncAvailabilityCommand
    AVAILABILITY_FEED_URL="https://raw.githubusercontent.com/catfreitas/availability_feed/refs/heads/main/availability_feed.json" 
    
    ```
    *Ensures there are no duplicate variables `(example: DB_CONNECTION=sqlite -> must be commented)`

6.  **Create Database:** Create the database specified in `DB_DATABASE` in your MySQL instance.
7.  **Run Database Migrations:**
    ```bash
    php artisan migrate
    ```
8.  **(Recommended) Run Database Seeders:**
    ```bash
    php artisan db:seed
    ```
    *(This will populate the database with initial sample data if you've created seeders for properties, rooms, and availabilities).*
9.  **Start Local Development Server:**
    ```bash
    php artisan serve --port=8000
    ```

### 3.3. Running the Scheduler (for `app:sync-availability`)
* The command is scheduled in `routes/console.php`.
* **To run scheduled tasks locally (daily check):**
    ```bash
    php artisan schedule:work
    ```
* **To run due tasks once:**
    ```bash
    php artisan schedule:run
    ```
* **To run the sync command manually for testing:**
    ```bash
    php artisan app:sync-availability
    ```
* **Production Cron Entry:**
    ```cron
    * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
    ```

### 3.4. Dialogflow Agent Setup
1.  The exported Dialogflow agent is provided as `[YourAgentName].zip`.
2.  Go to the Dialogflow ES console.
3.  In the agent settings (gear icon ⚙️), navigate to the "Export and Import" tab.
4.  Select "Import from ZIP" and upload the agent file.
5.  **Configure Webhook Fulfillment:**
    * In Dialogflow, go to "Fulfillment" (left menu).
    * Enable "Webhook".
    * Set the URL to `YOUR_TUNNEL_URL/api/agent-webhook`.
        * `YOUR_TUNNEL_URL` is the HTTPS URL provided by your tunneling service (e.g., localtunnel: `lt --port 8000`, or ngrok: `ngrok http 8000`) pointing to your local Laravel server.

## 4. API Endpoints

### 4.1. Ingest Availability Data
* **Method:** `POST`
* **URL:** `/api/availability-ingest`
* **Protection:** Rate limited by IP (e.g., 60 requests/hour via `throttle:ingestApi`).
    *(Note: For production, consider adding dedicated API key authentication via custom middleware).*
* **Request Body (JSON):**
    ```json
    {
        "property_id": "EXT001",
        "name": "Example Property Name",
        "rooms": [
            {
                "room_id": "R101",
                "date": "YYYY-MM-DD",
                "max_guests": 2,
                "price": 120.50
            }
        ]
    }
    ```
* **Success Response (200 OK):** `{"message": "Availability data ingested successfully."}`
* **Error Response (422 Unprocessable Entity for validation errors):** Standard Laravel JSON error structure.

### 4.2. Query Public Availability
* **Method:** `GET`
* **URL:** `/api/availability`
* **Protection:** Requires Authentication (Sanctum Bearer Token) & Rate Limited (100 requests/hour per token via `throttle:api`).
* **Headers:**
    * `Accept: application/json`
    * `Authorization: Bearer <YOUR_SANCTUM_API_TOKEN>`
* **Query Parameters:**
    * `property_id` (string, required, e.g., "EXT001" or "Example Property Name")
    * `check_in` (date `YYYY-MM-DD`, required)
    * `check_out` (date `YYYY-MM-DD`, required, after `check_in`)
    * `guests` (integer, required, min: 1)
* **Success Response (200 OK):**
    ```json
    {
        "data": {
            "property_id": "EXT001",
            "rooms": [
                {
                    "room_id": "R101",
                    "max_guests": 2,
                    "total_price": 241.00
                }
                // ... other available rooms
            ]
        }
    }
    ```
* **Error Responses:**
    * `401 Unauthenticated`
    * `422 Unprocessable Entity` (Validation errors)
    * `404 Not Found` (If property not found)
    * `429 Too Many Requests`
    * `503 Service Unavailable` (For critical config errors)
    * `500 Internal Server Error` (Generic errors)

### Generating an API Token (for testing `GET /api/availability`)
1.  Create a user via Tinker and Generate a token for the user:
    ```bash
    php artisan tinker
    >>> App\Models\User::factory()->create(['name' => 'Test Client', 'email' => 'client@example.com', 'password' => Illuminate\Support\Facades\Hash::make('password')]);
    >>> $user = App\Models\User::where('email', 'client@example.com')->first();
    >>> $token = $user->createToken('test-client-token')->plainTextToken;
    >>> echo $token;
    >>> exit
    ```

## 5. Rate Limiting Strategy Explanation

### Justification
Rate limiting is implemented to protect the API from abuse, ensure fair usage for all clients, and maintain server stability. Different strategies are applied to user-facing query APIs versus system-to-system data ingestion APIs.

### Configuration
* **Public Query API (`GET /api/availability`):**
    * Uses the `'api'` named rate limiter, configured in `AppServiceProvider::boot()`.
    * Limit: 100 requests per hour per authenticated API token (identified by user ID). If a user is not authenticated (though this route requires it), it would fall back to IP.
    * Applied via `throttle:api` middleware.
* **Ingestion API (`POST /api/availability-ingest`):**
    * Uses the `'ingestApi'` named rate limiter, configured in `AppServiceProvider::boot()`.
    * Limit: Example 60 requests per hour per IP address. This is suitable for a trusted system or scheduled job source that doesn't use user-specific tokens.

## 6. Caching Strategy Explanation

### Justification
A caching strategy is implemented for the `GET /api/availability` endpoint to strongly improve response times for frequent queries and to reduce the load on the database, especially for commonly requested availability searches. The strategy prioritizes data freshness aligned with the data synchronization schedule and uses efficient invalidation. 

### Configuration
* **What is Cached:** The processed result (`AvailabilityResultDTO` content) from `AvailabilitySearchService` for each unique combination of query parameters (`property_id`, `check_in`, `check_out`, `guests`).
* **How:** Using Laravel's `Cache::tags(...)->remember(...)` method within the `AvailabilitySearchService`, orchestrated by the `Cacheable` trait.
* **Cache Driver:** Designed and configured for **Redis** (`CACHE_DRIVER=redis` in `.env`). Redis is chosen for its high performance and robust support for cache tagging, which enables granular invalidation.
* **Cache Key:** A unique key is generated using `md5(http_build_query(sorted_params))` prefixed with `'availability'`. Sorting parameters ensures consistency.
* **Time-To-Live (TTL):** Configured via `config/caching_settings.php` (e.g., 24 hours), aligning with the daily data injection cycle.
* **Invalidation Strategy:**
    * Cache entries are tagged with a general `'availability'` tag and a property-specific tag (e.g., `'property:EXTERNAL_PROPERTY_ID'`).
    * When the `app:sync-availability` Artisan command successfully ingests new data (daily), it flushes the cache using `Cache::tags('availability')->flush()`. This clears all cached items specifically tagged for 'availability', ensuring that subsequent API requests will fetch fresh data which then gets re-cached.
    * This tag-based invalidation with Redis is efficient. If the `file` cache were used as a fallback during development, the command would use `Cache::flush()` (clearing the entire default data cache store) to ensure freshness.

## 7. Running Tests
* To run all PHPUnit tests (Unit and Feature):
    ```bash
    php artisan test
    ```
* To run specific test files:
    ```bash
    php artisan test tests/Unit/Services/AvailabilityIngestionServiceTest.php
    php artisan test tests/Feature/Api/PublicAvailabilityApiTest.php
    php artisan test tests/Feature/Console/SyncAvailabilityCommandTest.php 
    php artisan test tests/Feature/Api/AvailabilityIngestionApiTest.php
    ```
