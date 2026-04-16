<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WeatherController;

Route::get('/', [WeatherController::class, 'index'])->name('weather.index');
Route::get('/weather/{city}', [WeatherController::class, 'show'])->name('weather.show');

// Optional: API endpoint for frontend frameworks
Route::get('/api/weather/{city}', function ($city) {
    return app(\App\Services\WeatherApiService::class)->getWeather($city);
})->middleware('throttle:30,1'); // Rate limit: 30 req/min