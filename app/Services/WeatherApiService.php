<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WeatherApiService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.weather.api_key', env('WEATHER_API_KEY'));
        $this->baseUrl = config('services.weather.base_url', 'https://api.openweathermap.org/data/2.5');
    }

    /**
     * Fetch weather data for a city with caching
     */
    public function getWeather(string $city): array
    {
        $cacheKey = "weather:{$city}";
        
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($city) {
            try {
                $response = Http::timeout(10)->get("{$this->baseUrl}/weather", [
                    'q' => $city,
                    'appid' => $this->apiKey,
                    'units' => 'metric', // or 'imperial'
                ]);

                if ($response->failed()) {
                    Log::warning("Weather API failed for {$city}", [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return $this->getDefaultWeather($city);
                }

                return $this->normalizeApiResponse($response->json(), $city);
                
            } catch (\Throwable $e) {
                Log::error("Weather API exception for {$city}", ['error' => $e->getMessage()]);
                return $this->getDefaultWeather($city);
            }
        });
    }

    /**
     * Fallback data when API fails
     */
    protected function getDefaultWeather(string $city): array
    {
        return [
            'city' => $city,
            'temperature' => 20.0,
            'humidity' => 60,
            'status' => 'unknown',
            'description' => 'Data temporarily unavailable',
            'source' => 'cache_fallback',
        ];
    }

    /**
     * Normalize API response to our app's format
     */
    protected function normalizeApiResponse(array $data, string $city): array
    {
        return [
            'city' => $city,
            'country_code' => $data['sys']['country'] ?? null,
            'temperature' => round($data['main']['temp'] ?? 0, 2),
            'humidity' => (int) ($data['main']['humidity'] ?? 0),
            'status' => $data['weather'][0]['main'] ?? 'unknown',
            'description' => $data['weather'][0]['description'] ?? '',
            'source' => 'api',
            'recorded_at' => now(),
        ];
    }

    /**
     * Search for cities matching a query
     */
    public function searchCities(string $query): array
    {
        $cacheKey = "weather:search:{$query}";
        
        return Cache::remember($cacheKey, now()->addHours(1), function () use ($query) {
            try {
                $response = Http::get("{$this->baseUrl}/find", [
                    'q' => $query,
                    'appid' => $this->apiKey,
                    'cnt' => 5,
                ]);

                return $response->successful() 
                    ? $response->json('list', []) 
                    : [];
            } catch (\Throwable $e) {
                Log::error("City search failed", ['error' => $e->getMessage()]);
                return [];
            }
        });
    }
}