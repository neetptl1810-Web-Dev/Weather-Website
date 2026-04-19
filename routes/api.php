<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

Route::get('/cities/suggest', function (\Illuminate\Http\Request $request) {
    $query = $request->input('q');
    if (!$query || strlen($query) < 2) return response()->json([]);

    $cacheKey = "suggest:{$query}";
    return Cache::remember($cacheKey, now()->addHours(24), function () use ($query) {
        $res = Http::timeout(3)->get('https://api.openweathermap.org/geo/1.0/direct', [
            'q' => $query,
            'limit' => 8,
            'appid' => config('services.weather.api_key')
        ]);

        return $res->successful() 
            ? collect($res->json())->pluck('name')->unique()->values()
            : [];
    });
})->middleware('throttle:30,1');