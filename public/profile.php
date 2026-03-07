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

<div class="card">
    <h2><?php echo $user["username"]; ?></h2>

    <div class="stat"><strong>Level:</strong> <?php echo $user["level"]; ?></div>
    <div class="stat"><strong>XP:</strong> <?php echo $user["xp"]; ?></div>
    <div class="stat"><strong>Total Distance:</strong> <?php echo round($total_distance,2); ?> km</div>
    <div class="stat"><strong>Total Grids:</strong> <?php echo $grid_count; ?></div>
    <div class="stat"><strong>Global Rank:</strong> <?php echo $rank; ?></div>
    <div class="stat"><strong>Member Since:</strong> <?php echo $user["created_at"]; ?></div>

    <br>
    <a href="dashboard.php">Back to Map</a>
     <form action="logout.php" method="POST" style="display:inline;">
        <button type="submit" class="logout-btn">Logout</button>
    </form>
 
</div>


</body>
</html>