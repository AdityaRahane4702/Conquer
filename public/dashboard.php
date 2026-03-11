<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$u_res = pg_query_params($conn, "SELECT color FROM users WHERE id = $1", [$user_id]);
$user_data = pg_fetch_assoc($u_res);
$user_color = $user_data['color'] ?? '#3f4e1f'; // Military green fallback
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
<body class="map-page">

<div id="map"></div>

<div class="stats-panel">
    <div>Level: <span id="level">1</span></div>
    <div>XP: <span id="xp">0</span></div>
    <div>Distance: <span id="distance">0</span> km</div>
    <div id="debug-coords">Finding location...</div>
    <div class="capture-controls">
        <button id="capture-toggle-btn" onclick="toggleCapturing()" class="capture-btn start">
            <span class="btn-icon">▶️</span> <span class="btn-text">Start Run</span>
        </button>
        <?php if (isset($_SESSION["is_admin"]) && $_SESSION["is_admin"]): ?>
            <button id="deploy-bot-btn" onclick="toggleDeployMode()" class="capture-btn deploy">
                <span class="btn-icon">🤖</span> <span class="btn-text">Deploy Bot</span>
            </button>
        <?php endif; ?>
        <button onclick="recenterMap()" class="recenter-btn">Recenter Map</button>
    </div>
</div>

<script>
    window.currentUserId = <?php echo $_SESSION['user_id']; ?>;
    window.currentUserName = <?php echo json_encode($_SESSION['username']); ?>;
    window.currentUserColor = <?php echo json_encode($user_color); ?>;
    window.isAdmin = <?php echo (isset($_SESSION["is_admin"]) && $_SESSION["is_admin"]) ? 'true' : 'false'; ?>;
</script>

<div class="bottom-nav">
    <a href="dashboard.php" class="active"><span>📍</span> Map</a>
    <a href="leaderboard.php"><span>🏆</span> Leaders</a>
    <a href="notifications.php" class="nav-item">
        <span>🔔</span> Alerts
        <span id="notif-badge" class="notif-badge">0</span>
    </a>
    <a href="profile.php"><span>👤</span> Profile</a>
    <?php if (isset($_SESSION["is_admin"]) && $_SESSION["is_admin"]): ?>
        <a href="admin.php" style="color: #f87171;"><span>🛡️</span> Admin</a>
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

    fetch('/api/get_unread_notifications.php')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById("notif-badge");
            if (data.unread_count > 0) {
                badge.innerText = data.unread_count;
                badge.style.display = "block";
            } else {
                badge.style.display = "none";
            }
        });
}
setInterval(loadStats, 2000);
loadStats();
</script>

</body>
</html>