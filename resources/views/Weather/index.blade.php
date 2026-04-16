<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌤️ Weather Broadcast</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="container">
    
    <header class="header">
        <h1>🌤️ Live Weather Dashboard</h1>
        <span class="last-updated">Last updated: {{ $lastUpdated }}</span>
    </header>

    <div class="test-badge">
        ✨ Plain CSS is working!
    </div>

    <form action="{{ route('weather.index') }}" method="GET" class="search-form">
        <input type="text" name="city" placeholder="Search city..." 
               value="{{ request('city') }}" required>
        <button type="submit">🔍 Search</button>
    </form>

    <div class="weather-grid">
        @forelse($weatherData as $city => $weather)
            <article class="weather-card">
                <div class="weather-card-header">
                    <div>
                        <h2 class="weather-card-title">{{ $weather['city'] }}</h2>
                        @if(!empty($weather['country_code']))
                            <span class="weather-card-country">{{ $weather['country_code'] }}</span>
                        @endif
                    </div>
                    <span class="weather-card-icon" title="{{ $weather['status'] }}">
                        @php
                            $status = strtolower($weather['status']);
                        @endphp
                        @if(str_contains($status, 'rain')) 🌧️
                        @elseif(str_contains($status, 'sun') || str_contains($status, 'clear')) ☀️
                        @elseif(str_contains($status, 'cloud')) ☁️
                        @elseif(str_contains($status, 'snow')) ❄️
                        @elseif(str_contains($status, 'storm')) ⛈️
                        @else 🌤️
                        @endif
                    </span>
                </div>
                
                <div class="weather-card-temp">{{ $weather['temperature'] }}°C</div>
                
                <p class="weather-card-desc">
                    {{ !empty($weather['description']) ? $weather['description'] : 'No description available' }}
                </p>
                
                <div class="weather-card-meta">
                    <span>💧 {{ $weather['humidity'] }}% humidity</span>
                    <span class="weather-status {{ strtolower($weather['status']) }}">
                        {{ ucfirst($weather['status']) }}
                    </span>
                </div>
                
                @if(isset($weather['source']) && $weather['source'] === 'cache_fallback')
                    <div style="margin-top: 0.5rem; font-size: 0.75rem; color: #92400e; background: #fef3c7; padding: 0.25rem 0.5rem; border-radius: 4px; display: inline-block;">
                        ⚠️ Cached data
                    </div>
                @endif
            </article>
        @empty
            <div style="text-align: center; padding: 3rem 0;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                <h3 style="font-weight: 600; margin-bottom: 0.5rem;">No weather data found</h3>
                <p style="color: var(--color-text-muted);">Try searching for a different city</p>
            </div>
        @endforelse
    </div>

</div>
</body>
</html>