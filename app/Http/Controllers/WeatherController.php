<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWeatherRequest;
use Illuminate\Http\Request;
use App\Services\WeatherApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WeatherController extends Controller
{
    public function __construct(protected WeatherApiService $apiService) {}

public function index(Request $request)
{
    $city = $request->input('city', 'London');

    try {
        // Cache for 10 mins to respect API rate limits
        $data = Cache::remember("weather:{$city}", now()->addMinutes(10), function () use ($city) {
            return app(\App\Services\WeatherApiService::class)->fetchCompleteWeather($city);
        });
    } catch (\Exception $e) {
        Log::error("Weather fetch failed for {$city}: " . $e->getMessage());
        return back()->with('error', 'Failed to load weather data. Please try again.');
    }

    return view('weather.index', [
        'current'     => $data['current'],
        'sun'         => $data['sun'],
        'metrics'     => $data['metrics'],
        'alerts'      => $data['alerts'] ?? [],
        'hourly'      => $data['hourly'] ?? [],
        'daily'       => $data['daily'] ?? [],
        'lastUpdated' => now()->format('g:i A'),
        'weatherData' => $data['weatherData'] ?? [],
    ]);
}

    /**
     * Search & fetch specific city
     */
    public function show(StoreWeatherRequest $request)
    {
        $city = $request->validated('city');
        $weather = $this->apiService->getWeather($city);

        return view('weather.show', compact('weather', 'city'));
    }
}