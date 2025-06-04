<?php

namespace App\Providers;

use Illuminate\Http\Request;
use App\Repositories\RoomRepository;
use App\Repositories\DbRoomRepository;
use Illuminate\Support\ServiceProvider;
use App\Repositories\PropertyRepository;
use Illuminate\Cache\RateLimiting\Limit;
use App\Repositories\DbPropertyRepository;
use Illuminate\Support\Facades\RateLimiter;
use App\Repositories\RoomAvailabilityRepository;
use App\Repositories\DbRoomAvailabilityRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Property Repository Binding
        $this->app->bind(
            PropertyRepository::class,
            DbPropertyRepository::class
        );

        // Room Repository Binding
        $this->app->bind(
            RoomRepository::class,
            DbRoomRepository::class
        );

        // RoomAvailability Repository Binding
        $this->app->bind(
            RoomAvailabilityRepository::class,
            DbRoomAvailabilityRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //Rate limit for Availability
        RateLimiter::for('getAvailability', function (Request $request) {
            return Limit::perHour(maxAttempts: 100)->by($request->user()?->id ?: $request->ip());
        });

        //Rate limit for Ingestion Endpoint
        RateLimiter::for('availabilityIngestion', function (Request $request) {
        return Limit::perHour(60)->by($request->ip());
        });
        }
}
