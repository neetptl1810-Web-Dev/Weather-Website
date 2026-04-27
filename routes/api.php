<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

Route::get('/cities/suggest', function (\Illuminate\Http\Request $request) {
    $query = $request->input('q');
    if (!$query || strlen($query) < 2) return response()->json([]);

    $cacheKey = "suggest:{$query}";
    return Cache::remember($cacheKey, now()->addHours(24), function () use ($query) {
        $res = Http::withoutVerifying()->timeout(3)->get('https://geocoding-api.open-meteo.com/v1/search', [
            'name' => $query,
            'count' => 8
        ]);

        return $res->successful() && !empty($res->json()['results']) 
            ? collect($res->json()['results'])->pluck('name')->unique()->values()
            : [];
    });
})->middleware('throttle:30,1');