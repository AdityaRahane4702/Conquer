<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$user = pg_query_params(
    $conn,
    "SELECT id, username, xp, level, total_distance, created_at
     FROM users
     WHERE id = $1",
    [$user_id]
);

$user = pg_fetch_assoc($user);
$total_distance = $user["total_distance"];

$grid_count = pg_fetch_result(
    pg_query_params(
        $conn,
        "SELECT COUNT(*) FROM grids WHERE owner_id = $1",
        [$user_id]
    ),
    0, 0
);

// Optimize Rank Calculation: count users with more XP
$rank = pg_fetch_result(
    pg_query_params(
        $conn,
        "SELECT COUNT(*) + 1 FROM users WHERE xp > $1",
        [$user["xp"]]
    ),
    0, 0
);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile - Conquer</title>
    <style>
        body { font-family: Arial; }
        .card {
            max-width: 400px;
            margin: 40px auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 10px;
        }
        .stat { margin: 10px 0; }
    </style>
    <link rel="stylesheet" href="/assets/css/profile.css">

</head>
<body>

<div class="container">
    <div class="header-actions">
        <a href="dashboard.php" class="back-btn">← Back to Map</a>
    </div>

    <div class="profile-card">
        <div class="profile-icon-container">
            👤
        </div>
        
        <h2><?php echo htmlspecialchars($user["username"]); ?></h2>
        <div class="rank-badge">Global Rank #<?php echo $rank; ?></div>

        <div class="stats-grid">
            <div class="stat-item">
                <span class="stat-value"><?php echo $user["level"]; ?></span>
                <span class="stat-label">Level</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo number_format($user["xp"]); ?></span>
                <span class="stat-label">Total XP</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo round($total_distance, 1); ?></span>
                <span class="stat-label">KM Walked</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo $grid_count; ?></span>
                <span class="stat-label">Grids Captured</span>
            </div>
        </div>

        <div class="member-since">
            Enlisted on <?php echo date("M d, Y", strtotime($user["created_at"])); ?>
        </div>

        <div class="profile-actions">
            <form action="logout.php" method="POST" style="width: 100%;">
                <button type="submit" class="logout-btn">Sign Out</button>
            </form>
        </div>
    </div>
</div>


</body>
</html>