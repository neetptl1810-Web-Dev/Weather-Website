/**
 * Weather Dashboard - Interactive JavaScript Module
 * Features: Theme toggle, geolocation, unit switch, live clock,
 *           autocomplete, toasts, animations, accessibility, PWA-ready
 */

document.addEventListener('DOMContentLoaded', () => {
  WeatherApp.init();
});

const WeatherApp = {
  // 📦 Config
  config: {
    refreshInterval: 600000, // 10 mins auto-refresh
    debounceDelay: 300, // Search API debounce
    toastDuration: 4000,
    units: localStorage.getItem('weather-units') || 'metric', // 'metric' = °C, 'imperial' = °F
  },

  // 🎯 State
  state: {
    currentCity: null,
    isRefreshing: false,
    lastFetch: null,
  },

  // 🚀 Initialization
  init() {
    this.cacheElements();
    this.bindEvents();
    this.initTheme();
    this.initUnitToggle();
    this.initLiveClock();
    this.initGeolocation();
    this.initHourlyScroll();
    this.initSwipeGestures();
    this.respectReducedMotion();
    this.setupAccessibility();
    this.injectAnimations();
    this.initMap();

    // Auto-refresh if enabled
    if (this.config.refreshInterval > 0) {
      this.startAutoRefresh();
    }

    console.log('⚡ Weather Dashboard initialized');
  },

  // 🔍 Cache DOM elements for performance
  cacheElements() {
    this.elements = {
      themeToggle: document.getElementById('theme-toggle'),
      unitToggle: document.getElementById('unit-toggle'),
      citySearch: document.getElementById('city-search'),
      autocompleteList: document.getElementById('autocomplete-list'),
      searchBtn: document.getElementById('search-btn'),
      geoBtn: document.getElementById('geo-btn'),
      refreshBtn: document.getElementById('refresh-btn'),
      liveClock: document.getElementById('live-clock'),
      hourlyScroll: document.querySelector('.hourly-scroll'),
      dailyRows: document.querySelectorAll('.daily-row'),
      mapLayers: document.querySelectorAll('.map-layer'),
      weatherIcon: document.getElementById('weather-icon'),
      dynamicBg: document.getElementById('dynamic-bg'),
      toastContainer:
        document.getElementById('toast-container') || this.createToastContainer(),
    };
  },

  // 🎛️ Bind all event listeners
  bindEvents() {
    // Theme & Unit toggles
    this.elements.themeToggle?.addEventListener('click', () => this.toggleTheme());
    this.elements.unitToggle?.addEventListener('click', () => this.toggleUnits());

    // Search with debounce + keyboard nav
    if (this.elements.citySearch) {
      let debounceTimer;
      this.elements.citySearch.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(
          () => this.handleSearchInput(e.target.value),
          this.config.debounceDelay
        );
      });

      // Keyboard navigation for autocomplete
      this.elements.citySearch.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          e.preventDefault();
          this.navigateAutocomplete(e.key);
        }
        if (
          e.key === 'Enter' &&
          this.elements.autocompleteList?.querySelector('.autocomplete-item.active')
        ) {
          e.preventDefault();
          this.elements.autocompleteList
            .querySelector('.autocomplete-item.active')
            .click();
        }
      });
    }

    // Search button click — fill the input and let the native form submit
    this.elements.searchBtn?.addEventListener('click', () => this.performSearch());

    // Geolocation
    this.elements.geoBtn?.addEventListener('click', () => this.detectLocation());

    // Manual refresh
    this.elements.refreshBtn?.addEventListener('click', () => this.refreshWeather());

    // Hourly card click animation
    document.querySelectorAll('.hourly-card')?.forEach((card) => {
      card.addEventListener('click', (e) => {
        e.currentTarget.classList.add('pulse');
        setTimeout(() => e.currentTarget.classList.remove('pulse'), 300);
        this.showToast(
          `Showing details for ${e.currentTarget.querySelector('.h-time')?.textContent}`,
          'info'
        );
      });
    });

    // Daily accordion with smooth expand
    this.elements.dailyRows.forEach((row) => {
      const btn = row.querySelector('.expand-btn');
      const details = row.querySelector('.day-details');
      btn?.addEventListener('click', () => {
        const isExpanded = row.classList.toggle('expanded');
        btn.setAttribute('aria-expanded', isExpanded);
        btn.textContent = isExpanded ? '▲' : '▼';

        // Smooth height animation
        if (details) {
          details.style.maxHeight = isExpanded ? `${details.scrollHeight}px` : '0';
        }
      });
    });

    // Map layer switching with loading state
    this.elements.mapLayers.forEach((layer) => {
      layer.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const layerType = btn.dataset.layer;

        // Visual feedback
        document.querySelector('.map-layer.active')?.classList.remove('active');
        btn.classList.add('active');
        this.showMapLoading(true);

        // Simulate API call (replace with real Leaflet/Mapbox logic)
        await new Promise((resolve) => setTimeout(resolve, 800));

        this.showMapLoading(false);
        this.showToast(
          `🗺️ ${
            layerType === 'temp'
              ? 'Temperature'
              : layerType === 'rain'
              ? 'Precipitation'
              : 'Wind'
          } layer loaded`,
          'success'
        );

        // Update map placeholder text
        const placeholder = document.querySelector('.map-placeholder p');
        if (placeholder) {
          placeholder.textContent = `Displaying ${layerType} overlay for ${
            this.state.currentCity || 'current location'
          }`;
        }
      });
    });

    // Close autocomplete on outside click
    document.addEventListener('click', (e) => {
      if (
        !this.elements.citySearch?.contains(e.target) &&
        !this.elements.autocompleteList?.contains(e.target)
      ) {
        this.hideAutocomplete();
      }
    });
  },

  // 🌓 Theme Toggle with smooth transition
  toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const next = current === 'light' ? 'dark' : 'light';

    // Add transition class for smooth fade
    document.body.classList.add('theme-transition');

    html.setAttribute('data-theme', next);
    localStorage.setItem('weather-theme', next);
    this.elements.themeToggle.textContent = next === 'dark' ? '☀️' : '🌙';

    // Update background + icon animation
    this.updateDynamicBackground();
    this.animateWeatherIcon();

    // Remove transition class after animation
    setTimeout(() => document.body.classList.remove('theme-transition'), 300);

    this.showToast(`Switched to ${next} mode`, 'info');
  },

  initTheme() {
    const stored =
      localStorage.getItem('weather-theme') ||
      (window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light');
    document.documentElement.setAttribute('data-theme', stored);
    this.elements.themeToggle.textContent = stored === 'dark' ? '☀️' : '🌙';
    this.updateDynamicBackground();
  },

  // 🌡️ Unit Toggle (°C / °F)
  toggleUnits() {
    this.config.units = this.config.units === 'metric' ? 'imperial' : 'metric';
    localStorage.setItem('weather-units', this.config.units);
    this.elements.unitToggle.textContent =
      this.config.units === 'metric' ? '°F' : '°C';

    // Convert visible temperatures
    this.convertTemperatures();
    this.showToast(
      `Units changed to ${
        this.config.units === 'metric' ? 'Celsius (°C)' : 'Fahrenheit (°F)'
      }`,
      'success'
    );
  },

  initUnitToggle() {
    if (this.elements.unitToggle) {
      this.elements.unitToggle.textContent =
        this.config.units === 'metric' ? '°F' : '°C';
    }
  },

  convertTemperatures() {
    const isMetric = this.config.units === 'metric';
    const convert = (celsius) =>
      isMetric ? parseFloat(celsius) : Math.round((parseFloat(celsius) * 9) / 5 + 32);
    const unit = isMetric ? '°C' : '°F';

    // Update current temp
    const tempEl = document.getElementById('current-temp');
    if (tempEl && tempEl.dataset.celsius) {
      tempEl.textContent = `${convert(tempEl.dataset.celsius)}${unit}`;
    }

    // Update feels like – number + label
    const feelsEl = document.getElementById('feels-like');
    if (feelsEl && feelsEl.dataset.celsius) {
      feelsEl.textContent = convert(feelsEl.dataset.celsius);
    }
    // Update the static unit suffix next to feels like
    const feelsUnit = document.querySelector('.feels-like');
    if (feelsUnit) {
      feelsUnit.childNodes.forEach(node => {
        if (node.nodeType === Node.TEXT_NODE && node.textContent.includes('°')) {
          node.textContent = unit + '\n';
        }
      });
      // Simpler: replace °C/°F suffix text
      const feelsHtml = feelsUnit.innerHTML;
      feelsUnit.innerHTML = feelsHtml.replace(/°[CF]\s*$/, unit);
    }

    // Update hourly cards
    document.querySelectorAll('.hourly-card .h-temp').forEach((el) => {
      if (el.dataset.celsius) {
        el.textContent = `${convert(el.dataset.celsius)}${unit}`;
      }
    });

    // Update daily forecast
    document
      .querySelectorAll('.day-temp .high, .day-temp .low')
      .forEach((el) => {
        if (el.dataset.celsius) {
          el.textContent = `${convert(el.dataset.celsius)}${unit}`;
        }
      });
  },

  // 🕐 Live Clock with auto-update
  initLiveClock() {
    if (!this.elements.liveClock) return;

    const updateClock = () => {
      const now = new Date();
      this.elements.liveClock.textContent = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
      });
    };

    updateClock();
    setInterval(updateClock, 30000); // Update every 30 seconds
  },

  // 📍 Geolocation Detection
  async detectLocation() {
    if (!navigator.geolocation) {
      this.showToast('Geolocation not supported', 'error');
      return;
    }

    this.elements.geoBtn?.classList.add('loading');
    this.showToast('📍 Detecting your location...', 'info');

    try {
      const position = await new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, {
          enableHighAccuracy: true,
          timeout: 10000,
          maximumAge: 300000,
        });
      });

      const { latitude, longitude } = position.coords;

      // Reverse geocode to get city name (mock - replace with real API)
      const city = await this.reverseGeocode(latitude, longitude);

      if (city) {
        this.elements.citySearch.value = city;
        this.performSearch();
        this.showToast(`📍 Location set to ${city}`, 'success');
      }
    } catch (error) {
      console.error('Geolocation error:', error);
      this.showToast('Unable to detect location. Please search manually.', 'error');
    } finally {
      this.elements.geoBtn?.classList.remove('loading');
    }
  },

  async reverseGeocode(lat, lon) {
    try {
      const res = await fetch(`https://geocoding-api.open-meteo.com/v1/reverse?latitude=${lat}&longitude=${lon}&count=1`);
      if (!res.ok) throw new Error('Reverse geocoding failed');
      const data = await res.json();
      if (data.results && data.results.length > 0) {
        return data.results[0].name;
      }
      return null;
    } catch (error) {
      console.error(error);
      return null;
    }
  },

  initMap() {
    const mapContainer = document.querySelector('.map-container');
    if (!mapContainer || typeof L === 'undefined') return;

    const lat = parseFloat(mapContainer.dataset.lat) || 51.5074;
    const lon = parseFloat(mapContainer.dataset.lon) || -0.1278;

    this.map = L.map('weather-map').setView([lat, lon], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
      maxZoom: 19
    }).addTo(this.map);

    const cityName = this.state.currentCity || document.querySelector('.location')?.childNodes[0]?.textContent?.trim() || 'Location';
    L.marker([lat, lon]).addTo(this.map)
      .bindPopup(`<b>${cityName}</b>`)
      .openPopup();
  },

  initGeolocation() {
    // Auto-detect on first visit if user allows
    if (!localStorage.getItem('weather-city') && navigator.geolocation) {
      // Optional: auto-detect after 2 seconds
      // setTimeout(() => this.detectLocation(), 2000);
    }
  },

  // 🔍 Search with Autocomplete + API Integration Ready
  async handleSearchInput(query) {
    if (!query || query.length < 2) {
      this.hideAutocomplete();
      return;
    }

    try {
      // Fetch suggestions (replace with real API endpoint)
      const suggestions = await this.fetchCitySuggestions(query);
      this.renderAutocomplete(suggestions);
    } catch (error) {
      console.error('Search error:', error);
      this.showToast('Search unavailable. Try again.', 'error');
    }
  },

  async fetchCitySuggestions(query) {
    try {
        const res = await fetch(`/api/cities/suggest?q=${encodeURIComponent(query)}`);
        if (!res.ok) throw new Error('Autocomplete failed');
        return await res.json();
    } catch (error) {
        console.error(error);
        return [];
    }
},

  renderAutocomplete(cities) {
    const list = this.elements.autocompleteList;
    if (!list) return;

    list.innerHTML = '';

    if (cities.length === 0) {
      const item = document.createElement('div');
      item.className = 'autocomplete-item empty';
      item.textContent = 'No cities found';
      list.appendChild(item);
      list.style.display = 'block';
      return;
    }

    cities.forEach((city, index) => {
      const item = document.createElement('div');
      item.className = 'autocomplete-item';
      item.textContent = city;
      item.dataset.index = index;
      item.setAttribute('role', 'option');
      item.setAttribute('aria-selected', 'false');

      item.addEventListener('click', () => {
        this.elements.citySearch.value = city;
        this.hideAutocomplete();
        this.performSearch();
      });

      item.addEventListener('mouseenter', () => {
        document.querySelectorAll('.autocomplete-item').forEach((i) => {
          i.classList.remove('active');
          i.setAttribute('aria-selected', 'false');
        });
        item.classList.add('active');
        item.setAttribute('aria-selected', 'true');
      });

      list.appendChild(item);
    });

    list.style.display = 'block';
  },

  navigateAutocomplete(direction) {
    const items =
      this.elements.autocompleteList?.querySelectorAll('.autocomplete-item');
    if (!items || items.length === 0) return;

    const active = this.elements.autocompleteList.querySelector(
      '.autocomplete-item.active'
    );
    const currentIndex = active ? Array.from(items).indexOf(active) : -1;

    let nextIndex =
      direction === 'ArrowDown'
        ? Math.min(currentIndex + 1, items.length - 1)
        : Math.max(currentIndex - 1, 0);

    items.forEach((item, i) => {
      item.classList.toggle('active', i === nextIndex);
      item.setAttribute('aria-selected', i === nextIndex ? 'true' : 'false');
    });

    // Scroll into view if needed
    items[nextIndex]?.scrollIntoView({ block: 'nearest' });
  },

  hideAutocomplete() {
    if (this.elements.autocompleteList) {
      this.elements.autocompleteList.style.display = 'none';
    }
  },

  performSearch() {
    const city = this.elements.citySearch?.value.trim();
    if (!city) {
      this.showToast('Please enter a city name', 'error');
      this.elements.citySearch?.focus();
      return;
    }
    this.state.currentCity = city;
    localStorage.setItem('weather-city', city);
    this.showToast(`🔍 Fetching weather for ${city}...`, 'info');
    // Submit the native form — it posts ?city=... to the Laravel route
    const form = document.querySelector('.search-form');
    if (form) form.submit();
  },


  // 🔄 Auto-Refresh with Visual Indicator
  startAutoRefresh() {
    setInterval(() => {
      if (!document.hidden && !this.state.isRefreshing) {
        this.refreshWeather();
      }
    }, this.config.refreshInterval);
  },

  async refreshWeather() {
    if (this.state.isRefreshing) return;
    this.state.isRefreshing = true;
    this.elements.refreshBtn?.classList.add('spinning');
    this.showToast('🔄 Refreshing weather data...', 'info');

    try {
        const res = await fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        if (!res.ok) throw new Error('Refresh failed');
        
        this.showToast('✅ Weather data updated', 'success');
        
        // Update last updated text
        const lastUpdated = document.querySelector('.last-updated');
        if (lastUpdated) lastUpdated.textContent = `Last updated: ${new Date().toLocaleTimeString()}`;
        
        this.updateDynamicBackground();
        this.animateWeatherIcon();
    } catch (error) {
        console.error(error);
        this.showToast('Failed to refresh. Please try again.', 'error');
    } finally {
        this.state.isRefreshing = false;
        this.elements.refreshBtn?.classList.remove('spinning');
    }
},

  // 🎨 Dynamic Background + Animated Icons
  updateDynamicBackground() {
    const status =
      document.getElementById('weather-status')?.textContent?.toLowerCase() ||
      'clear';
    const hour = new Date().getHours();
    const isNight = hour >= 20 || hour < 6;
    const bg = this.elements.dynamicBg;

    if (!bg) return;

    // Smooth transition
    bg.style.transition = 'background 1s ease';

    // Reset classes
    bg.className = 'dynamic-bg';

    // Apply weather-based class
    if (isNight) {
      bg.classList.add('night');
    } else if (status.includes('rain') || status.includes('drizzle')) {
      bg.classList.add('rainy');
    } else if (status.includes('cloud') || status.includes('overcast')) {
      bg.classList.add('cloudy');
    } else if (status.includes('thunder') || status.includes('storm')) {
      bg.classList.add('stormy');
      this.animateStormEffect();
    } else {
      bg.classList.add('sunny');
      this.animateSunEffect();
    }
  },

  animateWeatherIcon() {
    const icon = this.elements.weatherIcon;
    if (!icon) return;

    // Remove existing animation
    icon.style.animation = 'none';
    icon.offsetHeight; // Trigger reflow

    // Apply weather-specific animation
    const status =
      document.getElementById('weather-status')?.textContent?.toLowerCase() || '';

    if (status.includes('rain')) {
      icon.style.animation = 'rain-drop 1.5s ease-in-out infinite';
    } else if (status.includes('cloud')) {
      icon.style.animation = 'float 3s ease-in-out infinite';
    } else if (status.includes('storm')) {
      icon.style.animation = 'flash 0.8s ease-in-out infinite';
    } else if (status.includes('snow')) {
      icon.style.animation = 'snow-fall 2s linear infinite';
    } else {
      icon.style.animation = 'pulse-glow 2s ease-in-out infinite';
    }
  },

  animateStormEffect() {
    // Add lightning flash effect to body
    document.body.classList.add('storm-flash');
    setTimeout(() => document.body.classList.remove('storm-flash'), 200);
  },

  animateSunEffect() {
    // Add subtle sun rays animation
    const icon = this.elements.weatherIcon;
    if (icon && icon.textContent === '☀️') {
      icon.style.textShadow = '0 0 20px rgba(251, 191, 36, 0.6)';
    }
  },

  // 📱 Horizontal Scroll with Snap + Keyboard Support
  initHourlyScroll() {
    const container = this.elements.hourlyScroll;
    if (!container) return;

    // Mouse wheel horizontal scroll
    container.addEventListener(
      'wheel',
      (e) => {
        if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
          e.preventDefault();
          container.scrollLeft += e.deltaY;
        }
      },
      { passive: false }
    );

    // Keyboard arrow navigation when focused
    container.setAttribute('tabindex', '0');
    container.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight') {
        container.scrollBy({ left: 200, behavior: 'smooth' });
      } else if (e.key === 'ArrowLeft') {
        container.scrollBy({ left: -200, behavior: 'smooth' });
      }
    });

    // Snap scrolling for mobile
    if (window.innerWidth < 768) {
      container.style.scrollSnapType = 'x mandatory';
      container.querySelectorAll('.hourly-card').forEach((card) => {
        card.style.scrollSnapAlign = 'start';
      });
    }
  },

  // 👆 Touch Swipe Gestures for Mobile
  initSwipeGestures() {
    let touchStartX = 0;
    let touchEndX = 0;

    document.addEventListener(
      'touchstart',
      (e) => {
        touchStartX = e.changedTouches[0].screenX;
      },
      { passive: true }
    );

    document.addEventListener(
      'touchend',
      (e) => {
        touchEndX = e.changedTouches[0].screenX;
        this.handleSwipe(touchStartX, touchEndX);
      },
      { passive: true }
    );
  },

  handleSwipe(startX, endX) {
    const diff = startX - endX;
    const threshold = 50;

    if (Math.abs(diff) > threshold) {
      const hourly = this.elements.hourlyScroll;
      if (hourly) {
        hourly.scrollBy({
          left: diff > 0 ? 200 : -200,
          behavior: 'smooth',
        });
      }
    }
  },

  // 🗺️ Map Loading States
  showMapLoading(isLoading) {
    const placeholder = document.querySelector('.map-placeholder');
    if (!placeholder) return;

    if (isLoading) {
      placeholder.innerHTML = `
        <div class="map-loading">
          <div class="spinner"></div>
          <p>Loading map layer...</p>
        </div>
      `;
    } else {
      placeholder.innerHTML = `<p>Map layer ready</p>`;
      setTimeout(() => {
        placeholder.innerHTML = `<p>Interactive weather map</p>`;
      }, 1500);
    }
  },

  // 🔔 Toast Notification System
  showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'polite');

    const icons = {
      success: '✅',
      error: '❌',
      info: 'ℹ️',
      warning: '⚠️',
    };

    toast.innerHTML = `
      <span class="toast-icon">${icons[type] || icons.info}</span>
      <span class="toast-message">${message}</span>
      <button class="toast-close" aria-label="Close notification">&times;</button>
    `;

    this.elements.toastContainer.appendChild(toast);

    // Auto-dismiss
    const dismissTimer = setTimeout(() => {
      toast.classList.add('toast-exit');
      setTimeout(() => toast.remove(), 300);
    }, this.config.toastDuration);

    // Manual close
    toast.querySelector('.toast-close').addEventListener('click', () => {
      clearTimeout(dismissTimer);
      toast.classList.add('toast-exit');
      setTimeout(() => toast.remove(), 300);
    });

    // Entrance animation
    requestAnimationFrame(() => {
      toast.classList.add('toast-enter');
    });
  },

  createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-atomic', 'true');
    document.body.appendChild(container);
    return container;
  },

  // ♿ Accessibility Enhancements
  setupAccessibility() {
    // Skip link for keyboard users
    if (!document.getElementById('skip-link')) {
      const skipLink = document.createElement('a');
      skipLink.href = '#main-content';
      skipLink.id = 'skip-link';
      skipLink.textContent = 'Skip to content';
      skipLink.className = 'skip-link';
      document.body.insertBefore(skipLink, document.body.firstChild);
    }

    // Focus visible polyfill behavior
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Tab') {
        document.body.classList.add('keyboard-nav');
      }
    });

    document.addEventListener('mousedown', () => {
      document.body.classList.remove('keyboard-nav');
    });

    // ARIA live region for dynamic updates
    const liveRegion = document.createElement('div');
    liveRegion.setAttribute('aria-live', 'polite');
    liveRegion.setAttribute('aria-atomic', 'true');
    liveRegion.className = 'sr-only';
    liveRegion.id = 'weather-live-region';
    document.body.appendChild(liveRegion);
  },

  // 🎭 Respect User Preferences
  respectReducedMotion() {
    const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

    const applyReducedMotion = (matches) => {
      if (matches) {
        document.documentElement.style.setProperty('--transition', 'none');
        document.querySelectorAll('*').forEach((el) => {
          el.style.animation = 'none';
          el.style.transition = 'none';
        });
      }
    };

    applyReducedMotion(mediaQuery.matches);
    mediaQuery.addEventListener('change', (e) => applyReducedMotion(e.matches));
  },

  // 🎨 CSS Helper: Add animation keyframes dynamically if needed
  injectAnimations() {
    const style = document.createElement('style');
    style.textContent = `
      @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
      @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
      @keyframes rain-drop { 0% { transform: translateY(-10px); opacity: 0; } 50% { opacity: 1; } 100% { transform: translateY(10px); opacity: 0; } }
      @keyframes flash { 0%, 100% { opacity: 1; text-shadow: 0 0 10px rgba(255,255,255,0.8); } 50% { opacity: 0.3; text-shadow: 0 0 30px rgba(255,255,255,1); } }
      @keyframes snow-fall { 0% { transform: translateY(-20px) rotate(0deg); opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } 100% { transform: translateY(20px) rotate(360deg); opacity: 0; } }
      @keyframes pulse-glow { 0%, 100% { text-shadow: 0 0 10px rgba(251,191,36,0.4); } 50% { text-shadow: 0 0 25px rgba(251,191,36,0.9); } }
      
      .pulse { animation: pulse 0.3s ease; }
      .theme-transition { transition: background-color 0.3s ease, color 0.3s ease !important; }
      .storm-flash { animation: flash 0.2s ease; }
      
      .toast {
        position: fixed; bottom: 1rem; right: 1rem;
        background: var(--glass-bg); backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border); border-radius: 12px;
        padding: 0.75rem 1rem; display: flex; align-items: center; gap: 0.5rem;
        box-shadow: var(--glass-shadow); z-index: 1000;
        transform: translateX(150%); transition: transform 0.3s ease;
        max-width: 300px;
      }
      .toast-enter { transform: translateX(0); }
      .toast-exit { transform: translateX(150%); opacity: 0; }
      .toast-success { border-left: 4px solid #22c55e; }
      .toast-error { border-left: 4px solid #ef4444; }
      .toast-info { border-left: 4px solid #3b82f6; }
      .toast-warning { border-left: 4px solid #eab308; }
      .toast-close {
        background: none; border: none; color: inherit;
        font-size: 1.2rem; cursor: pointer; margin-left: auto;
        opacity: 0.7; transition: opacity 0.2s;
      }
      .toast-close:hover { opacity: 1; }
      
      .skip-link {
        position: absolute; top: -40px; left: 0;
        background: var(--accent); color: white;
        padding: 0.5rem 1rem; z-index: 1001;
        transition: top 0.2s;
      }
      .skip-link:focus { top: 0; }
      
      .sr-only {
        position: absolute; width: 1px; height: 1px;
        padding: 0; margin: -1px; overflow: hidden;
        clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
      }
      
      .keyboard-nav :focus {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
      }
      
      .map-loading {
        display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
      }
      .spinner {
        width: 24px; height: 24px;
        border: 3px solid rgba(0,0,0,0.1);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 1s linear infinite;
      }
      @keyframes spin { to { transform: rotate(360deg); } }
      
      .loading {
        position: relative; pointer-events: none; opacity: 0.8;
      }
      .loading::after {
        content: ''; position: absolute;
        width: 16px; height: 16px;
        border: 2px solid currentColor;
        border-top-color: transparent;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        right: 0.5rem; top: 50%; transform: translateY(-50%);
      }
      .spinning { animation: spin 1s linear infinite; }
    `;
    document.head.appendChild(style);
  },
};