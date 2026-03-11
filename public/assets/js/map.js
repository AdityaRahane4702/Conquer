const map = L.map('map').setView([20, 78], 3);

L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

let playerMarker;
let movementTrail = L.polyline([], { color: 'white' }).addTo(map);
let gridLayer = L.layerGroup().addTo(map);
let otherPlayersLayer = L.layerGroup().addTo(map);
let playerMarkers = {}; // Registry for other player markers

const gridSize = 0.0005;

let currentGrid = null;
let previousGrid = JSON.parse(sessionStorage.getItem('last_grid')) || null;
let lastLatLng = null;
let dbLastLatLng = null; // Track last position sent to DB
let firstLocationFix = false;
let isCapturing = localStorage.getItem('isCapturing') === 'true';
let sessionId = localStorage.getItem('sessionId') || null;
let isDeployMode = false;

// Grid State Cache for animations - stored in session to prevent flashing on refresh
let gridCache = JSON.parse(sessionStorage.getItem('gridCache')) || {};

// Handle map clicks for bot deployment
map.on('click', function(e) {
    if (isDeployMode && window.isAdmin) {
        const customName = prompt("Enter a name for this AI Soldier (or leave blank for random):");
        if (customName !== null) { // Handle cancel
            handleDeployBot(e.latlng.lat, e.latlng.lng, customName);
        }
    }
});

// UI Update for capture state on load
document.addEventListener('DOMContentLoaded', () => {
    updateCaptureUI();
});

function updateCaptureUI() {
    const btn = document.getElementById('capture-toggle-btn');
    if (!btn) return;

    if (isCapturing) {
        btn.classList.remove('start');
        btn.classList.add('stop');
        btn.querySelector('.btn-icon').innerText = '⏹️';
        btn.querySelector('.btn-text').innerText = 'End Run';
    } else {
        btn.classList.remove('stop');
        btn.classList.add('start');
        btn.querySelector('.btn-icon').innerText = '▶️';
        btn.querySelector('.btn-text').innerText = 'Start Run';
    }
}

function toggleDeployMode() {
    isDeployMode = !isDeployMode;
    const btn = document.getElementById('deploy-bot-btn');
    if (!btn) return;

    if (isDeployMode) {
        btn.classList.add('active-deploy');
        btn.querySelector('.btn-text').innerText = 'Cancel';
        showNotification("Tap map to spawn bot", 3000);
    } else {
        btn.classList.remove('active-deploy');
        btn.querySelector('.btn-text').innerText = 'Deploy Bot';
    }
}

function handleDeployBot(lat, lng, botName = '') {
    fetch('/api/add_bot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lat, lng, bot_name: botName })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showNotification(`Bot ${data.bot_name} Deployed!`, 3000);
            toggleDeployMode(); // Turn off deployment mode
            loadPlayers(); // Refresh players to show new bot
        } else {
            alert("Error: " + data.message);
        }
    });
}

function showNotification(msg, duration = 2500) {
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();

    const div = document.createElement('div');
    div.className = 'notification';
    div.innerText = msg;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), duration);
}

function toggleCapturing() {
    isCapturing = !isCapturing;
    localStorage.setItem('isCapturing', isCapturing);

    if (isCapturing) {
        // Just started capturing - call start session API
        fetch('/api/start_session.php', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    sessionId = data.session_id;
                    localStorage.setItem('sessionId', sessionId);
                    console.log("Run started, Session ID:", sessionId);
                }
            });
    } else {
        // Just stopped capturing - call end session API
        if (sessionId) {
            fetch('/api/end_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: sessionId })
            }).then(() => {
                sessionId = null;
                localStorage.removeItem('sessionId');
                console.log("Run ended");
            });
        }
        previousGrid = null;
    }
    updateCaptureUI();
}

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

function drawGrid(grid_x, grid_y, color, strength, animationClass = '') {
    const lat = grid_x * gridSize;
    const lng = grid_y * gridSize;

    const opacity = Math.min(0.3 + strength * 0.05, 0.9);

    const rect = L.rectangle([
        [lat, lng],
        [lat + gridSize, lng + gridSize]
    ], {
        color: color,
        weight: 1,
        fillColor: color,
        fillOpacity: opacity,
        className: animationClass // This triggers our CSS animations!
    }).addTo(gridLayer);

    // Double-check class is applied to the DOM element
    if (animationClass && rect.getElement()) {
        rect.getElement().classList.add(animationClass);
    }

    const centerLat = lat + gridSize / 2;
    const centerLng = lng + gridSize / 2;

    const marker = L.marker([centerLat, centerLng], {
        icon: L.divIcon({
            className: 'grid-strength-marker',
            html: `<div style="color:white;font-size:12px;font-weight:bold;text-shadow:0 0 3px black;">${strength}</div>`
        })
    }).addTo(gridLayer);

    return { rect, marker };
}

