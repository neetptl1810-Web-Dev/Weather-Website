<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $current['city'] ?? 'Weather' }} – Weather Broadcast</title>
    <meta name="description" content="Live weather for {{ $current['city'] ?? 'your location' }}. Current conditions, hourly and 7-day forecast.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @vite(['resources/css/weather.css', 'resources/js/weather.js'])
</head>

<body>

    <!-- ─── HEADER ──────────────────────────────────────────── -->
    <header class="site-header">
        <div class="site-header__inner">
            <div class="site-header__brand">
                <svg class="brand-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="5" fill="#FFD100"/>
                    <path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" stroke="#FFD100" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span class="brand-name">Weather<strong>Broadcast</strong></span>
            </div>

            <form action="{{ route('weather.index') }}" method="GET" class="header-search-form" role="search">
                <div class="header-search-box">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" name="city" id="city-search" value="{{ request('city') }}"
                           placeholder="Search city or postcode..." autocomplete="off" aria-label="Search location">
                    <div class="autocomplete-list" id="autocomplete-list"></div>
                    <button type="submit" class="header-search-btn">Search</button>
                </div>
            </form>

            <div class="header-controls">
                <button id="unit-toggle" class="ctrl-btn ctrl-btn--unit" aria-label="Toggle units">°F</button>
                <button id="theme-toggle" class="ctrl-btn ctrl-btn--theme" aria-label="Toggle theme">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <!-- ─── ALERTS ──────────────────────────────────────────── -->
    @if(!empty($alerts))
        <div class="alert-strip" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <strong>Weather Alert:</strong> {{ $alerts[0]['message'] ?? 'Severe weather expected in your area.' }}
        </div>
    @endif

    <div class="page-wrapper">
        <main class="main-content">

            <!-- ─── HERO ─────────────────────────────────────── -->
            <section class="weather-hero">
                <div class="hero-location">
                    <h1 class="hero-city">{{ $current['city'] ?? 'London' }}</h1>
                    <span class="hero-updated">Updated just now</span>
                </div>

                <div class="hero-body">
                    <div class="hero-current">
                        <div class="hero-icon-wrap">
                            <span class="hero-icon">{{ $current['icon'] ?? '☀️' }}</span>
                        </div>
                        <div class="hero-temps">
                            <div class="hero-temp" id="current-temp" data-celsius="{{ $current['temperature'] ?? 22 }}">
                                {{ $current['temperature'] ?? 22 }}<span class="hero-temp-unit">°C</span>
                            </div>
                            <div class="hero-feels">
                                Feels like <span id="feels-like" data-celsius="{{ $current['feels_like'] ?? $current['temperature'] ?? 20 }}">{{ $current['feels_like'] ?? $current['temperature'] ?? 20 }}°</span>
                            </div>
                            <div class="hero-condition">{{ $current['status'] ?? 'Clear Sky' }}</div>
                            <div class="hero-hl">
                                <span class="hero-high">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
                                    <span id="today-high" data-celsius="{{ $daily[0]['high'] ?? 24 }}">{{ $daily[0]['high'] ?? 24 }}°</span>
                                </span>
                                <span class="hero-sep">|</span>
                                <span class="hero-low">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                    <span id="today-low" data-celsius="{{ $daily[0]['low'] ?? 15 }}">{{ $daily[0]['low'] ?? 15 }}°</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick stats strip -->
                    <div class="hero-quick-stats">
                        @if(isset($metrics['humidity']))
                            <div class="quick-stat">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                                <span>{{ $metrics['humidity']['val'] }}{{ $metrics['humidity']['unit'] }}</span>
                                <small>Humidity</small>
                            </div>
                        @endif
                        @if(isset($metrics['wind']))
                            <div class="quick-stat">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.59 4.59A2 2 0 1 1 11 8H2m10.59 11.41A2 2 0 1 0 14 16H2m15.73-8.27A2.5 2.5 0 1 1 19.5 12H2"/></svg>
                                <span>{{ $metrics['wind']['val'] }}{{ $metrics['wind']['unit'] }}</span>
                                <small>Wind</small>
                            </div>
                        @endif
                        @if(isset($metrics['visibility']))
                            <div class="quick-stat">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <span>{{ $metrics['visibility']['val'] }}{{ $metrics['visibility']['unit'] }}</span>
                                <small>Visibility</small>
                            </div>
                        @endif
                        @if(isset($metrics['uv']))
                            <div class="quick-stat">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                                <span>{{ $metrics['uv']['val'] }}</span>
                                <small>UV Index</small>
                            </div>
                        @endif
                        @if(isset($metrics['pressure']))
                            <div class="quick-stat">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10"/><path d="M12 6v6l4 2"/></svg>
                                <span>{{ $metrics['pressure']['val'] }}</span>
                                <small>Pressure</small>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            <!-- ─── DAY CAROUSEL ─────────────────────────────── -->
            <section class="day-carousel-section">
                <div class="day-carousel" id="day-carousel">
                    @foreach($daily as $i => $day)
                        <button class="day-tile {{ $i === 0 ? 'active' : '' }}" data-date="{{ $day['date'] }}">
                            <span class="day-tile__label">{{ $i === 0 ? 'Today' : substr($day['day'], 0, 3) }}</span>
                            <span class="day-tile__icon">{{ $day['icon'] }}</span>
                            <span class="day-tile__high" data-celsius="{{ $day['high'] }}">{{ $day['high'] }}°</span>
                            <span class="day-tile__low" data-celsius="{{ $day['low'] }}">{{ $day['low'] }}°</span>
                        </button>
                    @endforeach
                </div>
            </section>

            <!-- ─── HOURLY FORECAST ──────────────────────────── -->
            <section class="forecast-section">
                <div class="section-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Hourly Forecast
                </div>
                <div class="hourly-table-wrap">
                    <div class="hourly-table" id="hourly-table">
                        @php $firstDate = $daily[0]['date'] ?? date('Y-m-d'); @endphp
                        @forelse($hourly[$firstDate] ?? [] as $i => $hour)
                            <div class="hour-col {{ $i === 0 && $firstDate === date('Y-m-d') ? 'hour-col--now' : '' }}">
                                <span class="hour-col__time">{{ $i === 0 && $firstDate === date('Y-m-d') ? 'Now' : $hour['time'] }}</span>
                                <span class="hour-col__icon">{{ $hour['icon'] }}</span>
                                <span class="hour-col__temp" data-celsius="{{ $hour['temp'] }}">{{ $hour['temp'] }}°</span>
                                <div class="hour-col__precip">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                                    {{ $hour['precip'] }}%
                                </div>
                            </div>
                        @empty
                            <div class="hour-col"><span class="hour-col__time">N/A</span></div>
                        @endforelse
                    </div>
                </div>
            </section>

            <!-- ─── METRICS GRID ─────────────────────────────── -->
            <section class="forecast-section">
                <div class="section-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Weather Details
                </div>
                <div class="metrics-grid">
                    @foreach($metrics as $key => $metric)
                        <div class="metric-card {{ $key === 'aqi' ? 'metric-card--aqi' : '' }}">
                            <div class="metric-card__header">
                                <span class="metric-card__icon">{{ $metric['icon'] }}</span>
                                <span class="metric-card__label">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                            </div>
                            <div class="metric-card__value {{ $key === 'aqi' ? 'aqi-' . ($metric['level'] ?? 'good') : '' }}">
                                {{ $metric['val'] }}<span class="metric-card__unit">{{ $metric['unit'] }}</span>
                            </div>
                            @if($key === 'aqi')
                                <div class="aqi-track">
                                    <div class="aqi-fill" style="width: {{ min(($metric['val'] / 300) * 100, 100) }}%"></div>
                                </div>
                                <div class="aqi-labels"><span>Good</span><span>Moderate</span><span>Poor</span></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <!-- ─── CITY WEATHER ─────────────────────────────── -->
            @if(!empty($weatherData))
                <section class="forecast-section">
                    <div class="section-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        Around the World
                    </div>
                    <div class="city-grid">
                        @forelse($weatherData as $cityName => $weather)
                            <article class="city-card">
                                <div class="city-card__top">
                                    <div>
                                        <h2 class="city-card__name">{{ $weather['city'] ?? $cityName }}</h2>
                                        @if(!empty($weather['country_code']))
                                            <span class="city-card__country">{{ $weather['country_code'] }}</span>
                                        @endif
                                    </div>
                                    <span class="city-card__icon" data-condition="{{ strtolower($weather['status'] ?? 'clear') }}">☀️</span>
                                </div>
                                <div class="city-card__temp">{{ $weather['temperature'] ?? '--' }}°C</div>
                                <p class="city-card__desc">{{ $weather['description'] ?? $weather['status'] ?? '' }}</p>
                                <div class="city-card__meta">
                                    <span>💧 {{ $weather['humidity'] ?? '--' }}%</span>
                                    <span class="city-card__badge city-card__badge--{{ strtolower($weather['status'] ?? 'unknown') }}">{{ $weather['status'] ?? 'Unknown' }}</span>
                                </div>
                            </article>
                        @empty
                            <div class="empty-state">
                                <div class="empty-state__icon">🔍</div>
                                <p>No city data available. Search for a city above.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            @endif

            <!-- ─── MAP ──────────────────────────────────────── -->
            <section class="forecast-section">
                <div class="section-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
                    Live Map
                </div>
                <div class="map-container" data-lat="{{ $current['lat'] ?? 51.5074 }}" data-lon="{{ $current['lon'] ?? -0.1278 }}">
                    <div id="weather-map" style="width:100%;height:360px;border-radius:12px;z-index:1;"></div>
                </div>
            </section>

        </main>
    </div>

    <!-- ─── FOOTER ──────────────────────────────────────────── -->
    <footer class="site-footer">
        <div class="site-footer__inner">
            <span>Weather Broadcast © {{ date('Y') }}</span>
            <span>Data via Open-Meteo · Refreshes every 10 min</span>
        </div>
    </footer>

    <!-- hidden fields for JS -->
    <span id="weather-status" style="display:none">{{ $current['status'] ?? '' }}</span>
    <script id="hourly-data" type="application/json">@json($hourly)</script>

</body>
</html>