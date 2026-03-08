const map = L.map('map').setView([20, 78], 3);

L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

let playerMarker;
let movementTrail = L.polyline([], { color: 'white' }).addTo(map);
let gridLayer = L.layerGroup().addTo(map);

const gridSize = 0.002;

let currentGrid = null;
let previousGrid = JSON.parse(sessionStorage.getItem('last_grid')) || null;
let lastLatLng = null;
let dbLastLatLng = null; // Track last position sent to DB
let firstLocationFix = false;

function getGridCoords(lat, lng) {
    const x = Math.floor(lat / gridSize);
    const y = Math.floor(lng / gridSize);
    return { x, y };
}

function distanceBetween(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;

    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) *
        Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLng / 2) * Math.sin(dLng / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c * 1000;
}

function drawGrid(grid_x, grid_y, color, strength) {
    const lat = grid_x * gridSize;
    const lng = grid_y * gridSize;

    const opacity = Math.min(0.3 + strength * 0.05, 0.9);

    L.rectangle([
        [lat, lng],
        [lat + gridSize, lng + gridSize]
    ], {
        color: color,
        weight: 1,
        fillColor: color,
        fillOpacity: opacity
    }).addTo(gridLayer);

    const centerLat = lat + gridSize / 2;
    const centerLng = lng + gridSize / 2;

    L.marker([centerLat, centerLng], {
        icon: L.divIcon({
            className: '',
            html: `<div style="color:white;font-size:12px;font-weight:bold;">${strength}</div>`
        })
    }).addTo(gridLayer);
}

function loadGrids() {
    fetch('/api/get_grids.php')
        .then(res => res.json())
        .then(data => {
            gridLayer.clearLayers();
            data.forEach(grid => {
                drawGrid(
                    parseInt(grid.grid_x),
                    parseInt(grid.grid_y),
                    grid.color,
                    parseInt(grid.strength)
                );
            });
        });
}

function captureGrid(x, y) {
    fetch('/api/capture_grid.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ grid_x: x, grid_y: y })
    }).then(loadGrids);
}

function updatePosition(position) {
    const lat = position.coords.latitude;
    const lng = position.coords.longitude;
    const accuracy = position.coords.accuracy;

    // --- 1. ALWAYS UPDATE DEBUG INFO ---
    const debugEl = document.getElementById('debug-coords');
    if (debugEl) debugEl.innerText = `${lat.toFixed(4)}, ${lng.toFixed(4)} (±${Math.round(accuracy)}m)`;

    // --- 2. FILTER JITTER (Skip if stationary, but ALWAYS allow the FIRST fix) ---
    if (firstLocationFix) {
        // Skip updates if accuracy is very poor (usually means bad signal)
        if (accuracy > 50) return;

        // Don't move the character unless they moved > 2 meters
        if (lastLatLng) {
            const driftDist = distanceBetween(lastLatLng.lat, lastLatLng.lng, lat, lng);
            if (driftDist < 2) return; 
        }
    }

    // --- 2. VISUAL UPDATE ---
    if (!playerMarker) {
        const charIcon = L.divIcon({
            className: 'custom-char-icon',
            html: `
                <div class="character-marker">
                    <div class="mini-warrior">
                        <div class="warrior-jetpack">
                            <div class="jetpack-exhaust"></div>
                        </div>
                        <div class="warrior-body"></div>
                        <div class="warrior-head"></div>
                        <div class="warrior-hand hand-left"></div>
                        <div class="warrior-hand hand-right">
                            <div class="warrior-sword"></div>
                        </div>
                        <div class="warrior-shadow"></div>
                    </div>
                </div>
            `,
            iconSize: [40, 40],
            iconAnchor: [20, 40]
        });

        playerMarker = L.marker([lat, lng], { icon: charIcon }).addTo(map);
    } else {
        playerMarker.setLatLng([lat, lng]);
    }

    if (!firstLocationFix) {
        map.flyTo([lat, lng], 18, { animate: true, duration: 1.5 });
        firstLocationFix = true;
    } else {
        map.panTo([lat, lng], { animate: true });
    }

    if (lastLatLng && playerMarker) {
        const charContainer = playerMarker.getElement().querySelector('.character-marker');
        if (charContainer) {
            if (lng > lastLatLng.lng) {
                charContainer.classList.remove('facing-left');
            } else if (lng < lastLatLng.lng) {
                charContainer.classList.add('facing-left');
            }
        }
    }

    lastLatLng = { lat, lng };
    movementTrail.addLatLng([lat, lng]);

    // --- 3. SERVER LOGIC UPDATE (Every 5 meters) ---
    if (dbLastLatLng) {
        const moved = distanceBetween(dbLastLatLng.lat, dbLastLatLng.lng, lat, lng);
        if (moved < 5) return;
    }

    dbLastLatLng = { lat, lng };

    // Update Distance/XP
    fetch('/api/save_movement.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lat, lng })
    });

    // Check Grid Change
    currentGrid = getGridCoords(lat, lng);
    if (!previousGrid || (currentGrid.x !== previousGrid.x || currentGrid.y !== previousGrid.y)) {
        captureGrid(currentGrid.x, currentGrid.y);
        previousGrid = currentGrid;
        sessionStorage.setItem('last_grid', JSON.stringify(currentGrid));
    }
}

function recenterMap() {
    if (lastLatLng) {
        map.flyTo([lastLatLng.lat, lastLatLng.lng], 18, { animate: true, duration: 1.5 });
    } else {
        alert("Location not found yet. Please check browser permissions.");
    }
}

function handleLocationError(error) {
    let msg = "";
    switch (error.code) {
        case error.PERMISSION_DENIED: msg = "Please allow location access to play."; break;
        case error.POSITION_UNAVAILABLE: msg = "Location info unavailable."; break;
        case error.TIMEOUT: msg = "Location request timed out."; break;
        default: msg = "An unknown error occurred."; break;
    }
    alert("Location Error: " + msg + "\n(Make sure you are using HTTPS on mobile!)");
}

navigator.geolocation.watchPosition(updatePosition, handleLocationError, {
    enableHighAccuracy: true,
    maximumAge: 0,
    timeout: 10000
});

// IMPORTANT: This makes it work on Laptops!
// watchPosition can be slow on stationary devices. This forces an immediate update.
navigator.geolocation.getCurrentPosition(updatePosition, handleLocationError, {
    enableHighAccuracy: true,
    timeout: 5000
});

// Ensures map renders correctly if window size was in flux during load
setTimeout(() => { map.invalidateSize(); }, 500);

setInterval(loadGrids, 4000);
loadGrids();