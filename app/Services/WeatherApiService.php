<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class WeatherApiService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.weather.api_key');
        $this->baseUrl = config('services.weather.base_url', 'https://api.openweathermap.org/data/2.5');
        
        if (!$this->apiKey) {
            throw new Exception('Weather API key is missing. Add WEATHER_API_KEY to your .env file.');
        }
    }

    /**
     * Fetch complete weather data for a city
     */
    public function fetchCompleteWeather(string $city): array
    {
        // 1. Geocode city to get coordinates
        $geo = $this->getCityCoordinates($city);
        if (!$geo) {
            throw new Exception("City '{$city}' not found.");
        }

        // 2. Fetch all data in parallel
        $current = $this->getCurrentWeather($geo['lat'], $geo['lon']);
        $forecast = $this->getForecast($geo['lat'], $geo['lon']);
        $aqi = $this->getAirQuality($geo['lat'], $geo['lon']);

        return $this->mapToViewStructure($current, $forecast, $aqi, $geo);
    }

    /**
     * Get city coordinates & country code
     */
    protected function getCityCoordinates(string $city): ?array
    {
        $cacheKey = "geo:{$city}";
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($city) {
            $res = Http::timeout(5)->get("{$this->baseUrl}/geo/1.0/direct", [
                'q' => $city,
                'limit' => 1,
                'appid' => $this->apiKey
            ]);

            return $res->successful() ? ($res->json()[0] ?? null) : null;
        });
    }

    /**
     * Current weather
     */
    protected function getCurrentWeather(float $lat, float $lon): array
    {
        $res = Http::timeout(5)->get("{$this->baseUrl}/weather", [
            'lat' => $lat, 'lon' => $lon,
            'units' => 'metric', 'appid' => $this->apiKey
        ]);

        return $res->successful() ? $res->json() : [];
    }

    /**
     * 5-day / 3-hour forecast
     */
    protected function getForecast(float $lat, float $lon): array
    {
        $res = Http::timeout(5)->get("{$this->baseUrl}/forecast", [
            'lat' => $lat, 'lon' => $lon,
            'units' => 'metric', 'appid' => $this->apiKey
        ]);

        return $res->successful() ? $res->json() : [];
    }

    /**
     * Air Quality Index
     */
    protected function getAirQuality(float $lat, float $lon): array
    {
        $res = Http::timeout(5)->get("https://api.openweathermap.org/data/2.5/air_pollution", [
            'lat' => $lat, 'lon' => $lon, 'appid' => $this->apiKey
        ]);

        return $res->successful() ? ($res->json()['list'][0] ?? []) : [];
    }

    /**
     * Map raw API data to your Blade structure
     */
    protected function mapToViewStructure(array $current, array $forecast, array $aqi, array $geo): array
    {
        return [
            'current' => [
                'city' => $current['name'] ?? $geo['name'],
                'country_code' => $current['sys']['country'] ?? $geo['country'],
                'temperature' => round($current['main']['temp'] ?? 0, 1),
                'feels_like' => round($current['main']['feels_like'] ?? 0, 1),
                'status' => $current['weather'][0]['main'] ?? 'Unknown',
                'icon' => $this->getIconCode($current['weather'][0]['icon'] ?? '01d'),
                'description' => ucfirst($current['weather'][0]['description'] ?? 'No description'),
                'humidity' => $current['main']['humidity'] ?? 0,
            ],
            'sun' => [
                'rise' => date('h:i A', $current['sys']['sunrise'] ?? time()),
                'set'  => date('h:i A', $current['sys']['sunset'] ?? time()),
            ],
            'metrics' => [
                'humidity'   => ['val' => $current['main']['humidity'] ?? 0, 'unit' => '%', 'icon' => '💧'],
                'wind'       => ['val' => round($current['wind']['speed'] ?? 0, 1), 'unit' => 'm/s', 'icon' => '💨'],
                'pressure'   => ['val' => $current['main']['pressure'] ?? 0, 'unit' => 'hPa', 'icon' => '📊'],
                'uv'         => ['val' => $forecast['list'][0]['uvi'] ?? 0, 'unit' => '', 'icon' => '☀️'],
                'visibility' => ['val' => round(($current['visibility'] ?? 10000) / 1000, 1), 'unit' => 'km', 'icon' => '👁️'],
                'aqi'        => $this->formatAqi($aqi['main']['aqi'] ?? 1),
            ],
            'hourly' => $this->extractHourly($forecast),
            'daily'  => $this->extractDaily($forecast),
            'alerts' => [], // OWM free tier doesn't include alerts
            'weatherData' => [
                $current['name'] ?? $geo['name'] => [
                    'city' => $current['name'] ?? $geo['name'],
                    'country_code' => $current['sys']['country'] ?? $geo['country'],
                    'temperature' => round($current['main']['temp'] ?? 0, 1),
                    'status' => $current['weather'][0]['main'] ?? 'Unknown',
                    'description' => ucfirst($current['weather'][0]['description'] ?? ''),
                    'humidity' => $current['main']['humidity'] ?? 0,
                ]
            ]
        ];
    }

    protected function getIconCode(string $code): string
    {
        $map = ['01d' => '☀️', '01n' => '🌙', '02d' => '🌤️', '02n' => '🌙', '03d' => '☁️', '04d' => '☁️', '09d' => '🌧️', '10d' => '🌦️', '11d' => '⛈️', '13d' => '❄️', '50d' => '🌫️'];
        return $map[$code] ?? '🌤️';
    }

    protected function formatAqi(int $aqi): array
    {
        $levels = [1 => 'good', 2 => 'moderate', 3 => 'moderate', 4 => 'poor', 5 => 'poor'];
        $labels = [1 => 'Good', 2 => 'Fair', 3 => 'Moderate', 4 => 'Poor', 5 => 'Very Poor'];
        return [
            'val' => $aqi * 20, // Scale to 100 for CSS
            'level' => $levels[$aqi] ?? 'good',
            'icon' => '🫁'
        ];
    }

    protected function extractHourly(array $forecast): array
    {
        return collect($forecast['list'] ?? [])
            ->take(8)
            ->map(fn($item) => [
                'time' => date('H:i', strtotime($item['dt_txt'])),
                'temp' => round($item['main']['temp'], 1),
                'icon' => $this->getIconCode($item['weather'][0]['icon']),
            ])->values()->all();
    }

    protected function extractDaily(array $forecast): array
    {
        $daily = collect($forecast['list'] ?? [])
            ->groupBy(fn($item) => date('Y-m-d', strtotime($item['dt_txt'])))
            ->take(7)
            ->map(function ($day) {
                $temps = $day->pluck('main.temp');
                return [
                    'day' => date('l', strtotime($day->first()['dt_txt'])),
                    'high' => round($temps->max(), 1),
                    'low' => round($temps->min(), 1),
                    'icon' => $this->getIconCode($day->first()['weather'][0]['icon']),
                ];
            })->values()->all();

        return $daily;
    }
}