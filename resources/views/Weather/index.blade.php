<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weather Broadcast</title>
    @vite(['resources/css/weather.css', 'resources/js/weather.js'])
</head>
<body>
    <!-- Dynamic Background Layer -->
    <div class="dynamic-bg" id="dynamic-bg"></div>
    
    <!-- Weather Particles Container -->
    <div class="weather-particles" id="weather-particles"></div>

    <div class="app-wrapper">
        <!-- Header -->
        <header class="app-header glass">
            <div class="logo">Weather Broadcast</div>
            <div class="header-controls">
                <button id="unit-toggle" class="theme-toggle" aria-label="Toggle units">°F</button>
                <button class="theme-toggle" id="theme-toggle" aria-label="Toggle theme">🌙</button>
            </div>
        </header>

        <!-- Alerts Banner -->
        @if(!empty($alerts))
        <div class="alert-banner glass">
            ⚠️ <strong>Weather Alert:</strong> {{ $alerts[0]['message'] ?? 'Severe weather expected.' }}
        </div>
        @endif

        <main class="main-content">
            <!-- Hero Section -->
            <section class="hero-section glass">
                <div class="hero-left">
                    <h2 class="location">
                        {{ $current['city'] ?? 'London' }} 
                        <span class="country">{{ $current['country_code'] ?? 'GB' }}</span>
                    </h2>
                    <div class="temp-wrapper">
                        <span class="weather-icon" 
                              data-condition="{{ strtolower($current['status'] ?? 'clear') }}">
                            ☀️
                        </span>
                        <span class="temp" id="current-temp" 
                              data-celsius="{{ $current['temperature'] ?? 22 }}">
                            {{ $current['temperature'] ?? 22 }}°
                        </span>
                    </div>
                    <p class="condition" id="weather-status">
                        {{ $current['status'] ?? 'Clear Sky' }}
                    </p>
                    <p class="feels-like">
                        Feels like 
                        <span id="feels-like" data-celsius="{{ $current['feels_like'] ?? 20 }}">
                            {{ $current['feels_like'] ?? 20 }}
                        </span>°C
                    </p>
                </div>
                <div class="hero-right">
                    <div class="search-container">
                        <input type="text" id="city-search" placeholder="Search city..." 
                               autocomplete="off" value="{{ request('city') }}">
                        <div class="autocomplete-list" id="autocomplete-list"></div>
                        <button type="button" id="search-btn">🔍</button>
                    </div>
                    <div class="sun-times">
                        <div class="sun-item"> <span>{{ $sun['rise'] ?? '06:12' }}</span></div>
                        <div class="sun-item"> <span>{{ $sun['set'] ?? '18:45' }}</span></div>
                    </div>
                </div>
            </section>

            <!-- Weather Highlights -->
            <section class="highlights-grid">
                @foreach($metrics as $key => $metric)
                <div class="highlight-card glass {{ $key === 'aqi' ? 'aqi-card' : '' }}">
                    <span class="h-icon">{{ $metric['icon'] }}</span>
                    <div class="h-data">
                        <span class="h-label">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                        <span class="h-value {{ $key === 'aqi' ? 'aqi-' . ($metric['level'] ?? 'good') : '' }}">
                            {{ $metric['val'] }} {{ $metric['unit'] }}
                        </span>
                    </div>
                    @if($key === 'aqi')
                    <div class="aqi-bar">
                        <div class="aqi-fill" style="width: {{ min($metric['val'] / 100 * 100, 100) }}%"></div>
                    </div>
                    @endif
                </div>
                @endforeach
            </section>

            <!-- City Weather Grid -->
            <section class="section-wrapper">
                <h3 class="section-title">🌍 City Weather</h3>
                <div class="weather-grid">
                    @forelse($weatherData as $cityName => $weather)
                        <article class="weather-card glass">
                            <div class="weather-card-header">
                                <div>
                                    <h2 class="weather-card-title">
                                        {{ $weather['city'] ?? $cityName }}
                                    </h2>
                                    @if(!empty($weather['country_code']))
                                        <span class="weather-card-country">
                                            {{ $weather['country_code'] }}
                                        </span>
                                    @endif
                                </div>
                                <span class="weather-card-icon" 
                                      data-condition="{{ strtolower($weather['status'] ?? 'clear') }}">
                                    ☀️
                                </span>
                            </div>
                            <div class="weather-card-temp">
                                {{ $weather['temperature'] ?? '--' }}°C
                            </div>
                            <p class="weather-card-desc">
                                {{ $weather['description'] ?? 'No description available' }}
                            </p>
                            <div class="weather-card-meta">
                                <span>💧 {{ $weather['humidity'] ?? '--' }}% humidity</span>
                                <span class="weather-status {{ strtolower($weather['status'] ?? 'unknown') }}">
                                    {{ ucfirst($weather['status'] ?? 'Unknown') }}
                                </span>
                            </div>
                        </article>
                    @empty
                        <div class="empty-state">
                            <div class="empty-icon">🔍</div>
                            <h3>No weather data found</h3>
                            <p>Try searching for a different city</p>
                        </div>
                    @endforelse
                </div>
            </section>

            <!-- Hourly Forecast -->
<div class="hourly-scroll">
    @forelse($hourly as $hour)
    <div class="hourly-card glass">
        <span class="h-time">{{ $hour['time'] }}</span>
        <span class="h-icon">{{ $hour['icon'] }}</span>
        <span class="h-temp">{{ $hour['temp'] }}°</span>
    </div>
    @empty
    <div class="hourly-card glass"><span>No hourly data</span></div>
    @endforelse
</div>

<!-- 7-Day Forecast -->
<div class="daily-forecast glass">
    @forelse($daily as $day)
    <div class="daily-row">
        <span class="day-name">{{ $day['day'] }}</span>
        <span class="day-icon">{{ $day['icon'] }}</span>
        <span class="day-temp">
            <span class="low">{{ $day['low'] }}°</span>
            <span class="high">{{ $day['high'] }}°</span>
        </span>
    </div>
    @empty
    <p class="text-center p-4">Forecast unavailable</p>
    @endforelse
</div>

            <!-- Interactive Map -->
            <section class="section-wrapper">
                <h3 class="section-title">🗺️ Live Radar Map</h3>
                <div class="map-container glass">
                    <div class="map-overlay">
                        <button class="map-layer active" data-layer="temp">🌡️ Temp</button>
                        <button class="map-layer" data-layer="rain">🌧️ Precipitation</button>
                        <button class="map-layer" data-layer="wind">💨 Wind</button>
                    </div>
                    <div class="map-placeholder">
                        <p>Interactive weather map loads here via Leaflet / Mapbox</p>
                        <span class="map-credit">Powered by OpenWeatherMap Layers</span>
                    </div>
                </div>
            </section>
        </main>

        <footer class="app-footer glass">
            <p>Weather Broadcast © {{ date('Y') }} | Data refreshes every 10 mins</p>
        </footer>
    </div>
</body>
</html>