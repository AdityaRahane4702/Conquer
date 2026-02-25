const map = L.map('map').setView([20, 78], 3);

L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
    maxZoom: 15,
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

let playerMarker;
let movementTrail = L.polyline([], { color: 'white' }).addTo(map);
let gridLayer = L.layerGroup().addTo(map);

const gridSize = 0.002;

let currentGrid = null;
let previousGrid = null;
let lastLatLng = null;
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
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(lat1 * Math.PI/180) *
        Math.cos(lat2 * Math.PI/180) *
        Math.sin(dLng/2) * Math.sin(dLng/2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
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

    if (!playerMarker) {
        playerMarker = L.circleMarker([lat, lng], {
            radius: 8,
            color: 'lime',
            fillOpacity: 1
        }).addTo(map);
    } else {
        playerMarker.setLatLng([lat, lng]);
    }

    if (!firstLocationFix) {
        map.setView([lat, lng], 18);
        firstLocationFix = true;
    }

    if (lastLatLng) {
        const moved = distanceBetween(
            lastLatLng.lat,
            lastLatLng.lng,
            lat,
            lng
        );

        if (moved < 10) {
            return;
        }
    }

    lastLatLng = { lat, lng };

    movementTrail.addLatLng([lat, lng]);

    currentGrid = getGridCoords(lat, lng);

    if (!previousGrid) {
        previousGrid = currentGrid;
        captureGrid(currentGrid.x, currentGrid.y);
        return;
    }

    if (
        currentGrid.x !== previousGrid.x ||
        currentGrid.y !== previousGrid.y
    ) {
        captureGrid(currentGrid.x, currentGrid.y);
        previousGrid = currentGrid;
    }
}

navigator.geolocation.watchPosition(updatePosition, null, {
    enableHighAccuracy: true,
    minZoom: 15,
    maximumAge: 0,
    timeout: 5000
});

setInterval(loadGrids, 4000);
loadGrids();