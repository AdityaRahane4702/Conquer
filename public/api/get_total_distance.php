<?php
session_start();
require_once "../../config/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status"=>"error"]);
    exit;
}

$user_id = $_SESSION["user_id"];

$result = pg_query_params(
    $conn,
    "SELECT latitude, longitude
     FROM user_movements
     WHERE user_id = $1
     ORDER BY recorded_at ASC",
    [$user_id]
);

$points = [];

while ($row = pg_fetch_assoc($result)) {
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

$xp = floor($total_distance * 100);
$level = floor($xp / 500) + 1;

pg_query_params(
    $conn,
    "UPDATE users SET xp = $1, level = $2 WHERE id = $3",
    [$xp, $level, $user_id]
);

echo json_encode([
    "status"=>"success",
    "distance_km"=>round($total_distance, 3),
    "xp"=>$xp,
    "level"=>$level
]);
