<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Conquer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="theme-color" content="#1e293b">
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
</head>
<body>

<div id="map"></div>

<div class="stats-panel">
    <div>Level: <span id="level">1</span></div>
    <div>XP: <span id="xp">0</span></div>
    <div>Distance: <span id="distance">0</span> km</div>
    <div style="font-size: 10px; color: #94a3b8; margin-top: 5px;">
        <span id="debug-coords">Finding location...</span>
    </div>
    <button onclick="recenterMap()" style="margin-top: 8px; font-size: 10px; background: #22d3ee; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer;">Recenter Map</button>
</div>

<div class="bottom-nav">
    <a href="dashboard.php" class="active">Map</a>
    <a href="leaderboard.php">Leaderboard</a>
    <a href="profile.php">Profile</a>
    <?php if (isset($_SESSION["is_admin"]) && $_SESSION["is_admin"]): ?>
        <a href="admin.php" style="color: #f87171;">Admin</a>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="/assets/js/map.js"></script>

<script>
function loadStats() {
    fetch('/api/get_total_distance.php')
        .then(res => res.json())
        .then(data => {
            if (data.status === "success") {
                document.getElementById("distance").innerText = data.distance_km;
                document.getElementById("xp").innerText = data.xp;
                document.getElementById("level").innerText = data.level;
            }
        });
}
setInterval(loadStats, 2000);
loadStats();
</script>

</body>
</html>