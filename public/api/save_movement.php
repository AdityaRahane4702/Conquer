<?php
session_start();
require_once "../../config/db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status"=>"error"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["lat"]) || !isset($data["lng"])) {
    echo json_encode(["status"=>"error"]);
    exit;
}

$user_id = $_SESSION["user_id"];
$lat = floatval($data["lat"]);
$lng = floatval($data["lng"]);

$last = pg_query_params(
    $conn,
    "SELECT latitude, longitude, recorded_at
     FROM user_movements
     WHERE user_id = $1
     ORDER BY recorded_at DESC
     LIMIT 1",
    [$user_id]
);

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

$distance_km = 0;

if ($last && pg_num_rows($last) > 0) {

    $row = pg_fetch_assoc($last);

    $distance_km = haversine(
        $row["latitude"],
        $row["longitude"],
        $lat,
        $lng
    );

    $time_diff = time() - strtotime($row["recorded_at"]);

    if ($time_diff > 0) {
        $speed = ($distance_km / $time_diff) * 3600;

        if ($speed > 15) {
            echo json_encode(["status"=>"speed_violation"]);
            exit;
        }
    }
}

pg_query_params(
    $conn,
    "INSERT INTO user_movements (user_id, latitude, longitude)
     VALUES ($1, $2, $3)",
    [$user_id, $lat, $lng]
);

if ($distance_km > 0) {

    $xp_gain = floor(($distance_km * 1000) / 10);

    pg_query_params(
        $conn,
        "UPDATE users
         SET total_distance = total_distance + $1,
             xp = xp + $2,
             level = FLOOR((xp + $2)/100) + 1
         WHERE id = $3",
        [$distance_km, $xp_gain, $user_id]
    );
}

echo json_encode(["status"=>"saved"]);