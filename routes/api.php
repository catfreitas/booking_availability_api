<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AvailabilityController;

/* Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum'); */

//GetAvailability w/Auth
Route::middleware(['auth:sanctum',  'throttle:getAvailability'])->get('/availability', [AvailabilityController::class, 'index']);

//Availability Ingestion
Route::post('/availability-ingest', [AvailabilityController::class, 'store'])->middleware('throttle:availabilityIngestion');

//Connection Agent Dialogflow
Route::post('/agent-webhook', [AvailabilityController::class, 'handleAvailability']);