function triggerMapShake() {
    const mapEl = document.getElementById('map');
    mapEl.classList.remove('map-shake');
    void mapEl.offsetWidth; // Trigger reflow to restart animation
    mapEl.classList.add('map-shake');
}

function loadGrids() {
    fetch('/api/get_grids.php')
        .then(res => res.json())
        .then(data => {
            gridLayer.clearLayers();
            
            const newCache = {};
            data.forEach(grid => {
                const key = `${grid.grid_x}_${grid.grid_y}`;
                const oldGrid = gridCache[key];
                
                let animClass = '';
                
                if (!oldGrid) {
                    animClass = 'grid-new-capture';
                } else if (oldGrid.owner_id != grid.owner_id) {
                    // NEW: Ownership changed = Conquest!
                    animClass = 'grid-taken-capture';
                    triggerMapShake();
                } else if (parseInt(grid.strength) < parseInt(oldGrid.strength)) {
                    // NEW: Strength decreased = Attacked!
                    animClass = 'grid-attacked';
                    triggerMapShake(); // Shake map a bit for attacks too
                } else if (parseInt(grid.strength) > parseInt(oldGrid.strength)) {
                    // Strength increased = Reinforce
                    animClass = 'grid-reinforce-pulse';
                }

                drawGrid(
                    parseInt(grid.grid_x),
                    parseInt(grid.grid_y),
                    grid.color,
                    parseInt(grid.strength),
                    animClass
                );

                // Update cache
                newCache[key] = { owner_id: grid.owner_id, strength: grid.strength };
            });

            gridCache = newCache;
            sessionStorage.setItem('gridCache', JSON.stringify(gridCache));
        });
}

function captureGrid(x, y) {
    fetch('/api/capture_grid.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ grid_x: x, grid_y: y, session_id: sessionId })
    }).then(loadGrids);
}

function loadPlayers() {
    fetch('/api/get_players.php')
        .then(res => res.json())
        .then(data => {
            data.forEach(p => {
                // Skip the local player
                if (p.id == window.currentUserId) return;

                if (!playerMarkers[p.id]) {
                    // Create new marker for this player/bot
                    const botIcon = L.divIcon({
                        className: 'custom-char-icon',
                        html: `
                            <div class="character-marker" id="p-marker-${p.id}" 
                                 style="--char-primary: ${p.color}; --char-secondary: ${p.color};">
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
                                    <div class="bot-name">${p.username}</div>
                                </div>
                            </div>
                        `,
                        iconSize: [40, 40],
                        iconAnchor: [20, 40]
                    });
                    playerMarkers[p.id] = L.marker([p.lat, p.lng], { icon: botIcon }).addTo(otherPlayersLayer);
                } else {
                    // Update existing marker
                    const oldLatLng = playerMarkers[p.id].getLatLng();
                    playerMarkers[p.id].setLatLng([p.lat, p.lng]);
                    
                    const markerEl = document.getElementById(`p-marker-${p.id}`);
                    if (markerEl) {
                        if (p.lng > oldLatLng.lng) markerEl.classList.remove('facing-left');
                        else if (p.lng < oldLatLng.lng) markerEl.classList.add('facing-left');
                    }
                }
            });
        });
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
                <div class="character-marker" style="--char-primary: ${window.currentUserColor}; --char-secondary: ${window.currentUserColor};">
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
                        <div class="bot-name">${window.currentUserName}</div>
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

    // Broadcast position to other players (even if not capturing)
    fetch('/api/save_movement.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lat, lng, session_id: sessionId })
    });

    // ONLY CAPTURE GRIDS IF CAPTURING IS ACTIVE
    if (!isCapturing) return;

    // Check Grid Change
    currentGrid = getGridCoords(lat, lng);
    if (!previousGrid || (currentGrid.x !== previousGrid.x || currentGrid.y !== previousGrid.y)) {
        if (isCapturing) {
            captureGrid(currentGrid.x, currentGrid.y);
            previousGrid = currentGrid;
            sessionStorage.setItem('last_grid', JSON.stringify(currentGrid));
        }
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

setInterval(loadPlayers, 3000);
loadPlayers();