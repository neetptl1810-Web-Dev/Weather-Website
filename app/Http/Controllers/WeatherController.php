<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWeatherRequest;
use Illuminate\Http\Request;
use App\Services\WeatherApiService;
use Illuminate\Support\Facades\Cache;

class WeatherController extends Controller
{
    public function __construct(protected WeatherApiService $apiService) {}

    /**
     * Display live weather dashboard
     */
        public function index(Request $request)
{
    $city = $request->input('city', 'London');
    
    // Fetch or generate weather data (cached for 10 mins)
    $data = Cache::remember("weather:{$city}", now()->addMinutes(10), function () use ($city) {
        return [
            'current' => [
                'city' => $city,
                'country_code' => 'GB',
                'temperature' => 22,
                'feels_like' => 20,
                'status' => 'Clear Sky',
                'icon' => '☀️',
                'description' => 'Beautiful clear skies'
            ],
            'sun' => ['rise' => '06:12 AM', 'set' => '06:45 PM'],
            'metrics' => [
                'humidity'   => ['val' => 65, 'unit' => '%', 'icon' => '💧'],
                'wind'       => ['val' => 12, 'unit' => 'km/h', 'icon' => '💨'],
                'pressure'   => ['val' => 1013, 'unit' => 'hPa', 'icon' => '📊'],
                'uv'         => ['val' => 4, 'unit' => '', 'icon' => '☀️'],
                'visibility' => ['val' => 10, 'unit' => 'km', 'icon' => '👁️'],
                'aqi'        => ['val' => 42, 'unit' => '', 'icon' => '🫁', 'level' => 'good']
            ],
            'alerts' => []
        ];
    });

    // ✅ CRITICAL: Build $weatherData array for the @forelse loop in Blade
    // This must be an associative array: ['CityName' => [weather data], ...]
    $weatherData = [
        $data['current']['city'] => $data['current']
    ];

    return view('weather.index', [
        'current'     => $data['current'],
        'sun'         => $data['sun'],
        'metrics'     => $data['metrics'],
        'alerts'      => $data['alerts'],
        'lastUpdated' => now()->format('g:i A'),
        'weatherData' => $weatherData, // ✅ This is what the @forelse loop uses
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