<?php
session_start();
require_once "../../config/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status"=>"error"]);
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
    echo json_encode(["status"=>"denied"]);
    exit;
}

$total_users = pg_fetch_result(
    pg_query($conn, "SELECT COUNT(*) FROM users"),
    0, 0
);

$total_grids = pg_fetch_result(
    pg_query($conn, "SELECT COUNT(*) FROM grids"),
    0, 0
);

$total_xp = pg_fetch_result(
    pg_query($conn, "SELECT COALESCE(SUM(xp),0) FROM users"),
    0, 0
);

$top_player = pg_query($conn,
    "SELECT username, xp FROM users ORDER BY xp DESC LIMIT 1"
);

$top = pg_fetch_assoc($top_player);

$movements = pg_query($conn,
    "SELECT user_id, latitude, longitude
     FROM user_movements
     ORDER BY user_id, recorded_at ASC"
);

$points_by_user = [];

while ($row = pg_fetch_assoc($movements)) {
    $points_by_user[$row["user_id"]][] = $row;
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

foreach ($points_by_user as $user_points) {
    for ($i = 1; $i < count($user_points); $i++) {
        $total_distance += haversine(
            $user_points[$i-1]["latitude"],
            $user_points[$i-1]["longitude"],
            $user_points[$i]["latitude"],
            $user_points[$i]["longitude"]
        );
    }
}

echo json_encode([
    "status"=>"success",
    "total_users"=>$total_users,
    "total_grids"=>$total_grids,
    "total_xp"=>$total_xp,
    "total_distance"=>round($total_distance, 2),
    "top_player"=>$top ? $top["username"] : "None",
    "top_xp"=>$top ? $top["xp"] : 0
]);