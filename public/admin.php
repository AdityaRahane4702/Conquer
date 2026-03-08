<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$check = pg_query_params(
    $conn,
    "SELECT is_admin FROM users WHERE id = $1",
    [$user_id]
);

$row = pg_fetch_assoc($check);

if (!$row["is_admin"]) {
    die("Access denied.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - Conquer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

<div class="admin-container">
    <header>
        <h2>Admin Dashboard</h2>
        <div class="nav-links">
            <a href="dashboard.php" class="btn btn-secondary">Back to Game</a>
            <a href="admin_users.php" class="btn btn-primary">Manage Users</a>
            <a href="admin_reset_map.php" class="btn btn-secondary" onclick="return confirm('Are you sure you want to reset the entire map?')">Reset Map</a>
        </div>
    </header>

    <div id="stats" class="stats-grid">
        <!-- Stats will be loaded here -->
        <div class="card">
            <h3>Loading stats...</h3>
        </div>
    </div>
</div>

<script>
function loadStats() {
    fetch('/api/admin_stats.php')
        .then(res => res.json())
        .then(data => {

            if (data.status !== "success") return;

            document.getElementById("stats").innerHTML = `
                <div class="card">
                    <h3>Total Users</h3>
                    <p>${data.total_users}</p>
                </div>

                <div class="card">
                    <h3>Total Grids</h3>
                    <p>${data.total_grids}</p>
                </div>

                <div class="card">
                    <h3>Total XP</h3>
                    <p>${data.total_xp}</p>
                </div>

                <div class="card">
                    <h3>Total Distance</h3>
                    <p>${data.total_distance} km</p>
                </div>

                <div class="card">
                    <h3>Top Player</h3>
                    <p>${data.top_player}</p>
                    <small>${data.top_xp} XP</small>
                </div>
            `;
        });
}

setInterval(loadStats, 5000);
loadStats();
</script>

</body>
</html>