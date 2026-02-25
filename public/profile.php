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
    "SELECT id, username, xp, level, created_at
     FROM users
     WHERE id = $1",
    [$user_id]
);

$user = pg_fetch_assoc($user);

$grid_count = pg_fetch_result(
    pg_query_params(
        $conn,
        "SELECT COUNT(*) FROM grids WHERE owner_id = $1",
        [$user_id]
    ),
    0, 0
);

$rank_result = pg_query(
    $conn,
    "SELECT id FROM users ORDER BY xp DESC"
);

$rank = 1;
while ($row = pg_fetch_assoc($rank_result)) {
    if ($row["id"] == $user_id) break;
    $rank++;
}

$movements = pg_query_params(
    $conn,
    "SELECT latitude, longitude
     FROM user_movements
     WHERE user_id = $1
     ORDER BY recorded_at ASC",
    [$user_id]
);

$points = [];

while ($row = pg_fetch_assoc($movements)) {
    $points[] = $row;
}

function haversine($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

$total_distance = 0;

for ($i = 1; $i < count($points); $i++) {
    $total_distance += haversine(
        $points[$i-1]["latitude"],
        $points[$i-1]["longitude"],
        $points[$i]["latitude"],
        $points[$i]["longitude"]
    );
}
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