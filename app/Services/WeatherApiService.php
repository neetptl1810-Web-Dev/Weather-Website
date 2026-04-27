<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class WeatherApiService
{
    /**
     * Fetch complete weather data for a city using Open-Meteo (No API Key Required)
     */
    public function fetchCompleteWeather(string $city): array
    {
        // 1. Geocode city to get coordinates
        $geo = $this->getCityCoordinates($city);
        if (!$geo) {
            throw new Exception("City '{$city}' not found.");
        }

        // 2. Fetch weather and AQI
        $weatherRes = Http::withoutVerifying()->timeout(5)->get("https://api.open-meteo.com/v1/forecast", [
            'latitude' => $geo['lat'],
            'longitude' => $geo['lon'],
            'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,is_day,precipitation,weather_code,cloud_cover,pressure_msl,surface_pressure,wind_speed_10m,visibility',
            'hourly' => 'temperature_2m,weather_code',
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,sunrise,sunset,uv_index_max',
            'timezone' => $geo['timezone'] ?? 'auto'
        ]);

        $aqiRes = Http::withoutVerifying()->timeout(5)->get("https://air-quality-api.open-meteo.com/v1/air-quality", [
            'latitude' => $geo['lat'],
            'longitude' => $geo['lon'],
            'current' => 'european_aqi'
        ]);

        if (!$weatherRes->successful()) {
            throw new Exception("Failed to fetch weather data from Open-Meteo.");
        }

        $weather = $weatherRes->json();
        $aqi = $aqiRes->successful() ? $aqiRes->json() : [];

        return $this->mapToViewStructure($weather, $aqi, $geo);
    }

    /**
     * Search & fetch specific city (for show route)
     */
    public function getWeather(string $city): array
    {
        $geo = $this->getCityCoordinates($city);
        if (!$geo) {
            throw new Exception("City '{$city}' not found.");
        }

        $weatherRes = Http::withoutVerifying()->timeout(5)->get("https://api.open-meteo.com/v1/forecast", [
            'latitude' => $geo['lat'],
            'longitude' => $geo['lon'],
            'current' => 'temperature_2m,relative_humidity_2m,weather_code',
            'timezone' => 'auto'
        ]);

        if (!$weatherRes->successful()) {
            throw new Exception("Failed to fetch weather data.");
        }

        $weather = $weatherRes->json();
        $wmo = $this->getWmoStatus($weather['current']['weather_code'] ?? 0);

        return [
            'city' => $geo['name'],
            'country_code' => $geo['country_code'] ?? '',
            'temperature' => round($weather['current']['temperature_2m'] ?? 0, 1),
            'status' => $wmo['status'],
            'description' => $wmo['status'],
            'humidity' => $weather['current']['relative_humidity_2m'] ?? 0,
        ];
    }

    /**
     * Get city coordinates & country code
     */
    protected function getCityCoordinates(string $city): ?array
    {
        $cacheKey = "geo_om:{$city}";
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($city) {
            $res = Http::withoutVerifying()->timeout(5)->get("https://geocoding-api.open-meteo.com/v1/search", [
                'name' => $city,
                'count' => 1
            ]);

            if ($res->successful() && !empty($res->json()['results'])) {
                $result = $res->json()['results'][0];
                return [
                    'lat' => $result['latitude'],
                    'lon' => $result['longitude'],
                    'name' => $result['name'],
                    'country_code' => $result['country_code'] ?? '',
                    'timezone' => $result['timezone'] ?? 'auto'
                ];
            }

            return null;
        });
    }

    /**
     * Map raw API data to your Blade structure
     */
    protected function mapToViewStructure(array $weather, array $aqi, array $geo): array
    {
        $current = $weather['current'] ?? [];
        $daily = $weather['daily'] ?? [];
        $hourly = $weather['hourly'] ?? [];

        $wmo = $this->getWmoStatus($current['weather_code'] ?? 0);

        return [
            'current' => [
                'city' => $geo['name'],
                'country_code' => $geo['country_code'] ?? '',
                'lat' => $geo['lat'],
                'lon' => $geo['lon'],
                'temperature' => round($current['temperature_2m'] ?? 0, 1),
                'feels_like' => round($current['apparent_temperature'] ?? 0, 1),
                'status' => $wmo['status'],
                'icon' => $wmo['icon'],
                'description' => $wmo['status'],
                'humidity' => $current['relative_humidity_2m'] ?? 0,
            ],
            'sun' => [
                'rise' => isset($daily['sunrise'][0]) ? date('h:i A', strtotime($daily['sunrise'][0])) : '--',
                'set'  => isset($daily['sunset'][0]) ? date('h:i A', strtotime($daily['sunset'][0])) : '--',
            ],
            'metrics' => [
                'humidity'   => ['val' => $current['relative_humidity_2m'] ?? 0, 'unit' => '%', 'icon' => '💧'],
                'wind'       => ['val' => round($current['wind_speed_10m'] ?? 0, 1), 'unit' => 'km/h', 'icon' => '💨'],
                'pressure'   => ['val' => $current['pressure_msl'] ?? 0, 'unit' => 'hPa', 'icon' => '📊'],
                'uv'         => ['val' => $daily['uv_index_max'][0] ?? 0, 'unit' => '', 'icon' => '☀️'],
                'visibility' => ['val' => round(($current['visibility'] ?? 10000) / 1000, 1), 'unit' => 'km', 'icon' => '👁️'],
                'aqi'        => $this->formatAqi($aqi['current']['european_aqi'] ?? 20),
            ],
            'hourly' => $this->extractHourly($hourly),
            'daily'  => $this->extractDaily($daily),
            'alerts' => [], // Open-Meteo doesn't easily provide alerts out of the box
            'weatherData' => [
                $geo['name'] => [
                    'city' => $geo['name'],
                    'country_code' => $geo['country_code'] ?? '',
                    'temperature' => round($current['temperature_2m'] ?? 0, 1),
                    'status' => $wmo['status'],
                    'description' => $wmo['status'],
                    'humidity' => $current['relative_humidity_2m'] ?? 0,
                ]
            ]
        ];
    }

    protected function getWmoStatus(int $code): array
    {
        $map = [
            0 => ['status' => 'Clear Sky', 'icon' => '☀️'],
            1 => ['status' => 'Mainly Clear', 'icon' => '🌤️'],
            2 => ['status' => 'Partly Cloudy', 'icon' => '⛅'],
            3 => ['status' => 'Overcast', 'icon' => '☁️'],
            45 => ['status' => 'Fog', 'icon' => '🌫️'],
            48 => ['status' => 'Depositing Rime Fog', 'icon' => '🌫️'],
            51 => ['status' => 'Light Drizzle', 'icon' => '🌧️'],
            53 => ['status' => 'Moderate Drizzle', 'icon' => '🌧️'],
            55 => ['status' => 'Dense Drizzle', 'icon' => '🌧️'],
            56 => ['status' => 'Freezing Drizzle', 'icon' => '🌧️'],
            57 => ['status' => 'Dense Freezing Drizzle', 'icon' => '🌧️'],
            61 => ['status' => 'Slight Rain', 'icon' => '🌧️'],
            63 => ['status' => 'Moderate Rain', 'icon' => '🌧️'],
            65 => ['status' => 'Heavy Rain', 'icon' => '🌧️'],
            66 => ['status' => 'Light Freezing Rain', 'icon' => '🌧️'],
            67 => ['status' => 'Heavy Freezing Rain', 'icon' => '🌧️'],
            71 => ['status' => 'Slight Snow', 'icon' => '❄️'],
            73 => ['status' => 'Moderate Snow', 'icon' => '❄️'],
            75 => ['status' => 'Heavy Snow', 'icon' => '❄️'],
            77 => ['status' => 'Snow Grains', 'icon' => '❄️'],
            80 => ['status' => 'Slight Rain Showers', 'icon' => '🌦️'],
            81 => ['status' => 'Moderate Rain Showers', 'icon' => '🌦️'],
            82 => ['status' => 'Violent Rain Showers', 'icon' => '🌦️'],
            85 => ['status' => 'Slight Snow Showers', 'icon' => '❄️'],
            86 => ['status' => 'Heavy Snow Showers', 'icon' => '❄️'],
            95 => ['status' => 'Thunderstorm', 'icon' => '⛈️'],
            96 => ['status' => 'Thunderstorm with Hail', 'icon' => '⛈️'],
            99 => ['status' => 'Heavy Thunderstorm with Hail', 'icon' => '⛈️'],
        ];

        return $map[$code] ?? ['status' => 'Unknown', 'icon' => '🌤️'];
    }

    protected function formatAqi(float $europeanAqi): array
    {
        if ($europeanAqi <= 20) { $level = 'good'; $val = 1; }
        elseif ($europeanAqi <= 40) { $level = 'good'; $val = 2; }
        elseif ($europeanAqi <= 60) { $level = 'moderate'; $val = 3; }
        elseif ($europeanAqi <= 80) { $level = 'poor'; $val = 4; }
        else { $level = 'poor'; $val = 5; }

        return [
            'val' => $val * 20, // Scale to 100 for CSS
            'level' => $level,
            'icon' => '🫁',
            'unit' => ''
        ];
    }

    protected function extractHourly(array $hourly): array
    {
        if (empty($hourly['time'])) return [];
        
        $now = time();
        $items = [];
        
        foreach ($hourly['time'] as $index => $time) {
            $timestamp = strtotime($time);
            if ($timestamp >= $now && count($items) < 8) {
                $wmo = $this->getWmoStatus($hourly['weather_code'][$index] ?? 0);
                $items[] = [
                    'time' => date('H:i', $timestamp),
                    'temp' => round($hourly['temperature_2m'][$index] ?? 0, 1),
                    'icon' => $wmo['icon'],
                ];
            }
        }
        
        // Fallback if no upcoming hours found
        if (empty($items)) {
            for ($i=0; $i<8; $i++) {
                if (!isset($hourly['time'][$i])) break;
                $wmo = $this->getWmoStatus($hourly['weather_code'][$i] ?? 0);
                $items[] = [
                    'time' => date('H:i', strtotime($hourly['time'][$i])),
                    'temp' => round($hourly['temperature_2m'][$i] ?? 0, 1),
                    'icon' => $wmo['icon'],
                ];
            }
        }
        
        return $items;
    }

    protected function extractDaily(array $daily): array
    {
        if (empty($daily['time'])) return [];
        
        $items = [];
        foreach ($daily['time'] as $index => $time) {
            if ($index >= 7) break;
            $wmo = $this->getWmoStatus($daily['weather_code'][$index] ?? 0);
            $items[] = [
                'day' => date('l', strtotime($time)),
                'high' => round($daily['temperature_2m_max'][$index] ?? 0, 1),
                'low' => round($daily['temperature_2m_min'][$index] ?? 0, 1),
                'icon' => $wmo['icon'],
            ];
        }
        return $items;
    }
}