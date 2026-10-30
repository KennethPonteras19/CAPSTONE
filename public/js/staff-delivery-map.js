
/* TMTA POS Staff Delivery Map
 * Leaflet + Photon search + reverse geocoding.
 * No GeoJSON dependency.
 */
window.createStaffDeliveryMap = function ({ onChange, onBusyChange, onZoneChange }) {
    const byId = id => document.getElementById(id);

    const container = byId('deliveryMap');
    const address = byId('deliveryAddress');
    const status = byId('deliveryMapStatus');
    const overlay = byId('deliveryMapOverlay');
    const searchInput = byId('deliveryLocationSearch');
    const searchButton = byId('searchDeliveryLocation');
    const searchResults = byId('deliverySearchResults');
    const zoneName = byId('deliveryZoneName');
    const zoneFee = byId('deliveryZoneFee');
    const locateButton = byId('locateDelivery');
    const centerButton = byId('pinDeliveryCenter');
    const dialog = byId('deliveryMapDialog');
    const panel = byId('deliveryMapPanel');
    const expandButton = byId('expandDeliveryMap');
    const mapTools = byId('deliveryMapTools');
    let toolsWereOpen = false;

    if (!container || !address || !status || !overlay) {
        console.error('Delivery map HTML elements are missing.');
        return {
            setEnabled() {},
            reset() {},
            getSelectedZone() { return null; },
            getPinnedCoordinates() { return null; }
        };
    }

    const searchUrl = 'https://photon.komoot.io/api/';

    let map = null;
    let marker = null;
    let enabled = false;
    let busy = false;
    let generation = 0;
    let timer = null;
    let reverseRequest = null;
    let zoneRequest = null;
    let searchRequest = null;
    let pinnedPoint = null;
    let selectedZone = null;

    const addressCache = new Map();

    function message(text, error = false) {
        status.textContent = text;
        status.dataset.error = String(error);
    }

    function setBusy(value) {
        busy = value;

        address.setAttribute('aria-busy', String(value));

        if (locateButton) {
            locateButton.disabled = !enabled || !map || value;
        }

        if (centerButton) {
            centerButton.disabled = !enabled || !map;
        }

        if (searchButton) {
            searchButton.disabled = !enabled || !map || value;
        }

        if (searchInput) {
            searchInput.disabled = !enabled || !map;
        }

        if (typeof onBusyChange === 'function') {
            onBusyChange(value);
        }
    }

    function cancelLookup() {
        generation++;
        clearTimeout(timer);
        reverseRequest?.abort();
        zoneRequest?.abort();
        setBusy(false);
    }

    function formatAddress(properties = {}) {
        const street = [
            properties.housenumber,
            properties.street
        ].filter(Boolean).join(' ');

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

        return [...new Set(
            parts.filter(value =>
                typeof value === 'string' && value.trim()
            )
        )].join(', ');
    }

    function showZone(zone) {
        selectedZone = zone || null;

        if (zoneName) {
            zoneName.textContent = zone
                ? zone.zone_name
                : 'Not verified';
        }

        if (zoneFee) {
            zoneFee.textContent = zone
                ? Number(zone.fee).toLocaleString('en-PH', {
                    style: 'currency',
                    currency: 'PHP',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })
                : 'Not calculated';
        }

        onZoneChange?.(selectedZone);
    }

    async function lookupDeliveryZone(fullAddress, version) {
        const token = document.querySelector(
            'meta[name="csrf-token"]'
        )?.content;

        const controller = new AbortController();
        zoneRequest = controller;
        const timeout = setTimeout(() => controller.abort(), 12000);
        try {
            const response = await fetch(container.dataset.zoneUrl, {
                method: 'POST',
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {})
                },
                body: JSON.stringify({ address: fullAddress })
            });

            const result = await response.json();

            if (version !== generation || !enabled) return;

            if (!response.ok || !result.success || !result.zone) {
                throw new Error(
                    result.message ||
                    'Could not identify the delivery zone. Check the address.'
                );
            }

            showZone(result.zone);
            message(`Delivery zone detected: ${result.zone.zone_name}.`);
        } finally {
            clearTimeout(timeout);
        }
    }

    function checkEnteredAddress() {
        cancelLookup();
        showZone(null);
        const fullAddress = address.value.trim();
        if (!enabled || !fullAddress) return;

        const version = generation;
        message('Checking delivery zone…');
        setBusy(true);
        timer = setTimeout(async () => {
            try {
                await lookupDeliveryZone(fullAddress, version);
            } catch (error) {
                if (version === generation && enabled) {
                    message(error.name === 'AbortError' ? 'Delivery zone lookup timed out. Your address is still saved here; edit it to retry.' : (error.message || 'Could not identify the delivery zone.'), true);
                }
            } finally {
                if (version === generation) setBusy(false);
            }
        }, 400);
    }

    async function lookupAddress(latlng, version) {
        if (version !== generation || !enabled) return;

        const controller = new AbortController();
        reverseRequest = controller;

        const timeout = setTimeout(() => controller.abort(), 12000);

        try {
            const key =
                `${latlng.lat.toFixed(6)},${latlng.lng.toFixed(6)}`;

            let fullAddress = addressCache.get(key);

            if (!fullAddress) {
                const url = new URL(container.dataset.reverseUrl);

                url.search = new URLSearchParams({
                    lat: latlng.lat,
                    lon: latlng.lng,
                    lang: 'en',
                    limit: 1
                });

                const response = await fetch(url, {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' }
                });

                if (!response.ok) {
                    throw new Error('Address lookup failed.');
                }

                const result = await response.json();
                const properties = result.features?.[0]?.properties;

                if (!properties) {
                    throw new Error('No address found for this pin.');
                }

                fullAddress = formatAddress(properties);

                if (!fullAddress) {
                    throw new Error('No readable address found.');
                }

                addressCache.set(key, fullAddress);
            }

            if (version !== generation || !enabled) return;

            address.value = fullAddress;

            if (typeof onChange === 'function') {
                onChange();
            }

            message('Address found. Checking delivery zone…');

            try {
                await lookupDeliveryZone(fullAddress, version);
            } catch (error) {
                if (version !== generation || !enabled) return;

                showZone(null);
                message(error.message, true);
            }
        } catch (error) {
            if (version !== generation || !enabled) return;

            showZone(null);

            message(
                error.name === 'AbortError'
                    ? 'Address lookup timed out. Try moving the pin.'
                    : 'Could not find an address. Move the pin or enter the address manually.',
                true
            );
        } finally {
            clearTimeout(timeout);

            if (version === generation) {
                setBusy(false);
            }
        }
    }

    function pinLocation(latlng, selectedAddress = null) {
        if (!enabled || !map) return;

        cancelLookup();

        const point = latlng.wrap();
        pinnedPoint = point;

        if (marker) {
            marker.setLatLng(point);
        } else {
            marker = L.marker(point, {
                draggable: true,
                title: 'Delivery location',
                alt: 'Delivery location pin'
            }).addTo(map);

            marker.on('dragend', () => {
                pinLocation(marker.getLatLng());
            });
        }

        // Keep the current address until an explicit selection resolves successfully.
        showZone(null);

        if (typeof onChange === 'function') {
            onChange();
        }

        message('Finding address and delivery zone…');
        setBusy(true);

        const version = generation;

        if (selectedAddress) {
            address.value = selectedAddress;
            checkEnteredAddress();
            return;
        }

        timer = setTimeout(() => {
            lookupAddress(point, version);
        }, 400);
    }

    async function searchLocations() {
        if (!enabled || !map || !searchInput || busy) return;

        const query = searchInput.value.trim();

        if (query.length < 3) {
            message('Enter at least 3 characters to search.', true);
            return;
        }

        searchRequest?.abort();

        const controller = new AbortController();
        searchRequest = controller;
        const timeout = setTimeout(() => controller.abort(), 12000);

        if (searchButton) searchButton.disabled = true;

        if (searchResults) {
            searchResults.replaceChildren();
            searchResults.hidden = true;
        }

        message('Searching for location…');

        try {
            const url = new URL(searchUrl);

            url.search = new URLSearchParams({
                q: query,
                limit: 6,
                lang: 'en'
            });

            const response = await fetch(url, {
                signal: controller.signal,
                headers: { Accept: 'application/json' }
            });

            if (!response.ok) {
                throw new Error('Location search failed.');
            }

            const result = await response.json();
            if (searchRequest !== controller || controller.signal.aborted || !enabled) return;
            const features = (result.features || []).filter(feature =>
                Array.isArray(feature.geometry?.coordinates) &&
                feature.geometry.coordinates.length >= 2
            );

            if (!features.length) {
                message('No matching locations found. Try another search.', true);
                return;
            }

            features.forEach(feature => {
                const coordinates = feature.geometry.coordinates;
                const point = L.latLng(coordinates[1], coordinates[0]);

                const label =
                    formatAddress(feature.properties || {}) ||
                    feature.properties?.name ||
                    query;

                const button = document.createElement('button');

                button.type = 'button';
                button.className = 'secondary';
                button.textContent = label;

                button.addEventListener('click', () => {
                    if (!enabled) return;

                    searchResults.hidden = true;
                    map.setView(point, 16);
                    pinLocation(point, label);
                });

                searchResults.appendChild(button);
            });

            searchResults.hidden = false;
            message('Choose a search result to place the pin.');
        } catch (error) {
            if (searchRequest === controller && enabled) {
                message(error.name === 'AbortError' ? 'Search stopped. Search again or type the delivery address manually.' : 'Location search failed. Please try again or enter the address manually.', true);
            }
        } finally {
            clearTimeout(timeout);
            if (searchRequest === controller && searchButton) {
                searchButton.disabled = !enabled || !map || busy;
            }
        }
    }

    try {
        if (!window.L) {
            throw new Error('Leaflet did not load.');
        }

        map = L.map(container, {
            scrollWheelZoom: false
        }).setView([12.8797, 121.774], 6);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
        }).addTo(map);

        map.on('click', event => pinLocation(event.latlng));

        if (window.ResizeObserver) {
            new ResizeObserver(() => map.invalidateSize())
                .observe(container);
        }

        setTimeout(() => map?.invalidateSize(), 150);
    } catch (error) {
        console.error(error);
        message(
            'The map could not load. Check that Leaflet is included on this page.',
            true
        );
    }

    centerButton?.addEventListener('click', () => {
        if (enabled && map) {
            pinLocation(map.getCenter());
        }
    });

    locateButton?.addEventListener('click', () => {
        if (!enabled || !map) return;

        if (!navigator.geolocation) {
            message('Location is unavailable. Search or click the map instead.', true);
            return;
        }

        cancelLookup();
        const version = generation;

        message('Finding your current location…');
        setBusy(true);

        navigator.geolocation.getCurrentPosition(
            position => {
                if (version !== generation || !enabled) return;

                const point = L.latLng(
                    position.coords.latitude,
                    position.coords.longitude
                );

                map.setView(point, 17);
                pinLocation(point);
            },
            () => {
                if (version !== generation || !enabled) return;

                message(
                    'Could not access your location. Allow browser location access or search for an address.',
                    true
                );

                setBusy(false);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000
            }
        );
    });

    searchButton?.addEventListener('click', searchLocations);

    // Move the same controls and Leaflet instance; opening/closing never changes data.
    expandButton?.addEventListener('click', () => {
        if (!dialog || !panel || !map) return;
        toolsWereOpen = mapTools.open;
        mapTools.open = true;
        byId('expandedMapContent').append(panel);
        expandButton.hidden = true;
        dialog.showModal();
        requestAnimationFrame(() => map.invalidateSize({pan: false}));
        searchInput?.focus();
    });
    function restoreMap() {
        if (!panel || panel.parentElement === byId('deliveryMapHome')) return;
        byId('deliveryMapHome').append(panel);
        mapTools.open = toolsWereOpen;
        expandButton.hidden = false;
        requestAnimationFrame(() => map?.invalidateSize({pan: false}));
        expandButton.focus();
    }
    function closeMap() {
        dialog.close();
        restoreMap();
    }
    byId('closeDeliveryMap')?.addEventListener('click', closeMap);
    dialog?.addEventListener('close', restoreMap);
    dialog?.addEventListener('cancel', event => { event.preventDefault(); closeMap(); });
    mapTools?.addEventListener('toggle', () => requestAnimationFrame(() => map?.invalidateSize({pan: false})));
    dialog?.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) closeMap();
    });
    searchInput?.addEventListener('input', () => {
        searchRequest?.abort();
        searchResults.replaceChildren();
        searchResults.hidden = true;
    });

    searchInput?.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchLocations();
        }
    });

    address.addEventListener('input', () => {
        checkEnteredAddress();

        if (typeof onChange === 'function') {
            onChange();
        }
    });

    return {
        setEnabled(value) {
            const wasEnabled = enabled;
            enabled = Boolean(value);
            if (expandButton) expandButton.disabled = !enabled || !map;

            if (!enabled) {
                cancelLookup();
                searchRequest?.abort();

                if (searchResults) {
                    searchResults.hidden = true;
                }
            }

            container.inert = !enabled || !map;
            container.style.pointerEvents = enabled && map ? 'auto' : 'none';

            overlay.hidden = enabled && Boolean(map);
            overlay.textContent = !map
                ? 'Map unavailable. Enter the address below.'
                : 'Select Delivery to pin a location.';

            if (locateButton) {
                locateButton.disabled = !enabled || !map || busy;
            }

            if (centerButton) {
                centerButton.disabled = !enabled || !map;
            }

            if (searchButton) {
                searchButton.disabled = !enabled || !map || busy;
            }

            if (searchInput) {
                searchInput.disabled = !enabled || !map;
            }

            if (marker) {
                if (enabled) {
                    marker.dragging.enable();
                } else {
                    marker.dragging.disable();
                }
            }

            if (enabled && !wasEnabled) {
                setTimeout(() => map?.invalidateSize(), 100);
                if (!selectedZone) checkEnteredAddress();
            }
        },

        reset() {
            cancelLookup();

            if (marker && map) {
                map.removeLayer(marker);
                marker = null;
            }

            pinnedPoint = null;
            showZone(null);

            if (searchResults) {
                searchResults.replaceChildren();
                searchResults.hidden = true;
            }

            message(
                address.value
                    ? 'Saved address loaded. Place a pin to choose a different location.'
                    : 'Select Delivery, then search or click the map to place a pin.'
            );
            if (enabled) checkEnteredAddress();
        },

        getSelectedZone() {
            return selectedZone ? { ...selectedZone } : null;
        },

        getPinnedCoordinates() {
            return pinnedPoint
                ? {
                    latitude: pinnedPoint.lat,
                    longitude: pinnedPoint.lng
                }
                : null;
        }
    };
};
