<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWeatherRequest;
use App\Services\WeatherApiService;
use Illuminate\Support\Facades\Cache;

class WeatherController extends Controller
{
    public function __construct(protected WeatherApiService $apiService) {}

    /**
     * Display live weather dashboard
     */
    public function index()
    {
        // Default cities to show on dashboard
        $cities = ['London', 'New York', 'Tokyo', 'Sydney', 'Mumbai'];
        
        // Fetch all from cache/API in parallel
        $weatherData = collect($cities)->mapWithKeys(function ($city) {
            return [$city => $this->apiService->getWeather($city)];
        });

        return view('weather.index', [
            'weatherData' => $weatherData,
            'lastUpdated' => now()->format('g:i A'),
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