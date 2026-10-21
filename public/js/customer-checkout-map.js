(() => {
    const container = document.getElementById('checkoutDeliveryMap');
    if (!container) return;

    const address = document.getElementById('address');
    const status = document.getElementById('checkoutMapStatus');
    const pinnedLocation = document.getElementById('checkoutPinnedLocation');

    let map = null;
    let leafletLoading = null;
    let marker = null;
    let lookupController = null;
    let lookupVersion = 0;

    const setStatus = message => {
        status.textContent = message;
    };

    function formatAddress(properties = {}) {
        const street = [properties.housenumber, properties.street]
            .filter(Boolean)
            .join(' ');
        const parts = [
            properties.name === properties.street ? '' : properties.name,
            street,
            properties.locality,
            properties.district,
            properties.city,
            properties.county,
            properties.state,
            properties.postcode,
            properties.country
        ];

        return [...new Set(parts.filter(value =>
            typeof value === 'string' && value.trim()
        ))].join(', ');
    }

    async function describeLocation(point, version) {
        const controller = new AbortController();
        lookupController = controller;

        try {
            const url = new URL(container.dataset.reverseUrl);
            url.search = new URLSearchParams({
                lat: point.lat,
                lon: point.lng,
                lang: 'en',
                limit: 1
            });

            const response = await fetch(url, {
                signal: controller.signal,
                headers: { Accept: 'application/json' }
            });

            if (!response.ok) throw new Error('Address lookup failed.');

            const result = await response.json();
            const location = formatAddress(result.features?.[0]?.properties || {});

            if (version !== lookupVersion) return;
            if (!location) throw new Error('No readable address found for this pin.');

            address.value = location;
            pinnedLocation.textContent = location;
            setStatus('Delivery address added from the map pin.');
        } catch (error) {
            if (version !== lookupVersion || error.name === 'AbortError') return;

            pinnedLocation.textContent = 'Address not found for this pin.';
            setStatus('Could not identify this location. Try pinning a nearby point on the map.');
        } finally {
            if (version === lookupVersion) {
                address.setAttribute('aria-busy', 'false');
            }
        }
    }

    function pinLocation(point) {
        if (!map) return;

        lookupController?.abort();
        const version = ++lookupVersion;

        if (marker) {
            marker.setLatLng(point);
        } else {
            marker = L.marker(point, {
                draggable: true,
                title: 'Delivery location',
                alt: 'Delivery location pin'
            }).addTo(map);
            marker.on('dragend', () => pinLocation(marker.getLatLng()));
        }

        address.value = '';
        address.setAttribute('aria-busy', 'true');
        pinnedLocation.textContent = 'Looking up this location…';
        setStatus('Finding the location name…');
        describeLocation(point, version);
    }

    function loadLeafletFallback() {
        if (window.L) return Promise.resolve();
        if (leafletLoading) return leafletLoading;

        leafletLoading = Promise.all([
            new Promise((resolve, reject) => {
                const stylesheet = document.createElement('link');
                stylesheet.rel = 'stylesheet';
                stylesheet.href = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css';
                stylesheet.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
                stylesheet.crossOrigin = '';
                stylesheet.onload = resolve;
                stylesheet.onerror = reject;
                document.head.appendChild(stylesheet);
            }),
            new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js';
                script.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
                script.crossOrigin = '';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            })
        ]);

        return leafletLoading;
    }

    async function initializeMap() {
        if (!map) {
            if (!window.L) {
                setStatus('Loading map…');
                try {
                    await loadLeafletFallback();
                } catch {
                    setStatus('The map could not load. Check your connection and try again.');
                    return;
                }
            }

            if (!container.getClientRects().length) return;

            if (!map) {
                map = L.map(container, { scrollWheelZoom: true })
                    .setView([12.8797, 121.774], 6);

                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
                }).addTo(map);

                map.on('click', event => pinLocation(event.latlng));
            }
        }

        requestAnimationFrame(() => map.invalidateSize());
    }

    window.initCheckoutDeliveryMap = initializeMap;
})();