/**
 * Weather Broadcast - Interactive JavaScript Module
 * BBC-inspired logic for carousel interaction, theme/unit toggles, and data updates.
 */

document.addEventListener('DOMContentLoaded', () => {
  WeatherApp.init();
});

const WeatherApp = {
  // 📦 Config
  config: {
    refreshInterval: 600000, // 10 mins
    debounceDelay: 300,
    toastDuration: 4000,
    units: localStorage.getItem('weather-units') || 'metric', // 'metric' = °C, 'imperial' = °F
  },

  // 🎯 State
  state: {
    hourlyData: {}, // Grouped by date
    activeDate: null,
    isRefreshing: false,
  },

  // 🚀 Initialization
  init() {
    this.cacheElements();
    this.loadState();
    this.bindEvents();
    this.initTheme();
    this.initUnitToggle();
    this.initCarousel();
    this.initMap();
    this.setupAccessibility();
    this.injectAnimations();
    this.initDynamicWeather();

    console.log('⚡ Weather Broadcast UI initialized');
  },

  // 🔍 Cache DOM elements
  cacheElements() {
    this.elements = {
      themeToggle: document.getElementById('theme-toggle'),
      unitToggle: document.getElementById('unit-toggle'),
      citySearch: document.getElementById('city-search'),
      autocompleteList: document.getElementById('autocomplete-list'),
      searchForm: document.querySelector('.header-search-form'),
      dayCarousel: document.getElementById('day-carousel'),
      hourlyTable: document.getElementById('hourly-table'),
      hourlyDataScript: document.getElementById('hourly-data'),
      currentTemp: document.getElementById('current-temp'),
      feelsLike: document.getElementById('feels-like'),
      cityHeroName: document.querySelector('.hero-city'),
      toastContainer: document.getElementById('toast-container') || this.createToastContainer(),
    };
  },

  loadState() {
    if (this.elements.hourlyDataScript) {
      try {
        this.state.hourlyData = JSON.parse(this.elements.hourlyDataScript.textContent);
        this.state.activeDate = Object.keys(this.state.hourlyData)[0];
      } catch (e) {
        console.error('Failed to parse hourly data', e);
      }
    }
  },

  // 🎛️ Bind events
  bindEvents() {
    this.elements.themeToggle?.addEventListener('click', () => this.toggleTheme());
    this.elements.unitToggle?.addEventListener('click', () => this.toggleUnits());

    // Search
    if (this.elements.citySearch) {
      this.elements.citySearch.addEventListener('input', (e) => {
        clearTimeout(this._searchTimer);
        this._searchTimer = setTimeout(() => this.handleSearchInput(e.target.value.trim()), this.config.debounceDelay);
      });
    }

    // Carousel click (delegated)
    this.elements.dayCarousel?.addEventListener('click', (e) => {
      const tile = e.target.closest('.day-tile');
      if (tile) this.handleDayClick(tile);
    });

    // Close autocomplete
    document.addEventListener('click', (e) => {
      if (!this.elements.citySearch?.contains(e.target) && !this.elements.autocompleteList?.contains(e.target)) {
        this.hideAutocomplete();
      }
    });
  },

  // 🌓 Theme
  toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('weather-theme', next);
    this.showToast(`Switched to ${next} mode`, 'info');
  },

  initTheme() {
    const stored = localStorage.getItem('weather-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', stored);
  },

  // 🌡️ Units
  toggleUnits() {
    this.config.units = this.config.units === 'metric' ? 'imperial' : 'metric';
    localStorage.setItem('weather-units', this.config.units);
    this.initUnitToggle();
    this.convertAllTemperatures();
    this.showToast(`Units changed to ${this.config.units === 'metric' ? 'Celsius' : 'Fahrenheit'}`, 'success');
  },

  initUnitToggle() {
    if (this.elements.unitToggle) {
      this.elements.unitToggle.textContent = this.config.units === 'metric' ? '°F' : '°C';
    }
  },

  convertAllTemperatures() {
    const isMetric = this.config.units === 'metric';
    const unit = isMetric ? '°C' : '°F';
    const convert = (c) => isMetric ? parseFloat(c) : Math.round((parseFloat(c) * 9) / 5 + 32);

    // Current Temp
    if (this.elements.currentTemp && this.elements.currentTemp.dataset.celsius) {
      const val = convert(this.elements.currentTemp.dataset.celsius);
      this.elements.currentTemp.innerHTML = `${val}<span class="hero-temp-unit">${unit}</span>`;
    }

    // Feels Like
    if (this.elements.feelsLike && this.elements.feelsLike.dataset.celsius) {
      this.elements.feelsLike.textContent = convert(this.elements.feelsLike.dataset.celsius) + '°';
    }

    // Today High/Low
    document.querySelectorAll('#today-high, #today-low').forEach(el => {
      if (el.dataset.celsius) el.textContent = convert(el.dataset.celsius) + '°';
    });

    // Carousel High/Low
    document.querySelectorAll('.day-tile__high, .day-tile__low').forEach(el => {
      if (el.dataset.celsius) el.textContent = convert(el.dataset.celsius) + '°';
    });

    // Hourly Table
    document.querySelectorAll('.hour-col__temp').forEach(el => {
      if (el.dataset.celsius) el.textContent = convert(el.dataset.celsius) + '°';
    });

    // Daily Items (if still present)
    document.querySelectorAll('.daily-item__high, .daily-item__low').forEach(el => {
      if (el.dataset.celsius) el.textContent = convert(el.dataset.celsius) + '°';
    });

    // City Cards
    document.querySelectorAll('.city-card__temp').forEach(el => {
      // City cards might not have dataset-celsius yet, let's fix that in blade or handle here
      const text = el.textContent;
      if (text.includes('°')) {
        // This is tricky without data attributes. I'll rely on data attributes mostly.
      }
    });
  },

  // 🗓️ Carousel & Hourly Update
  initCarousel() {
    // Initial active state set by Blade
  },

  handleDayClick(tile) {
    const date = tile.dataset.date;
    if (!date || !this.state.hourlyData[date]) return;

    // Update UI active state
    this.elements.dayCarousel.querySelectorAll('.day-tile').forEach(t => t.classList.remove('active'));
    tile.classList.add('active');

    // Update Hourly Table
    this.updateHourlyTable(date);
    this.state.activeDate = date;
  },

  updateHourlyTable(date) {
    const data = this.state.hourlyData[date];
    if (!data || !this.elements.hourlyTable) return;

    const isToday = date === new Date().toISOString().split('T')[0];
    const isMetric = this.config.units === 'metric';
    const unit = isMetric ? '°C' : '°F';
    const convert = (c) => isMetric ? parseFloat(c) : Math.round((parseFloat(c) * 9) / 5 + 32);

    let html = '';
    data.forEach((hour, i) => {
      const isNow = isToday && i === 0;
      html += `
        <div class="hour-col ${isNow ? 'hour-col--now' : ''}">
          <span class="hour-col__time">${isNow ? 'Now' : hour.time}</span>
          <span class="hour-col__icon">${hour.icon}</span>
          <span class="hour-col__temp" data-celsius="${hour.temp}">${convert(hour.temp)}°</span>
          <div class="hour-col__precip">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
            ${hour.precip}%
          </div>
        </div>
      `;
    });

    this.elements.hourlyTable.innerHTML = html || '<div class="hour-col">No data</div>';

    // Smooth scroll back to start
    this.elements.hourlyTable.parentElement.scrollTo({ left: 0, behavior: 'smooth' });
  },

  // 🔍 Search
  async handleSearchInput(query) {
    if (!query || query.length < 2) {
      this.hideAutocomplete();
      return;
    }

    try {
      const res = await fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(query)}&count=5`);
      const data = await res.json();
      this.renderAutocomplete(data.results || []);
    } catch (error) {
      console.error('Search error:', error);
    }
  },

  renderAutocomplete(results) {
    const list = this.elements.autocompleteList;
    if (!list) return;

    list.innerHTML = results.map(city => `
      <div class="autocomplete-item" data-name="${city.name}">
        <strong>${city.name}</strong>, ${city.country_code || city.country || ''}
      </div>
    `).join('');

    list.querySelectorAll('.autocomplete-item').forEach(item => {
      item.addEventListener('click', () => {
        this.elements.citySearch.value = item.dataset.name;
        this.hideAutocomplete();
        this.elements.searchForm.submit();
      });
    });

    list.classList.add('show');
  },

  hideAutocomplete() {
    this.elements.autocompleteList?.classList.remove('show');
  },

  // 🗺️ Map
  initMap() {
    const mapContainer = document.querySelector('.map-container');
    if (!mapContainer || typeof L === 'undefined') return;

    const lat = parseFloat(mapContainer.dataset.lat) || 51.5074;
    const lon = parseFloat(mapContainer.dataset.lon) || -0.1278;

    const map = L.map('weather-map').setView([lat, lon], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    L.marker([lat, lon]).addTo(map).bindPopup('<b>Location</b>').openPopup();
  },

  // 🔔 Toasts
  showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<span>${message}</span>`;
    this.elements.toastContainer.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('toast-enter'));

    setTimeout(() => {
      toast.classList.add('toast-exit');
      setTimeout(() => toast.remove(), 300);
    }, this.config.toastDuration);
  },

  createToastContainer() {
    const c = document.createElement('div');
    c.id = 'toast-container';
    document.body.appendChild(c);
    return c;
  },

  injectAnimations() {
    const s = document.createElement('style');
    s.textContent = `
      #toast-container { position: fixed; bottom: 2rem; right: 2rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
      .toast { background: var(--col-header); color: #fff; padding: 0.75rem 1.25rem; border-radius: 8px; box-shadow: var(--shadow-lg); border-left: 4px solid var(--col-accent); transform: translateX(120%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-size: 0.9rem; font-weight: 500; }
      .toast-enter { transform: translateX(0); }
      .toast-exit { transform: translateX(120%); opacity: 0; }
      .toast-success { border-left-color: var(--col-good); }
      .toast-error { border-left-color: var(--col-poor); }
    `;
    document.head.appendChild(s);
  },

  setupAccessibility() {
    // Add skip link if needed, etc.
  },

  // 🌩️ Dynamic Weather Adapation
  initDynamicWeather() {
    const statusEl = document.getElementById('weather-status');
    if (!statusEl) return;

    const statusText = statusEl.textContent.trim().toLowerCase();
    const body = document.body;
    
    // Map condition to theme
    let themeClass = 'theme-cloudy'; // fallback
    if (statusText.includes('clear') || statusText.includes('sun')) {
      themeClass = 'theme-sunny';
    } else if (statusText.includes('rain') || statusText.includes('drizzle')) {
      themeClass = 'theme-rain';
    } else if (statusText.includes('snow')) {
      themeClass = 'theme-snow';
    } else if (statusText.includes('thunderstorm') || statusText.includes('storm')) {
      themeClass = 'theme-storm';
    }

    body.classList.add('dynamic-weather', themeClass);

    // Initialize particles for specific themes
    if (themeClass === 'theme-rain') {
      this.generateParticles('rain-drop', 60);
    } else if (themeClass === 'theme-snow') {
      this.generateParticles('snow-flake', 40);
    }
  },

  generateParticles(className, count) {
    const container = document.createElement('div');
    container.className = 'weather-particles-container';
    document.body.prepend(container);

    for (let i = 0; i < count; i++) {
      const p = document.createElement('div');
      p.className = className;
      p.style.left = Math.random() * 100 + 'vw';
      
      if (className === 'rain-drop') {
         p.style.animationDuration = (Math.random() * 0.5 + 0.4) + 's';
         p.style.animationDelay = (Math.random() * 2) + 's';
      } else if (className === 'snow-flake') {
         p.style.animationDuration = (Math.random() * 4 + 4) + 's';
         p.style.animationDelay = (Math.random() * 5) + 's';
         p.style.width = p.style.height = (Math.random() * 4 + 4) + 'px';
         p.style.opacity = Math.random() * 0.6 + 0.2;
      }
      
      container.appendChild(p);
    }
  }
};